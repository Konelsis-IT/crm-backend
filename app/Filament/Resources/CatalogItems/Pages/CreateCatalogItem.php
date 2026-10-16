<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\CatalogItemService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Katalog kalemi ekle (D-189): ad normalize edilir ve ayni turde (Urun ve
 * Hizmet tek aile) ayni ad varsa kalem acilmaz (CatalogItemService).
 */
class CreateCatalogItem extends CreateRecord
{
    use HasColoredFormActions;

    protected static string $resource = CatalogItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CatalogItemService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
