<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use App\Filament\Support\ActionColors;
use App\Models\Party\Party;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/** Tedarikci sayfasi (D-232): kimlik karti, hizmet alanlari, Kisiler ve Adresler. */
class ViewPurchaseSupplier extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PurchaseSupplierResource::class;

    /** Kimlik karti adi basliginda yaziyor; alt baslik tekrar etmez (D-205). */
    protected function recordSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            // Ortak firma: Taraflar'daki kaydi ac (ayni firma, ayni sayfa kimligi).
            Action::make('open_party')
                ->label(__('purchase_supplier.actions.open_party'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color(ActionColors::NEUTRAL)
                ->outlined()
                ->visible(fn (Party $record): bool => ($membership = PurchaseSupplierResource::membership($record)) !== null
                    && ! (bool) $membership->getAttribute('purchase_only')
                    && PartyResource::canView($record))
                ->url(fn (Party $record): string => PartyResource::getUrl('view', ['record' => $record])),
            PurchaseSupplierResource::archiveAction(),
            PurchaseSupplierResource::restoreAction(),
        ];
    }
}
