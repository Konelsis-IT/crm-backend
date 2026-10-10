<?php

declare(strict_types=1);

namespace App\Filament\Support;

use BackedEnum;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

use function Filament\Support\generate_icon_html;

/**
 * Kompakt belge yukleme kutusu (D-185, 9 Ekim 2026 kullanici talebi: "bu yuklu
 * belgeler icin asiri kullanissiz bir dosya yukleme tasarimi gorunuyor; buna
 * cok daha guzel bir cozum bul").
 *
 * Her belge turu tek kucuk kutudur:
 * - ustte turun adi (gerekirse yaninda kucuk "KonelsisAI yakinda" etiketi);
 * - kayitli dosyalar kucuk notr ciplerdir: dosya turune gore simge, kirpilan
 *   ad (tam ad ipucunda), "Rev 01"; tiklaninca dosya iner;
 * - altinda kucuk "Yukle" dugmesi: Filament FileUpload'in birakma alani CSS ile
 *   dugmeye kuculur (resources/css/filament/konelsis.css, `.kc-upload`),
 *   surukle-birak calismaya devam eder. Dosyalar eskisi gibi formla kaydedilir
 *   (coklu dosya); sinir UploadLimits.
 *
 * D-186: teklif kutularinda revizyon yok (her dosya yeni belge); duzenleme
 * kutusunda kayitli dosyalar carpili cip (editSlot), goruntulemede ayni kutu
 * yukleme dugmesiz (viewSlot).
 */
final class CompactUpload
{
    public const SLOT_CLASS = 'kc-upload-slot';

    /**
     * Kutu: dosya cipleri + yukleme dugmesi (+ gizli ad alanlari).
     *
     * @param  list<Component>  $extra
     */
    public static function slot(TextEntry $files, FileUpload $upload, array $extra = []): Group
    {
        return Group::make([$files, self::upload($upload), ...$extra])
            ->extraAttributes(['class' => self::SLOT_CLASS]);
    }

    /**
     * Duzenleme kutusu (D-186, 9 Ekim 2026 kullanici talimati: "var olan
     * belgeleri de isterse carpi butonu ile kaldirip yeni surume yeni belgeleri
     * yukleyebilecektir"; ayni gun: "son yaptigin tasarim gayet guzeldi, bozma").
     * D-185 kutusunun aynisi: baslik, notr dosya cipleri, altinda kucuk "Yukle".
     * Tek ek: her cipin sag ucunda kucuk notr "x" (ipucu "Kaldir"). Basinca cip
     * soluk ve ustu cizili olur, "x" yerine "Geri al" simgesi gelir; isaret
     * gizli `$removedPath` alaninda (revizyon kimlikleri) tutulur ve kaydedince
     * uygulanir (sayfanin toggleProposalFile metodu, RemovesProposalFiles).
     * Teklif belgelerinde revizyon olmadigi icin ciplerde "Rev" yazmaz.
     *
     * @param  Closure(?Model): list<array{revision_id: int, title: string, revision: string|null, file: string|null, url: string|null}>  $infos
     * @param  list<Component>  $extra
     */
    public static function editSlot(string $removedPath, string|Htmlable $label, Closure $infos, FileUpload $upload, array $extra = []): Group
    {
        return Group::make([
            TextEntry::make($removedPath.'_chips')
                ->label($label)
                ->state(static fn (Get $get, ?Model $record): ?HtmlString => self::chips(
                    $infos($record),
                    withRevision: false,
                    removePath: $removedPath,
                    removed: array_map('intval', (array) $get($removedPath)),
                ))
                ->dehydrated(false),
            self::upload($upload),
            Hidden::make($removedPath)->default([]),
            ...$extra,
        ])->extraAttributes(['class' => self::SLOT_CLASS]);
    }

    /**
     * Goruntuleme kutusu (D-186): duzenleme kutusuyla ayni kutu ve baslik,
     * kayitli dosyalar cip; "Yukle" dugmesi yok.
     */
    public static function viewSlot(TextEntry $files): Group
    {
        return Group::make([$files->placeholder('-')])
            ->extraAttributes(['class' => self::SLOT_CLASS]);
    }

