<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Enums\Platform\Feature;
use App\Filament\Exports\ReportExporter;
use App\Filament\Exports\ReportPdf;
use App\Filament\Exports\ReportWorkbook;
use App\Filament\Support\ActionColors;
use App\Filament\Support\ExportActions;
use App\Filament\Support\ExportSwitches;
use App\Models\Report\Report;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapor ekranlarinin ortak eylemleri (D-167):
 *
 * - copy(): raporun bicimli metnini panoya zengin metin (HTML) + duz metin
 *   olarak kopyalar; e-postaya / Word'e yapistirinca baslik, madde ve kalin
 *   yazilar korunur. Tarayici betigi resources/js/report-copy.js (kullanici
 *   onayi 6 Ekim 2026: Filament'in copyable() yalniz duz metin kopyalar).
 *   Sayfa CopiesReportText kullanmalidir.
 * - exports() / rowExports(): rapora ozel PDF ve Excel; Raporlar arayuzunun
 *   kendi anahtarlariyla (reports.exports.*), genel disa aktarimdan bagimsiz.
 */
final class ReportActions
{
    public static function switches(): ExportSwitches
    {
        return ExportSwitches::for(Feature::ReportExcel, Feature::ReportPdf);
    }

    public static function copy(): Action
    {
        return Action::make('copyReportText')
            ->label(__('report.actions.copy'))
            ->tooltip(__('report.help.copy'))
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color(ActionColors::NEUTRAL)
            ->alpineClickHandler(fn (Report $record): string => sprintf(
                'window.konelsisReportCopy && window.konelsisReportCopy.copy(() => $wire.reportCopyPayload(%d), %s)',
                (int) $record->getKey(),
                Js::from([
                    'ok' => (string) __('report.messages.copied'),
                    'fail' => (string) __('report.messages.copy_failed'),
                ])->toHtml(),
            ));
    }

    /** Rapor detayi: "Disa aktar" grubu (PDF, Excel). */
    public static function exports(): ActionGroup
    {
        return ExportActions::record(
            ReportExporter::class,
            self::switches(),
            pdfUsing: static fn (Report $record): StreamedResponse => app(ReportPdf::class)->download($record),
            excelUsing: static fn (Report $record): StreamedResponse => app(ReportWorkbook::class)->download($record),
        );
    }

    /** Rapor listesi satiri: yalniz simgeli "Disa aktar" grubu. */
    public static function rowExports(): ActionGroup
    {
        return ExportActions::row(
            self::switches(),
            static fn (Report $record): StreamedResponse => app(ReportPdf::class)->download($record),
            static fn (Report $record): StreamedResponse => app(ReportWorkbook::class)->download($record),
        );
    }
}
