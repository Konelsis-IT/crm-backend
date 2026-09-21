<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use Carbon\CarbonInterface;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Number;
use OpenSpout\Common\Entity\Style\Style;
use UnitEnum;

/**
 * Konelsis disa aktariminin temeli (D-110, 21 Eylul 2026 kullanici karari:
 * "Filament'in kendi tablo Excel disa aktarimini kullan").
 *
 * - Kuyruksuz (sync): dosya istek sirasinda hazirlanir ve dogrudan iner
 *   (onay penceresi yok); bildirim yalniz aktarilamayan satir varsa cikar
 *   (KonelsisExportCompletion). Sunucuda kuyruk iscisi gerekmez.
 * - Yalniz Excel (XLSX); baslik satiri kalin. Metin sutunlari "=" ile
 *   baslayan girdiyi formul olarak degil metin olarak yazar.
 * - Sutunlar tablodaki sutunlarla ayni yazilir ("tablo gorundugu gibi"):
 *   durumlar Turkce etiketiyle, tarihler gun.ay.yil, evet/hayir okunur.
 *   Ayni sutunlar ayrinti sayfasindaki Excel ve PDF'te de kullanilir
 *   (RecordPdf).
 */
abstract class KonelsisExporter extends Exporter
{
    /** Dosya adi on eki (or. "taraflar"); dil dosyasindan. */
    abstract public static function fileLabel(): string;

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    /**
     * @return array<ExportFormat>
     */
    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getFileName(Export $export): string
    {
        return str(static::fileLabel())->slug()->append('-'.now()->format('Y-m-d').'-'.$export->getKey())->value();
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)->setFontBold();
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return __('export.notifications.completed_title', ['name' => static::fileLabel()]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('export.notifications.completed_body', ['count' => Number::format($export->successful_rows)]);

        if (($failed = $export->getFailedRowsCount()) > 0) {
            $body .= ' '.__('export.notifications.failed_rows', ['count' => Number::format($failed)]);
        }

        return $body;
    }

    /** Metin sutunu: durum etiketleri ve listeler okunur hale getirilir. */
    protected static function text(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->preventFormulaInjection()
            ->formatStateUsing(fn (mixed $state): mixed => self::display($state));
    }

    protected static function date(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (mixed $state): ?string => $state instanceof CarbonInterface ? $state->format('d.m.Y') : self::display($state));
    }

    protected static function dateTime(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (mixed $state): ?string => $state instanceof CarbonInterface
                ? $state->timezone(config('app.timezone'))->format('d.m.Y H:i')
                : self::display($state));
    }

    protected static function boolean(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (mixed $state): string => $state ? __('export.values.yes') : __('export.values.no'));
    }

    /** Tutar: tablodaki gibi iki basamak, Turkce ayraclarla (1.234,50). */
    protected static function decimal(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state) ? number_format((float) $state, 2, ',', '.') : null);
    }

    /** Ekranda gorunen deger: enum etiketi, sayi, metin. */
    public static function display(mixed $state): mixed
    {
        if ($state instanceof HasLabel) {
            return (string) $state->getLabel();
        }

        if ($state instanceof UnitEnum) {
            return $state instanceof \BackedEnum ? (string) $state->value : $state->name;
        }

        if ($state instanceof CarbonInterface) {
            return $state->format('d.m.Y');
        }

        return $state;
    }
}