    /**
     * Kutunun basligi ve kayitli dosyalari. $infos kayittan DocumentLine::info
     * satirlarini verir (bos liste: yalniz baslik).
     *
     * @param  Closure(?Model): list<array{title: string, revision: string|null, file: string|null, url: string|null}>  $infos
     */
    public static function files(string $name, string|Htmlable $label, Closure $infos): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(static fn (?Model $record): ?HtmlString => self::chips($infos($record)))
            ->dehydrated(false);
    }

    /** FileUpload'i kucuk "Yukle" dugmesine cevirir; etiket kutunun basligindadir. */
    public static function upload(FileUpload $upload): FileUpload
    {
        return $upload
            ->hiddenLabel()
            ->placeholder(self::buttonHtml())
            ->extraAttributes(['class' => 'kc-upload'], merge: true);
    }

    /** Baslik + kucuk satir ici etiket (or. "KonelsisAI yakinda"). */
    public static function taggedLabel(string $label, string $tag, ?string $tooltip = null): HtmlString
    {
        $icon = self::icon(Heroicon::OutlinedSparkles);
        $title = $tooltip !== null ? ' title="'.e($tooltip).'"' : '';

        return new HtmlString(e($label).' <span class="kc-ai-tag"'.$title.'>'.$icon.e($tag).'</span>');
    }

    /**
     * Dosya cipleri; dosya yoksa null.
     *
     * $withRevision: "Rev 01" yazilsin mi (D-186: teklif belgelerinde hayir).
     * $removePath: verilirse (duzenleme) her cipin sag ucunda kucuk "x"; $removed
     * kaldirilmak uzere isaretlenen revizyon kimlikleridir (cip soluk, ustu
     * cizili, "Geri al" simgesi). Cipin `revision_id` anahtari olmalidir.
     *
     * @param  list<array{revision_id?: int, title: string, revision: string|null, file: string|null, url: string|null, preview?: string|null}>  $infos
     * @param  list<int>  $removed
     */
    public static function chips(array $infos, bool $withRevision = true, ?string $removePath = null, array $removed = []): ?HtmlString
    {
        $chips = [];

        foreach ($infos as $info) {
            $name = filled($info['file'] ?? null) ? (string) $info['file'] : (string) ($info['title'] ?? '-');
            $revision = $withRevision && filled($info['revision'] ?? null) ? __('document.short.revision', ['code' => $info['revision']]) : null;
            [$icon, $kind] = self::fileKind($name);
            $inner = self::icon($icon)
                .'<span class="kc-file-chip__name">'.e($name).'</span>'
                .($revision !== null ? '<span class="kc-file-chip__rev">'.e($revision).'</span>' : '');
            $title = e(DocumentLine::text($withRevision ? $info : [...$info, 'revision' => null], withTitle: true));
            $class = 'kc-file-chip kc-file-chip--'.$kind;
            $revisionId = isset($info['revision_id']) ? (int) $info['revision_id'] : null;
            $removable = $removePath !== null && $revisionId !== null && preg_match('/^[a-z0-9_.]+$/', $removePath) === 1;
            $isRemoved = $removable && in_array($revisionId, $removed, true);
            $preview = filled($info['preview'] ?? null) ? (string) $info['preview'] : null;

            // D-185 cipi aynen: onizleme ve kaldirma yoksa tek baglanti.
            if (! $removable && $preview === null) {
                $chips[] = filled($info['url'] ?? null)
                    ? '<a class="'.$class.'" href="'.e((string) $info['url']).'" target="_blank" rel="noopener" title="'.$title.'">'.$inner.'</a>'
                    : '<span class="'.$class.'" title="'.$title.'">'.$inner.'</span>';

                continue;
            }

            // D-186: cipin govdesi onizler (ozellik documents.office_preview) ya da
            // indirir; onizlemede yaninda kucuk indir simgesi; duzenlemede sag
            // uctaki "x" isaretler / "Geri al" isareti kaldirir (kaydedince uygulanir).
            $target = $preview ?? (filled($info['url'] ?? null) ? (string) $info['url'] : null);
            $body = $target !== null && ! $isRemoved
                ? '<a class="kc-file-chip__open" href="'.e($target).'" target="_blank" rel="noopener" title="'.($preview !== null ? e(__('document.short.preview')).' · ' : '').$title.'">'.$inner.'</a>'
                : '<span class="kc-file-chip__open" title="'.$title.'">'.$inner.'</span>';
            $download = $preview !== null && ! $isRemoved && filled($info['url'] ?? null)
                ? '<a class="kc-file-chip__icon" href="'.e((string) $info['url']).'" title="'.e(__('document.short.download')).'" aria-label="'.e(__('document.short.download')).'">'.self::icon(Heroicon::OutlinedArrowDownTray).'</a>'
                : '';
            $toggle = '';

            if ($removable) {
                $label = e(__($isRemoved ? 'document.short.undo_remove' : 'document.short.remove'));
                $toggle = '<button type="button" class="kc-file-chip__icon kc-file-chip__remove" wire:click="toggleProposalFile(\''.$removePath.'\', '.$revisionId.')" title="'.$label.'" aria-label="'.$label.'">'
                    .self::icon($isRemoved ? Heroicon::OutlinedArrowUturnLeft : Heroicon::XMark)
                    .'</button>';
            }

            $chips[] = '<span class="'.$class.' kc-file-chip--split'.($isRemoved ? ' kc-file-chip--removed' : '').'">'.$body.$download.$toggle.'</span>';
        }

        if ($chips === []) {
            return null;
        }

        return new HtmlString('<span class="kc-file-chips">'.implode('', $chips).'</span>');
    }

    private static function buttonHtml(): string
    {
        return '<span class="kc-upload-btn">'.self::icon(Heroicon::OutlinedArrowUpTray).'<span>'.e(__('document.short.upload')).'</span></span>';
    }

    /**
     * Dosya turune gore simge ve renk sinifi.
     *
     * @return array{0: Heroicon, 1: string}
     */
    private static function fileKind(string $name): array
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'xls', 'xlsx', 'xlsm', 'csv', 'ods' => [Heroicon::OutlinedTableCells, 'sheet'],
            'pdf' => [Heroicon::OutlinedDocumentText, 'pdf'],
            'doc', 'docx', 'odt', 'rtf', 'txt' => [Heroicon::OutlinedDocumentText, 'doc'],
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'tif', 'tiff' => [Heroicon::OutlinedPhoto, 'image'],
            'zip', 'rar', '7z', 'gz', 'tar' => [Heroicon::OutlinedArchiveBox, 'archive'],
            default => [Heroicon::OutlinedDocument, 'file'],
        };
    }

    private static function icon(BackedEnum $icon): string
    {
        return generate_icon_html($icon, size: IconSize::Small)?->toHtml() ?? '';
    }
}
