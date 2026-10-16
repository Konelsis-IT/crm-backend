<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ViewRole as ShieldViewRole;

/** Shield sayfasi, uygulamanin rol kaynagiyla (D-130: koruma adi gizli). */
class ViewRole extends ShieldViewRole
{
    use HasRecordHeading;

    protected static string $resource = RoleResource::class;
}
