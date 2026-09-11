<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Acquisition\OpportunityStageHistory;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

final class OpportunityStageHistoryPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, OpportunityStageHistory $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return false;
    }

    public function update(Personnel $personnel, OpportunityStageHistory $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, OpportunityStageHistory $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, OpportunityStageHistory $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, OpportunityStageHistory $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, OpportunityStageHistory $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
