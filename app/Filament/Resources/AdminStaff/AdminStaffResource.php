<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Filament\NavigationGroup;
use App\Filament\Resources\CatalogItems\CatalogItemResource;
use App\Filament\Resources\CatalogItems\RelationManagers\CostLinesRelationManager;
use App\Filament\Resources\AdminStaff\Pages\CreateAdminStaff;
use App\Filament\Resources\AdminStaff\Pages\EditAdminStaff;
use App\Filament\Resources\AdminStaff\Pages\ListAdminStaff;
use App\Filament\Resources\AdminStaff\Pages\ViewAdminStaff;
use App\Services\Platform\FeatureFlags;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Idari > Idari Kadro (D-228, 19 Ekim 2026, kullanici: "Idari Kadro kismini
 * ise Idari ana menusu altina alalim"). Malzeme ve Hizmetler'in idari kadro
 * kalemleri; ekran, form, arsiv ve kurallar CatalogItemResource'tan (yalniz tur
 * sabit: Idari kadro). Satin alinmaz: kalem sayfasinda yalniz teklif / proje
 * maliyet satirlari.
 */
class AdminStaffResource extends CatalogItemResource
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Administrative;

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'idari-kadro';

    public static function kinds(): array
    {
        return [CatalogItemKind::AdminStaff];
    }

    public static function getModelLabel(): string
    {
        return __('catalog.admin_staff.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.admin_staff.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.admin_staff.nav');
    }

    public static function createLabel(): string
    {
        return __('catalog.admin_staff.create');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::CatalogAdminStaff) && parent::canAccess();
    }

    /** D-228 ek: kendi kategori sayfasi; menude bu satir secili kalir. */
    public static function categoryResource(): string
    {
        return AdminStaffCategoryResource::class;
    }

    public static function getNavigationItemActiveRoutePattern(): string | array
    {
        return [static::getRouteBaseName().'.*', AdminStaffCategoryResource::getRouteBaseName().'.*'];
    }

    public static function getRelations(): array
    {
        return [
            CostLinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminStaff::route('/'),
            'create' => CreateAdminStaff::route('/create'),
            'view' => ViewAdminStaff::route('/{record}'),
            'edit' => EditAdminStaff::route('/{record}/edit'),
        ];
    }
}
