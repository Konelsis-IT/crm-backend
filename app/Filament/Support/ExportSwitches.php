<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;

/**
 * Bir arayuzun Excel / PDF anahtarlari (D-167, 6 Ekim 2026 kullanici karari:
 * "excel ve pdf sistemini genel projede kapattim ... her yerdeki kismini
 * acmayacagiz, kademe kademe acmak istiyorum").
 *
 * - general(): genel disa aktarim (Araclar > Disa aktarim, tools.exports.*);
 *   ayri anahtari olmayan butun listeler ve detay sayfalari bunu kullanir.
 * - for(): arayuze ozel anahtarlar (ilk: Raporlar, reports.exports.*). Arayuz
 *   acildiginda genel anahtardan bagimsizdir; ust anahtar kapaninca iki alt
 *   anahtar da kapanir (Feature hiyerarsisi).
 *
 * Yeni bir arayuzu acmak: Feature'a `<alan>.exports`, `.pdf`, `.excel`
 * eklenir ve o ekranin ExportActions cagrisina for(...) verilir.
 */
final readonly class ExportSwitches
{
    private function __construct(
        public Feature $excel,
        public Feature $pdf,
    ) {}

    public static function general(): self
    {
        return new self(Feature::ExcelExport, Feature::PdfExport);
    }

    public static function for(Feature $excel, Feature $pdf): self
    {
        return new self($excel, $pdf);
    }

    public function excelEnabled(): bool
    {
        return FeatureFlags::enabled($this->excel);
    }

    public function pdfEnabled(): bool
    {
        return FeatureFlags::enabled($this->pdf);
    }
}
