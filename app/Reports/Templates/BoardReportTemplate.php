<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportPresentation;
use App\Models\Report\ReportItem;
use App\Reports\ReportTemplate;
use Illuminate\Support\Collection;

/**
 * Pano tipli taslaklarin ortak govdesi (gunluk / haftalik / aylik calisma):
 * is kalemleri durum sutunlarinda gosterilir, toplam saat ve tamamlanan /
 * acik kalem sayilari metrik olarak yazilir.
 */
abstract class BoardReportTemplate extends ReportTemplate
{
    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Board;
    }

    /**
     * 23 Eylul 2026 (kullanici karari, D-117): gunluk / haftalik / aylik
     * calisma raporu artik Raporlar > Rapor olustur ekranindan secilmez;
     * bu raporlari Is panosu uretir (Gunu kapat / Haftayi kapat).
     */
    public function isManualEntry(): bool
    {
        return false;
    }

    /** Calisma ozeti: is panosu onerisi ve rapor onerisi olmaz (D-167). */
    public function summarisesWork(): bool
    {
        return true;
    }

    /**
     * Pano metrikleri + rapora giren kaynak satiri sayilari (D-167: gorusme,
     * yazilan rapor; metrik kodu alanin adidir).
     *
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, ReportItem>|null  $items
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $items ??= new Collection;
        $sources = [];

        foreach ($this->sourceFields() as $field) {
            $rows = is_array($payload[$field->name] ?? null) ? $payload[$field->name] : [];
            $count = count(array_filter($rows, static fn ($row): bool => is_array($row) && (bool) ($row['included'] ?? false)));

            if ($count > 0) {
                $sources[$field->name] = (float) $count;
            }
        }

        return [
            ...parent::metrics($payload, $items),
            'total_hours' => (float) $items->sum(fn (ReportItem $item): float => (float) ($item->work_hours ?? 0)),
            'done_count' => (float) $items->filter(fn (ReportItem $item): bool => $item->status === ReportItemStatus::Done)->count(),
            'open_count' => (float) $items->filter(fn (ReportItem $item): bool => $item->status !== ReportItemStatus::Done)->count(),
            ...$sources,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function extraMetricUnits(): array
    {
        $units = ['total_hours' => 'saat', 'done_count' => 'adet', 'open_count' => 'adet'];

        foreach ($this->sourceFields() as $field) {
            $units[$field->name] = 'adet';
        }

        return $units;
    }
}
