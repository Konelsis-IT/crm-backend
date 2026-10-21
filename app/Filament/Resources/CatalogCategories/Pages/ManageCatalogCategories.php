<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogCategories\Pages;

use App\Enums\Procurement\CatalogItemKind;
use App\Filament\Resources\CatalogCategories\CatalogCategoryResource;
use App\Query\Procurement\CatalogScreenQueries;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Katalog kategorileri (D-189); ekleme ve duzenleme pencerede. D-228 ek'ten
 * beri her sayfa tek agac (Urun / Hizmet, Genel gider, Idari kadro ayri).
 */
class ManageCatalogCategories extends ManageRecords
{
    protected static string $resource = CatalogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        // D-228 ek: her kategori sayfasi tek agac; sekme yalniz birden fazla agacta.
        $resource = static::getResource();

        if ($resource::singleKind()) {
            return [];
        }

        $screen = app(CatalogScreenQueries::class);
        $counts = $screen->categoryKindCounts();
        $tabs = [];

        foreach ($resource::kindOptions() as $value => $label) {
            $kind = CatalogItemKind::from($value);
            $tabs[$value] = Tab::make($label)
                ->icon($kind->getIcon())
                ->badge($counts[$value] ?? 0)
                ->modifyQueryUsing(static fn (Builder $query): Builder => $screen->categoriesOfKind($query, $kind));
        }

        return $tabs;
    }
}
