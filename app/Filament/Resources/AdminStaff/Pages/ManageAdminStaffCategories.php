<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff\Pages;

use App\Filament\Resources\CatalogCategories\Pages\ManageCatalogCategories;
use App\Filament\Resources\AdminStaff\AdminStaffCategoryResource;

/** ManageCatalogCategories (D-228 ek): ayni sayfa, AdminStaffCategoryResource ekraninda. */
class ManageAdminStaffCategories extends ManageCatalogCategories
{
    protected static string $resource = AdminStaffCategoryResource::class;
}
