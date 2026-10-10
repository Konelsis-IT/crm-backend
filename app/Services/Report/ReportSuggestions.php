<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Query\Report\ReportQueries;
use App\Query\Report\ReportSuggestionQueries;
use App\Query\Report\WorkItemQueries;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gunluk / haftalik rapor onerileri (D-167, 6 Ekim 2026 kullanici karari):
 * "Raporlar + gorusme notlari + isler hepsi gunluk / haftalik rapora tarih
 * uydugu surece eklenecektir ... ozellikle kaldirmazsa raporda yer alacaktir."
 *
 * - Isler: yazarin donemdeki is panosu kartlari, Gunu / Haftayi kapat ile ayni
 *   satirlar (WorkItemPresenter::reportRows). Pano yoksa onceki raporun
 *   bitmemis kalemleri (eski davranis).
 * - Kaynaklar (ReportField::SOURCES): yazarin donemdeki gorusme notlari ve
 *   ilgili kayitlara yazdigi raporlar. Karta donmus olan kayit ikinci kez
 *   onerilmez. Raporda donmus kopya saklanir: her satir anahtar, tarih,
 *   baslik, ayrinti, metin, sonraki adim ve `included` (isaretli mi) tasir.
 *
 * Kural: kaydedilirken alan formdan geldiyse yalniz isaretli anahtarlar
 * rapora girer; alan hic gelmediyse (is panosundan Gunu / Haftayi kapat)
 * onceki secim korunur ve yeni oneriler eklenir. Ileri tarihli kayit kendi
 * gununun raporunda onerilir.
 *
 * D-179 (8 Ekim 2026 kullanici istegi): rapor yazilirken
 * - panodan is secilir (pickOptions / pickedRow): yazarin daha once olusmus
 *   kartlari, donemin kartlari once; kalem kartin kimligini tasir;
 * - oneriler listesi (boardSuggestions): donemin rapora girmemis kartlari ve
 *   is panosu onerileri (hareketler) tek tikla eklenir; eklenmis olan
 *   "Eklendi" olarak isaretlenir. Gorusme notu ve rapor hareketleri kaynak
 *   alanlarinda zaten oldugu icin burada tekrar onerilmez.
 */
