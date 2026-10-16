<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Detay sayfalarinda aciklama / not gibi uzun metinler duz yazi degil,
 * kilitli (disabled) bir textarea gibi kutu icinde gorunur (kullanici 14 Ekim
 * 2026: "proje genelindeki detay sayfasi aciklama alanlari textarea input'un
 * disable hali gibi gorulmelidir"). Satir sonlari korunur.
 *
 * register(): adi asagidaki uzun metin adlarindan biriyle biten her TextEntry
 * (description, note, summary...) kendiliginden kutu olur; yeni ekranlar da
 * kurala uyar. Baska adli uzun metin alanlari apply() ile isaretlenir (ornek:
 * rapor taslaklarinin uzun metin cevaplari). Tablo bicimli tekrarlayan
 * girdilerin hucrelerinde kutu cizilmez (konelsis.css).
 */
final class LongText
{
    /** Kutu sinifi (konelsis.css). */
    public const CSS_CLASS = 'kc-longtext';

    /** Uzun metin alan adlari (iliski yolunun son parcasi). */
    private const NAMES = [
        'description',
        'short_description',
        'scope_description',
        'item_description',
        'note',
        'notes',
        'closing_note',
        'status_note',
        'network_note',
        'summary',
        'change_summary',
        'body',
        'comment',
        'review_comment',
        'reason',
        'archive_reason',
        'revoke_reason',
        'invalidation_reason',
    ];

    public static function register(): void
    {
        TextEntry::configureUsing(function (TextEntry $entry): void {
            if (in_array(Str::afterLast($entry->getName(), '.'), self::NAMES, true)) {
                self::apply($entry);
            }
        });
    }

    public static function apply(TextEntry $entry): TextEntry
    {
        return $entry
            ->extraAttributes(['class' => self::CSS_CLASS], merge: true)
            // Yazilan satir sonlari korunur. Girdinin kendi formatStateUsing'i bunu ezer.
            ->formatStateUsing(fn (mixed $state, TextEntry $component): mixed => self::lineBreaks($state, $component));
    }

    /** Cok satirli duz metin satir satir yazilir; html, markdown ve kisaltilmis girdiler oldugu gibi kalir. */
    private static function lineBreaks(mixed $state, TextEntry $component): mixed
    {
        if (! is_string($state) || ! str_contains($state, "\n")) {
            return $state;
        }

        if ($component->isHtml() || $component->isMarkdown() || $component->getCharacterLimit() !== null || $component->getWordLimit() !== null) {
            return $state;
        }

        return new HtmlString(nl2br(e(trim($state)), false));
    }
}
