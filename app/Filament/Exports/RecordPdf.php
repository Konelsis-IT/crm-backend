<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ayrinti sayfasinin PDF'i (D-110): kaydin disa aktarma sutunlari "Alan -
 * Deger" tablosu olarak basilir; sutunlar ve degerler Excel ile aynidir
 * (tek kaynak: ilgili KonelsisExporter). dompdf + DejaVu Sans (Turkce).
 */
final class RecordPdf
{
    /**
     * @param  class-string<KonelsisExporter>  $exporter
     */
    public function download(string $exporter, Model $record, string $heading, string $title): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.record', [
            'heading' => $heading,
            'title' => $title,
            'rows' => $this->rows($exporter, $record),
            'exportedBy' => (string) (auth()->user()?->full_name ?? ''),
            'exportedAt' => now()->timezone(config('app.timezone'))->format('d.m.Y H:i'),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);

        $fileName = str($exporter::fileLabel().' '.$title)->slug()->limit(80, '')->append('.pdf')->value();

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->output();
            },
            $fileName,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Etiket => deger (bos degerler "-").
     *
     * @param  class-string<KonelsisExporter>  $exporter
     * @return array<string, string>
     */
    public function rows(string $exporter, Model $record): array
    {
        $columnMap = collect($exporter::getVisibleColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => (string) $column->getLabel()])
            ->all();

        $instance = new $exporter(new Export, $columnMap, []);

        foreach ($instance->getCachedColumns() as $column) {
            $column->preventFormulaInjection(false);
        }

        $values = $instance($record);

        return collect(array_values($columnMap))
            ->mapWithKeys(fn (string $label, int $index): array => [$label => filled($values[$index] ?? null) ? (string) $values[$index] : '-'])
            ->all();
    }
}
