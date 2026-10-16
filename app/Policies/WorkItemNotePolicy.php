<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Models\Report\WorkItemNote;
use Illuminate\Support\Facades\Gate;

/**
 * Is notu (D-219): isi goren notu gorur, isi guncelleyebilen not yazar. Not
 * duzenlenmez ve silinmez; yenisi yazilir.
 */
final class WorkItemNotePolicy
{
    public function viewAny(Personnel $personnel): bool
    {
        return Gate::forUser($personnel)->allows('viewAny', WorkItem::class);
    }

    public function view(Personnel $personnel, WorkItemNote $record): bool
    {
        $item = $record->workItem;

        return $item instanceof WorkItem && Gate::forUser($personnel)->allows('view', $item);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->viewAny($personnel);
    }

    public function update(Personnel $personnel, WorkItemNote $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, WorkItemNote $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, WorkItemNote $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, WorkItemNote $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, WorkItemNote $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
