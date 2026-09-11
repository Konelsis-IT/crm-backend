<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification\Announcement;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Services\Notification\AudienceResolver;

/**
 * Duyurular herkese gorunur (panoda); gonderme yetkisi hedef kitle
 * izinlerine baglidir (notify:*). Duzenleme/silme yok: kayit yalniz eklenir.
 */
final class AnnouncementPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, Announcement $record): bool
    {
        return $personnel->isActive();
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive() && app(AudienceResolver::class)->permittedKinds($personnel) !== [];
    }

    public function update(Personnel $personnel, Announcement $record): bool
    {
        return false;
    }

    public function delete(Personnel $personnel, Announcement $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, Announcement $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, Announcement $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, Announcement $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }
}
