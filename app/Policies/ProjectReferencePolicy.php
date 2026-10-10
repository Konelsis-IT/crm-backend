<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Acquisition\ProjectReference;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Referanslar (B50, D-177). Kendi izin anahtari verilmediyse teklif yetkisi
 * gecerlidir (PermissionSubjects: ProjectReference -> Proposal): teklif
 * hazirlayan referans listesini gorur ve referans ekler. Silme yok, arsiv var
 * (D-156); arsivdeki referans degistirilemez.
 */
final class ProjectReferencePolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, ProjectReference $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, ProjectReference $record): bool
    {
        return ! $record->isArchived() && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'));
    }

    /** Arsive alma ve geri alma duzenleme yetkisiyle (D-156). */
    public function archive(Personnel $personnel, ProjectReference $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, ProjectReference $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, ProjectReference $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, ProjectReference $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, ProjectReference $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
