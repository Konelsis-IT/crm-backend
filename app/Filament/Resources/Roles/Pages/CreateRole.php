<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole as ShieldCreateRole;

/**
 * Shield sayfasi, uygulamanin rol kaynagiyla. Koruma adi formda gizlidir ve
 * her zaman 'web' yazilir (D-130); izinler de ayni korumayla olusur.
 */
class CreateRole extends ShieldCreateRole
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = RoleResource::GUARD;
        $this->data['guard_name'] = RoleResource::GUARD;

        return parent::mutateFormDataBeforeCreate($data);
    }
}
