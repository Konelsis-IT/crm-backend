<?php

declare(strict_types=1);

namespace App\Query\Project;

use App\Enums\Platform\Feature;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\WorkstreamStatus;
use App\Enums\Report\WorkItemStatus;
use App\Models\Personnel\OrgUnit;
use App\Models\Project\OperationGroupDefinition;
use App\Models\Project\Project;
use App\Models\Report\WorkItem;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Dashboard\Departments;
use Illuminate\Database\Eloquent\Builder;

/**
 * Projelendirme ekrani (D-234; kullanici 22 Ekim 2026: "Projelendirme
 * sekmesinin olayi su olacak onlara verilen aktif devam eden projeler takip
 * edecekleri projeler gibi sekmeler olur ... Detay ekranini simdilik o
 * listelerin yapma"). Liste, Projelendirme is kolu (operasyon grubu PROJECT)
 * olan taahhutlerdir; sekme is kolunun durumuna gore:
 *
 * - Devam eden: is kolu aktif ya da incelemede.
 * - Sirada: is kolu henuz baslamadi (hazir degil / hazir) ya da bloke.
 * - Takip edilecek: Projelendirme isi bitti (tamamlandi / muaf) ama taahhut
 *   suruyor (revizyon, saha sorulari, son durum projesi).
 */
final class DesignEngineeringQueries
{
    public const TAB_ACTIVE = 'active';

    public const TAB_UPCOMING = 'upcoming';

    public const TAB_FOLLOW = 'follow';

    public const TAB_ALL = 'all';

    /** @var array<string, list<WorkstreamStatus>> */
    private const STATUSES = [
        self::TAB_ACTIVE => [WorkstreamStatus::Active, WorkstreamStatus::Review],
        self::TAB_UPCOMING => [WorkstreamStatus::NotReady, WorkstreamStatus::Ready, WorkstreamStatus::Blocked],
        self::TAB_FOLLOW => [WorkstreamStatus::Completed, WorkstreamStatus::Waived],
    ];

    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B17')
            && SchemaReadiness::hasBatch('B17A')
            && FeatureFlags::enabled(Feature::Projects)
            && FeatureFlags::enabled(Feature::ProjectDesign);
    }

    /** Projelendirme is kolu olan taahhutler; is kolu, kod, isveren, mudur ve ekibin acik isleri. */
    public function base(): Builder
    {
        $unitId = OrgUnit::query()->where('code', Departments::PROJECTS)->value('id');
        $open = array_map(static fn (WorkItemStatus $status): string => $status->value, array_filter(WorkItemStatus::cases(), static fn (WorkItemStatus $status): bool => $status->isOpen()));

        return Project::query()
            ->whereHas('designWorkstream')
            ->with(['businessCode', 'customerParty:id,display_name', 'projectManager:id,full_name', 'designWorkstream.owner:id,full_name'])
            ->addSelect([
                'design_open_work' => WorkItem::query()->notArchived()
                    ->selectRaw('count(*)')
                    ->whereColumn('work_items.project_id', 'projects.id')
                    ->where('work_items.org_unit_id', $unitId ?? 0)
                    ->whereIn('work_items.status', $open),
            ]);
    }

    public function forTab(Builder $query, string $tab): Builder
    {
        if ($tab === self::TAB_ALL || ! isset(self::STATUSES[$tab])) {
            return $query;
        }

        $statuses = array_map(static fn (WorkstreamStatus $status): string => $status->value, self::STATUSES[$tab]);

        $query->whereHas('designWorkstream', fn (Builder $stream): Builder => $stream->whereIn('status', $statuses));

        // Takip: is bitmis ama taahhut kapanmamis.
        if ($tab === self::TAB_FOLLOW) {
            $query->whereNotIn('projects.status', [ProjectStatus::Closed->value, ProjectStatus::Cancelled->value]);
        }

        return $query;
    }

    /**
     * Sekme rozetleri.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $out = [];

        foreach ([self::TAB_ACTIVE, self::TAB_UPCOMING, self::TAB_FOLLOW, self::TAB_ALL] as $tab) {
            $out[$tab] = $this->forTab(Project::query()->whereHas('designWorkstream'), $tab)->count();
        }

        return $out;
    }

    /** Projelendirme is kolunun kodu (operasyon grubu). */
    public static function groupCode(): string
    {
        return OperationGroupDefinition::DESIGN_CODE;
    }
}
