<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Report\ReportStatus;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Reports\Templates\DailyControlReportTemplate;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Reports\Templates\WeeklyWorkReportTemplate;
use App\Reports\Work\ControlSectionCatalog;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Kontrol matrisi ve kisi uzerindeki sonucu (B36, D-115; gunluk doldurma
 * D-116, 23 Eylul 2026).
 *
 * - Gunluk gorunum: IK o gunun isaretlerini girer (bir kisi + bolum + gun
 *   icin bir kontrol raporu).
 * - Haftalik gorunum: ayni haftanin gunluk raporlarinin toplamidir; salt
 *   okunurdur. Yalniz uygun olmayanlar yazilir ("Soft kayitlar x4"),
 *   uygunlar yazilmaz; aciklama sutunu o haftanin butun gunluk
 *   aciklamalarinin toplamidir.
 * - "Haftalik rapor" sutunu elle isaretlenmez, sistemden gelir (kisi
 *   haftasini kapatti mi) ve dogasi geregi haftaliktir.
 *
 * Isaret degerleri: ok (uygun), bad (uygun degil), none (isaretsiz / uygulanmaz).
 * Uygunluk orani = uygun / (uygun + uygun degil); isaretsiz sayilmaz.
 */
final class ControlMatrixQueries
{
    public const SUMMARY_WEEKS = 4;

    public const HISTORY_WEEKS = 12;

    /** Hafta gorunumunde gosterilen gunler (pazartesi - pazar). */
    private const WEEK_DAYS = 7;

    public function __construct(
        private readonly ControlSectionCatalog $sections,
    ) {}

