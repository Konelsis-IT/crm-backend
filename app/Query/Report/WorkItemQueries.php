<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Report\ReportStatus;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\TenderNotice;
use App\Models\Activity\PersonnelActivity;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Party\MeetingPlan;
use App\Models\Party\Party;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhoto;
use App\Models\Project\ProjectSupplyItem;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Models\Report\WorkSuggestionDismissal;
use App\Models\WorkRequest\WorkRequest;
use App\Reports\Templates\CoordinationBoardReportTemplate;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Reports\Templates\WeeklyWorkReportTemplate;
use App\Reports\Work\WorkSuggestionCatalog;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Is panosu okuma sorgulari (B36, D-115; tek pano D-119): kapsam (kendi
 * kartlarim, ekibim, tum sirket) ile departman ve proje suzgecleri, tarih
 * araligi ve devreden kartlar, gorunurluk, sistemden gelen oneriler,
 * gunu/haftayi kapat kart setleri, bagli kayit aramasi ve personel
 * kartindaki sayilar.
 *
 * Tarih kurali: kart `work_on` gunu panoya duser; tamamlanan kart ayrica
 * tamamlandigi gun (`done_on`) gorunur; acik kart, aralik bugunu kapsiyorsa
 * onceki gunlerden devreder.
 */
final class WorkItemQueries
{
    public const BOARD_LIMIT = 600;

    /** Pano kapsamlari (D-119): kendi kartlarim, ekibim, tum sirket. @var list<string> */
    public const SCOPES = ['mine', 'team', 'all'];

