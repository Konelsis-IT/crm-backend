<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Models\Personnel\Personnel;
use App\Query\Procurement\PurchaseRequestQueries;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satin alma talepleri listesi (D-192): Tumu / Benim taleplerim / Acik /
 * Isleniyor / Tamamlandi / Iptal; rozetler kisinin gorebildigi aktif talep
 * sayisidir.
 */
class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('purchase_request.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $user = auth()->user();
        $personnel = $user instanceof Personnel ? $user : null;
        $counts = app(PurchaseRequestQueries::class)->tabCounts($personnel);
        $tabs = [];

        foreach (PurchaseRequestQueries::TABS as $tab) {
            $tabs[$tab] = Tab::make(__('purchase_request.tabs.'.$tab))
                ->badge($counts[$tab] ?? null)
                ->badgeColor(match ($tab) {
                    PurchaseRequestQueries::TAB_OPEN => 'warning',
                    PurchaseRequestQueries::TAB_IN_PROGRESS => 'info',
                    default => 'gray',
                })
                ->modifyQueryUsing(fn (Builder $query): Builder => app(PurchaseRequestQueries::class)->tabScope($query, $tab, $personnel));
        }

        return $tabs;
    }
}
