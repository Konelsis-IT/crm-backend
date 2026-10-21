<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\Schemas;

use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use App\Filament\Support\CardGallery;
use App\Models\Party\Party;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Tedarikci sayfasi (D-232): solda Taraflar'daki kimlik kartinin aynisi (ad, uzun
 * ad, adres, kanallar, birincil kisi), sagda hizmet alanlari, not, ortak firma
 * bilgisi ve arsiv.
 */
final class PurchaseSupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();

        if (! $record instanceof Party) {
            return $schema->components([]);
        }

        $record->loadMissing(['country', 'organizationProfile', 'roles', 'addresses', 'ownCommunicationPoints', 'contacts.contact', 'purchaseSupplier.serviceAreas']);
        $membership = PurchaseSupplierResource::membership($record);

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 4])->extraAttributes(['class' => 'kc-grid-top'])->components([
                app(CardGallery::class)
                    ->partyDetailCard($record, PurchaseSupplierResource::canEdit($record) ? PurchaseSupplierResource::getUrl('edit', ['record' => $record]) : null)
                    ->columnSpan(['default' => 1, 'lg' => 3]),
                Section::make(__('purchase_supplier.sections.side'))
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->components([
                        TextEntry::make('supplier_areas')
                            ->label(__('purchase_supplier.fields.service_areas'))
                            ->state(fn (): array => $membership?->serviceAreas->pluck('name')->all() ?? [])
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),
                        TextEntry::make('supplier_note')
                            ->label(__('purchase_supplier.fields.note'))
                            ->state(fn (): ?string => $membership?->getAttribute('note'))
                            ->placeholder('-'),
                        TextEntry::make('supplier_shared')
                            ->label(__('purchase_supplier.fields.shared'))
                            ->state(fn (): string => $membership !== null && ! (bool) $membership->getAttribute('purchase_only')
                                ? __('purchase_supplier.values.shared')
                                : __('purchase_supplier.values.purchase_only'))
                            ->badge()
                            ->color(fn (): string => $membership !== null && ! (bool) $membership->getAttribute('purchase_only') ? 'info' : 'gray'),
                        TextEntry::make('supplier_created_at')
                            ->label(__('purchase_supplier.fields.created_at'))
                            ->icon(Heroicon::OutlinedClock)
                            ->state(fn () => $membership?->getAttribute('created_at'))
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('supplier_archived_at')
                            ->label(__('purchase_supplier.fields.archived_at'))
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->iconColor('danger')
                            ->state(fn () => $membership?->getAttribute('archived_at'))
                            ->dateTime('d.m.Y H:i')
                            ->visible(fn (): bool => $membership?->isArchived() ?? false),
                    ]),
            ]),
        ]);
    }
}
