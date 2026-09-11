<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffVersions\Pages;

use App\Enums\Acquisition\ReviewDecision;
use App\Exceptions\AbstractException;
use App\Filament\Resources\OperationHandoffVersions\OperationHandoffVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewOperationHandoffVersion extends ViewRecord
{
    protected static string $resource = OperationHandoffVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('submit')
                ->label(__('operation_handoff_version.actions.submit'))
                ->color('primary')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\Acquisition\HandoffVersionStatus::Draft)
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Acquisition\OperationHandoffVersionService::class)->submit($record);
                        DomainNotifications::success(__('operation_handoff_version.messages.done'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
            Action::make('review')
                ->label(__('operation_handoff_version.actions.review'))
                ->color('success')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->requiresConfirmation()
                ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
            Select::make('decision')
                ->label(__('operation_handoff_version.fields.decision'))
                ->options(ReviewDecision::class)
                ->default(ReviewDecision::Accepted->value)
                ->required()
                ->native(false),
            Textarea::make('comment')
                ->label(__('operation_handoff_version.fields.comment'))
                ->columnSpanFull(),
            TextInput::make('project_name')
                ->label(__('operation_handoff_version.fields.project_name'))
                ->maxLength(255),
            Select::make('project_manager_employee_id')
                ->label(__('operation_handoff_version.fields.project_manager'))
                ->options(fn (): array => app(\App\Query\Personnel\PersonnelQueries::class)->personnelOptions())
                ->searchable()
                ->native(false),
            TextInput::make('site_location')
                ->label(__('operation_handoff_version.fields.site_location'))
                ->maxLength(100),
            DatePicker::make('planned_start_on')
                ->label(__('operation_handoff_version.fields.planned_start_on'))
                ->displayFormat('d.m.Y'),
            DatePicker::make('planned_finish_on')
                ->label(__('operation_handoff_version.fields.planned_finish_on'))
                ->displayFormat('d.m.Y'),
                ])))
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\Acquisition\HandoffVersionStatus::Submitted)
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Acquisition\HandoffReviewService::class)->create([
                            'handoff_version_id' => $record->getKey(),
                            'decision' => $data['decision'],
                            'comment' => $data['comment'] ?? null,
                            'project_overrides' => [
                                'name' => $data['project_name'] ?? null,
                                'project_manager_employee_id' => $data['project_manager_employee_id'] ?? null,
                                'site_location' => $data['site_location'] ?? null,
                                'planned_start_on' => $data['planned_start_on'] ?? null,
                                'planned_finish_on' => $data['planned_finish_on'] ?? null,
                            ],
                        ]);
                        DomainNotifications::success(__('operation_handoff_version.messages.reviewed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }
}
