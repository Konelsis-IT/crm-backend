<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff\Pages;

use App\Filament\Resources\CatalogItems\Pages\ListCatalogItems;
use App\Filament\Resources\AdminStaff\AdminStaffResource;

/** ListCatalogItems (D-228): ayni sayfa, AdminStaffResource ekraninda. */
class ListAdminStaff extends ListCatalogItems
{
    protected static string $resource = AdminStaffResource::class;
}