    /** @var list<string> */
    public const RANGES = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'range'];

    public function __construct(
        private readonly ReportQueries $reports,
        private readonly WorkSuggestionCatalog $suggestions,
    ) {}

    /** Kurum gunu (Europe/Istanbul) gece yarisi. */
    public static function today(): Carbon
    {
        return Carbon::now(DisplayTime::zone())->startOfDay();
    }

    /** Kurum saatinde bir gunun basi. */
    public static function day(?string $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('Y-m-d', $value, DisplayTime::zone())->startOfDay();
        }

        return self::today();
    }

    /** Haftanin pazartesisi. */
    public static function weekStart(Carbon $day): Carbon
    {
        return $day->copy()->startOfDay()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Tarih suzgeci -> [baslangic, bitis] (kurum gunu, ikisi de dahil).
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function range(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = self::today();
        $preset = in_array($preset, self::RANGES, true) ? $preset : 'this_week';

        return match ($preset) {
            'today' => [$today->copy(), $today->copy(), $preset],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay(), $preset],
            'last_week' => [self::weekStart($today)->subWeek(), self::weekStart($today)->subWeek()->addDays(6), $preset],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay(), $preset],
            'range' => $this->customRange($from, $to, $today),
            default => [self::weekStart($today), self::weekStart($today)->addDays(6), 'this_week'],
        };
    }

    /**
     * Kartla birlikte yuklenen iliskiler (pano, liste, rapor dondurma).
     *
     * @return list<string>
     */
    public function relations(): array
    {
        return [
            'personnel:id,full_name,org_unit_id', 'orgUnit:id,name,code', 'project:id,name', 'parent:id,title',
            'waitingPersonnel:id,full_name', 'waitingParty:id,display_name',
            ...(SchemaReadiness::hasBatch('B37') ? ['requesterPersonnel:id,full_name', 'requesterParty:id,display_name'] : []),
            ...WorkItemLinkKind::relations(),
        ];
    }

    public function newQuery(): Builder
    {
        return WorkItem::query()->with($this->relations());
    }

    /** Tum kartlari gorebilen (Yonetim panosu yetkisi). */
    public function seesAll(Personnel $viewer): bool
    {
        return Gate::forUser($viewer)->allows('viewAll', WorkItem::class);
    }

    /**
     * Ekip: dogrudan astlar, yonetilen departmanlarin uyeleri ve (tum kartlari
     * gorenler icin) kendi departmani; kendisi dahil.
     *
     * @return list<int>
     */
    public function teamIds(Personnel $viewer, ?int $unitId = null): array
    {
        $me = (int) $viewer->getKey();
        $ids = [$me, ...$this->reports->teamPersonnelIds($me)];

        if ($this->seesAll($viewer)) {
            $unit = $unitId ?? ($viewer->org_unit_id !== null ? (int) $viewer->org_unit_id : null);

            if ($unit !== null) {
                $ids = [...$ids, ...Personnel::query()->where('org_unit_id', $unit)->pluck('id')->map(fn ($id): int => (int) $id)->all()];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Gorunurluk: kendi, olusturdugu, ekibinin karti; yetkili hepsini gorur. */
    public function applyVisible(Builder $query, ?Personnel $viewer): Builder
    {
        if (! $viewer instanceof Personnel) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->seesAll($viewer) || $this->isAuditor($viewer)) {
            return $query;
        }

        $me = (int) $viewer->getKey();
        $ids = [$me, ...$this->reports->teamPersonnelIds($me)];

        return $query->where(function (Builder $inner) use ($ids, $me): void {
            $inner->whereIn('work_items.personnel_id', $ids)
                ->orWhere('work_items.created_by_personnel_id', $me)
                // Benden beklenen is: kart baskasinin olsa da benim isim.
                ->orWhere('work_items.waiting_personnel_id', $me);
        });
    }

    /**
     * Tarih araligi: o gunlerde girilen, o gunlerde tamamlanan ve (aralik
     * bugunu iceriyorsa) onceki gunlerden devreden acik kartlar.
     *
     * Devir yalnizca bugunu iceren aralikta gecerlidir: gelecek bir hafta
     * secildiginde gecmisin acik kartlari o haftaya dusmez, gecmis bir hafta
     * secildiginde de o haftanin kendi kartlari gorunur (23 Eylul 2026
     * kullanici bildirimi).
     */
    public function applyRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        $today = self::today();
        $fromYmd = $from->format('Y-m-d');
        $toYmd = $to->format('Y-m-d');
        $carries = $from->lessThanOrEqualTo($today) && $to->greaterThanOrEqualTo($today);

        return $query->where(function (Builder $inner) use ($fromYmd, $toYmd, $carries): void {
            $inner->whereBetween('work_items.work_on', [$fromYmd, $toYmd])
                ->orWhereBetween('work_items.done_on', [$fromYmd, $toYmd]);

            if ($carries) {
                $inner->orWhere(function (Builder $open) use ($fromYmd): void {
                    $open->whereIn('work_items.status', WorkItemStatus::openValues())
                        ->where('work_items.work_on', '<', $fromYmd);
                });
            }
        });
    }

    /**
     * Pano kartlari.
     *
     * @param  array{projects?: list<int>, units?: list<int>, from: Carbon, to: Carbon}  $params
     * @return Collection<int, WorkItem>
     */
    public function board(Personnel $viewer, string $scope, array $params): Collection
    {
        $query = $this->newQuery()->withCount('children');
        $me = (int) $viewer->getKey();
        $projects = array_values(array_filter(array_map('intval', $params['projects'] ?? [])));
        $units = array_values(array_filter(array_map('intval', $params['units'] ?? [])));
        // Yonettigi projenin butun kartlari kapsamdan bagimsiz gorunur (eski Proje panosu).
        $managed = $projects === [] ? [] : $this->managedProjectIds($viewer, $projects);

        $query->where(function (Builder $outer) use ($scope, $viewer, $me, $managed): void {
            $outer->where(function (Builder $inner) use ($scope, $viewer, $me): void {
                match ($scope) {
                    'team' => $inner->whereIn('work_items.personnel_id', $this->teamIds($viewer)),
                    'all' => $this->applyVisible($inner, $viewer),
                    // Kendi kartlarim + benden beklenen isler.
                    default => $inner->where('work_items.personnel_id', $me)
                        ->orWhere('work_items.waiting_personnel_id', $me),
                };
            });

            if ($managed !== []) {
                $outer->orWhereIn('work_items.project_id', $managed);
            }
        });

        if ($projects !== []) {
            $query->whereIn('work_items.project_id', $projects);
        }

        if ($units !== []) {
            $query->whereIn('work_items.org_unit_id', $units);
        }

        $this->applyRange($query, $params['from'], $params['to']);

        return $query
            ->orderBy('work_items.sort_order')
            ->orderByDesc('work_items.is_critical')
            ->orderByDesc('work_items.work_at')
            ->orderByDesc('work_items.id')
            ->limit(self::BOARD_LIMIT)
            ->get();
    }

    /**
     * Kisinin bir gun araligindaki kartlari (Genel bakis kutusu): o gunlerin
     * kartlari ve onceki gunlerden devreden acik kartlar.
     */
    public function personnelRange(int $personnelId, Carbon $from, Carbon $to): Builder
    {
        return $this->applyRange(
            WorkItem::query()->with(['project:id,name'])->where('work_items.personnel_id', $personnelId),
            $from,
            $to,
        );
    }

    /**
     * Verilen projelerden bu kisinin yonettikleri (D-119): proje yoneticisi
     * kendi projesinin butun kartlarini gorur.
     *
     * @param  list<int>  $projectIds
     * @return list<int>
     */
    public function managedProjectIds(Personnel $viewer, array $projectIds): array
    {
        if ($projectIds === [] || ! SchemaReadiness::hasBatch('B17')) {
            return [];
        }

        if ($this->seesAll($viewer)) {
            return $projectIds;
        }

        return Project::query()
            ->whereIn('id', $projectIds)
            ->where('project_manager_employee_id', (int) $viewer->getKey())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Proje panosu: projenin kartlari. Proje yoneticisi ve tum kartlari goren
     * hepsini, digerleri kendi ve ekibinin kartini gorur.
     */
    public function applyVisibleForProject(Builder $query, Personnel $viewer, int $projectId): Builder
    {
        $query->where('work_items.project_id', $projectId > 0 ? $projectId : 0);

        $manages = $projectId > 0 && Project::query()
            ->whereKey($projectId)
            ->where('project_manager_employee_id', (int) $viewer->getKey())
            ->exists();

        return $manages ? $query : $this->applyVisible($query, $viewer);
    }

    /**
     * Pano secimleri icin projeler (en cok kart alanlar once degil, ada gore).
     *
     * @return array<int, string>
     */
    public function projectNames(): array
    {
        if (! SchemaReadiness::hasBatch('B17')) {
            return [];
        }

        return Project::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * Departmanlar: id => { name, code }.
     *
     * @return array<int, array{name: string, code: string}>
     */
    public function units(): array
    {
        return OrgUnit::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (OrgUnit $unit): array => [(int) $unit->getKey() => ['name' => (string) $unit->name, 'code' => (string) $unit->code]])
            ->all();
    }

    /**
     * Aktif personel: id => { name, unit }.
     *
     * @return array<int, array{name: string, unit: int|null}>
     */
    public function people(): array
    {
        return Personnel::query()
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'org_unit_id'])
            ->mapWithKeys(fn (Personnel $person): array => [(int) $person->getKey() => [
                'name' => (string) $person->full_name,
                'unit' => $person->org_unit_id !== null ? (int) $person->org_unit_id : null,
            ]])
            ->all();
    }

    /** Kisinin ana is olarak secebilecegi kartlar (acik, ust karti olmayan). */
    public function parentOptions(int $personnelId, ?int $exceptId = null): array
    {
        return WorkItem::query()
            ->whereNull('parent_id')
            ->where('personnel_id', $personnelId)
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->orderByDesc('work_at')
            ->limit(200)
            ->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id): array => [(int) $id => (string) $title])
            ->all();
    }

    /** Kartin alt kart kimlikleri (ana is dongusu kontrolu). */
    public function hasChildren(int $itemId): bool
    {
        return WorkItem::query()->where('parent_id', $itemId)->exists();
    }

    public function maxSortOrder(int $personnelId, WorkItemStatus $status): int
    {
        return (int) WorkItem::query()
            ->where('personnel_id', $personnelId)
            ->where('status', $status->value)
            ->max('sort_order');
    }

    /**
     * Sistemden gelen oneriler: kisinin son gunlerdeki kendi hareketleri; karta
     * donmus ya da yoksayilmis olanlar haric. Ayni kayit ve islem icin en
     * yeni hareket.
     *
     * @return Collection<int, PersonnelActivity>
     */
    public function suggestionActivities(Personnel $me, bool $dismissed = false): Collection
    {
        $meId = (int) $me->getKey();

        $activities = PersonnelActivity::query()
            ->where('personnel_id', $meId)
            ->whereIn('action_code', $this->suggestions->actionCodes())
            ->where('occurred_at', '>=', Carbon::now('UTC')->subDays(WorkSuggestionCatalog::WINDOW_DAYS))
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('work_items')
                    ->whereColumn('work_items.source_activity_id', 'personnel_activities.id');
            })
            ->{$dismissed ? 'whereExists' : 'whereNotExists'}(function ($sub) use ($meId): void {
                $sub->select(DB::raw(1))
                    ->from('work_suggestion_dismissals')
                    ->whereColumn('work_suggestion_dismissals.personnel_activity_id', 'personnel_activities.id')
                    ->where('work_suggestion_dismissals.personnel_id', $meId);
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return $activities
            ->unique(fn (PersonnelActivity $activity): string => $activity->action_code.'|'.$activity->subject_type.'|'.$activity->subject_id)
            ->take(20)
            ->values();
    }

    /** Oneri tek tek (Kart yap / Yoksay): kisinin kendi, desteklenen hareketi. */
    public function suggestionActivity(Personnel $me, int $activityId): ?PersonnelActivity
    {
        $activity = PersonnelActivity::query()->whereKey($activityId)->first();

        if (! $activity instanceof PersonnelActivity
            || (int) $activity->personnel_id !== (int) $me->getKey()
            || ! $this->suggestions->supports((string) $activity->action_code)) {
            return null;
        }

        return $activity;
    }

    public function isConverted(int $activityId): bool
    {
        return WorkItem::query()->where('source_activity_id', $activityId)->exists();
    }

    public function dismissal(int $personnelId, int $activityId): ?WorkSuggestionDismissal
    {
        return WorkSuggestionDismissal::query()
            ->where('personnel_id', $personnelId)
            ->where('personnel_activity_id', $activityId)
            ->first();
    }

    /**
     * Onerilerin konu kayitlari, ture gore toplu yuklenir.
     *
     * @param  iterable<PersonnelActivity>  $activities
     * @return array<string, array<int, Model>>  subject_type => id => kayit
     */
    public function suggestionSubjects(iterable $activities): array
    {
        $ids = [];

        foreach ($activities as $activity) {
            $ids[(string) $activity->subject_type][] = (int) $activity->subject_id;
        }

        $loaders = [
            'proposal' => fn (array $keys) => Proposal::query()->with('businessCase.project:id,business_case_id,name')->whereIn('id', $keys)->get(),
            'proposal_version' => fn (array $keys) => ProposalVersion::query()->with('proposal.businessCase.project:id,business_case_id,name')->whereIn('id', $keys)->get(),
            'business_case' => fn (array $keys) => BusinessCase::query()->with('project:id,business_case_id,name')->whereIn('id', $keys)->get(),
            'tender_notice' => fn (array $keys) => TenderNotice::query()->whereIn('id', $keys)->get(),
            'document' => fn (array $keys) => Document::query()->whereIn('id', $keys)->get(),
            'document_revision' => fn (array $keys) => DocumentRevision::query()->with('document')->whereIn('id', $keys)->get(),
            'project_supply_item' => fn (array $keys) => ProjectSupplyItem::query()->with('project:id,name')->whereIn('id', $keys)->get(),
            'work_request' => fn (array $keys) => WorkRequest::query()->whereIn('id', $keys)->get(),
            'meeting_plan' => fn (array $keys) => MeetingPlan::query()->with('party:id,display_name')->whereIn('id', $keys)->get(),
            'party_meeting_note' => fn (array $keys) => PartyMeetingNote::query()->with('party:id,display_name')->whereIn('id', $keys)->get(),
            'report' => fn (array $keys) => Report::query()->whereIn('id', $keys)->get(),
            'contract' => fn (array $keys) => Contract::query()->whereIn('id', $keys)->get(),
            'project' => fn (array $keys) => Project::query()->whereIn('id', $keys)->get(['id', 'name']),
            'project_photo' => fn (array $keys) => ProjectPhoto::query()->with('project:id,name')->whereIn('id', $keys)->get(),
        ];

        $subjects = [];

        foreach ($ids as $type => $keys) {
            $loader = $loaders[$type] ?? null;

            if ($loader === null) {
                continue;
            }

            foreach ($loader(array_values(array_unique($keys))) as $model) {
                $subjects[$type][(int) $model->getKey()] = $model;
            }
        }

        return $subjects;
    }

    /** Gorusme notunun yansidigi gorusme plani (B34). */
    public function meetingPlanForNote(int $noteId): ?MeetingPlan
    {
        if (! SchemaReadiness::hasBatch('B34')) {
            return null;
        }

        return MeetingPlan::query()->where('meeting_note_id', $noteId)->first();
    }

    /**
     * Gunu kapat: o gun girilen, o gun tamamlanan ve o gune devreden acik kartlar.
     *
     * @return Collection<int, WorkItem>
     */
    public function dayCards(int $personnelId, Carbon $day): Collection
    {
        return $this->periodCards($personnelId, $day, $day);
    }

    /**
     * @return Collection<int, WorkItem>
     */
    public function periodCards(int $personnelId, Carbon $from, Carbon $to): Collection
    {
        $fromYmd = $from->format('Y-m-d');
        $toYmd = $to->format('Y-m-d');

        return $this->newQuery()
            ->where('work_items.personnel_id', $personnelId)
            ->where(function (Builder $inner) use ($fromYmd, $toYmd): void {
                $inner->whereBetween('work_items.work_on', [$fromYmd, $toYmd])
                    ->orWhereBetween('work_items.done_on', [$fromYmd, $toYmd])
                    ->orWhere(function (Builder $open) use ($fromYmd): void {
                        $open->whereIn('work_items.status', WorkItemStatus::openValues())
                            ->where('work_items.work_on', '<', $fromYmd);
                    });
            })
            ->orderBy('work_items.sort_order')
            ->orderByDesc('work_items.is_critical')
            ->orderBy('work_items.work_at')
            ->get();
    }

    /**
     * Yonetim panosu dondurmasi: kimlikleri verilen, gorunur kartlar.
     *
     * @param  list<int>  $ids
     * @return Collection<int, WorkItem>
     */
    public function visibleCards(Personnel $viewer, array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return $this->applyVisible($this->newQuery(), $viewer)
            ->whereIn('work_items.id', $ids)
            ->orderBy('work_items.project_id')
            ->orderBy('work_items.sort_order')
            ->get();
    }

    public function dailyReport(int $personnelId, Carbon $day): ?Report
    {
        return $this->reports->periodReport(DailyWorkReportTemplate::CODE, $personnelId, $day->format('Y-m-d'), null, null);
    }

    public function weeklyReport(int $personnelId, Carbon $weekStart): ?Report
    {
        return $this->reports->periodReport(WeeklyWorkReportTemplate::CODE, $personnelId, $weekStart->format('Y-m-d'), null, null);
    }

    public function boardFreezeReport(int $personnelId, Carbon $day): ?Report
    {
        return $this->reports->periodReport(CoordinationBoardReportTemplate::CODE, $personnelId, $day->format('Y-m-d'), null, null);
    }

    /** Gonderilmis (kapatilmis) gunluk rapor sayisi, aralikta. */
    public function closedDayCount(int $personnelId, Carbon $from, Carbon $to): int
    {
        return Report::query()
            ->where('template_code', DailyWorkReportTemplate::CODE)
            ->where('author_personnel_id', $personnelId)
            ->whereIn('status', [ReportStatus::Submitted->value, ReportStatus::Approved->value])
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->count();
    }

    /** Hafta ici is gunu sayisi (pazartesi - cuma), aralikta; bugunden sonrasi sayilmaz. */
    public static function workdays(Carbon $from, Carbon $to, bool $untilToday = true): int
    {
        $end = $untilToday ? $to->copy()->min(self::today()) : $to->copy();
        $count = 0;

        for ($day = $from->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
            if (! $day->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Personel kartindaki Isler sekmesi basligi: bu haftanin sayilari.
     *
     * @return array{done: int, open: int, waiting: int, hours: float, closed_days: int, workdays: int}
     */
    public function personnelWeek(int $personnelId, ?Carbon $day = null): array
    {
        $start = self::weekStart($day ?? self::today());
        $end = $start->copy()->addDays(6);
        $cards = $this->periodCards($personnelId, $start, $end);

        return [
            'done' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Done)->count(),
            'open' => $cards->filter(fn (WorkItem $item): bool => $item->status?->isOpen() ?? false)->count(),
            'waiting' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Waiting)->count(),
            'hours' => round((float) $cards->sum(fn (WorkItem $item): float => (float) ($item->work_hours ?? 0)), 2),
            'closed_days' => $this->closedDayCount($personnelId, $start, $start->copy()->addDays(4)),
            'workdays' => self::workdays($start, $start->copy()->addDays(4)),
        ];
    }

    /** Kisinin acik kart sayisi (sekme rozeti). */
    public function openCount(int $personnelId): int
    {
        return WorkItem::query()
            ->where('personnel_id', $personnelId)
            ->whereIn('status', WorkItemStatus::openValues())
            ->count();
    }

    /**
     * Bagli kayit aramasi (kart penceresi).
     *
     * @return list<array{id: int, no: string|null, label: string, project_id: int|null}>
     */
    public function searchLinks(WorkItemLinkKind $kind, string $term, ?int $onlyId = null): array
    {
        $term = trim($term);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $rows = match ($kind) {
            WorkItemLinkKind::BusinessCase => BusinessCase::query()
                ->with('project:id,business_case_id')
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where('title', 'like', $like))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (BusinessCase $case): array => ['id' => (int) $case->getKey(), 'no' => null, 'label' => (string) $case->title, 'project_id' => $case->project?->getKey()]),
            WorkItemLinkKind::Proposal => Proposal::query()
                ->with('businessCase.project:id,business_case_id')
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('proposal_no', 'like', $like)))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (Proposal $proposal): array => ['id' => (int) $proposal->getKey(), 'no' => $proposal->proposal_no, 'label' => (string) $proposal->title, 'project_id' => $proposal->businessCase?->project?->getKey()]),
            WorkItemLinkKind::TenderNotice => TenderNotice::query()
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('external_notice_id', 'like', $like)))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (TenderNotice $notice): array => ['id' => (int) $notice->getKey(), 'no' => $notice->external_notice_id, 'label' => (string) $notice->title, 'project_id' => null]),
            WorkItemLinkKind::Document => Document::query()
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('document_no', 'like', $like)))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (Document $document): array => ['id' => (int) $document->getKey(), 'no' => $document->document_no, 'label' => (string) $document->title, 'project_id' => $document->project_id !== null ? (int) $document->project_id : null]),
            WorkItemLinkKind::SupplyItem => ProjectSupplyItem::query()
                ->with('project:id,name')
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('item_code', 'like', $like)))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (ProjectSupplyItem $item): array => ['id' => (int) $item->getKey(), 'no' => $item->item_code, 'label' => trim((string) $item->name.($item->project !== null ? ' · '.$item->project->name : '')), 'project_id' => $item->project_id !== null ? (int) $item->project_id : null]),
            WorkItemLinkKind::WorkRequest => WorkRequest::query()
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('request_no', 'like', $like)))
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (WorkRequest $request): array => ['id' => (int) $request->getKey(), 'no' => $request->request_no, 'label' => (string) $request->title, 'project_id' => $request->project_id !== null ? (int) $request->project_id : null]),
            WorkItemLinkKind::MeetingPlan => MeetingPlan::query()
                ->with('party:id,display_name')
                ->when($onlyId !== null, fn (Builder $q) => $q->whereKey($onlyId), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('subject', 'like', $like)->orWhereHas('party', fn (Builder $p) => $p->where('display_name', 'like', $like))))
                ->orderByDesc('planned_on')->limit(20)->get()
                ->map(fn (MeetingPlan $plan): array => ['id' => (int) $plan->getKey(), 'no' => $plan->planned_on?->format('d.m.Y'), 'label' => trim((string) ($plan->party?->display_name ?? '').($plan->subject ? ' · '.$plan->subject : ''), ' ·'), 'project_id' => null]),
            WorkItemLinkKind::None => collect(),
        };

        return $rows->values()->all();
    }

    /** Bagli kayit var mi (kind + id)? */
    public function linkExists(WorkItemLinkKind $kind, int $id): bool
    {
        return $kind !== WorkItemLinkKind::None && $this->searchLinks($kind, '', $id) !== [];
    }

    /**
     * Taraf aramasi (kimden bekleniyor).
     *
     * @return list<array{id: int, label: string}>
     */
    public function searchParties(string $term): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return Party::query()
            ->where('display_name', 'like', $like)
            ->orderBy('display_name')
            ->limit(20)
            ->get(['id', 'display_name'])
            ->map(fn (Party $party): array => ['id' => (int) $party->getKey(), 'label' => (string) $party->display_name])
            ->values()
            ->all();
    }

    /** Isler listesi aramasi: is adi, proje adi, bagli kayit numarasi. */
    public function applySearch(Builder $query, string $search): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('work_items.title', 'like', $like)
                ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', $like))
                ->orWhereHas('linkedProposal', fn (Builder $link) => $link->where('proposal_no', 'like', $like))
                ->orWhereHas('linkedDocument', fn (Builder $link) => $link->where('document_no', 'like', $like))
                ->orWhereHas('linkedWorkRequest', fn (Builder $link) => $link->where('request_no', 'like', $like))
                ->orWhereHas('linkedSupplyItem', fn (Builder $link) => $link->where('item_code', 'like', $like))
                ->orWhereHas('linkedTenderNotice', fn (Builder $link) => $link->where('external_notice_id', 'like', $like));
        });
    }

    /** Tarih suzgeci (Isler listesi, personel sekmesi): hazir aralik ya da secilen gunler. */
    public function applyDateFilter(Builder $query, ?string $preset, ?string $from, ?string $to): Builder
    {
        if (! filled($preset)) {
            return $query;
        }

        [$start, $end] = $this->range($preset, $from, $to);

        return $this->applyRange($query, $start, $end);
    }

    /** Yalniz kritik kartlar. */
    public function applyCritical(Builder $query): Builder
    {
        return $query->where('work_items.is_critical', true);
    }

    /** Yedi gunden uzun bekleyen kartlar. */
    public function applyLongWaiting(Builder $query): Builder
    {
        return $query
            ->where('work_items.status', WorkItemStatus::Waiting->value)
            ->where('work_items.waiting_since', '<', Carbon::now('UTC')->subDays(WorkItem::LONG_WAIT_DAYS + 1));
    }

    /** Durum suzgeci; `open` = Tamamlandi haric. */
    public function applyStatuses(Builder $query, array $values): Builder
    {
        $values = array_values(array_filter($values, fn ($value): bool => is_string($value) && $value !== ''));

        if ($values === []) {
            return $query;
        }

        if (in_array('open', $values, true)) {
            $values = array_values(array_unique([...array_diff($values, ['open']), ...WorkItemStatus::openValues()]));
        }

        return $query->whereIn('work_items.status', $values);
    }

    /** Kisinin kartlari (personel kartindaki Isler sekmesi). */
    public function applyPersonnel(Builder $query, int $personnelId): Builder
    {
        return $query->where('work_items.personnel_id', $personnelId);
    }

    /**
     * Kartin hareket gecmisi (Personel Hareketleri, en yeni ustte).
     *
     * @return Collection<int, PersonnelActivity>
     */
    public function activitiesFor(int $itemId): Collection
    {
        return PersonnelActivity::query()
            ->with('personnel:id,full_name')
            ->where('subject_type', 'work_item')
            ->where('subject_id', (string) $itemId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Bagli kayit secim listesi (Filament formu): id => "NO · ad".
     *
     * @return array<int, string>
     */
    public function linkOptions(WorkItemLinkKind $kind, string $search = '', ?int $onlyId = null): array
    {
        $options = [];

        foreach ($this->searchLinks($kind, $search, $onlyId) as $row) {
            $options[$row['id']] = trim(($row['no'] !== null ? $row['no'].' · ' : '').$row['label']);
        }

        return $options;
    }

    /**
     * Taraf secim listesi (Filament formu).
     *
     * @return array<int, string>
     */
    public function partyOptions(string $search = '', ?int $onlyId = null): array
    {
        if ($onlyId !== null) {
            $name = Party::query()->whereKey($onlyId)->value('display_name');

            return $name !== null ? [$onlyId => (string) $name] : [];
        }

        $options = [];

        foreach ($this->searchParties($search) as $row) {
            $options[$row['id']] = $row['label'];
        }

        return $options;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function customRange(?string $from, ?string $to, Carbon $today): array
    {
        $start = self::day($from ?? $today->format('Y-m-d'));
        $end = self::day($to ?? $start->format('Y-m-d'));

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        // Asiri genis aralik panoyu bogmasin: en fazla bir yil.
        if ($start->diffInDays($end) > 366) {
            $start = $end->copy()->subDays(366);
        }

        return [$start, $end, 'range'];
    }

    private function isAuditor(Personnel $viewer): bool
    {
        return $this->reports->isAuditor($viewer);
    }
}
