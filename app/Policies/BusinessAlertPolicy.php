<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification\BusinessAlert;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Uyariyi sahibi, sahibinin amiri ve okuma yetkisi olanlar gorur; "gordum"
 * isaretini sahibi ya da yetkili verir. Kayit sistem tarafindan acilir/kapanir.
 */
final class BusinessAlertPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, BusinessAlert $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view') || $this->isOwner($personnel, $record);
    }

    public function acknowledge(Personnel $personnel, BusinessAlert $record): bool
    {
        return $record->isOpen() && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'acknowledge') || $this->isOwner($personnel, $record));
    }

    public function create(Personnel $personnel): bool
    {
        return false;
    }

    public function update(Personnel $personnel, BusinessAlert $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, BusinessAlert $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, BusinessAlert $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, BusinessAlert $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, BusinessAlert $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function isOwner(Personnel $personnel, BusinessAlert $record): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        if ((int) $record->owner_personnel_id === (int) $personnel->getKey()) {
            return true;
        }

        $owner = $record->owner;

        return $owner !== null && (int) ($owner->currentManager()?->getKey() ?? 0) === (int) $personnel->getKey();
    }
}
