<?php

declare(strict_types=1);

namespace App\Reports\Formatting;

use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportPeriodMode;
use App\Models\Report\Report;
use App\Models\Report\ReportItem;
use App\Models\Report\ReportMetric;
use App\Reports\ReportField;
use App\Reports\ReportFieldComponents;
use App\Reports\ReportTemplate;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\ReportSuggestions;
use App\Services\Report\WorkItemPresenter;
use App\Support\DisplayTime;
use Illuminate\Support\Collection;

/**
 * Raporun bicimli metni (D-167): rapor detayindaki "Rapor metni", Kopyala
 * (zengin metin + duz metin), rapora ozel PDF govdesi ve Excel satirlari
 * ayni kurallarla buradan uretilir; ekranda gorulen, kopyalanan ve PDF'e
 * aktarilan metin aynidir.
 *
 * Duzen: baslik ve kimlik satirlari (rapor no, tur, hazirlayan, donem, ilgili
 * kayit, durum, inceleyen); taslagin alanlari sirasiyla (kisa alanlar tek
 * "Ozet bilgiler" blogunda, uzun metinler kendi basliginda, plan satirlari
 * madde halinde); pano raporunda ozetten sonra isler (durum basliklari
 * altinda); gorusmeler ve yazilan raporlar; sayisal ozet; inceleme notu.
 *
 * D-179 (8 Ekim 2026 kullanici istegi: "kopyalanabilecek metin alaninin
 * icon'lari daha sade ve az olsun"): basliklarda, kimlik satirlarinda ve
 * durum basliklarinda simge (emoji) yok; metin yalniz baslik, kalin etiket
 * ve madde isaretiyle duzenlenir (WhatsApp ve e-postada sade okunur).
 */
final class ReportFormatter
{
    /**
     * Kimlik satirlari (anahtar, etiket, deger). Metin basligi, PDF bilgi
     * tablosu ve Excel ozet sayfasi ayni listeyi kullanir.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function facts(Report $report): array
    {
        $titles = SchemaReadiness::hasBatch('B26');
        $report->loadMissing([$titles ? 'author.title' : 'author', 'authorOrgUnit', 'reviewer']);
        $template = $report->template();
        $zone = DisplayTime::zone();

        $author = array_values(array_filter([
            $report->author?->full_name,
            $titles ? $report->author?->title?->name : null,
            $report->authorOrgUnit?->name,
        ], static fn ($part): bool => filled($part)));

        $reviewer = $report->reviewer?->full_name;

        if ($reviewer !== null && $report->reviewed_at !== null) {
            $reviewer .= ' · '.$report->reviewed_at->copy()->timezone($zone)->format('d.m.Y H:i');
        }

        $subject = $report->subjectLabel();

        $facts = [
            ['key' => 'report_no', 'label' => __('report.fields.report_no'), 'value' => (string) $report->report_no],
            ['key' => 'template', 'label' => __('report.text.type'), 'value' => $report->templateName()],
            ['key' => 'author', 'label' => __('report.text.author'), 'value' => implode(' · ', array_map('strval', $author))],
            ['key' => 'period', 'label' => $this->periodLabel($template), 'value' => (string) ($this->periodValue($report, $template) ?? '')],
            ['key' => 'subject', 'label' => __('report.fields.subject'), 'value' => filled($subject) ? $report->subject_kind->getLabel().': '.$subject : ''],
            ['key' => 'status', 'label' => __('report.fields.status'), 'value' => (string) $report->status->getLabel()],
            ['key' => 'created_at', 'label' => __('report.fields.created_at'), 'value' => DisplayTime::format($report->created_at, empty: '')],
            ['key' => 'submitted_at', 'label' => __('report.fields.submitted_at'), 'value' => DisplayTime::format($report->submitted_at, empty: '')],
            ['key' => 'reviewer', 'label' => __('report.fields.reviewer'), 'value' => (string) ($reviewer ?? '')],
        ];

        return array_values(array_filter($facts, static fn (array $fact): bool => $fact['value'] !== ''));
    }

    /**
     * Bicimli belge; $withHeader false ise baslik ve kimlik satirlari yazilmaz
     * (PDF bunlari kendi ust bilgisinde gosterir).
     */
    public function document(Report $report, bool $withHeader = true): FormattedText
    {
        $report->loadMissing([$this->itemRelation(), 'metrics']);
        $template = $report->template();
        $doc = new FormattedText;

        if ($withHeader) {
            $doc->heading(1, (string) ($report->title ?: $report->templateName()));
            $doc->facts(array_values(array_filter(
                $this->facts($report),
                static fn (array $fact): bool => ! in_array($fact['key'], ['created_at'], true),
            )));
        }

        if ($template === null) {
            return $doc->text(__('report.values.template_missing'));
        }

        $this->body($doc, $report, $template);

        return $doc;
    }

