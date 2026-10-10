<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectTypeCoordinator;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Proje tipi koordinatorleri (B49, D-175): kendi izin anahtari yoksa yetki
 * ana konudan (Project) devralinir (PermissionSubjects). Projeleri gorebilen
 * listeyi gorur; koordinator atamak / kaldirmak proje duzenleme yetkisi ister.
 * Atama satiri silinmez (gecmis kalir).
 */
final class ProjectTypeCoordinatorPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, ProjectTypeCoordinator $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view');
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function update(Personnel $personnel, ProjectTypeCoordinator $record): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function delete(Personnel $personnel, ProjectTypeCoordinator $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, ProjectTypeCoordinator $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, ProjectTypeCoordinator $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, ProjectTypeCoordinator $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
