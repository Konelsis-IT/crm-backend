<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Acquisition\BusinessCase;
use App\Models\Project\Project;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Detay ve duzenleme sayfalarinin ortak basligi (D-205; kullanici 14 Ekim
 * 2026: "Hem ustteki route yonlendirme adimlarinin gosterildigi yerde hem
 * baslikta hem de is schema kartinda isin adi alaninda ayni isim 3 kez
 * tekrar ediyor ... route link kisminda tum detay sayfalarinda is basligi
 * yerine kod kismi yazar ... Baslik kismi da detay sayfasinda Teklif detayi,
 * Is detayi, Rapor detayi ... gibi bir yazi olmali ... alt baslik gibi daha
 * az goze batacak sekilde baslik yazilabilir").
 *
 * - Baslik: kaydin turu ("Teklif detayi", "Is duzenle").
 * - Alt baslik: kaydin adi (soluk, kucuk); detay kartinda ayni ad tekrar edilmez.
 * - Ust gezinme satiri: kaydin kodu (TKLF-2026-0072, IS-2026-0012...); kodu
 *   olmayan kayitta kisaltilmis adi.
 * - Tarayici sekmesi: "kod · Teklif detayi".
 */
final class RecordHeading
{
    /** Gezinme satirinda kodu olmayan kaydin adi en fazla bu kadar karakter. */
    public const CRUMB_LIMIT = 40;

    /** Kayit kodu alanlari (D-130: katalog `code` alanlari gosterilmez, listede yok). */
    private const CODE_ATTRIBUTES = [
        'item_no', 'proposal_no', 'report_no', 'request_no', 'order_no', 'quotation_no', 'party_no',
        'document_no', 'contract_no', 'transmittal_no', 'change_no', 'risk_no', 'issue_no', 'decision_no',
        'clarification_no', 'exposure_no', 'package_code', 'wbs_code', 'cost_code', 'milestone_code',
    ];

    /** Kaydin adi olabilecek alanlar (sirayla). */
    private const NAME_ATTRIBUTES = ['title', 'display_name', 'full_name', 'name', 'name_tr', 'subject_label'];

    /**
     * Kayit turunun adi, cumle duzeninde ("Potansiyel is", "Teklif");
     * record_heading.labels'taki ad (adres parcasina gore) kaynak adinin yerine gecer.
     *
     * @param  class-string<resource>  $resource
     */
    public static function typeLabel(string $resource): string
    {
        $overrides = (array) __('record_heading.labels');

        try {
            $slug = $resource::getSlug(Filament::getCurrentOrDefaultPanel());
        } catch (Throwable) {
            $slug = null;
        }

        if ($slug !== null && is_string($overrides[$slug] ?? null)) {
            return $overrides[$slug];
        }

        return self::sentenceCase((string) $resource::getModelLabel());
    }

    /** "Potansiyel İş" -> "Potansiyel iş": ilk harf kalir, digerleri Turkce kucuk harf. */
    public static function sentenceCase(string $label): string
    {
        $label = trim($label);

        if ($label === '') {
            return $label;
        }

        $first = mb_substr($label, 0, 1);
        $rest = mb_substr($label, 1);
        $rest = app()->getLocale() === 'tr'
            ? mb_strtolower(strtr($rest, ['İ' => 'i', 'I' => 'ı']), 'UTF-8')
            : mb_strtolower($rest, 'UTF-8');

        return $first.$rest;
    }

    /** Kaydin is kodu (POTIS / PRJ / TKLF / IS / SAT...); yoksa null. */
    public static function code(Model $record): ?string
    {
        $code = match (true) {
            $record instanceof BusinessCase => ($record->caseCode() ?? $record->projectCode())?->formatted_code,
            $record instanceof Project => $record->businessCode?->formatted_code,
            default => self::firstAttribute($record, self::CODE_ATTRIBUTES),
        };

        return filled($code) ? (string) $code : null;
    }

    /** Kaydin adi (basligin alt satiri); yoksa null. */
    public static function name(Model $record): ?string
    {
        // Ad alani olmayan kayit kendi basligini uretir (satin alma talebi: kalemleri).
        if (method_exists($record, 'displayTitle')) {
            $title = Str::squish((string) $record->displayTitle());

            return $title !== '' && $title !== self::code($record) ? $title : null;
        }

        $name = self::firstAttribute($record, self::NAME_ATTRIBUTES);

        return filled($name) ? Str::squish((string) $name) : null;
    }

    /** Gezinme satirindaki kayit adimi: kod, yoksa kisaltilmis ad. */
    public static function crumb(Model $record, ?string $fallback = null): string
    {
        return self::code($record)
            ?? Str::limit(Str::squish((string) ($fallback ?? self::name($record) ?? '')), self::CRUMB_LIMIT, '…');
    }

    /**
     * @param  list<string>  $attributes
     */
    private static function firstAttribute(Model $record, array $attributes): mixed
    {
        $values = $record->getAttributes();

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $values) && filled($values[$attribute])) {
                return $values[$attribute];
            }
        }

        return null;
    }
}
