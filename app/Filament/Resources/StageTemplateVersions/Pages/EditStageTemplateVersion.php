<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplateVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\StageTemplateVersions\StageTemplateVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\StageTemplateVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStageTemplateVersion extends EditRecord
{
    protected static string $resource = StageTemplateVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(StageTemplateVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
