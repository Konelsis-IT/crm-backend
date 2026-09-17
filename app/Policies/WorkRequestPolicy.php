<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\WorkRequest\WorkRequest;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\WorkRequest\WorkRequestQueries;

/**
 * Talep (D-84): her aktif personel talep acar; talebi taraflari gorur
 * (talep eden, muhatap kisi, muhatap birimin uyeleri/yoneticisi, sorumlu).
 * Muhatap taraf kabul eder / tamamlar / reddeder; talep eden iptal eder ve
 * onay merciini belirler (D-87); birim yoneticisi sorumlu atar. Talep eden
 * kendi talebini hicbir zaman kabul edemez / tamamlayamaz / reddedemez.
 * Kayit silinmez.
 */
final class WorkRequestPolicy
{
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, WorkRequest $record): bool
    {
        return $this->canRead($personnel)
            || $this->permits($personnel, 'view')
            || $this->isRequesterSide($personnel, $record)
            || $this->isTargetSide($personnel, $record)
            || $this->isApprover($personnel, $record);
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    /** Talep eden, talep henuz kabul edilmemisken duzenler. */
    public function update(Personnel $personnel, WorkRequest $record): bool
    {
        return $record->status->value === 'open'
            && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update') || $this->isRequester($personnel, $record));
    }

    public function accept(Personnel $personnel, WorkRequest $record): bool
    {
        return $record->status->value === 'open' && $this->canHandle($personnel, $record);
    }

    public function complete(Personnel $personnel, WorkRequest $record): bool
    {
        return $record->isOpen() && $this->canHandle($personnel, $record);
    }

    public function reject(Personnel $personnel, WorkRequest $record): bool
    {
        return $record->isOpen() && $this->canHandle($personnel, $record);
    }

    public function cancel(Personnel $personnel, WorkRequest $record): bool
    {
        return $record->isOpen()
            && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'cancel') || $this->isRequesterSide($personnel, $record));
    }

    public function reassign(Personnel $personnel, WorkRequest $record): bool
    {
        if (! $record->isOpen() || ! $record->targetsOrgUnit()) {
            return false;
        }

        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'reassign')
            || app(WorkRequestQueries::class)->managesUnit((int) $personnel->getKey(), (int) $record->target_org_unit_id);
    }

    public function delete(Personnel $personnel, WorkRequest $record): bool
    {
        return false;
    }

    public function deleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function forceDelete(Personnel $personnel, WorkRequest $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Personnel $personnel): bool
    {
        return false;
    }

    public function restore(Personnel $personnel, WorkRequest $record): bool
    {
        return false;
    }

    public function restoreAny(Personnel $personnel): bool
    {
        return false;
    }

    public function replicate(Personnel $personnel, WorkRequest $record): bool
    {
        return false;
    }

    public function reorder(Personnel $personnel): bool
    {
        return false;
    }

    /**
     * Onaya tabi yapma (D-87): talep eden tarafi, acik talepte, henuz onay
     * mercii yokken onay merciini belirler.
     */
    public function designateApprover(Personnel $personnel, WorkRequest $record): bool
    {
        return $record->isOpen()
            && ! (bool) $record->requires_approval
            && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'designateApprover') || $this->isRequesterSide($personnel, $record));
    }

    /**
     * Kabul / tamamla / reddet muhatap tarafin isidir; talep eden kendi
     * talebini yonetemez (12 Eylul 2026 netlestirmesi), sistem yoneticisi
     * olsa bile.
     */
    private function canHandle(Personnel $personnel, WorkRequest $record): bool
    {
        if ($this->isRequester($personnel, $record)) {
            return false;
        }

        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'complete')
            || $this->isTargetSide($personnel, $record);
    }

    private function isRequester(Personnel $personnel, WorkRequest $record): bool
    {
        return $personnel->isActive() && (int) $record->requester_personnel_id === (int) $personnel->getKey();
    }

    /** Onaya tabi talebin onay mercii (D-87) talebi gorur. */
    private function isApprover(Personnel $personnel, WorkRequest $record): bool
    {
        return $personnel->isActive()
            && $record->approver_personnel_id !== null
            && (int) $record->approver_personnel_id === (int) $personnel->getKey();
    }

    private function isRequesterSide(Personnel $personnel, WorkRequest $record): bool
    {
        if ($this->isRequester($personnel, $record)) {
            return true;
        }

        return $personnel->isActive()
            && $record->requester_org_unit_id !== null
            && app(WorkRequestQueries::class)->managesUnit((int) $personnel->getKey(), (int) $record->requester_org_unit_id);
    }

    private function isTargetSide(Personnel $personnel, WorkRequest $record): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        $id = (int) $personnel->getKey();

        if ((int) $record->assignee_personnel_id === $id || (int) $record->target_personnel_id === $id) {
            return true;
        }

        return $record->targetsOrgUnit()
            && app(WorkRequestQueries::class)->belongsToUnit($id, (int) $record->target_org_unit_id);
    }
}
