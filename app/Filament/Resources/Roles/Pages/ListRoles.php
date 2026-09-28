<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles as ShieldListRoles;

/** Shield sayfasi, uygulamanin rol kaynagiyla (D-130: koruma adi gizli). */
class ListRoles extends ShieldListRoles
{
    protected static string $resource = RoleResource::class;
}
