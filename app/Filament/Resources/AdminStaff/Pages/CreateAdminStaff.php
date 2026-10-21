<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminStaff\Pages;

use App\Filament\Resources\CatalogItems\Pages\CreateCatalogItem;
use App\Filament\Resources\AdminStaff\AdminStaffResource;

/** CreateCatalogItem (D-228): ayni sayfa, AdminStaffResource ekraninda. */
class CreateAdminStaff extends CreateCatalogItem
{
    protected static string $resource = AdminStaffResource::class;
}
