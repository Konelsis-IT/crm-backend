<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseServiceAreas\Pages;

use App\Filament\Resources\PurchaseServiceAreas\PurchaseServiceAreaResource;
use Filament\Resources\Pages\ManageRecords;

/** Tedarikci hizmet alanlari (D-232); ekleme ve duzenleme pencerede. */
class ManagePurchaseServiceAreas extends ManageRecords
{
    protected static string $resource = PurchaseServiceAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseServiceAreaResource::createAction(),
        ];
    }
}