#[Scoped]
final class ReportSuggestions
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $rowMemo = [];

    /** @var array<string, Collection<int, WorkItem>> */
    private array $cardMemo = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $boardMemo = [];

    /** @var array<string, array<string, array<int|string, string>>> */
    private array $pickMemo = [];

    public function __construct(
        private readonly ReportSuggestionQueries $queries,
        private readonly WorkItemQueries $workItems,
        private readonly WorkItemPresenter $presenter,
        private readonly ReportQueries $reports,
    ) {}

    /** Taslak oneri alir mi (gun / hafta / ay donemli ve kaynak alanli ya da panolu)? */
    public function supports(ReportTemplate $template): bool
    {
        return $template->periodMode()->isCalendarUnit()
            && ($template->sourceFields() !== [] || $template->summarisesWork());
    }

    /**
     * Secilen gunun donemi (gun / hafta Pzt-Paz / ay); tarih yoksa null.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function period(ReportTemplate $template, mixed $periodStart): ?array
    {
        $mode = $template->periodMode();

        if (! $mode->isCalendarUnit() || ! filled($periodStart)) {
            return null;
        }

        $value = $periodStart instanceof CarbonInterface ? $periodStart->format('Y-m-d') : (string) $periodStart;

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        return $mode->bounds(Carbon::createFromFormat('Y-m-d', substr($value, 0, 10), DisplayTime::zone())->startOfDay());
    }

    /**
     * Yeni rapor formunun on dolu degerleri: kaynak alanlarinda butun
     * onerilerin anahtarlari (hepsi isaretli) ve pano kalemleri.
     *
     * @return array{payload: array<string, list<string>>, items: list<array<string, mixed>>|null}
     */
    public function prefill(ReportTemplate $template, int $authorId, mixed $periodStart): array
    {
        $period = $this->period($template, $periodStart);
        $payload = [];

        foreach ($template->sourceFields() as $field) {
            $payload[$field->name] = $period !== null
                ? array_keys($this->rows($field, $authorId, $period[0], $period[1]))
                : [];
        }

        return [
            'payload' => $payload,
            'items' => $template->hasItems() ? $this->itemRows($template, $authorId, $period) : null,
        ];
    }

    /** Kalemler is panosu kartlarindan mi gelir (calisma ozeti + B36)? */
    public function usesBoard(ReportTemplate $template): bool
    {
        return $template->summarisesWork() && $template->hasItems() && SchemaReadiness::hasBatch('B36');
    }

    /**
     * Pano kalemleri (Repeater satir bicimi): is panosu varsa donemin kartlari
     * (Gunu / Haftayi kapat ile ayni satirlar), yoksa onceki raporun bitmemis
     * kalemleri.
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $period
     * @return list<array<string, mixed>>
     */
    public function itemRows(ReportTemplate $template, int $authorId, ?array $period): array
    {
        if ($period !== null && $this->usesBoard($template)) {
            return array_map(
                static fn (array $row): array => ['id' => null, 'carried_from_item_id' => null, ...$row],
                $this->presenter->reportRows($this->cards($authorId, $period[0], $period[1])),
            );
        }

        return $this->reports->carryOverItems($template->code(), $authorId);
    }

    /**
     * Formdaki secim listesi: kayitli satirlar (donem icindekiler) + guncel
     * oneriler; anahtar => satir.
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $period
     * @return array<string, array<string, mixed>>
     */
    public function choices(ReportField $field, int $authorId, ?array $period, mixed $saved = null, ?int $exceptReportId = null): array
    {
        $rows = $this->savedRows($saved, $period);

        if ($period !== null) {
            foreach ($this->rows($field, $authorId, $period[0], $period[1], $exceptReportId) as $key => $row) {
                $rows[$key] = [...$row, 'included' => $rows[$key]['included'] ?? true];
            }
        }

        return $this->sorted($rows);
    }

    /**
     * Duzenleme formunda isaretli anahtarlar: kayitta isaretli olanlar ve
     * kayittan sonra cikan yeni oneriler (kaldirilmadikca rapora girer).
     *
     * @return list<string>
     */
    public function selectedForEdit(ReportField $field, Report $report): array
    {
        $template = $report->template();
        $period = $template !== null ? $this->period($template, $report->period_start) : null;
        $saved = $this->savedRows(($report->payload ?? [])[$field->name] ?? null, null);
        $selected = [];

        foreach ($this->choices($field, (int) $report->author_personnel_id, $period, $saved, (int) $report->getKey()) as $key => $row) {
            if (! array_key_exists($key, $saved) || (bool) ($saved[$key]['included'] ?? false)) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    /**
     * Kayit oncesi kaynak alanlarini donmus satirlara cevirir (ReportService).
     *
     * @param  array<string, mixed>  $payload  normalize edilmis cevaplar
     * @return array<string, mixed>
     */
    public function resolve(ReportTemplate $template, array $payload, mixed $periodStart, int $authorId, ?Report $existing): array
    {
        $fields = $template->sourceFields();

        if ($fields === []) {
            return $payload;
        }

        $period = $this->period($template, $periodStart);

        foreach ($fields as $field) {
            $previous = $this->savedRows(($existing?->payload ?? [])[$field->name] ?? null, $period);
            $current = $period !== null
                ? $this->rows($field, $authorId, $period[0], $period[1], $existing !== null ? (int) $existing->getKey() : null, fresh: true)
                : [];
            $selected = array_key_exists($field->name, $payload) ? $this->selectedKeys($payload[$field->name]) : null;
            $merged = $previous;

            foreach ($current as $key => $row) {
                $merged[$key] = [...$row, 'included' => $previous[$key]['included'] ?? true];
            }

            if ($selected !== null) {
                foreach (array_keys($merged) as $key) {
                    $merged[$key]['included'] = in_array($key, $selected, true);
                }
            }

            $payload[$field->name] = $merged === [] ? null : array_values($this->sorted($merged));
        }

        return $payload;
    }

    /**
     * Rapora giren (isaretli) satirlar; gorunum, kopya metni, PDF ve Excel.
     *
     * @return list<array<string, mixed>>
     */
    public static function included(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($row): bool => is_array($row) && (bool) ($row['included'] ?? false) && filled($row['key'] ?? null)));
    }

    /** Secim listesindeki satir adi: "06.10.2026 · Firma X — Konu". */
    public static function optionLabel(array $row): string
    {
        $date = self::displayDate($row['date'] ?? null);

        return trim(($date !== null ? $date.' · ' : '').(string) ($row['title'] ?? ''), ' ·');
    }

    /** Secim listesindeki aciklama: ayrinti + metnin basi. */
    public static function optionDescription(array $row): ?string
    {
        $parts = array_filter([
            filled($row['meta'] ?? null) ? (string) $row['meta'] : null,
            filled($row['text'] ?? null) ? Str::limit(Str::squish((string) $row['text']), 160, '…') : null,
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /** Y-m-d -> d.m.Y; gecersizse null. */
    public static function displayDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) !== 1) {
            return null;
        }

        return $m[3].'.'.$m[2].'.'.$m[1];
    }

    /**
     * D-179: "Panodan is ekle" secenekleri, gruplu: donemin isleri, acik
     * isler, diger isler (kart kimligi => "Is — 07.10.2026 · Durum · Proje").
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $period
     * @return array<string, array<int, string>>
     */
    public function pickOptions(int $authorId, ?array $period, string $search = ''): array
    {
        $memoKey = implode('|', [$authorId, $period !== null ? $period[0]->format('Y-m-d').'_'.$period[1]->format('Y-m-d') : '-', $search]);

        if (array_key_exists($memoKey, $this->pickMemo)) {
            return $this->pickMemo[$memoKey];
        }

        $groups = [];

        foreach ($this->queries->pickableWorkItems($authorId, $period[0] ?? null, $period[1] ?? null, $search) as $item) {
            $group = match ((int) $item->getAttribute('report_relevance')) {
                0 => __('report.pick.groups.period'),
                1 => __('report.pick.groups.open'),
                default => __('report.pick.groups.other'),
            };

            $groups[$group][(int) $item->getKey()] = $this->pickLabel($item);
        }

        return $this->pickMemo[$memoKey] = $groups;
    }

    /**
     * D-179: secilen kartin rapor kalemi (Gunu kapat ile ayni satir);
     * kart yazarin degilse ya da yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function pickedRow(int $authorId, int $workItemId): ?array
    {
        $item = $this->queries->pickableWorkItem($authorId, $workItemId);

        return $item instanceof WorkItem ? $this->cardRow($item) : null;
    }

    /** Kart -> rapor kalemi (Repeater satiri). */
    public function cardRow(WorkItem $item): array
    {
        return ['id' => null, 'carried_from_item_id' => null, ...$this->presenter->reportRow($item), 'picked' => true];
    }

    /**
     * D-179: rapor yazilirken gosterilen oneriler: donemin kartlari ve (yazar
     * kendisi yaziyorsa) is panosu onerileri. Her satir: key, kind (card /
     * activity), id, title, meta, date. Eklenmis olani arayuz isaretler.
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $period
     * @return list<array{key: string, kind: string, id: int, title: string, meta: string|null, date: string|null}>
     */
    public function boardSuggestions(ReportTemplate $template, int $authorId, ?array $period, ?Personnel $author): array
    {
        if ($period === null || $authorId <= 0 || ! $this->usesBoard($template)) {
            return [];
        }

        $withActivities = $author instanceof Personnel && (int) $author->getKey() === $authorId;
        $memoKey = implode('|', [$template->code(), $authorId, $period[0]->format('Y-m-d'), $period[1]->format('Y-m-d'), (int) $withActivities]);

        if (array_key_exists($memoKey, $this->boardMemo)) {
            return $this->boardMemo[$memoKey];
        }

        $rows = [];

        foreach ($this->cards($authorId, $period[0], $period[1]) as $item) {
            $rows[] = [
                'key' => 'card:'.(int) $item->getKey(),
                'kind' => 'card',
                'id' => (int) $item->getKey(),
                'title' => (string) $item->title,
                'meta' => $this->metaLine([
                    $item->work_on?->format('d.m.Y'),
                    $item->status?->getLabel(),
                    $item->project?->display_name,
                ]),
                'date' => $item->work_on?->format('Y-m-d'),
            ];
        }

        if ($withActivities) {
            $rows = [...$rows, ...$this->activityRows($template, $author, $period)];
        }

        usort($rows, static fn (array $a, array $b): int => [(string) $a['date'], $a['key']] <=> [(string) $b['date'], $b['key']]);

        return $this->boardMemo[$memoKey] = $rows;
    }

    /** D-179: bir oneri karta donunce ya da kart eklenince bu istegin onbellegi bosaltilir. */
    public function forget(): void
    {
        $this->rowMemo = [];
        $this->cardMemo = [];
        $this->boardMemo = [];
        $this->pickMemo = [];
    }

    /**
     * D-179: rapor kalemi yalniz yazarin gorebildigi karta baglanir (ReportService).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function visibleWorkItemIds(Personnel $author, array $ids): array
    {
        return $this->queries->visibleWorkItemIds($author, $ids);
    }

    /**
     * Is panosu onerileri (hareketler); kaynak alanlarinda zaten olan gorusme
     * notu / gorusme plani / rapor hareketleri haric.
     *
     * @param  array{0: Carbon, 1: Carbon}  $period
     * @return list<array{key: string, kind: string, id: int, title: string, meta: string|null, date: string|null}>
     */
    private function activityRows(ReportTemplate $template, Personnel $author, array $period): array
    {
        $covered = [];

        foreach ($template->sourceFields() as $field) {
            $covered[] = (string) $field->source;

            if ($field->source === ReportField::SOURCE_MEETING_NOTES) {
                $covered[] = 'meeting_plan';
            }
        }

        $activities = $this->queries->workSuggestionActivities((int) $author->getKey(), $period[0], $period[1])
            ->reject(static fn ($activity): bool => in_array((string) $activity->subject_type, $covered, true))
            ->values();

        if ($activities->isEmpty()) {
            return [];
        }

        $subjects = $this->workItems->suggestionSubjects($activities);
        $unitCode = $author->orgUnit?->code;
        $zone = DisplayTime::zone();
        $rows = [];

        foreach ($activities as $activity) {
            $suggestion = $this->presenter->suggestion($activity, $subjects, $unitCode);

            if ($suggestion === null) {
                continue;
            }

            $at = $activity->occurred_at?->copy()->timezone($zone);

            $rows[] = [
                'key' => 'activity:'.(int) $activity->getKey(),
                'kind' => 'activity',
                'id' => (int) $activity->getKey(),
                'title' => (string) ($suggestion['defaults']['title'] ?? $suggestion['subject']),
                'meta' => $this->metaLine([
                    $at?->format('d.m.Y H:i'),
                    (string) $suggestion['label'],
                    $suggestion['defaults']['project_name'] ?? null,
                ]),
                'date' => $at?->format('Y-m-d'),
            ];
        }

        return $rows;
    }

    /** Secim kutusundaki kart adi. */
    private function pickLabel(WorkItem $item): string
    {
        $meta = $this->metaLine([
            $item->work_on?->format('d.m.Y'),
            $item->status?->getLabel(),
            $item->project?->display_name,
        ]);

        return Str::limit((string) $item->title, 90, '…').($meta !== null ? ' — '.$meta : '');
    }

    /**
     * @param  list<mixed>  $parts
     */
    private function metaLine(array $parts): ?string
    {
        $parts = array_values(array_filter(array_map(static fn ($part): string => trim((string) $part), $parts), static fn (string $part): bool => $part !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Guncel oneriler (anahtar => satir); is panosunda karta donmus kayit haric.
     *
     * @return array<string, array<string, mixed>>
     */
    public function rows(ReportField $field, int $authorId, Carbon $from, Carbon $to, ?int $exceptReportId = null, bool $fresh = false): array
    {
        $memoKey = implode('|', [(string) $field->source, $authorId, $from->format('Y-m-d'), $to->format('Y-m-d'), (int) $exceptReportId]);

        if (! $fresh && array_key_exists($memoKey, $this->rowMemo)) {
            return $this->rowMemo[$memoKey];
        }

        $rows = [];

        if ($field->source === ReportField::SOURCE_MEETING_NOTES) {
            foreach ($this->queries->meetingNotes($authorId, $from, $to) as $note) {
                $row = $this->noteRow($note);
                $rows[$row['key']] = $row;
            }
        } elseif ($field->source === ReportField::SOURCE_REPORTS) {
            foreach ($this->queries->writtenReports($authorId, $from, $to, $exceptReportId) as $report) {
                if ($report->template()?->summarisesWork() ?? false) {
                    continue;
                }

                $row = $this->reportRow($report);
                $rows[$row['key']] = $row;
            }
        }

        $activityIds = $this->cards($authorId, $from, $to, $fresh)
            ->pluck('source_activity_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        foreach ($this->queries->convertedKeys($activityIds) as $key) {
            unset($rows[$key]);
        }

        return $this->rowMemo[$memoKey] = $rows;
    }

    /**
     * @return Collection<int, WorkItem>
     */
    private function cards(int $authorId, Carbon $from, Carbon $to, bool $fresh = false): Collection
    {
        if (! SchemaReadiness::hasBatch('B36') || $authorId <= 0) {
            return new Collection;
        }

        $memoKey = $authorId.'|'.$from->format('Y-m-d').'|'.$to->format('Y-m-d');

        if ($fresh || ! array_key_exists($memoKey, $this->cardMemo)) {
            $this->cardMemo[$memoKey] = $this->workItems->periodCards($authorId, $from, $to);
        }

        return $this->cardMemo[$memoKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteRow(PartyMeetingNote $note): array
    {
        $case = $note->relationLoaded('businessCase') ? $note->businessCase : null;
        $contact = $note->contact?->displayName();
        $proposals = $note->relationLoaded('proposals')
            ? $note->proposals->map(static fn ($proposal): string => (string) ($proposal->proposal_no ?: $proposal->title))->filter()->implode(', ')
            : '';
        $project = $case !== null && $case->relationLoaded('project') ? $case->project?->display_name : null;

        $meta = array_values(array_filter([
            $note->channel?->getLabel(),
            filled($contact) && $contact !== '-' ? __('report.sources.contact', ['name' => $contact]) : null,
            $case !== null ? trim(($case->caseCode()?->formatted_code ?? '').' '.$case->title) : null,
            $proposals !== '' ? __('report.sources.proposals', ['list' => $proposals]) : null,
            filled($project) ? __('report.sources.project', ['name' => $project]) : null,
        ], static fn ($part): bool => filled($part)));

        $nextOn = $note->next_action_on?->format('d.m.Y');
        $next = filled($note->next_action)
            ? trim((string) $note->next_action).($nextOn !== null ? ' ('.$nextOn.')' : '')
            : $nextOn;

        $title = trim((string) ($note->party?->display_name ?? ''));
        $subject = trim((string) ($note->subject ?? ''));

        return [
            'key' => ReportField::SOURCE_MEETING_NOTES.':'.(int) $note->getKey(),
            'included' => true,
            'date' => $note->noted_on?->format('Y-m-d'),
            'title' => mb_substr(trim($title.($subject !== '' ? ' — '.$subject : ''), ' —'), 0, 300),
            'meta' => $meta === [] ? null : mb_substr(implode(' · ', array_map('strval', $meta)), 0, 500),
            'text' => filled($note->note) ? mb_substr(trim((string) $note->note), 0, 4000) : null,
            'next' => filled($next) ? mb_substr((string) $next, 0, 500) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(Report $report): array
    {
        // Yazildigi gun (ReportSuggestionQueries tarih kurali).
        $date = $report->created_at?->copy()->timezone(DisplayTime::zone())->format('Y-m-d');
        $subject = $report->subjectLabel();

        $meta = array_values(array_filter([
            $report->templateName(),
            filled($subject) ? $report->subject_kind->getLabel().': '.$subject : null,
            $report->status->getLabel(),
        ], static fn ($part): bool => filled($part)));

        return [
            'key' => ReportField::SOURCE_REPORTS.':'.(int) $report->getKey(),
            'included' => true,
            'date' => $date,
            'title' => mb_substr(trim((string) $report->report_no.' · '.(string) $report->title, ' ·'), 0, 300),
            'meta' => mb_substr(implode(' · ', array_map('strval', $meta)), 0, 500),
            'text' => filled($report->summary) ? mb_substr(trim((string) $report->summary), 0, 4000) : null,
            'next' => null,
        ];
    }

    /**
     * Kayitli satirlar (anahtar => satir); donem verildiyse yalniz donem icindekiler.
     *
     * @param  array{0: Carbon, 1: Carbon}|null  $period
     * @return array<string, array<string, mixed>>
     */
    private function savedRows(mixed $value, ?array $period): array
    {
        if (! is_array($value)) {
            return [];
        }

        $from = $period !== null ? $period[0]->format('Y-m-d') : null;
        $to = $period !== null ? $period[1]->format('Y-m-d') : null;
        $rows = [];

        foreach ($value as $row) {
            if (! is_array($row) || ! is_string($row['key'] ?? null) || $row['key'] === '') {
                continue;
            }

            $date = is_string($row['date'] ?? null) ? substr($row['date'], 0, 10) : null;

            if ($from !== null && ($date === null || $date < $from || $date > $to)) {
                continue;
            }

            $rows[$row['key']] = [
                'key' => $row['key'],
                'included' => (bool) ($row['included'] ?? false),
                'date' => $date,
                'title' => (string) ($row['title'] ?? ''),
                'meta' => filled($row['meta'] ?? null) ? (string) $row['meta'] : null,
                'text' => filled($row['text'] ?? null) ? (string) $row['text'] : null,
                'next' => filled($row['next'] ?? null) ? (string) $row['next'] : null,
            ];
        }

        return $rows;
    }

    /**
     * Formdan gelen secim: anahtar listesi (ya da isaretli satirlar).
     *
     * @return list<string>
     */
    private function selectedKeys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $keys[] = $item;
            } elseif (is_array($item) && (bool) ($item['included'] ?? false) && is_string($item['key'] ?? null)) {
                $keys[] = $item['key'];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Tarihe, sonra anahtara gore sira.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function sorted(array $rows): array
    {
        uasort($rows, static fn (array $a, array $b): int => [(string) ($a['date'] ?? ''), (string) $a['key']] <=> [(string) ($b['date'] ?? ''), (string) $b['key']]);

        return $rows;
    }
}
