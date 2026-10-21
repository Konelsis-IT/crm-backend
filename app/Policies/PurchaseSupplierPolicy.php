<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseSupplier;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Satin alma tedarikcisi (D-232, B58): kendi izin anahtari yoksa Malzeme ve
 * Hizmetler (katalog) yetkisi gecerlidir (PermissionSubjects) - Satin Alma
 * yonetir, katalogu okuyabilen gorur. Silme yok, arsiv var (D-156).
 */
final class PurchaseSupplierPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, PurchaseSupplier $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, PurchaseSupplier $record): bool
    {
        return ! $record->isArchived() && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'));
    }

    public function archive(Personnel $personnel, PurchaseSupplier $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, PurchaseSupplier $record): bool
    {
        return false;
    }
}
