<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplates\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\StageTemplates\StageTemplateResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\StageTemplateService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStageTemplate extends EditRecord
{
    protected static string $resource = StageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(StageTemplateService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
