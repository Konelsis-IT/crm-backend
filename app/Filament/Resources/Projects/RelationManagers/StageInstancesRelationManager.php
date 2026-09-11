<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\StageInstanceStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ProjectStageInstances\ProjectStageInstanceResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectStageInstance;
use App\Services\Project\ProjectStageInstanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class StageInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'stageInstances';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedFlag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_stage_instance.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_stage_instance.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('owner_personnel_id')
                            ->label(__('project_stage_instance.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        DatePicker::make('condition_due_on')
                            ->label(__('project_stage_instance.fields.condition_due_on'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('project_stage_instance.relation.title'))
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('stageNode.stage_code')
                    ->label(__('project_stage_instance.fields.stage_code')),
                TextColumn::make('stageNode.name_tr')
                    ->label(__('project_stage_instance.fields.name')),
                TextColumn::make('owner.full_name')
                    ->label(__('project_stage_instance.fields.owner')),
                TextColumn::make('status')
                    ->label(__('project_stage_instance.fields.status'))
                    ->badge(),
                TextColumn::make('entered_at')
                    ->label(__('project_stage_instance.fields.entered_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('passed_at')
                    ->label(__('project_stage_instance.fields.passed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (ProjectStageInstance $record): string => ProjectStageInstanceResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (ProjectStageInstance $record, array $data): Model {
                        try {
                            return app(ProjectStageInstanceService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('project_stage_instance.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id')
            ->emptyStateHeading(__('project_stage_instance.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedFlag);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (StageInstanceStatus::cases() as $target) {
            if (in_array($target, [StageInstanceStatus::Passed, StageInstanceStatus::ConditionallyPassed, StageInstanceStatus::Rejected], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('project_stage_instance.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('project_stage_instance.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (ProjectStageInstance $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (ProjectStageInstance $record, array $data) use ($target): void {
                    try {
                        app(ProjectStageInstanceService::class)->changeStatus($record, $target, ['reason' => $data['reason'] ?? null]);
                        DomainNotifications::success(__('project_stage_instance.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
