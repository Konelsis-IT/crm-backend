<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyPurchases\Pages;

use App\Filament\Resources\CompanyPurchases\CompanyPurchaseResource;
use App\Query\Procurement\CompanyPurchaseQueries;
use App\Query\Procurement\PurchasingQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sirket ici alimlar listesi (D-189): Tümü / Yenilemesi yaklaşan (60 gün) /
 * Yenileme tarihi geçmiş / Tek seferlik; rozetler aktif kayit sayisidir.
 */
class ListCompanyPurchases extends ListRecords
{
    protected static string $resource = CompanyPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('company_purchase.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = app(PurchasingQueries::class)->companyPurchaseTabCounts();
        $tabs = [];

        foreach ([CompanyPurchaseQueries::TAB_ALL, CompanyPurchaseQueries::TAB_UPCOMING, CompanyPurchaseQueries::TAB_OVERDUE, CompanyPurchaseQueries::TAB_ONE_TIME] as $tab) {
            $tabs[$tab] = Tab::make(__('company_purchase.tabs.'.$tab))
                ->badge($counts[$tab] ?? null)
                ->badgeColor(match ($tab) {
                    CompanyPurchaseQueries::TAB_OVERDUE => 'danger',
                    CompanyPurchaseQueries::TAB_UPCOMING => 'warning',
                    default => 'gray',
                })
                ->modifyQueryUsing(fn (Builder $query): Builder => app(CompanyPurchaseQueries::class)->tabScope($query, $tab));
        }

        return $tabs;
    }
}
