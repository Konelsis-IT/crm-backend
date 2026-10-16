<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds\Pages;

use App\Filament\Resources\PurchaseNeeds\PurchaseNeedActions;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource;
use App\Models\Personnel\Personnel;
use App\Query\Procurement\PurchaseNeedQueries;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Siparis kalemleri listesi (D-189): kullanicinin istedigi sekmeler
 * (Tümü / Teklif grubunun gönderdiği / Projenin onayladığı / Termin geçmiş /
 * Acil) + Şirket içi, Sipariş verildi, Tamamlanan; rozetler kisinin
 * gorebildigi aktif kalem sayisidir.
 */
class ListPurchaseNeeds extends ListRecords
{
    protected static string $resource = PurchaseNeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseNeedActions::createNeed(manual: false),
            PurchaseNeedActions::createNeed(manual: true),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $user = auth()->user();
        $queries = app(PurchaseNeedQueries::class);
        $counts = $queries->tabCounts($user instanceof Personnel ? $user : null);
        $tabs = [];

        foreach (PurchaseNeedQueries::TABS as $tab) {
            $tabs[$tab] = Tab::make(__('purchasing.tabs.'.$tab))
                ->badge($counts[$tab] ?? null)
                ->badgeColor(match ($tab) {
                    PurchaseNeedQueries::TAB_OVERDUE, PurchaseNeedQueries::TAB_URGENT => 'danger',
                    PurchaseNeedQueries::TAB_FROM_PROPOSALS => 'warning',
                    default => 'gray',
                })
                ->modifyQueryUsing(fn (Builder $query): Builder => app(PurchaseNeedQueries::class)->tabScope($query, $tab));
        }

        return $tabs;
    }
}
