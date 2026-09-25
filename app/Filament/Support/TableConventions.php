<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Personnel\Personnel;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Tablo kurallari (D-125, 25 Eylul 2026 kullanici karari: "Bunu artik bir
 * kural olarak belirleyeceksin. Her tablo bu mantikta calisacak"). Butun
 * tablolara, alt tablolar (relation manager) ve widget tablolari dahil,
 * tek noktadan uygulanir (AppServiceProvider):
 *
 *  1. Satira tiklayinca kaydin detayi acilir; "Goruntule" / "Ac" dugmesi
 *     gosterilmez. Detay sayfasi olmayan kayitta (orn. hareket kaydi) ayni
 *     eylem pencere olarak satir tiklamasiyla acilmaya devam eder.
 *  2. Satirdaki eylemler (Duzenle, Arsivle, Sil...) yalniz simgedir; ad
 *     ustune gelince ipucu olarak yazar.
 *  3. Bagli kayit sutunu ("personnel.full_name", "proposal.title"...)
 *     tiklanabilir ve kaydin turunun simgesini gosterir; detay bilgi
 *     alanlarinda (infolist) da ayni.
 *  4. Personel adinin yaninda her zaman kisi simgesi vardir.
 *
 * Tek tek tabloda acikca verilen ayar (url, icon, recordUrl) bu kurali
 * ezer; kural yalniz varsayilani belirler.
 */
final class TableConventions
{
    /** "Goruntule / Ac" dugmesini gizleyen sinif (konelsis.css). */
    public const HIDDEN_ACTION_CLASS = 'kc-row-action-hidden';

    public static function register(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->recordUrl(fn (Model $record, Table $table): ?string => self::rowUrl($record, $table))
                ->modifyUngroupedRecordActionsUsing(fn (Action $action) => self::recordAction($action));
        });

        TextColumn::configureUsing(function (TextColumn $column): void {
            $column
                ->url(fn (TextColumn $column, ?Model $record): ?string => $record instanceof Model ? self::relatedUrl($record, $column->getName()) : null)
                ->icon(fn (TextColumn $column, ?Model $record) => $record instanceof Model ? self::icon($record, $column->getName()) : null);
        });

        TextEntry::configureUsing(function (TextEntry $entry): void {
            $entry
                ->url(fn (TextEntry $component, ?Model $record): ?string => $record instanceof Model ? self::relatedUrl($record, $component->getName()) : null)
                ->icon(fn (TextEntry $component, ?Model $record) => $record instanceof Model ? self::icon($record, $component->getName()) : null);
        });
    }

    /** Satirin detayi: alt tabloda iliskili resource, listede sayfanin resource'u. */
    public static function rowUrl(Model $record, Table $table): ?string
    {
        $livewire = $table->getLivewire();
        $resource = null;

        if ($livewire instanceof RelationManager) {
            $resource = $livewire::getRelatedResource();
        } elseif (is_object($livewire) && method_exists($livewire, 'getResource')) {
            try {
                $candidate = $livewire::getResource();
                $resource = $candidate::getModel() === $record::class ? $candidate : null;
            } catch (Throwable) {
                $resource = null;
            }
        }

        return RecordLinks::detailUrl($record, $resource);
    }

    /** Satir eylemi: Goruntule / Ac gizlenir, digerleri simge + ipucu olur. */
    public static function recordAction(Action $action): void
    {
        if ($action instanceof ViewAction || in_array($action->getName(), ['view', 'open'], true)) {
            $action->extraAttributes(['class' => self::HIDDEN_ACTION_CLASS], merge: true);

            return;
        }

        if (! self::hasIcon($action)) {
            // Simgesi olmayan eylem adiyla kalir (bos dugme cizilmesin).
            return;
        }

        $action->iconButton();

        if ((fn () => $this->tooltip)->call($action) === null) {
            $action->tooltip(fn (Action $action): ?string => $action->getLabel());
        }
    }

    private static function hasIcon(Action $action): bool
    {
        try {
            return ($action->getTableIcon() ?? $action->getIcon()) !== null;
        } catch (Throwable) {
            // Simge kayda bagli bir kapanissa (kayit henuz yok) var sayilir.
            return true;
        }
    }

    private static function relatedUrl(Model $record, string $name): ?string
    {
        $related = RecordLinks::related($record, $name);

        return $related instanceof Model ? RecordLinks::detailUrl($related, checkRecord: false) : null;
    }

    private static function icon(Model $record, string $name): mixed
    {
        $related = RecordLinks::related($record, $name);

        if ($related instanceof Model) {
            return RecordLinks::iconFor($related);
        }

        // Kaydin kendisi personel ve ad alani.
        if ($record instanceof Personnel && in_array($name, ['full_name', 'name', 'display_name'], true)) {
            return RecordLinks::PERSONNEL_ICON;
        }

        return null;
    }
}
