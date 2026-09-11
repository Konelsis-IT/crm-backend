<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Filament'in Heroicon setinde olmayan marka simgeleri (11 Eylul 2026,
 * kullanici istegi: WhatsApp icin "orjinal logo"). Action::icon() bir
 * Htmlable kabul eder (bkz. HasIcon::icon()), o yuzden satir ici SVG
 * yeterli; ayrica bir Blade/varlik dosyasi gerekmez. Kart (CardGallery)
 * ve tablo (PersonnelTable) ayni simgeyi kullanir.
 */
final class BrandIcons
{
    public static function whatsapp(): Htmlable
    {
        return new HtmlString(
            '<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">'
            .'<path d="M12.001 2C6.478 2 2 6.477 2 12c0 1.905.535 3.688 1.463 5.207L2 22l4.933-1.436A9.94 9.94 0 0 0 12.001 22C17.523 22 22 17.523 22 12S17.523 2 12.001 2zm0 18.163a8.13 8.13 0 0 1-4.144-1.13l-.297-.177-3.03.882.899-2.955-.194-.304A8.129 8.129 0 0 1 3.837 12c0-4.5 3.663-8.163 8.164-8.163 4.5 0 8.163 3.663 8.163 8.163 0 4.501-3.663 8.163-8.163 8.163z"/>'
            .'<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.472-.148-.67.15-.198.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.693.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>'
            .'</svg>'
        );
    }
}
