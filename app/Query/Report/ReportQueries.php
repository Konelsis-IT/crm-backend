<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Personnel\ReportingRelationType;
use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportStatus;
use App\Enums\Report\ReportSubjectKind;
use App\Models\Activity\PersonnelActivity;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\ReportingRelationship;
use App\Models\Report\Report;
use App\Models\Report\ReportItem;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Reports\ReportTemplate;
use App\Reports\Templates\DailyControlReportTemplate;
use App\Services\Authorization\PermissionKey;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rapor okuma sorgulari (D-86): sekmeler (benim / inceleme kutum / ekibim /
 * tumu), yonetici-ast kontrolleri, donem tekrar kontrolu, pano tasima,
 * konu secim listeleri ve hareket gecmisi.
 */
final class ReportQueries
{
    public function __construct(
        private readonly RoleResolver $roles,
        private readonly PersonnelQueries $personnel,
        private readonly ProjectCatalogQueries $projects,
        private readonly WorkRequestQueries $acquisition,
    ) {}

    /**
     * Dogrudan astlar (reporting_relationships `line`, gecerli).
     *
     * @return list<int>
     */
    public function subordinateIds(int $managerId): array
    {
        if (! SchemaReadiness::hasBatch('B03')) {
            return [];
        }

        return ReportingRelationship::query()
            ->where('manager_personnel_id', $managerId)
            ->where('relation_type', ReportingRelationType::Line->value)
            ->whereNull('valid_until')
            ->pluck('personnel_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Kisinin yonettigi departmanlar.
     *
     * @return list<int>
     */
    public function managedUnitIds(int $personnelId): array
    {
        if (! SchemaReadiness::hasBatch('B03')) {
            return [];
        }

        return OrgUnit::query()
            ->where('manager_personnel_id', $personnelId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Ekibim: dogrudan astlar + yonetilen departmanlarin uyeleri (kendisi haric).
     *
     * @return list<int>
     */
    public function teamPersonnelIds(int $personnelId): array
    {
        $ids = $this->subordinateIds($personnelId);
        $units = $this->managedUnitIds($personnelId);

        if ($units !== []) {
            $members = Personnel::query()
                ->whereIn('org_unit_id', $units)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $ids = [...$ids, ...$members];
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id !== $personnelId)));
    }

    /** Kisi bu personelin dogrudan amiri ya da departman yoneticisi mi? */
    public function managesPersonnel(int $managerId, int $personnelId): bool
    {
        if ($managerId === $personnelId || $personnelId <= 0) {
            return false;
        }

        return in_array($personnelId, $this->teamPersonnelIds($managerId), true);
    }

    public function hasFullAccess(Personnel $personnel): bool
    {
        return $personnel->isActive() && $this->roles->hasFullAccess($personnel);
    }

    public function isAuditor(Personnel $personnel): bool
    {
        return $personnel->isActive() && $this->roles->isAuditor($personnel);
    }

    /** Shield izni (`Ability:Report`) bu personelin rollerinden birinde var mi? */
    public function permits(Personnel $personnel, string $ability): bool
    {
        if (! $personnel->isActive()) {
            return false;
        }

        return $personnel->can(PermissionKey::for(\App\Policies\ReportPolicy::class, $ability));
    }

    /**
     * Kisi bu taslagi yazabilir mi (yazar kurali; konu secimi servis katmaninda
     * ayrica dogrulanir)? Sistem yoneticisi her taslagi yazar.
     */
    public function canAuthorTemplate(ReportTemplate $template, Personnel $personnel): bool
    {
        if (! $personnel->isActive() || ! $template->isManualEntry()) {
            return false;
        }

        if ($this->hasFullAccess($personnel)) {
            return true;
        }

        $id = (int) $personnel->getKey();

        return match ($template->authorRule()) {
            ReportAuthorRule::Anyone => true,
            ReportAuthorRule::SubjectManager => $this->teamPersonnelIds($id) !== [],
            ReportAuthorRule::Hr => $this->permits($personnel, 'authorHrEvaluation'),
            ReportAuthorRule::UnitManager => $this->managedUnitIds($id) !== [],
        };
    }

    public function applyMine(Builder $query, int $personnelId): Builder
    {
        return $query->where('author_personnel_id', $personnelId);
    }

    /** Inceleme kutum: bana atanmis, gonderilmis raporlar. */
    public function applyReviewInbox(Builder $query, int $personnelId): Builder
    {
        return $query
            ->where('status', ReportStatus::Submitted->value)
            ->where('reviewer_personnel_id', $personnelId);
    }

    /** Ekibim: astlarimin ve yonettigim birimlerin uyelerinin (gizli olmayan) raporlari. */
    public function applyTeam(Builder $query, int $personnelId): Builder
    {
        $team = $this->teamPersonnelIds($personnelId);

        if ($team === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('author_personnel_id', $team)
            ->where(function (Builder $inner) use ($personnelId): void {
                $inner->where('is_confidential', false)
                    ->orWhere('reviewer_personnel_id', $personnelId);
            });
    }

    /**
     * Gorebildigim raporlar: yetkili herkes icin tumu (gizliler ayri izinle),
     * digerleri icin yazdigim, inceledigim, ekibimin ve hakkimda yazilan
     * gizli olmayan raporlar ile ekibim hakkindaki gizli raporlar.
     */
    public function applyVisible(Builder $query, ?Personnel $viewer): Builder
    {
        if (! $viewer instanceof Personnel) {
            return $query->whereRaw('1 = 0');
        }

        $id = (int) $viewer->getKey();

        if ($this->hasFullAccess($viewer) || $this->isAuditor($viewer) || $this->permits($viewer, 'view')) {
            if ($this->hasFullAccess($viewer) || $this->permits($viewer, 'viewConfidential')) {
                return $query;
            }

            return $query->where(function (Builder $inner) use ($id): void {
                $inner->where('is_confidential', false)
                    ->orWhere('author_personnel_id', $id)
                    ->orWhere('reviewer_personnel_id', $id);
            });
        }

        $team = $this->teamPersonnelIds($id);

        return $query->where(function (Builder $inner) use ($id, $team): void {
            $inner->where('author_personnel_id', $id)
                ->orWhere('reviewer_personnel_id', $id)
                ->orWhere(function (Builder $about) use ($id): void {
                    $about->where('subject_personnel_id', $id)->where('is_confidential', false);
                });

            if ($team !== []) {
                $inner->orWhere(function (Builder $mine) use ($team): void {
                    $mine->whereIn('author_personnel_id', $team)->where('is_confidential', false);
                })->orWhereIn('subject_personnel_id', $team);
            }
        });
    }

    public function reviewInboxCount(int $personnelId): int
    {
        if (! SchemaReadiness::hasBatch('B10A')) {
            return 0;
        }

        return $this->applyReviewInbox(Report::query(), $personnelId)->count();
    }

    /** Ayni yazar + taslak + donem baslangici (+ konu) icin baska rapor var mi? */
    public function periodReport(string $templateCode, int $authorId, string $periodStart, ?string $subjectColumn, ?int $subjectId, ?int $exceptId = null): ?Report
    {
        $query = Report::query()
            ->where('template_code', $templateCode)
            ->where('author_personnel_id', $authorId)
            ->whereDate('period_start', $periodStart)
            ->where('status', '!=', ReportStatus::Rejected->value);

        if ($subjectColumn !== null) {
            $query->where($subjectColumn, $subjectId);
        }

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Kontrol matrisinin bir hucresi (B36, D-115; gunluk: D-116): kisi +
     * bolum + gun icin yazilmis kontrol raporu.
     */
    public function controlReport(string $section, int $subjectPersonnelId, string $day): ?Report
    {
        return Report::query()
            ->where('template_code', DailyControlReportTemplate::CODE)
            ->where('subject_personnel_id', $subjectPersonnelId)
            ->whereDate('period_start', $day)
            ->where('payload->section', $section)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Rapor iletilebilecek kisiler (23 Eylul 2026 kullanici istegi): yazarin
     * amirleri, departman yoneticileri ve ust yonetim. Yazarin kendisi ve su
     * anki inceleyen listede yer almaz.
     *
     * @return array<int, string>
     */
    public function forwardOptions(Report $report): array
    {
        $exclude = array_filter([
            $report->author_personnel_id !== null ? (int) $report->author_personnel_id : null,
            $report->reviewer_personnel_id !== null ? (int) $report->reviewer_personnel_id : null,
        ]);

        $managerIds = ReportingRelationship::query()
            ->whereNull('valid_until')
            ->pluck('manager_personnel_id');
        $unitManagerIds = OrgUnit::query()->whereNotNull('manager_personnel_id')->pluck('manager_personnel_id');

        return Personnel::query()
            ->where('status', 'active')
            ->whereKeyNot($exclude)
            ->where(function (Builder $query) use ($managerIds, $unitManagerIds): void {
                $query->whereIn('id', $managerIds)
                    ->orWhereIn('id', $unitManagerIds);
            })
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /** Yazarin bu taslakla yazdigi son rapor (en yeni donem). */
    public function latestReport(string $templateCode, int $authorId, ?int $exceptId = null): ?Report
    {
        $query = Report::query()
            ->with('items')
            ->where('template_code', $templateCode)
            ->where('author_personnel_id', $authorId)
            ->where('status', '!=', ReportStatus::Rejected->value);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->orderByDesc('period_start')->orderByDesc('id')->first();
    }

    /**
     * Bir onceki raporun tamamlanmamis kalemleri; yeni raporun panosuna
     * "tasinan is" olarak on doldurulur (Repeater satir bicimi).
     *
     * @return list<array<string, mixed>>
     */
    public function carryOverItems(string $templateCode, int $authorId): array
    {
        $previous = $this->latestReport($templateCode, $authorId);

        if ($previous === null) {
            return [];
        }

        return $previous->items
            ->filter(fn (ReportItem $item): bool => $item->status->carriesOver())
            ->values()
            ->map(fn (ReportItem $item): array => [
                'id' => null,
                'title' => $item->title,
                'description' => $item->description,
                'status' => $item->status === ReportItemStatus::Blocked ? ReportItemStatus::Blocked->value : ReportItemStatus::Planned->value,
                'project_id' => $item->project_id,
                'work_hours' => null,
                'due_on' => $item->due_on?->format('Y-m-d'),
                'carried_from_item_id' => (int) $item->getKey(),
            ])
            ->all();
    }

    /** Bugun icin gunluk rapor var mi? (Rapor listesi "bugunun raporu" kisayolu.) */
    public function hasReportForDay(string $templateCode, int $authorId, Carbon $day): bool
    {
        if (! SchemaReadiness::hasBatch('B10A')) {
            return true;
        }

        return $this->periodReport($templateCode, $authorId, $day->format('Y-m-d'), null, null) !== null;
    }

    /** Konu kaydi (etiket uretimi icin). */
    public function subject(ReportSubjectKind $kind, ?int $id): ?Model
    {
        $class = $kind->modelClass();

        if ($class === null || $id === null || $id <= 0) {
            return null;
        }

        return $class::query()->find($id);
    }

    /**
     * Konu secim listesi. Personel konusunda yazar kurali "konu personelin
     * yoneticisi" ise yalniz ekibi listelenir.
     *
     * @return array<int, string>
     */
    public function subjectOptions(ReportSubjectKind $kind, Personnel $viewer, bool $onlyTeam = false): array
    {
        return match ($kind) {
            ReportSubjectKind::Personnel => $this->personnelSubjectOptions($viewer, $onlyTeam),
            ReportSubjectKind::Project => SchemaReadiness::hasBatch('B17') ? $this->projects->projectOptions() : [],
            ReportSubjectKind::Component => SchemaReadiness::hasBatch('B17') ? $this->projects->componentDefinitionOptions() : [],
            ReportSubjectKind::Proposal => $this->acquisition->proposalOptions(),
            ReportSubjectKind::BusinessCase => $this->acquisition->businessCaseOptions(),
            ReportSubjectKind::None => [],
        };
    }

    /**
     * Raporun hareket gecmisi (Personel Hareketleri, en yeni ustte).
     *
     * @return Collection<int, PersonnelActivity>
     */
    public function activitiesFor(int $reportId): Collection
    {
        return PersonnelActivity::query()
            ->with('personnel')
            ->where('subject_type', 'report')
            ->where('subject_id', (string) $reportId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function personnelSubjectOptions(Personnel $viewer, bool $onlyTeam): array
    {
        if (! $onlyTeam || $this->hasFullAccess($viewer)) {
            $options = $this->personnel->personnelOptions();
            unset($options[(int) $viewer->getKey()]);

            return $options;
        }

        $team = $this->teamPersonnelIds((int) $viewer->getKey());

        if ($team === []) {
            return [];
        }

        return Personnel::query()
            ->whereIn('id', $team)
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }
}
