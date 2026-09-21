<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Party\MeetingPlanSource;
use App\Enums\Party\MeetingPlanStatus;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Personnel;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Gorusme plani (B34, D-109). Liste ve takvim izinle acilir; sorumlu ya da
 * katilan personel kendi plani uzerinde sonuc girer, erteler, iptal eder.
 * Gerceklesmis gorusme (done) duzenlenmez: sonucu Taraf > Gorusme
 * notlarindan duzeltilir. Nottan / sonraki adimdan yansiyan satir silinmez,
 * notla birlikte yasar.
 */
final class MeetingPlanPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'viewAny');
    }

    public function view(Personnel $personnel, MeetingPlan $record): bool
    {
        return $this->canRead($personnel) || $this->permits($personnel, 'view') || $this->isInvolved($personnel, $record);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'create');
    }

    public function update(Personnel $personnel, MeetingPlan $record): bool
    {
        return $record->status === MeetingPlanStatus::Planned
            && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update') || $this->isInvolved($personnel, $record));
    }

    /** Sonuc girme, erteleme, iptal: planli gorusmede sorumlu, katilan ya da yetkili. */
    public function complete(Personnel $personnel, MeetingPlan $record): bool
    {
        return $this->update($personnel, $record);
    }

    public function cancel(Personnel $personnel, MeetingPlan $record): bool
    {
        return $this->update($personnel, $record);
    }

    public function delete(Personnel $personnel, MeetingPlan $record): bool
    {
        return $record->status !== MeetingPlanStatus::Done
            && in_array($record->source, [MeetingPlanSource::Manual, MeetingPlanSource::Import], true)
            && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'delete'));
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, MeetingPlan $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, MeetingPlan $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, MeetingPlan $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    private function isInvolved(Personnel $personnel, MeetingPlan $record): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        $id = (int) $personnel->getKey();

        return (int) $record->personnel_id === $id
            || $record->participantRows()->where('personnel_id', $id)->exists();
    }
}
