<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Katalog kalemi sayfasi (D-189): Genel (B53 oncesi Siparisler de) sekmeleri
 * (CatalogItemResource::infolist), altta Teklif ve projeler ile Siparis
 * kalemleri. Baslikta Duzenle ve Arsive al / Arsivden cikar.
 */
class ViewCatalogItem extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = CatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            CatalogItemResource::archiveAction(),
            CatalogItemResource::restoreAction(),
        ];
    }
}
