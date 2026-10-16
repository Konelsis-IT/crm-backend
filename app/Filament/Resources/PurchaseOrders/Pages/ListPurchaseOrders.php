<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\ExportActions;
use App\Models\Personnel\Personnel;
use App\Query\Procurement\PurchaseOrderQueries;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Siparisler listesi (D-192): Tümü / Taslak / Onay bekliyor / Onaylandı /
 * Sipariş verildi / Yolda / Teslim alındı / Tamamlandı / Geciken / İptal;
 * rozetler kisinin gorebildigi, arsivde olmayan siparis sayisidir. Olusturma
 * dugmesi yoktur (siparis tekliften acilir).
 */
class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(PurchaseOrderExporter::class),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $user = auth()->user();
        $counts = app(PurchaseOrderQueries::class)->tabCounts($user instanceof Personnel ? $user : null);
        $tabs = [];

        foreach (PurchaseOrderQueries::TABS as $tab) {
            $tabs[$tab] = Tab::make(__('purchase_order.tabs.'.$tab))
                ->badge($counts[$tab] ?? null)
                ->badgeColor(match ($tab) {
                    PurchaseOrderQueries::TAB_LATE => 'danger',
                    PurchaseOrderQueries::TAB_AWAITING_APPROVAL => 'warning',
                    PurchaseOrderQueries::TAB_IN_TRANSIT => 'primary',
                    default => 'gray',
                })
                ->modifyQueryUsing(fn (Builder $query): Builder => app(PurchaseOrderQueries::class)->tabScope($query, $tab));
        }

        return $tabs;
    }
}
