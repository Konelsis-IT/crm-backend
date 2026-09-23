<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

final class PersonnelPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, Personnel $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, Personnel $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    /**
     * Personel kartindaki Personel Hareketleri sekmesi (D-116, kullanici
     * karari): yalniz ust yonetim gorur (kendi karti dahil); baska hic kimse
     * gormez.
     */
    public function viewActivities(Personnel $personnel, Personnel $record): bool
    {
        return $this->view($personnel, $record) && $this->seesCompanyWide($personnel);
    }

    public function changeStatus(Personnel $personnel, Personnel $record): bool
    {
        return ($this->hasFullAccess($personnel) || $this->permits($personnel, 'changeStatus')) && ! $personnel->is($record);
    }

    public function delete(Personnel $personnel, Personnel $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Personnel $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Personnel $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Personnel $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
