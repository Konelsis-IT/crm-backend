<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Support\DisplayTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Departman panosundaki gorunen tablonun Excel ve PDF'i (D-173; ekrana ozel
 * cikti kurali D-167). Ekranda secili sutunlar ve suzgecler neyse dosyada o
 * vardir; degerler ekrandaki gibi bicimlidir.
 *
 * - Excel: OpenSpout (Filament ile kurulu). "Tablo" sayfasi esit genislikte
 *   sutunlar ve sabit baslik; ikinci sayfa suzgecler. Hucreler hep metin
 *   yazilir (formul enjeksiyonu yok), ReportWorkbook ile ayni yol.
 * - PDF: dompdf; rapor PDF sablonu (pdf.report) yeniden kullanilir, govdesi
 *   kacirilmis bir HTML tablosudur. Yatay A4.
 */
final class DashboardTableExport
{
    private const COLUMN_WIDTH = 22;

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, string>>  $rows
     * @param  list<array{label: string, value: string}>  $facts
     */
    public function xlsx(string $title, array $columns, array $rows, array $facts): StreamedResponse
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'konelsis-dash-');
        $header = (new Style)->setFontBold()->setBackgroundColor('F3F4F6')->setCellVerticalAlignment(CellVerticalAlignment::TOP);
        $wrap = (new Style)->setShouldWrapText()->setCellVerticalAlignment(CellVerticalAlignment::TOP);

        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName($title));
        $sheet->setColumnWidthForRange(self::COLUMN_WIDTH, 1, max(1, count($columns)));
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        $writer->addRow($this->row(array_map(static fn (array $column): string => $column['label'], $columns), $header));

        foreach ($rows as $row) {
            $writer->addRow($this->row(array_map(static fn (array $column): string => (string) ($row[$column['key']] ?? ''), $columns), $wrap));
        }

        $info = $writer->addNewSheetAndMakeItCurrent();
        $info->setName($this->sheetName(__('dashboards.export.columns')));
        $info->setColumnWidth(28, 1);
        $info->setColumnWidth(70, 2);

        foreach ($facts as $fact) {
            $writer->addRow($this->row([$fact['label'], $fact['value']], $wrap));
        }

        $writer->close();

        return response()->streamDownload(
            static function () use ($path): void {
                readfile($path);
                @unlink($path);
            },
            $this->fileName($title, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, string>>  $rows
     * @param  list<array{label: string, value: string}>  $facts
     */
    public function pdf(string $title, array $columns, array $rows, array $facts): StreamedResponse
    {
        $exportedBy = (string) (auth()->user()?->full_name ?? '');
        $now = now()->timezone(DisplayTime::zone());

        $pdf = Pdf::loadView('pdf.report', [
            'company' => (string) config('konelsis.organization.name_tr', 'Konelsis'),
            'kind' => (string) __('dashboards.export.kind'),
            'title' => $title,
            'reportNo' => $now->format('d.m.Y'),
            'status' => (string) __('dashboards.export.rows', ['count' => count($rows)]),
            'author' => $exportedBy,
            'facts' => array_map(static fn (array $fact): array => ['key' => Str::slug($fact['label']), 'label' => $fact['label'], 'value' => $fact['value']], $facts),
            'body' => new HtmlString($this->tableHtml($columns, $rows)),
            'exportedBy' => $exportedBy,
            'exportedAt' => $now->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true);

        $bytes = (string) $pdf->output();

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            $this->fileName($title, 'pdf'),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Kacirilmis tablo HTML'i (her deger e() ile).
     *
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, string>>  $rows
     */
    private function tableHtml(array $columns, array $rows): string
    {
        $cell = 'border:1px solid #e5e7eb;padding:3px 5px;text-align:left;vertical-align:top;';
        $html = '<table style="width:100%;border-collapse:collapse;font-size:8.5px;"><thead><tr>';

        foreach ($columns as $column) {
            $html .= '<th style="'.$cell.'background:#f3f4f6;font-weight:bold;">'.e($column['label']).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $index => $row) {
            $html .= '<tr'.($index % 2 === 1 ? ' style="background:#fafafa;"' : '').'>';

            foreach ($columns as $column) {
                $html .= '<td style="'.$cell.'">'.e((string) ($row[$column['key']] ?? '')).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /**
     * @param  list<string>  $values
     */
    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(static fn (string $value): StringCell => new StringCell($value, null), array_values($values)), $style);
    }

    private function sheetName(string $name): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
    }

    private function fileName(string $title, string $extension): string
    {
        return Str::of(__('dashboards.export.kind').' '.$title.' '.now()->timezone(DisplayTime::zone())->format('Y-m-d'))
            ->slug()
            ->limit(90, '')
            ->append('.'.$extension)
            ->value();
    }
}
