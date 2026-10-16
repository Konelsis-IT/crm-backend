<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Report\RecurringWork;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\Report\ReportQueries;

/**
 * Gorev ve sorumluluk (tekrarli is tanimi, D-219). Kendi izin anahtari yoktur;
 * Is panosu (WorkItem) yetkisini devralir (PermissionSubjects).
 *
 * - Kisi kendi gorevlerini ve uyum durumunu gorur, tanimini degistirmez.
 * - Amir ve departman yoneticisi ekibinin gorevlerini tanimlar ve duzenler.
 * - Ust yonetim ve tam yetkili rol hepsini gorur ve duzenler.
 * - Tanim silinmez, arsivlenir (D-156).
 */
final class RecurringWorkPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, RecurringWork $record): bool
    {
        if (! $this->viewAny($personnel)) {
            return false;
        }

        return (int) $record->personnel_id === (int) $personnel->getKey()
            || $this->seesCompanyWide($personnel)
            || $this->isAuditor($personnel)
            || $this->manages($personnel, (int) $record->personnel_id);
    }

    /** Gorev tanimlamak: ekibi olan amir ya da ust yonetim. */
    public function create(Personnel $personnel): bool
    {
        if (! $this->viewAny($personnel)) {
            return false;
        }

        return $this->seesCompanyWide($personnel)
            || app(ReportQueries::class)->teamPersonnelIds((int) $personnel->getKey()) !== [];
    }

    public function update(Personnel $personnel, RecurringWork $record): bool
    {
        if (! $this->viewAny($personnel)) {
            return false;
        }

        return $this->seesCompanyWide($personnel)
            || ((int) $record->personnel_id !== (int) $personnel->getKey() && $this->manages($personnel, (int) $record->personnel_id));
    }

    /** Arsivle / arsivden cikar (D-156). */
    public function archive(Personnel $personnel, RecurringWork $record): bool
    {
        return $this->update($personnel, $record);
    }

    public function delete(Personnel $personnel, RecurringWork $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, RecurringWork $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, RecurringWork $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, RecurringWork $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function manages(Personnel $personnel, int $subjectId): bool
    {
        return $subjectId > 0 && app(ReportQueries::class)->managesPersonnel((int) $personnel->getKey(), $subjectId);
    }
}
