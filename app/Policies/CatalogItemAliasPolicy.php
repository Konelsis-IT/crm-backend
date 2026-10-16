<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItemAlias;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Katalog esleme adi (B52, D-189): katalog kalemi yetkisini devralir
 * (PermissionSubjects). Satirlar degismez; yanlis esleme adi silinebilir
 * (alt detay satiri).
 */
final class CatalogItemAliasPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, CatalogItemAlias $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function update(Personnel $personnel, CatalogItemAlias $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, CatalogItemAlias $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }
}
