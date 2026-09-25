<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Personnel\ReportingRelationship;
use App\Policies\Concerns\ResolvesInterimRoles;

final class ReportingRelationshipPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, ReportingRelationship $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    /**
     * Ek amir (islevsel / proje) eklemek (D-121): personel kartini
     * duzenleyebilen kisi. Dogrudan amir yine personel formundan degisir.
     */
    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    /** Ek amir iliskisini kapatmak (D-121); dogrudan amir buradan kapanmaz. */
    public function update(Personnel $personnel, ReportingRelationship $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, ReportingRelationship $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, ReportingRelationship $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, ReportingRelationship $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, ReportingRelationship $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
