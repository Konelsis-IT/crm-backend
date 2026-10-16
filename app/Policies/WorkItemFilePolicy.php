<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Models\Report\WorkItemFile;
use Illuminate\Support\Facades\Gate;

/**
 * Is belgesi (D-219): isi goren belgeyi acar, isi guncelleyebilen belge ekler
 * ve baglantiyi kaldirir (alt kayit, D-156 geregi silinebilir; dosya kalir).
 */
final class WorkItemFilePolicy
{
    public function viewAny(Personnel $personnel): bool
    {
        return Gate::forUser($personnel)->allows('viewAny', WorkItem::class);
    }

    public function view(Personnel $personnel, WorkItemFile $record): bool
    {
        $item = $record->workItem;

        return $item instanceof WorkItem && Gate::forUser($personnel)->allows('view', $item);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->viewAny($personnel);
    }

    public function update(Personnel $personnel, WorkItemFile $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, WorkItemFile $record): bool
    {
        $item = $record->workItem;

        return $item instanceof WorkItem && Gate::forUser($personnel)->allows('update', $item);
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, WorkItemFile $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, WorkItemFile $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, WorkItemFile $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
