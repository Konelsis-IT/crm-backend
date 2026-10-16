<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\CompanyPurchase;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Sirket ici alimlar (B52, D-189). Gormek: izin, ust yonetim, tam yetki,
 * denetci. Eklemek / duzenlemek / yenilemek / arsivlemek: izin (Satin Alma).
 * Silme yok, arsiv var (D-156).
 */
final class CompanyPurchasePolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, CompanyPurchase $record): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, CompanyPurchase $record): bool
    {
        return ! $record->isArchived() && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'));
    }

    public function archive(Personnel $personnel, CompanyPurchase $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, CompanyPurchase $record): bool
    {
        return false;
    }
}
