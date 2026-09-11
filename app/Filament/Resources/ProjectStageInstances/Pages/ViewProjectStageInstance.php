<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances\Pages;

use App\Enums\Project\StageInstanceStatus;
use App\Enums\Project\StageReviewDecision;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ProjectStageInstances\ProjectStageInstanceResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Services\Project\ProjectStageInstanceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewProjectStageInstance extends ViewRecord
{
    protected static string $resource = ProjectStageInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make($this->statusActions())
                ->label(__('project_stage_instance.actions.change_status'))
                ->icon(Heroicon::OutlinedArrowPath),
            Action::make('review')
                ->label(__('project_stage_instance.actions.review'))
                ->color('success')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->requiresConfirmation()
                ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
            Select::make('decision')
                ->label(__('project_stage_instance.fields.decision'))
                ->options(StageReviewDecision::class)
                ->default(StageReviewDecision::Passed->value)
                ->required()
                ->native(false),
            Textarea::make('conditions')
                ->label(__('project_stage_instance.fields.conditions'))
                ->columnSpanFull(),
            DatePicker::make('condition_due_on')
                ->label(__('project_stage_instance.fields.condition_due_on'))
                ->displayFormat('d.m.Y'),
            Textarea::make('comment')
                ->label(__('project_stage_instance.fields.comment'))
                ->columnSpanFull(),
                ])))
                ->visible(fn (): bool => in_array($this->getRecord()->status, [\App\Enums\Project\StageInstanceStatus::ReadyForReview, \App\Enums\Project\StageInstanceStatus::ApprovalPending], true))
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Project\StageReviewService::class)->create([
                            'project_stage_instance_id' => $record->getKey(),
                            'decision' => $data['decision'],
                            'conditions' => $data['conditions'] ?? null,
                            'condition_due_on' => $data['condition_due_on'] ?? null,
                            'comment' => $data['comment'] ?? null,
                        ]);
                        DomainNotifications::success(__('project_stage_instance.messages.done'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
            Action::make('waive')
                ->label(__('project_stage_instance.actions.waive'))
                ->color('warning')
                ->icon(Heroicon::OutlinedHandRaised)
                ->requiresConfirmation()
                ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
            Select::make('project_stage_requirement_id')
                ->label(__('project_stage_instance.fields.requirement'))
                ->options(fn (): array => $this->getRecord()->requirements()->pluck('name_snapshot_tr', 'id')->all())
                ->searchable()
                ->native(false),
            Textarea::make('reason')
                ->label(__('project_stage_instance.fields.reason'))
                ->required()
                ->columnSpanFull(),
            DatePicker::make('remediation_due_on')
                ->label(__('project_stage_instance.fields.remediation_due_on'))
                ->required()
                ->displayFormat('d.m.Y'),
            Select::make('risk_owner_personnel_id')
                ->label(__('project_stage_instance.fields.risk_owner'))
                ->options(fn (): array => app(\App\Query\Personnel\PersonnelQueries::class)->personnelOptions())
                ->searchable()
                ->required()
                ->native(false),
                ])))
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Project\StageWaiverService::class)->create([
                            'project_stage_instance_id' => $record->getKey(),
                            'project_stage_requirement_id' => $data['project_stage_requirement_id'] ?? null,
                            'reason' => $data['reason'],
                            'remediation_due_on' => $data['remediation_due_on'],
                            'risk_owner_personnel_id' => $data['risk_owner_personnel_id'],
                        ]);
                        DomainNotifications::success(__('project_stage_instance.messages.done'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
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
                ->visible(fn (): bool => Gate::allows('update', $this->getRecord())
                    && $this->getRecord()->status->canTransitionTo($target))
                ->action(function (array $data) use ($target): void {
                    $record = $this->getRecord();
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
