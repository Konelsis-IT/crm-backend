<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Exports\KonelsisExporter;
use App\Filament\Exports\RecordPdf;
use App\Query\Export\ExportQueries;
use App\Services\Platform\SchemaReadiness;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Disa aktarma eylemleri (D-110, B35; arayuz bazli acma D-167).
 *
 * - table(): liste sayfasi baslik eylemi, yalniz indirme simgesi (21 Eylul
 *   2026 kullanici karari: yaninda "Excel" yazmaz, onay penceresi yok, basinca
 *   dosya iner). Filament'in yerlesik Excel disa aktarimi; tablo ekranda
 *   gorundugu gibi yazilir: yalniz gorunen sutunlar, etkin suzgecler, arama,
 *   secili sekme (kart gorunumunde kart aramasi).
 * - record(): ayrinti sayfasi baslik eylemi grubu "Disa aktar": ayni
 *   sutunlarla tek kaydin Excel'i (Filament) ve PDF'i (dompdf); ikisi de
 *   basinca iner. Arayuz kendi dosya ureticilerini verebilir ($pdfUsing,
 *   $excelUsing; or. rapora ozel PDF ve Excel).
 * - row(): tablo satiri icin yalniz simgeli "Disa aktar" grubu (PDF / Excel),
 *   arayuzun kendi dosya ureticileriyle.
 *
 * Anahtarlar (D-167): her cagri ExportSwitches alir. Verilmezse genel anahtar
 * (tools.exports.*) gecerlidir; bugunku butun ekranlar boyle calisir. Bir
 * arayuz acildiginda kendi anahtarlarini verir (ilk: Raporlar,
 * reports.exports.*) ve genel anahtardan bagimsiz acilir / kapanir.
 */
final class ExportActions
{
    /**
     * @param  class-string<KonelsisExporter>  $exporter
     */
    public static function table(string $exporter, ?ExportSwitches $switches = null): ExportAction
    {
        $switches ??= ExportSwitches::general();

        return ExportAction::make('exportExcel')
            ->exporter($exporter)
            ->label(__('export.actions.excel'))
            ->tooltip(__('export.actions.excel_tooltip'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->iconButton()
            ->color('gray')
            ->modal(false)
            ->columnMapping(false)
            ->enableVisibleTableColumnsByDefault()
            ->modifyQueryUsing(fn (Builder $query, Component $livewire): Builder => method_exists($livewire, 'scopeExportQuery')
                ? $livewire->scopeExportQuery($query)
                : $query)
            ->after(self::downloadFile($exporter))
            ->visible(fn (): bool => $switches->excelEnabled() && SchemaReadiness::hasBatch('B35'));
    }

    /**
     * @param  class-string<KonelsisExporter>  $exporter
     * @param  (Closure(Model): StreamedResponse)|null  $pdfUsing  arayuze ozel PDF; yoksa RecordPdf
     * @param  (Closure(Model): StreamedResponse)|null  $excelUsing  arayuze ozel Excel; yoksa Filament disa aktarimi
     */
    public static function record(string $exporter, ?ExportSwitches $switches = null, ?Closure $pdfUsing = null, ?Closure $excelUsing = null): ActionGroup
    {
        $switches ??= ExportSwitches::general();

        $excel = $excelUsing !== null
            ? self::download('exportRecordExcel', __('export.actions.record_excel'), Heroicon::OutlinedTableCells, $excelUsing)
                ->visible(fn (): bool => $switches->excelEnabled())
            : ExportAction::make('exportRecordExcel')
                ->exporter($exporter)
                ->label(__('export.actions.record_excel'))
                ->icon(Heroicon::OutlinedTableCells)
                ->modal(false)
                ->columnMapping(false)
                ->modifyQueryUsing(fn (Builder $query, Model $record): Builder => $query->whereKey($record->getKey()))
                ->after(self::downloadFile($exporter))
                ->visible(fn (): bool => $switches->excelEnabled() && SchemaReadiness::hasBatch('B35'));

        $pdf = $pdfUsing !== null
            ? self::download('exportRecordPdf', __('export.actions.record_pdf'), Heroicon::OutlinedDocumentText, $pdfUsing)
            : Action::make('exportRecordPdf')
                ->label(__('export.actions.record_pdf'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->action(fn (Model $record, ViewRecord $livewire): StreamedResponse => app(RecordPdf::class)->download(
                    $exporter,
                    $record,
                    $livewire::getResource()::getModelLabel(),
                    (string) $livewire->getRecordTitle(),
                ));

        return ActionGroup::make([
            $excel,
            $pdf->visible(fn (): bool => $switches->pdfEnabled()),
        ])
            ->label(__('export.actions.group'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->button();
    }

    /**
     * Tablo satiri: yalniz simgeli grup (D-125), icinde etiketli PDF ve Excel.
     *
     * @param  Closure(Model): StreamedResponse  $pdfUsing
     * @param  Closure(Model): StreamedResponse  $excelUsing
     */
    public static function row(ExportSwitches $switches, Closure $pdfUsing, Closure $excelUsing): ActionGroup
    {
        return ActionGroup::make([
            self::download('exportRowPdf', __('export.actions.record_pdf'), Heroicon::OutlinedDocumentText, $pdfUsing)
                ->visible(fn (): bool => $switches->pdfEnabled()),
            self::download('exportRowExcel', __('export.actions.record_excel'), Heroicon::OutlinedTableCells, $excelUsing)
                ->visible(fn (): bool => $switches->excelEnabled()),
        ])
            ->label(__('export.actions.group'))
            ->tooltip(__('export.actions.group'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray');
    }

    /**
     * Dosyayi hemen indiren eylem (onay penceresi yok).
     *
     * @param  Closure(Model): StreamedResponse  $using
     */
    private static function download(string $name, string $label, Heroicon $icon, Closure $using): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->action(fn (Model $record): StreamedResponse => $using($record));
    }

    /**
     * Kuyruksuz disa aktarim eylem icinde biter; hazirlanan dosya tarayicida
     * hemen indirilir (Filament'in imzali indirme baglantisi, yalniz hazirlayan
     * personele acik).
     *
     * @param  class-string<KonelsisExporter>  $exporter
     */
    private static function downloadFile(string $exporter): Closure
    {
        return function (ExportAction $action, Component $livewire) use ($exporter): void {
            $guard = $action->getAuthGuard();
            $export = app(ExportQueries::class)->latestCompleted((int) auth($guard)->id(), $exporter);

            if ($export === null) {
                return;
            }

            $url = URL::signedRoute('filament.exports.download', [
                'authGuard' => $guard,
                'export' => $export,
                'format' => ExportFormat::Xlsx->value,
            ], absolute: false);

            $livewire->js('window.location.href = '.Js::from($url));
        };
    }
}
