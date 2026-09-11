<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Alan izgarasi (D-80, 10 Eylul 2026 kullanici karari): form ve bilgi
 * bolumleri 12 sutunlu izgara kullanir; her alan icerigine gore yer kaplar —
 * kod / tarih / sayi / para birimi gibi kisa alanlar 1/6, secimler 1/4,
 * serbest metin 1/3, uzun metin ve yukleme alanlari tam satir. Boylece
 * "gereginden fazla buyutulmus" alan kalmaz. Acik columnSpan verilen alanlar
 * oldugu gibi kalir; FieldGrid::fields() yalniz geri kalanina genislik atar.
 *
 * Dar ekranda (default) her sey tek sutun; orta ekranda (md) 6 sutun.
 * Eylem modallari (4xl) daha dar oldugu icin 4 sutunlu ayri olcek kullanir
 * (MODAL_COLUMNS + modal()): kisa alan 1/4, secim/metin 1/2, uzun metin tam.
 */
final class FieldGrid
{
    /** Bolum / adim / sekme izgarasi. */
    public const COLUMNS = ['default' => 1, 'md' => 6, 'xl' => 12];

    /** Eylem modali izgarasi. */
    public const MODAL_COLUMNS = ['default' => 1, 'md' => 4];

    /** Kod, tarih, sayi, yuzde, para birimi, durum, il/ilce: 1/6 (md 1/3). */
    public const SHORT = ['default' => 1, 'md' => 2, 'xl' => 2];

    /** Secim listeleri (kisi, tip, proje), tarih-saat: 1/4 (md 1/2). */
    public const NORMAL = ['default' => 1, 'md' => 3, 'xl' => 3];

    /** Serbest metin (ad, baslik, adres satiri): 1/3 (md 1/2). */
    public const WIDE = ['default' => 1, 'md' => 3, 'xl' => 4];

    /** Yarim satir (kapak gorseli + aciklama gibi ikili yerlesim). */
    public const HALF = ['default' => 1, 'md' => 6, 'xl' => 6];

    /** Tam satir. */
    public const FULL = ['default' => 'full'];

    private const SIZE_SHORT = 'short';

    private const SIZE_NORMAL = 'normal';

    private const SIZE_WIDE = 'wide';

    private const SIZE_FULL = 'full';

    /**
     * Adiyla kisa oldugu anlasilan alanlar (sonek `_id` ile de eslesir:
     * classification_id, category_id, currency_code, site_postal_code...).
     */
    private const SHORT_NAMES = '/(^|_)(code|prefix|pct|percent|percentage|hours|minutes|months|days|weeks|years|bytes|byte_size|latitude|longitude|postal_code|zip|currency|amount|price|value|status|kind|category|priority|level|profile|classification|city|district|sequence|order|sort_order|year|quantity|qty|weight|score|rate|ratio|number|no|phone|extension|locale|timezone|language|version|external_notice)(_id)?$/';

    /**
     * Acik columnSpan verilmemis bilesenlere icerigine gore genislik atar ve
     * ayni listeyi geri verir (`->components(FieldGrid::fields([...]))`).
     *
     * @param  array<int, mixed>  $components
     * @return array<int, mixed>
     */
    public static function fields(array $components, bool $modal = false): array
    {
        foreach ($components as $component) {
            if ($component instanceof Component && ! self::hasExplicitSpan($component)) {
                $component->columnSpan(self::span(self::sizeFor($component), $modal));
            }
        }

        return $components;
    }

    /**
     * Eylem modali icin ayni atama, 4 sutunlu olcekle
     * (`->schema(fn (Schema $schema) => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([...])))`).
     *
     * @param  array<int, mixed>  $components
     * @return array<int, mixed>
     */
    public static function modal(array $components): array
    {
        return self::fields($components, modal: true);
    }

