<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\Pages;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Filament\Resources\CatalogCategories\CatalogCategoryResource;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Support\ActionColors;
use App\Query\Procurement\CatalogQueries;
use App\Query\Procurement\CatalogScreenQueries;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Katalog listesi (D-189): Tumu, Urun, Hizmet, Genel gider, Idari kadro,
 * ve kullanim yerine gore Sirket ici, Saha sekmeleri (D-190); rozetler
 * arsivde olmayan kalem sayisidir.
 */
class ListCatalogItems extends ListRecords
{
    protected static string $resource = CatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // D-193: kategoriler ayri menude degil, buradan acilir.
            Action::make('categories')
                ->label(__('catalog.category.nav'))
                ->icon(Heroicon::OutlinedFolder)
                ->color(ActionColors::NEUTRAL)
                ->outlined()
                ->visible(fn (): bool => CatalogCategoryResource::canAccess())
                ->url(fn (): string => CatalogCategoryResource::getUrl('index')),
            CreateAction::make()
                ->label(__('catalog.actions.create')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = app(CatalogQueries::class)->kindCounts();
        $screen = app(CatalogScreenQueries::class);

        $tabs = [
            'all' => Tab::make(__('catalog.tabs.all'))
                ->badge(array_sum($counts)),
        ];

        foreach (CatalogItemKind::cases() as $kind) {
            $tabs[$kind->value] = Tab::make((string) $kind->getLabel())
                ->icon($kind->getIcon())
                ->badge($counts[$kind->value] ?? 0)
                ->modifyQueryUsing(static fn (Builder $query): Builder => $screen->itemsOfKind($query, $kind));
        }

        // D-190: kullanim yeri tur degildir; Sirket ici / Saha sekmeleri urun ve
        // hizmetleri kullanildigi yere gore suzer (bilgisayar hem Urun hem Sirket ici).
        $usageCounts = app(CatalogQueries::class)->usageCounts();

        foreach (UsageArea::cases() as $usage) {
            $tabs['usage_'.$usage->value] = Tab::make((string) $usage->getLabel())
                ->icon($usage->getIcon())
                ->badge($usageCounts[$usage->value] ?? 0)
                ->modifyQueryUsing(static fn (Builder $query): Builder => $screen->itemsOfUsage($query, $usage));
        }

        return $tabs;
    }
}
