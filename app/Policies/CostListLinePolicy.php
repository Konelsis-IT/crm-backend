<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\CostListLine;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Maliyet kalemi (B52, D-189): teklif yetkisini devralir (PermissionSubjects).
 * Katalogla eslestirme ve satin almaya gonderme teklif duzenleme yetkisiyle.
 */
final class CostListLinePolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, CostListLine $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return false;
    }

    /** Katalog eslesmesini cozmek ve satin almaya gondermek. */
    public function update(Personnel $personnel, CostListLine $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, CostListLine $record): bool
    {
        return false;
    }
}
