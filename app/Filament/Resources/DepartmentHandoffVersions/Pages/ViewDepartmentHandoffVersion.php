<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffVersions\Pages;

use App\Enums\Acquisition\ReviewDecision;
use App\Exceptions\AbstractException;
use App\Filament\Resources\DepartmentHandoffVersions\DepartmentHandoffVersionResource;
use App\Filament\Support\DomainNotifications;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDepartmentHandoffVersion extends ViewRecord
{
    protected static string $resource = DepartmentHandoffVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('submit')
                ->label(__('department_handoff_version.actions.submit'))
                ->color('primary')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\Acquisition\HandoffVersionStatus::Draft)
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Project\DepartmentHandoffVersionService::class)->submit($record);
                        DomainNotifications::success(__('department_handoff_version.messages.done'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
            Action::make('review')
                ->label(__('department_handoff_version.actions.review'))
                ->color('success')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->requiresConfirmation()
                ->schema([
            Select::make('decision')
                ->label(__('department_handoff_version.fields.decision'))
                ->options(ReviewDecision::class)
                ->default(ReviewDecision::Accepted->value)
                ->required()
                ->native(false),
            Textarea::make('comment')
                ->label(__('department_handoff_version.fields.comment'))
                ->columnSpanFull(),
                ])
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\Acquisition\HandoffVersionStatus::Submitted)
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Project\DepartmentHandoffReviewService::class)->create(['handoff_version_id' => $record->getKey(), 'decision' => $data['decision'], 'comment' => $data['comment'] ?? null]);
                        DomainNotifications::success(__('department_handoff_version.messages.done'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }
}
