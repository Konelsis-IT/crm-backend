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
 * Disa aktarma eylemleri (D-110, B35).
 *
 * - table(): liste sayfasi baslik eylemi, yalniz indirme simgesi (21 Eylul
 *   2026 kullanici karari: yaninda "Excel" yazmaz, onay penceresi yok, basinca
 *   dosya iner). Filament'in yerlesik Excel disa aktarimi; tablo ekranda
 *   gorundugu gibi yazilir: yalniz gorunen sutunlar, etkin suzgecler, arama,
 *   secili sekme (kart gorunumunde kart aramasi).
 * - record(): ayrinti sayfasi baslik eylemi grubu "Disa aktar": ayni
 *   sutunlarla tek kaydin Excel'i (Filament) ve PDF'i (dompdf); ikisi de
 *   basinca iner.
 */
final class ExportActions
{
    /**
     * @param  class-string<KonelsisExporter>  $exporter
     */
    public static function table(string $exporter): ExportAction
    {
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
            ->visible(fn (): bool => SchemaReadiness::hasBatch('B35'));
    }

    /**
     * @param  class-string<KonelsisExporter>  $exporter
     */
    public static function record(string $exporter): ActionGroup
    {
        return ActionGroup::make([
            ExportAction::make('exportRecordExcel')
                ->exporter($exporter)
                ->label(__('export.actions.record_excel'))
                ->icon(Heroicon::OutlinedTableCells)
                ->modal(false)
                ->columnMapping(false)
                ->modifyQueryUsing(fn (Builder $query, Model $record): Builder => $query->whereKey($record->getKey()))
                ->after(self::downloadFile($exporter))
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B35')),
            Action::make('exportRecordPdf')
                ->label(__('export.actions.record_pdf'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->action(fn (Model $record, ViewRecord $livewire): StreamedResponse => app(RecordPdf::class)->download(
                    $exporter,
                    $record,
                    $livewire::getResource()::getModelLabel(),
                    (string) $livewire->getRecordTitle(),
                )),
        ])
            ->label(__('export.actions.group'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->button();
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
