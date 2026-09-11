<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\ResolverType;
use App\Enums\Approval\UnresolvedReason;
use App\Enums\Personnel\PersonnelStatus;
use App\Models\Approval\ApprovalStep;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PositionAssignment;
use App\Models\Project\ProjectTeamMember;
use App\Services\Approval\Subjects\SubjectContext;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Support\Carbon;

/**
 * Bir onay adiminin onaycilarini bulur (12 SS2.3 resolver_type).
 *
 * - personnel / position: dogrudan hedef.
 * - line_manager: talep sahibinin su anki dogrudan amiri.
 * - org_unit_manager: talep sahibinin biriminin yoneticisi.
 * - functional_manager: konunun (dokumanin) sahibi birimin yoneticisi;
 *   yoksa talep sahibinin birim yoneticisi.
 * - project_role: konunun projesindeki ekip rolu (proje yoneticisi vb.).
 * - executive: ust yonetim — simdilik system_admin rolu (kurumsal
 *   "yonetim" rolu tanimlanana kadar).
 * - rbac_role: verilen role sahip aktif personel.
 * - functional_area_role: fonksiyonel alanlar (SM01) kurulmadigi icin cozulmez.
 *
 * Yalniz aktif personel donulur; bulunamayinca neden (12 SS2.5) verilir.
 */
final class ApproverResolver
{
    public function resolve(ApprovalStep $step, SubjectContext $context, int $requesterId): ResolvedApprovers
    {
        $type = $step->resolver_type;

        return match ($type) {
            ResolverType::Personnel => $this->direct($step->resolver_target_id),
            ResolverType::Position => $this->position($step->resolver_target_id),
            ResolverType::LineManager => $this->lineManager($requesterId),
            ResolverType::OrgUnitManager => $this->orgUnitManager($this->requesterOrgUnitId($requesterId)),
            ResolverType::FunctionalManager => $this->orgUnitManager($context->orgUnitId ?? $this->requesterOrgUnitId($requesterId)),
            ResolverType::ProjectRole => $this->projectRole($context->projectId, (string) $step->role_code),
            ResolverType::FunctionalAreaRole => ResolvedApprovers::none(UnresolvedReason::NoRoleHolder),
            ResolverType::Executive => $this->roleHolders(RoleResolver::SYSTEM_ADMIN),
            ResolverType::RbacRole => $this->roleHolders((string) $step->role_code),
        };
    }

    private function direct(?int $personnelId): ResolvedApprovers
    {
        if ($personnelId === null) {
            return ResolvedApprovers::none(UnresolvedReason::NoRoleHolder);
        }

        $ids = $this->activeIds([$personnelId]);

        return $ids === [] ? ResolvedApprovers::none(UnresolvedReason::InactiveUser) : ResolvedApprovers::of($ids);
    }

    private function position(?int $positionId): ResolvedApprovers
    {
        if ($positionId === null || ! SchemaReadiness::hasBatch('B03')) {
            return ResolvedApprovers::none(UnresolvedReason::VacantPosition);
        }

        $today = Carbon::today();

        $ids = PositionAssignment::query()
            ->where('position_id', $positionId)
            ->where('valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->pluck('personnel_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ids = $this->activeIds($ids);

        return $ids === [] ? ResolvedApprovers::none(UnresolvedReason::VacantPosition) : ResolvedApprovers::of($ids);
    }

    private function lineManager(int $requesterId): ResolvedApprovers
    {
        /** @var Personnel|null $requester */
        $requester = Personnel::query()->find($requesterId);
        $manager = $requester?->currentManager();

        if ($manager === null) {
            return ResolvedApprovers::none(UnresolvedReason::NoManager);
        }

        $ids = $this->activeIds([(int) $manager->getKey()]);

        return $ids === [] ? ResolvedApprovers::none(UnresolvedReason::InactiveUser) : ResolvedApprovers::of($ids);
    }

    private function orgUnitManager(?int $orgUnitId): ResolvedApprovers
    {
        if ($orgUnitId === null) {
            return ResolvedApprovers::none(UnresolvedReason::NoManager);
        }

        /** @var OrgUnit|null $unit */
        $unit = OrgUnit::query()->find($orgUnitId);
        $managerId = $unit?->manager_personnel_id;

        if ($managerId === null) {
            return ResolvedApprovers::none(UnresolvedReason::NoManager);
        }

        $ids = $this->activeIds([(int) $managerId]);

        return $ids === [] ? ResolvedApprovers::none(UnresolvedReason::InactiveUser) : ResolvedApprovers::of($ids);
    }

    private function projectRole(?int $projectId, string $roleCode): ResolvedApprovers
    {
        if ($projectId === null || $roleCode === '' || ! SchemaReadiness::hasBatch('B17A')) {
            return ResolvedApprovers::none(UnresolvedReason::NoRoleHolder);
        }

        $ids = ProjectTeamMember::query()
            ->where('project_id', $projectId)
            ->where('team_role', $roleCode)
            ->where('status', 'active')
            ->pluck('personnel_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ids = $this->activeIds($ids);

        return $ids === [] ? ResolvedApprovers::none(UnresolvedReason::NoRoleHolder) : ResolvedApprovers::of($ids);
    }

    private function roleHolders(string $role): ResolvedApprovers
    {
        if ($role === '' || ! SchemaReadiness::hasBatch('B05')) {
            return ResolvedApprovers::none(UnresolvedReason::NoRoleHolder);
        }

        $ids = Personnel::query()
            ->role($role)
            ->where('status', PersonnelStatus::Active->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $ids === [] ? ResolvedApprovers::none(UnresolvedReason::NoRoleHolder) : ResolvedApprovers::of($ids);
    }

    private function requesterOrgUnitId(int $requesterId): ?int
    {
        $orgUnitId = Personnel::query()->whereKey($requesterId)->value('org_unit_id');

        return $orgUnitId === null ? null : (int) $orgUnitId;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function activeIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Personnel::query()
            ->whereIn('id', array_unique($ids))
            ->where('status', PersonnelStatus::Active->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
