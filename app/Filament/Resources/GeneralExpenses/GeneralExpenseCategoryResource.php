<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Filament\Resources\CatalogCategories\CatalogCategoryResource;
use App\Filament\Resources\GeneralExpenses\Pages\ManageGeneralExpenseCategories;
use App\Services\Platform\FeatureFlags;

/**
 * Muhasebe / Finans > Genel Gider kategorileri (D-228 ek, 19 Ekim 2026, kullanici: "idari kadronun ve
 * genel giderin kategorileri kendi menusu icinde olmali. Malzeme ve Hizmetler
 * tarafini acmamali"). Sayfa, form ve kurallar CatalogCategoryResource'tan;
 * yalniz bu turun agaci. Sol menude ayri satir yok: ekranin "Kategoriler"
 * dugmesiyle acilir, menude GeneralExpenseResource satiri secili kalir.
 */
class GeneralExpenseCategoryResource extends CatalogCategoryResource
{
    protected static ?string $slug = 'genel-gider-kategorileri';

    public static function kinds(): array
    {
        return [CatalogItemKind::GeneralExpense];
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.general_expense.categories');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.general_expense.categories');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::CatalogGeneralExpenses) && parent::canAccess();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGeneralExpenseCategories::route('/'),
        ];
    }
}
