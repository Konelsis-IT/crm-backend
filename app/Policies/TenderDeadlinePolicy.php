<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Acquisition\TenderDeadline;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

final class TenderDeadlinePolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, TenderDeadline $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->isSystemAdmin($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, TenderDeadline $record): bool
    {
        return $this->isSystemAdmin($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, TenderDeadline $record): bool
    {
        return $this->isSystemAdmin($personnel) || $this->permits($personnel, 'delete');
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, TenderDeadline $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, TenderDeadline $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, TenderDeadline $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
