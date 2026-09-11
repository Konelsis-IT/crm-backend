<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectSupplyItems\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\ProjectSupplyItems\ProjectSupplyItemResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\ProjectSupplyItemService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditProjectSupplyItem extends EditRecord
{
    protected static string $resource = ProjectSupplyItemResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ProjectSupplyItemService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
