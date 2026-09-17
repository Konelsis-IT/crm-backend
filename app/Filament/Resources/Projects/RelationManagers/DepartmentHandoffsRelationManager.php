<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Acquisition\HandoffStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\DepartmentHandoffs\DepartmentHandoffResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\DepartmentHandoff;
use App\Services\Project\DepartmentHandoffService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
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

class DepartmentHandoffsRelationManager extends RelationManager
{
    protected static string $relationship = 'departmentHandoffs';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowsRightLeft;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('department_handoff.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('department_handoff.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('source_workstream_id')
                            ->label(__('department_handoff.fields.source_workstream'))
                            ->relationship(
                            'sourceWorkstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('target_workstream_id')
                            ->label(__('department_handoff.fields.target_workstream'))
                            ->relationship(
                            'targetWorkstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('trigger_stage_instance_id')
                            ->label(__('department_handoff.fields.trigger_stage_instance'))
                            ->relationship(
                            'triggerStageInstance',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectStageInstance $record): string => $record->stageNode->stage_code.' '.$record->stageNode->name_tr)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        DatePicker::make('sla_due_at')
                            ->label(__('department_handoff.fields.sla_due_at'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('department_handoff.label'))
            ->heading(__('department_handoff.relation.title'))
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('sourceWorkstream.group.name_tr')
                    ->label(__('department_handoff.fields.source_workstream')),
                TextColumn::make('targetWorkstream.group.name_tr')
                    ->label(__('department_handoff.fields.target_workstream')),
                TextColumn::make('triggerStageInstance.stageNode.stage_code')
                    ->label(__('department_handoff.fields.trigger_stage_instance')),
                TextColumn::make('status')
                    ->label(__('department_handoff.fields.status'))
                    ->badge(),
                TextColumn::make('sla_due_at')
                    ->label(__('department_handoff.fields.sla_due_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('accepted_at')
                    ->label(__('department_handoff.fields.accepted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(DepartmentHandoffService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (DepartmentHandoff $record): string => DepartmentHandoffResource::getUrl('view', ['record' => $record])),
                ActionGroup::make($this->statusActions())
                    ->label(__('department_handoff.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('department_handoff.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (HandoffStatus::cases() as $target) {
            if (in_array($target, [HandoffStatus::Accepted], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('department_handoff.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('department_handoff.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (DepartmentHandoff $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (DepartmentHandoff $record, array $data) use ($target): void {
                    try {
                        app(DepartmentHandoffService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('department_handoff.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
