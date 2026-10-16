<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Birim (D-194, Ayarlar > Birimler). Gormek: tam yetki / denetci ya da izin;
 * eklemek, duzenlemek, pasife almak / aktif yapmak: tam yetki ya da izin.
 * Izin anahtari yoksa Malzeme ve Hizmetler izni gecer (PermissionSubjects:
 * kalemin birimini belirleyen satin alma, eksik birimi de ekleyebilir).
 * Silme yok; birim pasife alinir. Secim kutulari bu politikaya bakmaz.
 */
final class UnitOfMeasurePolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, UnitOfMeasure $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, UnitOfMeasure $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, UnitOfMeasure $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, UnitOfMeasure $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, UnitOfMeasure $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, UnitOfMeasure $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
