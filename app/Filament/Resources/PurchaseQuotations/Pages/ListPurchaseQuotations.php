<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations\Pages;

use App\Filament\Exports\PurchaseQuotationExporter;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Filament\Support\ExportActions;
use App\Query\Procurement\PurchaseQuotationQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satin alma teklifleri listesi (D-192): Tümü / Fiyat bekleniyor / Alındı /
 * Seçildi / Siparişe dönüştü / Reddedildi ve iptal; rozetler arsivde olmayan
 * teklif sayisidir. Toplu "Karşılaştır" iki ve daha fazla teklifi kalem x
 * tedarikci matrisinde gosterir.
 */
class ListPurchaseQuotations extends ListRecords
{
    protected static string $resource = PurchaseQuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(PurchaseQuotationExporter::class),
            CreateAction::make()->label(__('purchase_quotation.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = app(PurchaseQuotationQueries::class)->tabCounts();
        $tabs = [];

        foreach (PurchaseQuotationQueries::TABS as $tab) {
            $tabs[$tab] = Tab::make(__('purchase_quotation.tabs.'.$tab))
                ->badge($counts[$tab] ?? null)
                ->badgeColor(match ($tab) {
                    PurchaseQuotationQueries::TAB_DRAFT => 'warning',
                    PurchaseQuotationQueries::TAB_SELECTED => 'primary',
                    PurchaseQuotationQueries::TAB_ORDERED => 'success',
                    default => 'gray',
                })
                ->modifyQueryUsing(fn (Builder $query): Builder => app(PurchaseQuotationQueries::class)->tabScope($query, $tab));
        }

        return $tabs;
    }
}