    public function markdown(Report $report): string
    {
        return $this->document($report)->markdown();
    }

    /**
     * Panoya kopyalanacak hali: zengin metin (satir ici bicimli HTML, e-posta
     * ve Word bicimi korur) ve duz metin.
     *
     * @return array{html: string, text: string}
     */
    public function clipboard(Report $report): array
    {
        $doc = $this->document($report);

        return ['html' => $doc->html(inlineStyles: true), 'text' => $doc->plain()];
    }

    /** PDF govdesi: ayni metin, sablonun stilleriyle. */
    public function pdfBody(Report $report): string
    {
        return $this->document($report, withHeader: false)->html(icons: false);
    }

    /**
     * Excel ozet sayfasindaki cevaplar: etiket => duz metin.
     *
     * @return list<array{label: string, value: string}>
     */
    public function answers(Report $report): array
    {
        $template = $report->template();

        if ($template === null) {
            return [];
        }

        $payload = $report->payload ?? [];
        $rows = [];

        foreach ($template->fieldList() as $field) {
            $value = $payload[$field->name] ?? null;
            $text = match (true) {
                $field->isSources() => ($count = count(ReportSuggestions::included($value))) > 0 ? __('report.export.source_count', ['count' => $count]) : null,
                $field->type === ReportField::LINES => ($lines = $this->lineList($value)) !== [] ? implode("\n", array_map(static fn (string $line): string => '• '.$line, $lines)) : null,
                $field->type === ReportField::KEY_VALUE => is_array($value) && $value !== []
                    ? implode("\n", array_map(static fn ($key, $item): string => $key.': '.(is_scalar($item) ? (string) $item : ''), array_keys($value), $value))
                    : null,
                $field->type === ReportField::LONG_TEXT => filled($value) ? trim((string) $value) : null,
                default => $this->scalar($template, $field, $value),
            };

            if (filled($text)) {
                $rows[] = ['label' => $template->label($field->name), 'value' => (string) $text];
            }
        }

        foreach ($this->metricRows($report, $template) as $metric) {
            $rows[] = ['label' => $metric['label'], 'value' => $metric['value']];
        }

        if (filled($report->review_comment)) {
            $rows[] = ['label' => __('report.fields.review_comment'), 'value' => (string) $report->review_comment];
        }

        return $rows;
    }

    /**
     * Excel "Rapor satirlari": her is, gorusme, yazilan rapor ve plan satiri bir satir.
     *
     * @return list<array{section: string, date: string, title: string, detail: string, status: string, hours: string, note: string}>
     */
    public function lines(Report $report): array
    {
        $report->loadMissing([$this->itemRelation()]);
        $template = $report->template();

        if ($template === null) {
            return [];
        }

        $payload = $report->payload ?? [];
        $rows = [];

        if ($template->hasItems()) {
            foreach ($this->orderedItems($report->items) as $item) {
                $rows[] = [
                    'section' => (string) __('report.text.works'),
                    'date' => (string) ($item->due_on?->format('d.m.Y') ?? ''),
                    'title' => (string) $item->title,
                    'detail' => implode(' · ', $this->itemMeta($item, withHours: false, withDue: false)),
                    'status' => (string) $item->status->getLabel(),
                    'hours' => (string) (ReportFieldComponents::formatNumber($item->work_hours) ?? ''),
                    'note' => (string) ($item->description ?? ''),
                ];
            }
        }

        foreach ($template->fieldList() as $field) {
            if ($field->isSources()) {
                foreach (ReportSuggestions::included($payload[$field->name] ?? null) as $row) {
                    $rows[] = [
                        'section' => $template->label($field->name),
                        'date' => (string) (ReportSuggestions::displayDate($row['date'] ?? null) ?? ''),
                        'title' => (string) ($row['title'] ?? ''),
                        'detail' => (string) ($row['meta'] ?? ''),
                        'status' => '',
                        'hours' => '',
                        'note' => trim(implode("\n", array_filter([
                            filled($row['text'] ?? null) ? (string) $row['text'] : null,
                            filled($row['next'] ?? null) ? __('report.text.next_step').': '.$row['next'] : null,
                        ]))),
                    ];
                }
            } elseif ($field->type === ReportField::LINES) {
                foreach ($this->lineList($payload[$field->name] ?? null) as $line) {
                    $rows[] = ['section' => $template->label($field->name), 'date' => '', 'title' => $line, 'detail' => '', 'status' => '', 'hours' => '', 'note' => ''];
                }
            }
        }

        return $rows;
    }

