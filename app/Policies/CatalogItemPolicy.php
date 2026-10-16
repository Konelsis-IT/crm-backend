<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Katalog kalemi (B52, D-189). Gormek: katalog izni, ust yonetim ya da tam
 * yetki. Eklemek / duzenlemek / arsivlemek: katalog izni (Satin Alma).
 * Silme yok, arsiv var (D-156); arsivdeki kalem degistirilemez.
 */
final class CatalogItemPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, CatalogItem $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, CatalogItem $record): bool
    {
        return ! $record->isArchived() && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'));
    }

    public function archive(Personnel $personnel, CatalogItem $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, CatalogItem $record): bool
    {
        return false;
    }
}
