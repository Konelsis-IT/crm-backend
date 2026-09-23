<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Report\ReportStatus;
use App\Enums\Report\WorkItemStatus;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Is raporlari (B36, D-115): analiz panosu gostergeleri ve sure raporu.
 * Sureler durum gecmisinin izdusumunden (`*_seconds` + acik kartin surmekte
 * olan dilimi) hesaplanir, elle girilmez.
 *
 * Sure raporunda "ana is" gruplamasi: alt kart ana isinin grubunda, alt
 * karti olan kart kendi grubunda (ilk satir), ikisi de olmayan kart
 * "ana ise bagli olmayan isler" grubundadir (`root_key`).
 */
final class WorkAnalysisQueries
{
    public function __construct(
        private readonly WorkItemQueries $work,
    ) {}

    /**
     * Analiz panosu.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Personnel $viewer, string $month, ?int $unitId, ?int $projectId, bool $compare): array
    {
        [$from, $to] = $this->monthRange($month);
        $current = $this->periodStats($viewer, $from, $to, $unitId, $projectId);
        $previous = $compare ? $this->periodStats($viewer, $from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subDay(), $unitId, $projectId) : null;
        $cards = $current['cards'];

        return [
            'month' => $from->format('Y-m'),
            'label' => $from->copy()->locale(app()->getLocale())->translatedFormat('F Y'),
            'stats' => [
                'hours' => $current['hours'],
                'avg_days' => $current['avg_days'],
                'waiting_share' => $current['waiting_share'],
                'due_compliance' => $current['due_compliance'],
                'day_close' => $current['day_close'],
                'open_critical' => $current['open_critical'],
            ],
            'previous' => $previous === null ? null : [
                'hours' => $previous['hours'],
                'avg_days' => $previous['avg_days'],
                'waiting_share' => $previous['waiting_share'],
                'due_compliance' => $previous['due_compliance'],
                'day_close' => $previous['day_close'],
                'open_critical' => $previous['open_critical'],
            ],
            'units' => $this->hoursByUnit($cards),
            'distribution' => $current['distribution'],
            'weekly_done' => $this->weeklyDone($viewer, $unitId, $projectId),
            'waiting_parties' => $this->waitingParties($cards),
            'oldest' => $this->oldestOpen($viewer, $unitId, $projectId),
        ];
    }

    /**
     * Sure raporu sorgusu: gorunur kartlar + ana is grup anahtari.
     */
    public function durationQuery(?Personnel $viewer): Builder
    {
        $query = WorkItem::query()
            ->select('work_items.*')
            ->selectRaw('COALESCE(work_items.parent_id, CASE WHEN EXISTS (SELECT 1 FROM work_items AS c WHERE c.parent_id = work_items.id) THEN work_items.id END) AS root_key')
            ->withCount('children')
            ->with(['project:id,name', 'personnel:id,full_name', 'orgUnit:id,name', 'parent:id,title,due_on']);

        return $this->work->applyVisible($query, $viewer);
    }

    /** Ana is gruplamasinin sirasi: gruplar bitisik, bagimsiz kartlar sonda, ana is kendi grubunun basinda. */
    public function orderByRoot(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw('root_key IS NULL')
            ->orderBy('root_key', $direction)
            ->orderByRaw('work_items.parent_id IS NOT NULL')
            ->orderBy('work_items.work_at');
    }

    /** Grup ozeti icin sorguyu tek ana ise indirger. */
    public function scopeToRoot(Builder $query, mixed $rootKey): Builder
    {
        if ($rootKey === null || $rootKey === '') {
            return $query->whereNull('work_items.parent_id')
                ->whereNotExists(function ($sub): void {
                    $sub->select(DB::raw(1))->from('work_items as c')->whereColumn('c.parent_id', 'work_items.id');
                });
        }

        $id = (int) $rootKey;

        return $query->where(fn (Builder $inner) => $inner->where('work_items.id', $id)->orWhere('work_items.parent_id', $id));
    }

    /** Suzgec: acilis tarihi araligi. */
    public function applyOpenedBetween(Builder $query, ?string $from, ?string $until): Builder
    {
        return $query
            ->when(filled($from), fn (Builder $q) => $q->whereDate('work_items.work_on', '>=', (string) $from))
            ->when(filled($until), fn (Builder $q) => $q->whereDate('work_items.work_on', '<=', (string) $until));
    }

    /** Suzgec: yalniz kapananlar. */
    public function applyClosedOnly(Builder $query): Builder
    {
        return $query->where('work_items.status', WorkItemStatus::Done->value);
    }

    /** Suzgec: ana is (grubu). */
    public function applyRoot(Builder $query, ?int $rootId): Builder
    {
        return $rootId === null ? $query : $query->where(fn (Builder $inner) => $inner->where('work_items.id', $rootId)->orWhere('work_items.parent_id', $rootId));
    }

