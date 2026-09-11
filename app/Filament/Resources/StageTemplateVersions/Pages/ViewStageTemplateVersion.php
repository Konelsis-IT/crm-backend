<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplateVersions\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\StageTemplateVersions\StageTemplateVersionResource;
use App\Filament\Support\DomainNotifications;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewStageTemplateVersion extends ViewRecord
{
    protected static string $resource = StageTemplateVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('publish')
                ->label(__('stage_template_version.actions.publish'))
                ->color('success')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\Project\StageTemplateVersionStatus::Draft)
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    try {
                        app(\App\Services\Project\StageTemplateVersionService::class)->publish($record);
                        DomainNotifications::success(__('stage_template_version.messages.done'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }
}
