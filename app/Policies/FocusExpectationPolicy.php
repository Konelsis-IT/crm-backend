<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Project\FocusExpectation;
use App\Policies\Concerns\ResolvesInterimRoles;

final class FocusExpectationPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, FocusExpectation $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, FocusExpectation $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, FocusExpectation $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'delete');
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, FocusExpectation $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, FocusExpectation $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, FocusExpectation $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
