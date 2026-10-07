<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Report\Report;
use App\Reports\Formatting\ReportFormatter;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapora ozel Excel (D-167, 6 Ekim 2026 kullanici karari: "Excel'i raporda
 * nasil sunabiliriz bilmiyorum, sen bul").
 *
 * - "Rapor" sayfasi: kimlik satirlari (rapor no, tur, hazirlayan, donem,
 *   ilgili kayit, durum, tarihler, inceleyen) ve taslagin cevaplari (ozet,
 *   engeller, plan...), sayisal ozet ve inceleme notu; Alan | Deger.
 * - "Rapor satirlari" sayfasi: her is, gorusme, yazilan rapor ve plan
 *   satiri bir satir; sutunlar esit genislikte ve satir kaydirmali
 *   (Bolum | Tarih | Baslik | Ayrinti | Durum | Saat | Not), baslik sabit.
 *
 * Icerik ReportFormatter'dan gelir; ekrandaki ve PDF'teki metinle aynidir.
 * OpenSpout (Filament ile kurulu); kuyruk ve veritabani kaydi yok.
 */
final class ReportWorkbook
{
    /** "Rapor satirlari" sayfasinin esit sutun genisligi. */
    private const LINE_COLUMN_WIDTH = 32;

    public function __construct(private readonly ReportFormatter $formatter) {}

    public function download(Report $report): StreamedResponse
    {
        Gate::authorize('view', $report);

        $path = $this->write($report);

        return response()->streamDownload(
            static function () use ($path): void {
                readfile($path);
                @unlink($path);
            },
            ReportPdf::fileName($report, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** Gecici dosyaya yazar ve yolunu verir. */
    public function write(Report $report): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'konelsis-report-');
        $header = (new Style)->setFontBold()->setBackgroundColor('F3F4F6')->setCellVerticalAlignment(CellVerticalAlignment::TOP);
        $wrap = (new Style)->setShouldWrapText()->setCellVerticalAlignment(CellVerticalAlignment::TOP);
        $label = (new Style)->setFontBold()->setShouldWrapText()->setCellVerticalAlignment(CellVerticalAlignment::TOP);

        $writer = new Writer;
        $writer->openToFile($path);

        // 1. Rapor (ozet)
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName(__('report.export.summary_sheet')));
        $sheet->setColumnWidth(30, 1);
        $sheet->setColumnWidth(90, 2);

        $writer->addRow($this->row([(string) ($report->title ?: $report->templateName())], (new Style)->setFontBold()->setFontSize(14)));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow($this->row([__('report.export.field'), __('report.export.value')], $header));

        foreach ($this->formatter->facts($report) as $fact) {
            $writer->addRow($this->row([$fact['label'], $fact['value']], null, [$label, $wrap]));
        }

        foreach ($this->formatter->answers($report) as $answer) {
            $writer->addRow($this->row([$answer['label'], $answer['value']], null, [$label, $wrap]));
        }

        // 2. Rapor satirlari
        $lines = $writer->addNewSheetAndMakeItCurrent();
        $lines->setName($this->sheetName(__('report.export.lines_sheet')));
        $lines->setColumnWidthForRange(self::LINE_COLUMN_WIDTH, 1, 7);
        $lines->setSheetView((new SheetView)->setFreezeRow(2));

        $writer->addRow($this->row([
            __('report.export.columns.section'),
            __('report.export.columns.date'),
            __('report.export.columns.title'),
            __('report.export.columns.detail'),
            __('report.export.columns.status'),
            __('report.export.columns.hours'),
            __('report.export.columns.note'),
        ], $header));

        foreach ($this->formatter->lines($report) as $line) {
            $writer->addRow($this->row([
                $line['section'], $line['date'], $line['title'], $line['detail'], $line['status'], $line['hours'], $line['note'],
            ], $wrap));
        }

        $writer->close();

        return $path;
    }

    /**
     * Metin hucreleri her zaman metin yazilir: "=" ile baslayan kullanici
     * metni formul olmaz (formul enjeksiyonu yok).
     *
     * @param  list<string>  $values
     * @param  list<Style>  $columnStyles
     */
    private function row(array $values, ?Style $style = null, array $columnStyles = []): Row
    {
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $cells[] = new StringCell((string) $value, $columnStyles[$index] ?? null);
        }

        return new Row($cells, $style);
    }

    /** Excel sayfa adi en fazla 31 karakter, bazi isaretler yasak. */
    private function sheetName(string $name): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
    }
}