    /**
     * Ana is secim listesi: alt karti olan kartlar.
     *
     * @return array<int, string>
     */
    public function rootOptions(?Personnel $viewer): array
    {
        return $this->work->applyVisible(WorkItem::query(), $viewer)
            ->whereExists(function ($sub): void {
                $sub->select(DB::raw(1))->from('work_items as c')->whereColumn('c.parent_id', 'work_items.id');
            })
            ->orderByDesc('work_at')
            ->limit(300)
            ->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id): array => [(int) $id => (string) $title])
            ->all();
    }

    /**
     * Kart kumesinin sure ozeti (grup ozet satiri ve sayfa basi gostergeleri).
     *
     * @param  Collection<int, WorkItem>  $items
     * @return array{count: int, opened: Carbon|null, closed: Carbon|null, open: bool, total_days: int, progress: int, waiting: int, blocked: int, hours: float, deviation: int|null}
     */
    public function summarize(Collection $items): array
    {
        $now = Carbon::now('UTC');
        $opened = $items->min(fn (WorkItem $item): ?Carbon => $item->work_at);
        $open = $items->contains(fn (WorkItem $item): bool => $item->status?->isOpen() ?? false);
        $closed = $open ? null : $items->max(fn (WorkItem $item): ?Carbon => $item->completed_at);
        $zone = DisplayTime::zone();
        $totalDays = 0;

        if ($opened instanceof Carbon) {
            $end = ($closed ?? $now)->copy()->timezone($zone)->startOfDay();
            $totalDays = (int) max(1, $opened->copy()->timezone($zone)->startOfDay()->diffInDays($end, false));
        }

        $root = $items->first(fn (WorkItem $item): bool => $item->parent_id === null && (int) ($item->getAttribute('children_count') ?? 0) > 0);
        $due = $root?->due_on;
        $deviation = null;

        if ($due !== null) {
            $reference = $closed?->copy()->timezone($zone) ?? $now->copy()->timezone($zone);
            $deviation = (int) Carbon::parse($due->format('Y-m-d'), $zone)->startOfDay()->diffInDays(Carbon::parse($reference->format('Y-m-d'), $zone)->startOfDay(), false);
        } else {
            $deviations = $items->map(fn (WorkItem $item): ?int => $item->dueDeviation($now))->filter(fn (?int $value): bool => $value !== null);
            $deviation = $deviations->isEmpty() ? null : (int) $deviations->max();
        }

        return [
            'count' => $items->count(),
            'opened' => $opened,
            'closed' => $closed,
            'open' => $open,
            'total_days' => $totalDays,
            'progress' => (int) $items->sum(fn (WorkItem $item): int => $item->secondsIn('progress', $now)),
            'waiting' => (int) $items->sum(fn (WorkItem $item): int => $item->secondsIn('waiting', $now)),
            'blocked' => (int) $items->sum(fn (WorkItem $item): int => $item->secondsIn('blocked', $now)),
            'hours' => round((float) $items->sum(fn (WorkItem $item): float => (float) ($item->work_hours ?? 0)), 2),
            'deviation' => $deviation,
        ];
    }

    /**
     * Tablo ozet satiri: Filament'in grup kapsamli alt sorgusundaki kartlarin ozeti.
     *
     * @return array{count: int, opened: Carbon|null, closed: Carbon|null, open: bool, total_days: int, progress: int, waiting: int, blocked: int, hours: float, deviation: int|null, due: Carbon|null}
     */
    public function summarizeQuery(QueryBuilder $query): array
    {
        $ids = $query->limit(3000)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $items = $ids === []
            ? new Collection
            : WorkItem::query()->withCount('children')->whereIn('id', $ids)->get();
        $summary = $this->summarize($items);
        $root = $items->first(fn (WorkItem $item): bool => $item->parent_id === null && (int) ($item->getAttribute('children_count') ?? 0) > 0);
        $summary['due'] = $root?->due_on ?? $items->filter(fn (WorkItem $item): bool => $item->due_on !== null)->max('due_on');

        return $summary;
    }

    /**
     * Sayfa basi gostergeleri (suzulmus sorgudan).
     *
     * @return array{avg_total: float|null, avg_progress: float|null, avg_waiting: float|null, due_compliance: int|null, avg_root: float|null, roots: int, cards: int, total_days: int, hours: float}
     */
    public function durationKpis(Builder $query): array
    {
        /** @var Collection<int, WorkItem> $items */
        $items = (clone $query)->limit(2000)->get();
        $now = Carbon::now('UTC');

        if ($items->isEmpty()) {
            return ['avg_total' => null, 'avg_progress' => null, 'avg_waiting' => null, 'due_compliance' => null, 'avg_root' => null, 'roots' => 0, 'cards' => 0, 'total_days' => 0, 'hours' => 0.0];
        }

        $withDue = $items->filter(fn (WorkItem $item): bool => $item->due_on !== null && $item->status === WorkItemStatus::Done);
        $onTime = $withDue->filter(fn (WorkItem $item): bool => ($item->dueDeviation($now) ?? 1) <= 0)->count();
        $groups = $items->filter(fn (WorkItem $item): bool => $item->getAttribute('root_key') !== null)->groupBy(fn (WorkItem $item): string => (string) $item->getAttribute('root_key'));
        $rootDays = $groups->map(fn (Collection $group): int => $this->summarize(new Collection($group->all()))['total_days']);

        return [
            'avg_total' => round((float) $items->avg(fn (WorkItem $item): int => $item->totalDays($now)), 1),
            'avg_progress' => round((float) $items->avg(fn (WorkItem $item): int => $item->secondsIn('progress', $now)) / 86400, 1),
            'avg_waiting' => round((float) $items->avg(fn (WorkItem $item): int => $item->secondsIn('waiting', $now)) / 86400, 1),
            'due_compliance' => $withDue->isEmpty() ? null : (int) round($onTime * 100 / $withDue->count()),
            'avg_root' => $rootDays->isEmpty() ? null : round((float) $rootDays->avg(), 1),
            'roots' => $groups->count(),
            'cards' => $items->count(),
            'total_days' => (int) $items->sum(fn (WorkItem $item): int => $item->totalDays($now)),
            'hours' => round((float) $items->sum(fn (WorkItem $item): float => (float) ($item->work_hours ?? 0)), 2),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function monthRange(?string $month): array
    {
        $zone = DisplayTime::zone();

        if (! is_string($month) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            $start = WorkItemQueries::today()->startOfMonth();
        } else {
            $start = Carbon::createFromFormat('Y-m-d', $month.'-01', $zone)->startOfDay();
        }

        return [$start, $start->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodStats(Personnel $viewer, Carbon $from, Carbon $to, ?int $unitId, ?int $projectId): array
    {
        $cards = $this->scoped($viewer, $unitId, $projectId)
            ->where(function (Builder $inner) use ($from, $to): void {
                $inner->whereBetween('work_items.work_on', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                    ->orWhereBetween('work_items.done_on', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
            })
            ->limit(5000)
            ->get();

        $now = $to->copy()->endOfDay()->min(Carbon::now(DisplayTime::zone()))->timezone('UTC');
        $fromYmd = $from->format('Y-m-d');
        $toYmd = $to->format('Y-m-d');
        $inPeriod = static fn (?Carbon $day): bool => $day !== null && $day->format('Y-m-d') >= $fromYmd && $day->format('Y-m-d') <= $toYmd;
        $done = $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Done && $inPeriod($item->done_on));
        $progress = (int) $cards->sum(fn (WorkItem $item): int => $item->secondsIn('progress', $now));
        $waiting = (int) $cards->sum(fn (WorkItem $item): int => $item->secondsIn('waiting', $now));
        $blocked = (int) $cards->sum(fn (WorkItem $item): int => $item->secondsIn('blocked', $now));
        $sum = $progress + $waiting + $blocked;
        $withDue = $done->filter(fn (WorkItem $item): bool => $item->due_on !== null);
        $onTime = $withDue->filter(fn (WorkItem $item): bool => $item->done_on->lessThanOrEqualTo($item->due_on))->count();
        $people = $cards->pluck('personnel_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();
        $workdays = WorkItemQueries::workdays($from, $to);
        $closedDays = $people === [] ? 0 : Report::query()
            ->where('template_code', DailyWorkReportTemplate::CODE)
            ->whereIn('author_personnel_id', $people)
            ->whereIn('status', [ReportStatus::Submitted->value, ReportStatus::Approved->value])
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->count();
        $expected = count($people) * $workdays;

        return [
            'cards' => $cards,
            'hours' => round((float) $cards->filter(fn (WorkItem $item): bool => $inPeriod($item->work_on))->sum(fn (WorkItem $item): float => (float) ($item->work_hours ?? 0)), 1),
            'avg_days' => $done->isEmpty() ? null : round((float) $done->avg(fn (WorkItem $item): int => $item->totalDays()), 1),
            'waiting_share' => $sum > 0 ? (int) round($waiting * 100 / $sum) : null,
            'due_compliance' => $withDue->isEmpty() ? null : (int) round($onTime * 100 / $withDue->count()),
            'day_close' => $expected > 0 ? (int) min(100, round($closedDays * 100 / $expected)) : null,
            'open_critical' => $this->scoped($viewer, $unitId, $projectId)
                ->where('work_items.is_critical', true)
                ->whereIn('work_items.status', WorkItemStatus::openValues())
                ->count(),
            'distribution' => [
                'progress' => $sum > 0 ? (int) round($progress * 100 / $sum) : 0,
                'waiting' => $sum > 0 ? (int) round($waiting * 100 / $sum) : 0,
                'blocked' => $sum > 0 ? max(0, 100 - (int) round($progress * 100 / $sum) - (int) round($waiting * 100 / $sum)) : 0,
            ],
        ];
    }

    private function scoped(Personnel $viewer, ?int $unitId, ?int $projectId): Builder
    {
        return $this->work->applyVisible(WorkItem::query()->with(['orgUnit:id,name', 'waitingPersonnel:id,full_name', 'waitingParty:id,display_name']), $viewer)
            ->when($unitId !== null, fn (Builder $query) => $query->where('work_items.org_unit_id', $unitId))
            ->when($projectId !== null, fn (Builder $query) => $query->where('work_items.project_id', $projectId));
    }

    /**
     * @param  Collection<int, WorkItem>  $cards
     * @return list<array{name: string, hours: float}>
     */
    private function hoursByUnit(Collection $cards): array
    {
        return $cards
            ->filter(fn (WorkItem $item): bool => (float) ($item->work_hours ?? 0) > 0)
            ->groupBy(fn (WorkItem $item): string => (string) ($item->orgUnit?->name ?? __('work_item.values.no_unit')))
            ->map(fn ($group, string $name): array => ['name' => $name, 'hours' => round((float) $group->sum(fn (WorkItem $item): float => (float) $item->work_hours), 1)])
            ->sortByDesc('hours')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Son sekiz haftada tamamlanan kart sayisi.
     *
     * @return list<array{week: int, start: string, count: int}>
     */
    private function weeklyDone(Personnel $viewer, ?int $unitId, ?int $projectId): array
    {
        $start = WorkItemQueries::weekStart(WorkItemQueries::today())->subWeeks(7);
        $done = $this->scoped($viewer, $unitId, $projectId)
            ->where('work_items.status', WorkItemStatus::Done->value)
            ->where('work_items.done_on', '>=', $start->format('Y-m-d'))
            ->pluck('work_items.done_on');
        $rows = [];

        for ($i = 0; $i < 8; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->addDays(6);
            $rows[] = [
                'week' => (int) $weekStart->isoWeek(),
                'start' => $weekStart->format('Y-m-d'),
                'count' => $done->filter(fn ($day): bool => $day !== null && Carbon::parse($day)->format('Y-m-d') >= $weekStart->format('Y-m-d') && Carbon::parse($day)->format('Y-m-d') <= $weekEnd->format('Y-m-d'))->count(),
            ];
        }

        return $rows;
    }

    /**
     * En cok bekleten taraflar: ortalama bekleme gunu.
     *
     * @param  Collection<int, WorkItem>  $cards
     * @return list<array{name: string, days: float, count: int}>
     */
    private function waitingParties(Collection $cards): array
    {
        return $cards
            ->filter(fn (WorkItem $item): bool => $item->waiting_kind !== null && $item->secondsIn('waiting') > 0 && filled($item->waitingLabel()))
            ->groupBy(fn (WorkItem $item): string => (string) $item->waitingLabel())
            ->map(fn ($group, string $name): array => [
                'name' => $name,
                'days' => round((float) $group->avg(fn (WorkItem $item): int => $item->secondsIn('waiting')) / 86400, 1),
                'count' => $group->count(),
            ])
            ->sortByDesc('days')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * En uzun suredir acik kartlar (ilk bes).
     *
     * @return list<array<string, mixed>>
     */
    private function oldestOpen(Personnel $viewer, ?int $unitId, ?int $projectId): array
    {
        $today = WorkItemQueries::today();

        return $this->scoped($viewer, $unitId, $projectId)
            ->with(['project:id,name', 'personnel:id,full_name'])
            ->whereIn('work_items.status', WorkItemStatus::openValues())
            ->orderBy('work_items.work_on')
            ->orderBy('work_items.id')
            ->limit(5)
            ->get()
            ->map(fn (WorkItem $item): array => [
                'id' => (int) $item->getKey(),
                'title' => (string) $item->title,
                'project' => $item->project?->name,
                'status' => $item->status?->value,
                'status_label' => $item->status?->getLabel(),
                'owner' => $item->personnel?->full_name,
                'open_days' => $item->work_on !== null ? (int) Carbon::parse($item->work_on->format('Y-m-d'), DisplayTime::zone())->startOfDay()->diffInDays($today) : 0,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