    private function body(FormattedText $doc, Report $report, ReportTemplate $template): void
    {
        $payload = $report->payload ?? [];
        $fields = $template->fieldList();
        $summaryField = null;

        foreach ($fields as $field) {
            if ($field->summary) {
                $summaryField = $field->name;

                break;
            }
        }

        $scalars = [];

        foreach ($fields as $field) {
            if ($this->isScalar($field) && filled($text = $this->scalar($template, $field, $payload[$field->name] ?? null))) {
                $scalars[] = ['label' => $template->label($field->name), 'value' => (string) $text];
            }
        }

        $itemsPlaced = ! $template->hasItems();
        $scalarsPlaced = $scalars === [];
        $multiDay = $report->period_start !== null && $report->period_end !== null && ! $report->period_start->isSameDay($report->period_end);

        if (! $itemsPlaced && $summaryField === null) {
            $this->items($doc, $report);
            $itemsPlaced = true;
        }

        foreach ($fields as $field) {
            $value = $payload[$field->name] ?? null;
            $label = $template->label($field->name);

            if ($this->isScalar($field)) {
                if (! $scalarsPlaced) {
                    $doc->heading(2, __('report.text.key_facts'))->facts($scalars);
                    $scalarsPlaced = true;
                }

                continue;
            }

            if ($field->isSources()) {
                $rows = ReportSuggestions::included($value);

                if ($rows !== []) {
                    $doc->heading(2, $label.' ('.count($rows).')')->bullets(array_map(
                        static fn (array $row): array => [
                            'title' => (string) ($row['title'] ?? ''),
                            'rest' => $multiDay ? ReportSuggestions::displayDate($row['date'] ?? null) : null,
                            'detail' => $row['meta'] ?? null,
                            'quote' => $row['text'] ?? null,
                            'note_label' => filled($row['next'] ?? null) ? __('report.text.next_step') : null,
                            'note' => $row['next'] ?? null,
                        ],
                        $rows,
                    ));
                }
            } elseif ($field->type === ReportField::LINES) {
                $lines = $this->lineList($value);

                if ($lines !== []) {
                    $doc->heading(2, $label)->bullets(array_map(static fn (string $line): array => ['rest' => $line], $lines));
                }
            } elseif ($field->type === ReportField::KEY_VALUE) {
                if (is_array($value) && $value !== []) {
                    $doc->heading(2, $label)->bullets(array_map(
                        static fn ($key, $item): array => ['title' => (string) $key, 'rest' => is_scalar($item) ? (string) $item : null],
                        array_keys($value),
                        $value,
                    ));
                }
            } elseif (filled($value)) {
                $doc->heading(2, $label)->text((string) $value);
            }

            if (! $itemsPlaced && $field->name === $summaryField) {
                $this->items($doc, $report);
                $itemsPlaced = true;
            }
        }

        $metrics = $this->metricRows($report, $template);

        if ($metrics !== []) {
            $doc->heading(2, __('report.sections.metrics'))->facts($metrics);
        }

        if (filled($report->review_comment)) {
            $doc->heading(2, __('report.fields.review_comment'))->text((string) $report->review_comment);
        }
    }

    /** Pano kalemleri: durum basliklari altinda madde listesi. */
    private function items(FormattedText $doc, Report $report): void
    {
        $items = $report->items;

        if ($items->isEmpty()) {
            return;
        }

        $doc->heading(2, __('report.text.works').' ('.$items->count().')');

        foreach ($this->statusOrder() as $status) {
            $rows = $items->filter(static fn (ReportItem $item): bool => $item->status === $status)->values();

            if ($rows->isEmpty()) {
                continue;
            }

            $doc->heading(3, $status->getLabel().' ('.$rows->count().')')
                ->bullets($rows->map(fn (ReportItem $item): array => [
                    'title' => (string) $item->title,
                    'rest' => ($meta = $this->itemMeta($item)) !== [] ? implode(' · ', $meta) : null,
                    'detail' => filled($item->description) ? (string) $item->description : null,
                ])->all());
        }
    }

