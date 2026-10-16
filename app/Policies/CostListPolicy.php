<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\CostList;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Teklif maliyet listesi aktarimi (B52, D-189): teklifin aracidir; kendi izin
 * anahtari yoksa teklif yetkisi gecerlidir (PermissionSubjects: CostList ->
 * Proposal). Aktarim teklif duzenleme yetkisiyle; silme yok.
 */
final class CostListPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, CostList $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function update(Personnel $personnel, CostList $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, CostList $record): bool
    {
        return false;
    }
}
