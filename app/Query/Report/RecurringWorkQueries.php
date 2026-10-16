<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Report\WorkItemStatus;
use App\Models\Personnel\Personnel;
use App\Models\Report\RecurringWork;
use App\Models\Report\WorkItem;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Gorev ve sorumluluklar okuma sorgulari (D-219): gorunurluk, zamanlayicinin
 * acacagi tanimlar, donem isi var mi ve uyum (son donemlerde zamaninda /
 * gec / yapilmadi).
 */
final class RecurringWorkQueries
{
    /** Uyum sutununda bakilan donem sayisi. */
    public const COMPLIANCE_PERIODS = 8;

    public const ON_TIME = 'on_time';

    public const LATE = 'late';

    public const MISSED = 'missed';

    public const OPEN = 'open';

    public function __construct(private readonly ReportQueries $reports) {}

    /** Gorunurluk: kendi gorevleri, ekibinin gorevleri; tum kartlari goren hepsini. */
    public function applyVisible(Builder $query, ?Personnel $viewer): Builder
    {
        if (! $viewer instanceof Personnel) {
            return $query->whereRaw('1 = 0');
        }

        if (Gate::forUser($viewer)->allows('viewAll', WorkItem::class) || $this->reports->isAuditor($viewer)) {
            return $query;
        }

        $me = (int) $viewer->getKey();

        return $query->whereIn('recurring_works.personnel_id', [$me, ...$this->reports->teamPersonnelIds($me)]);
    }

    /** Arsiv suzgeci (D-156): active (varsayilan), archived, all. */
    public function applyArchive(Builder $query, ?string $value): Builder
    {
        return match ($value) {
            'archived' => $query->whereNotNull('recurring_works.archived_at'),
            'all' => $query,
            default => $query->whereNull('recurring_works.archived_at'),
        };
    }

    /**
     * Gunun donem isi acilabilecek tanimlar: aktif, arsivde degil, baslamis
     * ve bitmemis.
     *
     * @return Collection<int, RecurringWork>
     */
    public function activeOn(CarbonImmutable $day): Collection
    {
        $ymd = $day->format('Y-m-d');

        return RecurringWork::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('starts_on', '<=', $ymd)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $ymd))
            ->orderBy('id')
            ->get();
    }

    public function occurrenceExists(int $recurringWorkId, string $periodKey): bool
    {
        return WorkItem::query()
            ->where('recurring_work_id', $recurringWorkId)
            ->where('period_key', $periodKey)
            ->exists();
    }

    /**
     * Son donemlerin uyumu: tanim => [en yeniden eskiye durum listesi]. Durum:
     * zamaninda (son tarihte ya da once tamamlandi), gec, yapilmadi (son tarih
     * gecti ve acik ya da iptal), acik (suresi var).
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{state: string, period: string, item: int}>>
     */
    public function compliance(array $ids, int $periods = self::COMPLIANCE_PERIODS): array
    {
        if ($ids === []) {
            return [];
        }

        $today = CarbonImmutable::now(DisplayTime::zone())->format('Y-m-d');
        $rows = WorkItem::query()
            ->whereIn('recurring_work_id', $ids)
            ->orderByDesc('work_on')
            ->orderByDesc('id')
            ->get(['id', 'recurring_work_id', 'period_key', 'status', 'due_on', 'done_on', 'title']);
        $result = [];

        foreach ($rows as $row) {
            $id = (int) $row->recurring_work_id;

            if (count($result[$id] ?? []) >= $periods) {
                continue;
            }

            $result[$id][] = [
                'state' => self::stateOf($row, $today),
                'period' => (string) $row->period_key,
                'item' => (int) $row->getKey(),
            ];
        }

        return $result;
    }

    /**
     * Uyum ozeti (liste ve personel sekmesi): yapilan / bakilan donem.
     *
     * @param  list<array{state: string}>  $rows
     * @return array{done: int, total: int, missed: int, late: int}
     */
    public static function summarize(array $rows): array
    {
        $closed = array_values(array_filter($rows, fn (array $row): bool => $row['state'] !== self::OPEN));

        return [
            'done' => count(array_filter($closed, fn (array $row): bool => in_array($row['state'], [self::ON_TIME, self::LATE], true))),
            'total' => count($closed),
            'missed' => count(array_filter($closed, fn (array $row): bool => $row['state'] === self::MISSED)),
            'late' => count(array_filter($closed, fn (array $row): bool => $row['state'] === self::LATE)),
        ];
    }

    private static function stateOf(WorkItem $row, string $today): string
    {
        $due = $row->due_on?->format('Y-m-d');

        if ($row->status === WorkItemStatus::Done) {
            $done = $row->done_on?->format('Y-m-d');

            return $due !== null && $done !== null && $done > $due ? self::LATE : self::ON_TIME;
        }

        if ($row->status === WorkItemStatus::Blocked) {
            return self::MISSED;
        }

        return $due !== null && $due < $today ? self::MISSED : self::OPEN;
    }
}
