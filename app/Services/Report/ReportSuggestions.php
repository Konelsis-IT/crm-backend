<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Party\PartyMeetingNote;
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
 */
#[Scoped]
final class ReportSuggestions
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $rowMemo = [];

    /** @var array<string, Collection<int, WorkItem>> */
    private array $cardMemo = [];

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
        $project = $case !== null && $case->relationLoaded('project') ? $case->project?->name : null;

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
