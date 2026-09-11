<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\WorkstreamStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ProjectWorkstreams\ProjectWorkstreamResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectWorkstream;
use App\Services\Project\ProjectWorkstreamService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class WorkstreamsRelationManager extends RelationManager
{
    protected static string $relationship = 'workstreams';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedSquares2x2;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_workstream.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_workstream.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('owner_personnel_id')
                            ->label(__('project_workstream.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('progress_pct')
                            ->label(__('project_workstream.fields.progress_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        DatePicker::make('planned_start_on')
                            ->label(__('project_workstream.fields.planned_start_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('planned_finish_on')
                            ->label(__('project_workstream.fields.planned_finish_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('actual_start_on')
                            ->label(__('project_workstream.fields.actual_start_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('actual_finish_on')
                            ->label(__('project_workstream.fields.actual_finish_on'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('project_workstream.relation.title'))
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('group.name_tr')
                    ->label(__('project_workstream.fields.group')),
                TextColumn::make('owner.full_name')
                    ->label(__('project_workstream.fields.owner')),
                TextColumn::make('status')
                    ->label(__('project_workstream.fields.status'))
                    ->badge(),
                TextColumn::make('progress_pct')
                    ->label(__('project_workstream.fields.progress_pct'))
                    ->suffix('%'),
                TextColumn::make('planned_finish_on')
                    ->label(__('project_workstream.fields.planned_finish_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('block_reason')
                    ->label(__('project_workstream.fields.block_reason'))
                    ->limit(30)
                    ->placeholder('-'),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (ProjectWorkstream $record): string => ProjectWorkstreamResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (ProjectWorkstream $record, array $data): Model {
                        try {
                            return app(ProjectWorkstreamService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('project_workstream.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id')
            ->emptyStateHeading(__('project_workstream.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (WorkstreamStatus::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('project_workstream.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('project_workstream.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (ProjectWorkstream $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (ProjectWorkstream $record, array $data) use ($target): void {
                    try {
                        app(ProjectWorkstreamService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('project_workstream.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