    /**
     * Bolumun uyeleri: bolum departmanlarinin aktif personeli.
     *
     * @return Collection<int, Personnel>
     */
    public function members(string $section): Collection
    {
        $units = $this->sections->units($section);

        if ($units === []) {
            return new Collection;
        }

        return Personnel::query()
            ->with('orgUnit:id,name,code')
            ->where('status', 'active')
            ->whereHas('orgUnit', fn (Builder $query) => $query->whereIn('code', $units))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'org_unit_id', 'job_title']);
    }

    /**
     * Uyesi olan bolumler (sekme listesi).
     *
     * @return list<array{code: string, label: string, count: int, criteria: list<array{code: string, label: string, auto: bool}>}>
     */
    public function sectionList(): array
    {
        $list = [];

        foreach ($this->sections->codes() as $code) {
            $count = $this->members($code)->count();

            if ($count === 0) {
                continue;
            }

            $list[] = [
                'code' => $code,
                'label' => $this->sections->sectionLabel($code),
                'count' => $count,
                'criteria' => array_map(fn (string $criterion): array => [
                    'code' => $criterion,
                    'label' => $this->sections->criterionLabel($criterion),
                    'auto' => $criterion === ControlSectionCatalog::WEEKLY_REPORT,
                ], $this->sections->criteria($code)),
            ];
        }

        return $list;
    }

    /**
     * Gunluk matris: bolumun bir gunu (IK doldurur).
     *
     * @return array<string, mixed>
     */
    public function daySheet(string $section, Carbon $day): array
    {
        $weekStart = WorkItemQueries::weekStart($day);
        $context = $this->context($section, $weekStart);
        $key = $day->format('Y-m-d');
        $rows = [];
        $controller = null;

        foreach ($context['members'] as $person) {
            $id = (int) $person->getKey();
            $report = $context['reports'][$id][$key] ?? null;
            $marks = [];

            foreach ($context['manual'] as $criterion) {
                $marks[$criterion] = $this->markOf($report, $criterion);
            }

            if ($report instanceof Report && $controller === null) {
                $controller = $report->author?->full_name;
            }

            $rows[] = [
                'personnel' => $this->person($person),
                'marks' => $marks,
                'auto' => $this->autoOf($context, $id, $weekStart),
                'note' => $report?->payload['note'] ?? null,
                'report' => $this->reportInfo($report),
                'summary' => $this->rowSummary($context, $id, $weekStart),
            ];
        }

        return [
            'mode' => 'day',
            'day' => $key,
            'week' => $weekStart->format('Y-m-d'),
            'week_end' => $weekStart->copy()->addDays(6)->format('Y-m-d'),
            'label' => $day->copy()->locale(app()->getLocale())->translatedFormat('l').' '.$day->format('d.m.Y'),
            'rows' => $rows,
            'controller' => $controller,
        ];
    }

    /**
     * Haftalik matris: gunluk isaretlerin toplami (salt okunur).
     *
     * @return array<string, mixed>
     */
    public function weekSheet(string $section, Carbon $weekStart): array
    {
        $context = $this->context($section, $weekStart);
        $days = [];

        for ($offset = 0; $offset < self::WEEK_DAYS; $offset++) {
            $day = $weekStart->copy()->addDays($offset);
            $days[] = ['date' => $day->format('Y-m-d'), 'label' => $day->format('d.m'), 'name' => $day->copy()->locale(app()->getLocale())->translatedFormat('l')];
        }

        $rows = [];
        $controller = null;

        foreach ($context['members'] as $person) {
            $id = (int) $person->getKey();
            $bad = [];
            $checked = [];
            $notes = [];
            $filled = 0;

            foreach ($days as $day) {
                $report = $context['reports'][$id][$day['date']] ?? null;

                if (! $report instanceof Report) {
                    continue;
                }

                $filled++;

                if ($controller === null) {
                    $controller = $report->author?->full_name;
                }

                foreach ($context['manual'] as $criterion) {
                    $mark = $this->markOf($report, $criterion);

                    if ($mark === 'none') {
                        continue;
                    }

                    $checked[$criterion] = ($checked[$criterion] ?? 0) + 1;

                    if ($mark === 'bad') {
                        $bad[$criterion] = ($bad[$criterion] ?? 0) + 1;
                    }
                }

                if (filled($report->payload['note'] ?? null)) {
                    $notes[] = ['label' => $day['label'], 'text' => (string) $report->payload['note']];
                }
            }

            $rows[] = [
                'personnel' => $this->person($person),
                'bad' => $bad,
                'checked' => $checked,
                'auto' => $this->autoOf($context, $id, $weekStart),
                'days' => $filled,
                'notes' => $notes,
                'note' => implode(' · ', array_map(static fn (array $note): string => $note['label'].' '.$note['text'], $notes)),
                'summary' => $this->rowSummary($context, $id, $weekStart),
            ];
        }

        return [
            'mode' => 'week',
            'week' => $weekStart->format('Y-m-d'),
            'week_end' => $weekStart->copy()->addDays(6)->format('Y-m-d'),
            'label' => $weekStart->format('d.m').' – '.$weekStart->copy()->addDays(5)->format('d.m.Y'),
            'days' => $days,
            'rows' => $rows,
            'controller' => $controller,
        ];
    }

    /**
     * Kisinin kontrol raporlari (tum bolumler), eskiden yeniye.
     *
     * @return Collection<int, Report>
     */
    public function personnelReports(int $personnelId, Carbon $from, Carbon $to): Collection
    {
        return Report::query()
            ->with('author:id,full_name')
            ->where('template_code', DailyControlReportTemplate::CODE)
            ->where('subject_personnel_id', $personnelId)
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('period_start')
            ->orderBy('id')
            ->get();
    }

    /** Personel kartindaki Haftalik kontrol sekmesinin sorgusu. */
    public function personnelReportsQuery(int $personnelId): Builder
    {
        return Report::query()
            ->with('author:id,full_name')
            ->where('template_code', DailyControlReportTemplate::CODE)
            ->where('subject_personnel_id', $personnelId);
    }

    /**
     * Kontrol sekmesi basligi: bu hafta, son dort hafta, tekrar eden kriter.
     *
     * @return array{ok: int, total: int, weeks: list<array{label: string, ok: int, total: int}>, repeating: array{label: string, weeks: int}|null}
     */
    public function personnelSummary(Personnel $person): array
    {
        $id = (int) $person->getKey();
        $weekStart = WorkItemQueries::weekStart(WorkItemQueries::today());
        $from = $weekStart->copy()->subWeeks(self::SUMMARY_WEEKS - 1);
        $reports = $this->personnelReports($id, $from, $weekStart->copy()->addDays(6));
        $weekly = $this->weeklyStatus([$id], $from, $weekStart)[$id] ?? [];
        $weeks = [];
        $badWeeks = [];

        for ($offset = self::SUMMARY_WEEKS - 1; $offset >= 0; $offset--) {
            $start = $weekStart->copy()->subWeeks($offset);
            $end = $start->copy()->addDays(6);
            $ok = 0;
            $total = 0;
            $sections = [];
            $seen = [];

            foreach ($reports as $report) {
                $date = $report->period_start?->format('Y-m-d');

                if ($date === null || $date < $start->format('Y-m-d') || $date > $end->format('Y-m-d')) {
                    continue;
                }

                $section = (string) ($report->payload['section'] ?? '');
                $sections[$section] = true;

                foreach ((array) ($report->payload['marks'] ?? []) as $criterion => $mark) {
                    if (! in_array($mark, ['ok', 'bad'], true)) {
                        continue;
                    }

                    $total++;
                    $ok += $mark === 'ok' ? 1 : 0;

                    if ($mark === 'bad' && ! isset($seen[(string) $criterion])) {
                        $seen[(string) $criterion] = true;
                        $badWeeks[(string) $criterion] = ($badWeeks[(string) $criterion] ?? 0) + 1;
                    }
                }
            }

            foreach (array_keys($sections) as $section) {
                if (! $this->sections->hasWeeklyReport((string) $section)) {
                    continue;
                }

                $auto = $this->autoMark($weekly[$start->format('Y-m-d')] ?? false, $start);

                if ($auto !== 'none') {
                    $total++;
                    $ok += $auto === 'ok' ? 1 : 0;
                }

                break;
            }

            $weeks[] = ['label' => $start->format('d.m'), 'ok' => $ok, 'total' => $total];
        }

        arsort($badWeeks);
        $repeat = array_key_first(array_filter($badWeeks, static fn (int $count): bool => $count >= 2));
        $current = $weeks[count($weeks) - 1] ?? ['ok' => 0, 'total' => 0];

        return [
            'ok' => (int) $current['ok'],
            'total' => (int) $current['total'],
            'weeks' => $weeks,
            'repeating' => $repeat !== null ? ['label' => $this->sections->criterionLabel((string) $repeat), 'weeks' => (int) $badWeeks[$repeat]] : null,
        ];
    }

    /**
     * Dikkat karti: son 12 haftanin kontrol ve rapor disiplini.
     *
     * @return array<string, mixed>
     */
    public function attentionCard(Personnel $person): array
    {
        $id = (int) $person->getKey();
        $today = WorkItemQueries::today();
        $weekStart = WorkItemQueries::weekStart($today);
        $from = $weekStart->copy()->subWeeks(self::HISTORY_WEEKS - 1);
        $previousFrom = $from->copy()->subWeeks(self::HISTORY_WEEKS);
        $current = $this->criteriaStats($id, $from, $weekStart);
        $previous = $this->criteriaStats($id, $previousFrom, $from->copy()->subWeek());

        $workdays = WorkItemQueries::workdays($from, $weekStart->copy()->addDays(6));
        [$onTimeDays, $closedDays] = $this->dailyDiscipline($id, $from, $today);
        [$onTimeWeeks, $closedWeeks, $revisions] = $this->weeklyDiscipline($id, $from, $weekStart);
        $notes = $current['notes'];

        return [
            'weeks' => self::HISTORY_WEEKS,
            'controls' => $current['reports'],
            'workdays' => $workdays,
            'score' => $current['pct'],
            'checked' => $current['checked'],
            'ok' => $current['ok'],
            'delta' => $current['pct'] !== null && $previous['pct'] !== null ? $current['pct'] - $previous['pct'] : null,
            'criteria' => $current['criteria'],
            'days_on_time' => $onTimeDays,
            'days_closed' => $closedDays,
            'weeks_on_time' => $onTimeWeeks,
            'weeks_closed' => $closedWeeks,
            'revisions' => $revisions,
            'notes' => count($notes),
            'last_note' => $notes === [] ? null : $notes[count($notes) - 1],
        ];
    }

    /**
     * Bir bolum + hafta icin ortak baglam: uyeler, dort haftalik gunluk
     * raporlar, haftalik rapor durumu ve kriterler.
     *
     * @return array{members: Collection<int, Personnel>, reports: array<int, array<string, Report>>, weekly: array<int, array<string, bool>>, manual: list<string>, has_weekly: bool}
     */
    private function context(string $section, Carbon $weekStart): array
    {
        $members = $this->members($section);
        $ids = $members->map(fn (Personnel $person): int => (int) $person->getKey())->all();
        $from = $weekStart->copy()->subWeeks(self::SUMMARY_WEEKS - 1);
        $to = $weekStart->copy()->addDays(6);

        return [
            'members' => $members,
            'reports' => $this->sectionReports($ids, $from, $to, $section),
            'weekly' => $this->weeklyStatus($ids, $from, $weekStart),
            'manual' => $this->sections->manualCriteria($section),
            'has_weekly' => $this->sections->hasWeeklyReport($section),
        ];
    }

    /**
     * Sag paneldeki ozet: bu hafta uygun / isaretli, son dort hafta ve
     * tekrar eden kriter (hepsi gunluk isaretlerden toplanir).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function rowSummary(array $context, int $personnelId, Carbon $weekStart): array
    {
        $weeks = [];
        $badWeeks = [];
        $current = ['ok' => 0, 'total' => 0];

        for ($offset = self::SUMMARY_WEEKS - 1; $offset >= 0; $offset--) {
            $start = $weekStart->copy()->subWeeks($offset);
            $ok = 0;
            $total = 0;
            $seen = [];

            for ($index = 0; $index < self::WEEK_DAYS; $index++) {
                $report = $context['reports'][$personnelId][$start->copy()->addDays($index)->format('Y-m-d')] ?? null;

                if (! $report instanceof Report) {
                    continue;
                }

                foreach ($context['manual'] as $criterion) {
                    $mark = $this->markOf($report, $criterion);

                    if ($mark === 'none') {
                        continue;
                    }

                    $total++;
                    $ok += $mark === 'ok' ? 1 : 0;

                    if ($mark === 'bad' && ! isset($seen[$criterion])) {
                        $seen[$criterion] = true;
                        $badWeeks[$criterion] = ($badWeeks[$criterion] ?? 0) + 1;
                    }
                }
            }

            $auto = $this->autoOf($context, $personnelId, $start);

            if ($auto !== 'none') {
                $total++;
                $ok += $auto === 'ok' ? 1 : 0;
            }

            $weeks[] = ['week' => $start->format('Y-m-d'), 'label' => $start->format('d.m'), 'ok' => $ok, 'total' => $total];
            $current = ['ok' => $ok, 'total' => $total];
        }

        arsort($badWeeks);
        $repeat = array_key_first(array_filter($badWeeks, static fn (int $count): bool => $count >= 2));

        return [
            'ok' => (int) $current['ok'],
            'total' => (int) $current['total'],
            'weeks' => $weeks,
            'repeating' => $repeat !== null ? ['label' => $this->sections->criterionLabel((string) $repeat), 'weeks' => (int) $badWeeks[$repeat]] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function autoOf(array $context, int $personnelId, Carbon $weekStart): string
    {
        if ($context['has_weekly'] !== true) {
            return 'none';
        }

        return $this->autoMark($context['weekly'][$personnelId][$weekStart->format('Y-m-d')] ?? false, $weekStart);
    }

    /**
     * @return array{id: int, name: string, unit: string|null, job_title: string|null}
     */
    private function person(Personnel $person): array
    {
        return [
            'id' => (int) $person->getKey(),
            'name' => (string) $person->full_name,
            'unit' => $person->orgUnit?->name,
            'job_title' => $person->job_title,
        ];
    }

    /**
     * @return array{id: int, no: string, url: string|null}|null
     */
    private function reportInfo(?Report $report): ?array
    {
        return $report instanceof Report
            ? ['id' => (int) $report->getKey(), 'no' => (string) $report->report_no, 'url' => $this->reportUrl($report)]
            : null;
    }

    /**
     * Kriter bazinda uygunluk (tum bolumler) + haftalik rapor otomatik kriteri.
     *
     * @return array{criteria: list<array<string, mixed>>, ok: int, checked: int, pct: int|null, reports: int, notes: list<string>}
     */
    private function criteriaStats(int $personnelId, Carbon $from, Carbon $to): array
    {
        $reports = $this->personnelReports($personnelId, $from, $to->copy()->addDays(6));
        $weekly = $this->weeklyStatus([$personnelId], $from, $to)[$personnelId] ?? [];
        $weekKeys = [];

        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addWeek()) {
            $weekKeys[] = $day->format('Y-m-d');
        }

        $index = array_flip($weekKeys);
        $stats = [];
        $order = [];
        $notes = [];
        $wantsWeekly = false;

        foreach ($reports as $report) {
            $section = (string) ($report->payload['section'] ?? '');
            $wantsWeekly = $wantsWeekly || $this->sections->hasWeeklyReport($section);
            $week = $report->period_start !== null
                ? ($index[WorkItemQueries::weekStart($report->period_start)->format('Y-m-d')] ?? null)
                : null;

            foreach ($this->sections->manualCriteria($section) as $criterion) {
                $order[$criterion] ??= count($order);
            }

            foreach ((array) ($report->payload['marks'] ?? []) as $criterion => $mark) {
                if (! in_array($mark, ['ok', 'bad'], true)) {
                    continue;
                }

                $stats[$criterion] ??= ['ok' => 0, 'checked' => 0, 'bad_weeks' => []];
                $stats[$criterion]['checked']++;

                if ($mark === 'ok') {
                    $stats[$criterion]['ok']++;
                } elseif ($week !== null) {
                    $stats[$criterion]['bad_weeks'][] = $week;
                }
            }

            if (filled($report->payload['note'] ?? null)) {
                $notes[] = (string) $report->payload['note'];
            }
        }

        $last = count($weekKeys) - 1;
        $rows = [];
        $ok = 0;
        $checked = 0;

        uksort($stats, fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        foreach ($stats as $criterion => $row) {
            $ok += $row['ok'];
            $checked += $row['checked'];
            $badWeeks = array_values(array_unique($row['bad_weeks']));
            sort($badWeeks);
            $consecutive = false;

            for ($i = 1; $i < count($badWeeks); $i++) {
                if ($badWeeks[$i] - $badWeeks[$i - 1] === 1) {
                    $consecutive = true;
                }
            }

            $lastBad = $badWeeks === [] ? null : $last - $badWeeks[count($badWeeks) - 1];

            $rows[] = [
                'code' => $criterion,
                'label' => $this->sections->criterionLabel($criterion),
                'pct' => $row['checked'] > 0 ? (int) round($row['ok'] * 100 / $row['checked']) : null,
                'warnings' => count($row['bad_weeks']),
                'consecutive' => $consecutive,
                'weeks_ago' => $lastBad,
                'auto' => false,
            ];
        }

        if ($wantsWeekly) {
            $closed = 0;
            $count = 0;

            foreach ($weekKeys as $key) {
                $start = Carbon::createFromFormat('Y-m-d', $key, DisplayTime::zone())->startOfDay();

                if ($start->copy()->addDays(6)->greaterThanOrEqualTo(WorkItemQueries::today()) && ! ($weekly[$key] ?? false)) {
                    continue;
                }

                $count++;

                if ($weekly[$key] ?? false) {
                    $closed++;
                }
            }

            $ok += $closed;
            $checked += $count;
            $rows[] = [
                'code' => ControlSectionCatalog::WEEKLY_REPORT,
                'label' => $this->sections->criterionLabel(ControlSectionCatalog::WEEKLY_REPORT),
                'pct' => $count > 0 ? (int) round($closed * 100 / $count) : null,
                'warnings' => $count - $closed,
                'consecutive' => false,
                'weeks_ago' => null,
                'auto' => true,
                'closed' => $closed,
                'of' => $count,
            ];
        }

        return [
            'criteria' => $rows,
            'ok' => $ok,
            'checked' => $checked,
            'pct' => $checked > 0 ? (int) round($ok * 100 / $checked) : null,
            'reports' => $reports->count(),
            'notes' => $notes,
        ];
    }

    /**
     * Gunluk rapor disiplini: [zamaninda (ayni gun gonderilen), kapatilan].
     *
     * @return array{0: int, 1: int}
     */
    private function dailyDiscipline(int $personnelId, Carbon $from, Carbon $to): array
    {
        $zone = DisplayTime::zone();
        $reports = Report::query()
            ->where('template_code', DailyWorkReportTemplate::CODE)
            ->where('author_personnel_id', $personnelId)
            ->whereIn('status', [ReportStatus::Submitted->value, ReportStatus::Approved->value])
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get(['id', 'period_start', 'submitted_at']);

        $onTime = $reports->filter(fn (Report $report): bool => $report->submitted_at !== null
            && $report->period_start !== null
            && $report->submitted_at->copy()->timezone($zone)->format('Y-m-d') <= $report->period_start->format('Y-m-d'))->count();

        return [$onTime, $reports->count()];
    }

    /**
     * Haftalik rapor disiplini: [zamaninda (izleyen pazartesiye kadar), kapatilan, revizyon].
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function weeklyDiscipline(int $personnelId, Carbon $from, Carbon $to): array
    {
        $zone = DisplayTime::zone();
        $reports = Report::query()
            ->where('template_code', WeeklyWorkReportTemplate::CODE)
            ->where('author_personnel_id', $personnelId)
            ->whereNotIn('status', [ReportStatus::Draft->value])
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get(['id', 'period_start', 'submitted_at', 'revision_count']);

        $onTime = $reports->filter(fn (Report $report): bool => $report->submitted_at !== null
            && $report->period_start !== null
            && $report->submitted_at->copy()->timezone($zone)->format('Y-m-d') <= $report->period_start->copy()->addDays(7)->format('Y-m-d'))->count();

        return [$onTime, $reports->count(), (int) $reports->sum('revision_count')];
    }

    /**
     * Bolumun gunluk kontrol raporlari: kisi => gun => rapor.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, Report>>
     */
    private function sectionReports(array $ids, Carbon $from, Carbon $to, string $section): array
    {
        if ($ids === []) {
            return [];
        }

        $map = [];

        $reports = Report::query()
            ->with('author:id,full_name')
            ->where('template_code', DailyControlReportTemplate::CODE)
            ->whereIn('subject_personnel_id', $ids)
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('payload->section', $section)
            ->orderBy('id')
            ->get();

        foreach ($reports as $report) {
            $map[(int) $report->subject_personnel_id][(string) $report->period_start?->format('Y-m-d')] = $report;
        }

        return $map;
    }

    /**
     * Haftalik calisma raporu gonderildi mi: kisi => hafta basi => bool.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, bool>>
     */
    private function weeklyStatus(array $ids, Carbon $from, Carbon $to): array
    {
        if ($ids === []) {
            return [];
        }

        $map = [];

        $reports = Report::query()
            ->where('template_code', WeeklyWorkReportTemplate::CODE)
            ->whereIn('author_personnel_id', $ids)
            ->whereNotIn('status', [ReportStatus::Draft->value])
            ->whereBetween('period_start', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get(['id', 'author_personnel_id', 'period_start']);

        foreach ($reports as $report) {
            $map[(int) $report->author_personnel_id][(string) $report->period_start?->format('Y-m-d')] = true;
        }

        return $map;
    }

    /** Otomatik sutun: gonderildi -> ok; hafta bittiyse ve yoksa -> bad; surerken -> none. */
    private function autoMark(bool $submitted, Carbon $weekStart): string
    {
        if ($submitted) {
            return 'ok';
        }

        return $weekStart->copy()->addDays(6)->lessThan(WorkItemQueries::today()) ? 'bad' : 'none';
    }

    private function markOf(?Report $report, string $criterion): string
    {
        $mark = $report?->payload['marks'][$criterion] ?? null;

        return in_array($mark, ['ok', 'bad'], true) ? $mark : 'none';
    }

    private function reportUrl(Report $report): ?string
    {
        try {
            return ReportResource::getUrl('view', ['record' => $report]);
        } catch (Throwable) {
            return null;
        }
    }
}
