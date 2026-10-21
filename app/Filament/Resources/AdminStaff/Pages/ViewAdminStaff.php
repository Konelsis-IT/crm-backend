<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff\Pages;

use App\Filament\Resources\CatalogItems\Pages\ViewCatalogItem;
use App\Filament\Resources\AdminStaff\AdminStaffResource;

/** ViewCatalogItem (D-228): ayni sayfa, AdminStaffResource ekraninda. */
class ViewAdminStaff extends ViewCatalogItem
{
    protected static string $resource = AdminStaffResource::class;
}