    /**
     * @return list<string>
     */
    private function itemMeta(ReportItem $item, bool $withHours = true, bool $withDue = true): array
    {
        $meta = [];

        if ($item->project !== null) {
            $meta[] = (string) $item->project->display_name;
        }

        if ($withHours && ($hours = ReportFieldComponents::formatNumber($item->work_hours, __('report.values.hours'))) !== null) {
            $meta[] = $hours;
        }

        if ($withDue && $item->due_on !== null) {
            $meta[] = __('report.text.due', ['date' => $item->due_on->format('d.m.Y')]);
        }

        if ((bool) $item->getAttribute('is_late')) {
            $meta[] = (string) __('report.values.added_late');
        } elseif ($item->carried_from_item_id !== null) {
            $meta[] = (string) __('report.values.carried_over');
        }

        return $meta;
    }

    /**
     * @param  Collection<int, ReportItem>  $items
     * @return list<ReportItem>
     */
    private function orderedItems(Collection $items): array
    {
        $ordered = [];

        foreach ($this->statusOrder() as $status) {
            foreach ($items as $item) {
                if ($item->status === $status) {
                    $ordered[] = $item;
                }
            }
        }

        return $ordered;
    }

    /**
     * Rapordaki durum sirasi: tamamlanan, suren, beklenen, iptal, planlanan.
     *
     * @return list<ReportItemStatus>
     */
    private function statusOrder(): array
    {
        return [ReportItemStatus::Done, ReportItemStatus::InProgress, ReportItemStatus::Waiting, ReportItemStatus::Blocked, ReportItemStatus::Planned];
    }

    /**
     * Sayisal ozet: gonderimde yazilan metrikler, taslakta taslagin hesabi.
     *
     * @return list<array{label: string, value: string}>
     */
    private function metricRows(Report $report, ReportTemplate $template): array
    {
        $values = $report->metrics->isNotEmpty()
            ? $report->metrics->mapWithKeys(static fn (ReportMetric $metric): array => [(string) $metric->metric_code => $metric->metric_value])->all()
            : $template->metrics($report->payload ?? [], $report->items);

        $rows = [];

        foreach ($values as $code => $value) {
            $formatted = ReportFieldComponents::formatNumber($value, $template->metricUnit((string) $code));

            if ($formatted !== null) {
                $rows[] = ['label' => $template->metricLabel((string) $code), 'value' => $formatted];
            }
        }

        return $rows;
    }

    /** Proje tablosu (B17) yoksa kalemlerin projesi yuklenmez. */
    private function itemRelation(): string
    {
        return SchemaReadiness::hasBatch('B17') ? 'items.project' : 'items';
    }

    private function isScalar(ReportField $field): bool
    {
        return ! in_array($field->type, [ReportField::LONG_TEXT, ReportField::LINES, ReportField::KEY_VALUE, ReportField::SOURCES], true);
    }

    private function scalar(ReportTemplate $template, ReportField $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        return match ($field->type) {
            ReportField::INTEGER, ReportField::DECIMAL, ReportField::PERCENT => ReportFieldComponents::formatNumber($value, $field->suffix),
            ReportField::RATING => ReportFieldComponents::ratingLabel($value),
            ReportField::CHOICE => $template->optionLabel($field->name, (string) $value),
            ReportField::BOOLEAN => (string) __((bool) $value ? 'export.values.yes' : 'export.values.no'),
            ReportField::DATE => ReportSuggestions::displayDate((string) $value) ?? (string) $value,
            default => trim((string) $value),
        };
    }

    /**
     * Cok satirli plan metni -> satirlar (madde isaretleri atilir).
     *
     * @return list<string>
     */
    private function lineList(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $lines = [];

        foreach (preg_split('/\r?\n/', $value) ?: [] as $line) {
            $line = trim($line, " \t-•*");

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function periodLabel(?ReportTemplate $template): string
    {
        return match ($template?->periodMode()) {
            ReportPeriodMode::Day => __('report.fields.period_day'),
            ReportPeriodMode::Week => __('report.fields.period_week'),
            ReportPeriodMode::Month => __('report.fields.period_month'),
            default => __('report.fields.period'),
        };
    }

    private function periodValue(Report $report, ?ReportTemplate $template): ?string
    {
        $start = $report->period_start;

        if ($start === null) {
            return $report->periodLabel();
        }

        return match ($template?->periodMode()) {
            ReportPeriodMode::Day => WorkItemPresenter::dayLabel($start->copy()),
            ReportPeriodMode::Week => $report->periodLabel().' · '.__('report.text.week_no', ['week' => (int) $start->isoWeek()]),
            ReportPeriodMode::Month => $start->copy()->locale(app()->getLocale())->translatedFormat('F Y'),
            default => $report->periodLabel(),
        };
    }
}