    /**
     * Duz alan listesini adlariyla bolumlere (Section) dagitir — "her adimda
     * uygun alanlar uygun bolumde" (D-80). Her bolum 12 sutunlu izgarayla ve
     * FieldGrid::fields() genislikleriyle kurulur. Listede adi gecmeyen alanlar
     * son bolume, adsiz bilesenler (Text, Callout) ilk bolume, Hidden alanlar
     * bolumlerin disina (koke) gider. Bos kalan bolum uretilmez.
     *
     * @param  array<int, mixed>  $components
     * @param  array<string, array{label: string, fields: list<string>, icon?: mixed, description?: string|null, visible?: mixed}>  $groups
     * @return array<int, mixed>
     */
    public static function group(array $components, array $groups): array
    {
        $byName = [];
        $unnamed = [];
        $hidden = [];

        foreach ($components as $component) {
            if ($component instanceof Hidden) {
                $hidden[] = $component;

                continue;
            }

            $name = ($component instanceof Field || $component instanceof Entry) ? $component->getName() : null;

            if ($name === null || $name === '') {
                $unnamed[] = $component;

                continue;
            }

            $byName[$name][] = $component;
        }

        $firstKey = array_key_first($groups);
        $lastKey = array_key_last($groups);
        $sections = [];

        foreach ($groups as $key => $group) {
            $picked = $key === $firstKey ? $unnamed : [];

            foreach ($group['fields'] as $fieldName) {
                foreach ($byName[$fieldName] ?? [] as $component) {
                    $picked[] = $component;
                }

                unset($byName[$fieldName]);
            }

            if ($key === $lastKey) {
                foreach ($byName as $rest) {
                    foreach ($rest as $component) {
                        $picked[] = $component;
                    }
                }

                $byName = [];
            }

            if ($picked === []) {
                continue;
            }

            $section = Section::make($group['label'])
                ->columns(self::COLUMNS)
                ->components(self::fields($picked));

            if (($group['icon'] ?? null) !== null) {
                $section->icon($group['icon']);
            }

            if (filled($group['description'] ?? null)) {
                $section->description($group['description']);
            }

            if (array_key_exists('visible', $group)) {
                $section->visible($group['visible']);
            }

            $sections[] = $section;
        }

        return [...$sections, ...$hidden];
    }

    private static function hasExplicitSpan(Component $component): bool
    {
        $span = $component->getColumnSpan();

        if (! is_array($span)) {
            return true;
        }

        if (($span['default'] ?? 1) !== 1) {
            return true;
        }

        foreach ($span as $breakpoint => $value) {
            if ($breakpoint !== 'default' && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int | string>
     */
    private static function span(string $size, bool $modal): array
    {
        if ($modal) {
            return match ($size) {
                self::SIZE_SHORT => ['default' => 1, 'md' => 1],
                self::SIZE_NORMAL, self::SIZE_WIDE => ['default' => 1, 'md' => 2],
                default => self::FULL,
            };
        }

        return match ($size) {
            self::SIZE_SHORT => self::SHORT,
            self::SIZE_NORMAL => self::NORMAL,
            self::SIZE_WIDE => self::WIDE,
            default => self::FULL,
        };
    }

    private static function sizeFor(Component $component): string
    {
        if ($component instanceof Hidden) {
            return self::SIZE_SHORT;
        }

        if (
            $component instanceof Textarea
            || $component instanceof RichEditor
            || $component instanceof MarkdownEditor
            || $component instanceof FileUpload
            || $component instanceof Repeater
            || $component instanceof KeyValue
            || $component instanceof TagsInput
        ) {
            return self::SIZE_FULL;
        }

        // Filament'ta DatePicker ve TimePicker, DateTimePicker'dan turer: once onlar denetlenir.
        if (
            $component instanceof DatePicker
            || $component instanceof TimePicker
            || $component instanceof Toggle
            || $component instanceof Checkbox
        ) {
            return self::SIZE_SHORT;
        }

        // Tarih-saat girdisi ("gg.aa.yyyy ss:dd") 1/6'ya sigmaz; 1/4.
        if ($component instanceof DateTimePicker) {
            return self::SIZE_NORMAL;
        }

        if ($component instanceof Field || $component instanceof Entry) {
            if (preg_match(self::SHORT_NAMES, $component->getName()) === 1) {
                return self::SIZE_SHORT;
            }

            if ($component instanceof Select) {
                return $component->isMultiple() ? self::SIZE_WIDE : self::SIZE_NORMAL;
            }

            if ($component instanceof TextInput) {
                $maxLength = method_exists($component, 'getMaxLength') ? $component->getMaxLength() : null;

                if ($component->isNumeric() || ($maxLength !== null && (int) $maxLength <= 32)) {
                    return self::SIZE_SHORT;
                }
            }

            return self::SIZE_WIDE;
        }

        // Ic ice yerlesim bilesenleri (Grid, Group, Fieldset, gomulu tablo...).
        return self::SIZE_FULL;
    }
}
