<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Report\ReportPeriodMode;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use App\Reports\ReportTemplateRegistry;
use App\Services\Report\ReportSuggestions;
use App\Services\Report\WorkItemSentence;
use Illuminate\Support\Carbon;

/**
 * UI Deneme > Rapor yazma denemesi (D-240) yapilandirmasi. Rapor tipleri ve
 * alanlari gercek taslaklardan (ReportTemplateRegistry) okunur; deneme her
 * tipi kendi alanlariyla cizer, metin alanlari Akilli is ekle duzenleyicisiyle
 * (rozetli duz yazi) yazilir, rapordan cikan isler ayni yazma alaniyla eklenir.
 * Kayit yoktur (features.demo): veritabani yalniz okunur (donemin isleri,
 * gorusme notu ve rapor onerileri).
 */
final class ReportLabConfig
{
    /** Rapor turu -> konusu (secilen kayit rozeti). */
    private const SUBJECTS = [
        'project' => 'project',
        'proposal' => 'proposal',
        'business_case' => 'case',
        'personnel' => 'person',
        'product' => 'catalog',
    ];

    /** Rapor turu -> grup (sol liste). */
    private const GROUPS = [
        'daily' => 'work', 'weekly' => 'work', 'monthly' => 'work',
        'project' => 'record', 'proposal' => 'record', 'business_case' => 'record', 'product' => 'record', 'system' => 'record',
        'personnel' => 'people',
    ];

    /** Denemede gosterilen en fazla is / oneri. */
    private const LIMIT = 8;

    /** Donemde kayit yoksa ornek icin bakilan gun sayisi. */
    private const SAMPLE_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $quick = WorkAppConfig::quick();
        $board = WorkAppConfig::board();
        $labels = __('work_item.ui');
        $lab = __('ui_gallery.report_lab_ui');
        $templates = app(ReportTemplateRegistry::class)->all();
        $user = auth()->user();

        return [
            ...$quick,
            'app' => 'report-lab',
            'labels' => [...(is_array($labels) ? $labels : []), ...(is_array($lab) ? $lab : [])],
            // Kayit uclari yok: oneriler ve arama yalniz okunur.
            'endpoints' => [
                'board' => $board['endpoints']['board'] ?? null,
                'quick_options' => $quick['endpoints']['quick_options'] ?? null,
                'quick_search' => $quick['endpoints']['quick_search'] ?? null,
            ],
            'features' => [...($quick['features'] ?? []), 'demo' => true, 'voice' => false],
            'types' => array_values(array_map(self::type(...), $templates)),
            'periods' => $user instanceof Personnel ? self::periods($user, $templates) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function type(ReportTemplate $template): array
    {
        $kind = $template->kind();

        return [
            'code' => $template->code(),
            'kind' => $kind->value,
            'kind_label' => (string) $kind->getLabel(),
            'group' => $template->isManualEntry() ? (self::GROUPS[$kind->value] ?? 'record') : 'system',
            'name' => $template->name(),
            'description' => $template->description(),
            'subject' => self::SUBJECTS[$kind->value] ?? null,
            'period' => $template->periodMode()->value,
            'board' => $template->summarisesWork(),
            'manual' => $template->isManualEntry(),
            'confidential' => $template->isConfidential(),
            'fields' => array_map(static fn (ReportField $field): array => [
                'name' => $field->name,
                'type' => $field->type,
                'label' => $template->label($field->name),
                'help' => $template->help($field->name),
                'required' => $field->required,
                'rows' => $field->rows,
                'min' => $field->min,
                'max' => $field->max,
                'suffix' => $field->suffix,
                'unit' => $field->unit,
                'source' => $field->source,
                'options' => array_map(
                    static fn (mixed $value): array => ['value' => (string) $value, 'label' => $template->optionLabel($field->name, (string) $value)],
                    array_values($field->options),
                ),
            ], $template->fieldList()),
        ];
    }

    /**
     * Gunluk / haftalik / aylik donemin etiketi, kisinin o donemdeki isleri
     * (okunus) ve kaynak onerileri. Donemde oneri yoksa son 30 gunden ornek
     * gelir ve `sample` ile isaretlenir (deneme bos gorunmesin).
     *
     * @param  array<int|string, ReportTemplate>  $templates
     * @return array<string, array<string, mixed>>
     */
    private static function periods(Personnel $me, array $templates): array
    {
        $today = WorkItemQueries::today();
        $suggestions = app(ReportSuggestions::class);
        $sentence = app(WorkItemSentence::class);
        $queries = app(WorkItemQueries::class);
        $out = [];

        foreach ([ReportPeriodMode::Day, ReportPeriodMode::Week, ReportPeriodMode::Month] as $mode) {
            [$from, $to] = $mode->bounds($today);
            $sources = [];

            foreach ($templates as $template) {
                if ($template->periodMode() !== $mode) {
                    continue;
                }

                foreach ($template->sourceFields() as $field) {
                    $sources[(string) $field->source] ??= self::sources($suggestions, $field, (int) $me->getKey(), $from, $to);
                }
            }

            $out[$mode->value] = [
                'label' => self::periodLabel($mode, $from, $to),
                'work' => $queries->periodCards((int) $me->getKey(), $from, $to)
                    ->take(self::LIMIT)
                    ->map(static fn (WorkItem $item): array => ['id' => (int) $item->getKey(), 'sentence' => $sentence->segments($item)])
                    ->values()
                    ->all(),
                'sources' => $sources,
            ];
        }

        return $out;
    }

    /**
     * @return array{sample: bool, rows: list<array<string, mixed>>}
     */
    private static function sources(ReportSuggestions $suggestions, ReportField $field, int $authorId, Carbon $from, Carbon $to): array
    {
        $rows = array_values($suggestions->rows($field, $authorId, $from, $to));
        $sample = false;

        if ($rows === []) {
            $rows = array_values($suggestions->rows($field, $authorId, $to->copy()->subDays(self::SAMPLE_DAYS), $to));
            $sample = $rows !== [];
        }

        return [
            'sample' => $sample,
            'rows' => array_map(static fn (array $row): array => [
                'key' => $row['key'],
                'date' => $row['date'] !== null ? Carbon::createFromFormat('Y-m-d', $row['date'])->format('d.m.Y') : null,
                'title' => $row['title'],
                'meta' => $row['meta'],
                'text' => $row['text'] !== null ? mb_substr((string) $row['text'], 0, 280) : null,
                'next' => $row['next'],
            ], array_slice($rows, -self::LIMIT)),
        ];
    }

    private static function periodLabel(ReportPeriodMode $mode, Carbon $from, Carbon $to): string
    {
        $from = $from->copy()->locale(app()->getLocale());
        $to = $to->copy()->locale(app()->getLocale());

        return match ($mode) {
            ReportPeriodMode::Week => $from->translatedFormat($from->month === $to->month ? 'j' : 'j F').' – '.$to->translatedFormat('j F Y'),
            ReportPeriodMode::Month => $from->translatedFormat('F Y'),
            default => $from->translatedFormat('j F Y, l'),
        };
    }
}
