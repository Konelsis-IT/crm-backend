<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\Pages;

use App\Filament\Resources\PurchaseServiceAreas\PurchaseServiceAreaResource;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use App\Filament\Support\ActionColors;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * Tedarikciler listesi (D-232). Hizmet alanlari ayri menude degil, basliktaki
 * dugmeyle acilir.
 */
class ListPurchaseSuppliers extends ListRecords
{
    protected static string $resource = PurchaseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('service_areas')
                ->label(__('purchase_supplier.actions.areas'))
                ->icon(Heroicon::OutlinedTag)
                ->color(ActionColors::NEUTRAL)
                ->outlined()
                ->visible(fn (): bool => PurchaseServiceAreaResource::canAccess())
                ->url(fn (): string => PurchaseServiceAreaResource::getUrl('index')),
            CreateAction::make()
                ->label(__('purchase_supplier.actions.create')),
        ];
    }
}
