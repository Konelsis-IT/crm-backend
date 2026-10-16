<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogCategory;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Katalog kategorisi (B52, D-189): kendi izin anahtari yoksa katalog kalemi
 * yetkisi gecerlidir (PermissionSubjects). Silme yok, arsiv var.
 */
final class CatalogCategoryPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, CatalogCategory $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, CatalogCategory $record): bool
    {
        return ! $record->isArchived() && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'));
    }

    public function archive(Personnel $personnel, CatalogCategory $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, CatalogCategory $record): bool
    {
        return false;
    }
}
