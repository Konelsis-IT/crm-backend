<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Filament\Resources\CatalogCategories\CatalogCategoryResource;
use App\Filament\Resources\AdminStaff\Pages\ManageAdminStaffCategories;
use App\Services\Platform\FeatureFlags;

/**
 * Idari > Idari Kadro kategorileri (D-228 ek, 19 Ekim 2026, kullanici: "idari kadronun ve
 * genel giderin kategorileri kendi menusu icinde olmali. Malzeme ve Hizmetler
 * tarafini acmamali"). Sayfa, form ve kurallar CatalogCategoryResource'tan;
 * yalniz bu turun agaci. Sol menude ayri satir yok: ekranin "Kategoriler"
 * dugmesiyle acilir, menude AdminStaffResource satiri secili kalir.
 */
class AdminStaffCategoryResource extends CatalogCategoryResource
{
    protected static ?string $slug = 'idari-kadro-kategorileri';

    public static function kinds(): array
    {
        return [CatalogItemKind::AdminStaff];
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.admin_staff.categories');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.admin_staff.categories');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::CatalogAdminStaff) && parent::canAccess();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdminStaffCategories::route('/'),
        ];
    }
}
