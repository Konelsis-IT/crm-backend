<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff\Pages;

use App\Filament\Resources\CatalogItems\Pages\EditCatalogItem;
use App\Filament\Resources\AdminStaff\AdminStaffResource;

/** EditCatalogItem (D-228): ayni sayfa, AdminStaffResource ekraninda. */
class EditAdminStaff extends EditCatalogItem
{
    protected static string $resource = AdminStaffResource::class;
}
