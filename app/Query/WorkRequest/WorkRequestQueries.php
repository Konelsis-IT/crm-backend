<?php

declare(strict_types=1);

namespace App\Query\WorkRequest;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Enums\WorkRequest\WorkRequestStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\Proposal;
use App\Models\Activity\PersonnelActivity;
use App\Models\Approval\ApprovalRequest;
use App\Models\Document\Document;
use App\Models\Party\Party;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\WorkRequest\WorkRequest;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Talep okuma sorgulari (D-84): gelen kutusu, taraf kontrolleri, ilgili
 * kayit secenekleri ve talebin hareket gecmisi.
 */
final class WorkRequestQueries
{
    /** Kisinin birimleri: kendi birimi + yonettigi birimler. */
    public function unitIdsFor(int $personnelId): array
    {
        $own = Personnel::query()->whereKey($personnelId)->value('org_unit_id');
        $managed = OrgUnit::query()->where('manager_personnel_id', $personnelId)->pluck('id')->all();

        $ids = array_map('intval', $managed);

        if ($own !== null) {
            $ids[] = (int) $own;
        }

        return array_values(array_unique($ids));
    }

    public function belongsToUnit(int $personnelId, int $unitId): bool
    {
        return in_array($unitId, $this->unitIdsFor($personnelId), true);
    }

    public function managesUnit(int $personnelId, int $unitId): bool
    {
        return OrgUnit::query()->whereKey($unitId)->where('manager_personnel_id', $personnelId)->exists();
    }

    /** Birim uyeleri + yoneticisi (bildirim alicilari). */
    public function unitMembers(int $unitId): Collection
    {
        $members = Personnel::query()
            ->reachable()
            ->where('org_unit_id', $unitId)
            ->get();

        $manager = OrgUnit::query()->whereKey($unitId)->first()?->manager;

        if ($manager instanceof Personnel && $manager->isReachable() && ! $members->contains(fn (Personnel $p): bool => $p->is($manager))) {
            $members->push($manager);
        }

        return $members;
    }

    /** Gelen kutusu: bana, sorumlusu oldugum ya da birimime gelen acik talepler. */
    public function applyInbox(Builder $query, int $personnelId): Builder
    {
        $unitIds = $this->unitIdsFor($personnelId);

        return $query
            ->whereIn('status', [WorkRequestStatus::Open->value, WorkRequestStatus::InProgress->value])
            ->where(function (Builder $inner) use ($personnelId, $unitIds): void {
                $inner->where('target_personnel_id', $personnelId)
                    ->orWhere('assignee_personnel_id', $personnelId);

                if ($unitIds !== []) {
                    $inner->orWhere(function (Builder $unit) use ($unitIds): void {
                        $unit->where('target_kind', RequestTargetKind::OrgUnit->value)
                            ->whereIn('target_org_unit_id', $unitIds);
                    });
                }
            });
    }

    public function applyMine(Builder $query, int $personnelId): Builder
    {
        return $query->where('requester_personnel_id', $personnelId);
    }

    public function applyOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [WorkRequestStatus::Open->value, WorkRequestStatus::InProgress->value]);
    }

    public function inboxCount(int $personnelId): int
    {
        if (! SchemaReadiness::hasBatch('B11B')) {
            return 0;
        }

        return $this->applyInbox(WorkRequest::query(), $personnelId)->count();
    }

    /** Talebin hareket gecmisi (Personel Hareketleri, en yeni ustte). */
    public function activitiesFor(int $requestId): Collection
    {
        return PersonnelActivity::query()
            ->with('personnel')
            ->where('subject_type', 'work_request')
            ->where('subject_id', (string) $requestId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Talebin onay talepleri (en yeni ustte).
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function approvalRequestsFor(int $requestId): Collection
    {
        if (! SchemaReadiness::hasBatch('B07')) {
            return new Collection;
        }

        return ApprovalRequest::query()
            ->where('subject_type', 'work_request')
            ->where('subject_id', $requestId)
            ->orderByDesc('requested_at')
            ->get();
    }

    /**
     * Onaya gonderilebilecek acik talepler: benim actiklarim ve gelen kutum.
     *
     * @return array<int, string>
     */
    public function approvableOptions(int $personnelId): array
    {
        $mine = $this->applyMine($this->applyOpen(WorkRequest::query()), $personnelId);
        $inbox = $this->applyInbox(WorkRequest::query(), $personnelId);

        return $mine->get()->merge($inbox->get())
            ->unique(fn (WorkRequest $request) => $request->getKey())
            ->sortByDesc('id')
            ->mapWithKeys(fn (WorkRequest $request): array => [(int) $request->getKey() => $request->request_no.' · '.$request->title])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function customerOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            return [];
        }

        return Party::query()
            ->whereNull('merged_into_party_id')
            ->whereHas('roles', fn (Builder $roles) => $roles->where('role_code', 'customer'))
            ->orderBy('display_name')
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function proposalOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            return [];
        }

        return Proposal::query()
            ->orderByDesc('id')
            ->get(['id', 'proposal_no', 'title'])
            ->mapWithKeys(fn (Proposal $proposal): array => [(int) $proposal->getKey() => trim($proposal->proposal_no.' · '.$proposal->title, ' ·')])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function businessCaseOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            return [];
        }

        return BusinessCase::query()
            ->orderByDesc('id')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function contractOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B16')) {
            return [];
        }

        return Contract::query()
            ->with('businessCase')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Contract $contract): array => [(int) $contract->getKey() => trim($contract->contract_no.' · '.($contract->businessCase?->title ?? ''), ' ·')])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function documentOptions(): array
    {
        if (! SchemaReadiness::hasBatch('B06')) {
            return [];
        }

        return Document::query()
            ->orderByDesc('id')
            ->get(['id', 'document_no', 'title'])
            ->mapWithKeys(fn (Document $document): array => [(int) $document->getKey() => trim($document->document_no.' · '.$document->title, ' ·')])
            ->all();
    }
}
