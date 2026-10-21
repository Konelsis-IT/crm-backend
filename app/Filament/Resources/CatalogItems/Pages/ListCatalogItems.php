<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogItems\Pages;

use App\Enums\Procurement\UsageArea;
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
 * Katalog listesi (D-189): Tumu, Urun, Hizmet ve kullanim yerine gore Sirket
 * ici, Saha sekmeleri (D-190); rozetler arsivde olmayan kalem sayisidir.
 * Genel gider ve Idari kadro D-228'den beri kendi ekranlarinda (sekmesiz).
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
                ->visible(fn (): bool => static::getResource()::categoryResource()::canAccess())
                ->url(fn (): string => static::getResource()::categoryResource()::getUrl('index')),
            CreateAction::make()
                ->label(static::getResource()::createLabel()),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        // D-228: Genel Gider ve Idari Kadro tek turlu ekranlar; sekme yok.
        $resource = static::getResource();

        if ($resource::singleKind()) {
            return [];
        }

        $counts = array_intersect_key(app(CatalogQueries::class)->kindCounts(), array_flip($resource::kindValues()));
        $screen = app(CatalogScreenQueries::class);

        $tabs = [
            'all' => Tab::make(__('catalog.tabs.all'))
                ->badge(array_sum($counts)),
        ];

        foreach ($resource::kinds() as $kind) {
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
