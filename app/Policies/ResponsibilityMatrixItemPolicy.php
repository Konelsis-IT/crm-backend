<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Acquisition\ResponsibilityMatrixItem;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

final class ResponsibilityMatrixItemPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, ResponsibilityMatrixItem $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->isSystemAdmin($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, ResponsibilityMatrixItem $record): bool
    {
        return $this->isSystemAdmin($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, ResponsibilityMatrixItem $record): bool
    {
        return $this->isSystemAdmin($personnel) || $this->permits($personnel, 'delete');
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, ResponsibilityMatrixItem $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, ResponsibilityMatrixItem $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, ResponsibilityMatrixItem $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
