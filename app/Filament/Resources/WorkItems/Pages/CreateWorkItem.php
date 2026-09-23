<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Report\WorkItemService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Isler > Yeni is: kayit WorkItemService'ten gecer (varsayilanlar, gecis kurali, hareket). */
class CreateWorkItem extends CreateRecord
{
    protected static string $resource = WorkItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(WorkItemService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return WorkItemResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
