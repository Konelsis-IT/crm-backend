<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Filament\NavigationGroup;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Resources\GeneralExpenses\Pages\CreateGeneralExpense;
use App\Filament\Resources\GeneralExpenses\Pages\EditGeneralExpense;
use App\Filament\Resources\GeneralExpenses\Pages\ListGeneralExpenses;
use App\Filament\Resources\GeneralExpenses\Pages\ViewGeneralExpense;
use App\Services\Platform\FeatureFlags;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Muhasebe / Finans > Genel Gider (D-228, 19 Ekim 2026, kullanici: "Muhasebe /
 * Finans diye bir ana menu olustur. Genel Gider'i oraya tasi"). Malzeme ve
 * Hizmetler'in genel gider kalemleri; ekran, form, arsiv ve kurallar
 * CatalogItemResource'tan (yalniz tur sabit: Genel gider).
 */
class GeneralExpenseResource extends CatalogItemResource
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'genel-gider';

    public static function kinds(): array
    {
        return [CatalogItemKind::GeneralExpense];
    }

    public static function getModelLabel(): string
    {
        return __('catalog.general_expense.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.general_expense.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.general_expense.nav');
    }

    public static function createLabel(): string
    {
        return __('catalog.general_expense.create');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::CatalogGeneralExpenses) && parent::canAccess();
    }

    /** D-228 ek: kendi kategori sayfasi; menude bu satir secili kalir. */
    public static function categoryResource(): string
    {
        return GeneralExpenseCategoryResource::class;
    }

    public static function getNavigationItemActiveRoutePattern(): string | array
    {
        return [static::getRouteBaseName().'.*', GeneralExpenseCategoryResource::getRouteBaseName().'.*'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralExpenses::route('/'),
            'create' => CreateGeneralExpense::route('/create'),
            'view' => ViewGeneralExpense::route('/{record}'),
            'edit' => EditGeneralExpense::route('/{record}/edit'),
        ];
    }
}
