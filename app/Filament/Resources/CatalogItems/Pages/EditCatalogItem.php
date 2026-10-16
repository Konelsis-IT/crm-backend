<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\CatalogItemService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Katalog kalemini duzenle (D-189): kayit CatalogItemService uzerinden. */
class EditCatalogItem extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = CatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cancelHeaderAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(CatalogItemService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
