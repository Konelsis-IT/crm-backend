<?php

declare(strict_types=1);

namespace App\Filament\Support;

/**
 * Iki satirli secim kutusu secenegi (D-192, 12 Ekim 2026 kullanici talimati:
 * "Inputta 2 satir halinde urun adi altinda aciklama yazisi gibi kategorisi
 * yazilir. Ayni input mantigini personel input'lari icinde yapalim. Personel
 * ismi / Departman ismi (aciklama gibi silik)"). HTML kullanimi kullanici
 * tarafindan onaylandi (D-192: "Iki satirli secimler, kalem rozetleri ve PDF
 * sablonu bu sekilde yapilir").
 *
 * Select'te `->allowHtml()` ile kullanilir; metinler her zaman kacirilir
 * (e()). Gorunum resources/css/filament/konelsis.css `.kc-opt` kurallaridir.
 */
final class TwoLineOption
{
    /** Ust satir ad, alt satir silik aciklama (kategori / departman). */
    public static function html(string $title, ?string $subtitle = null): string
    {
        $title = '<span class="kc-opt-title">'.e($title).'</span>';

        if ($subtitle === null || trim($subtitle) === '') {
            return '<span class="kc-opt">'.$title.'</span>';
        }

        // Aradaki bosluk ekranda gorunmez (flex sutun) ama ekran okuyucu ve
        // Filament'in aria-label'i "Ad Aciklama" diye ayri okur.
        return '<span class="kc-opt">'.$title.' <span class="kc-opt-sub">'.e(trim($subtitle)).'</span></span>';
    }

    /**
     * Kimlik => [ad, aciklama] listesini secenek dizisine cevirir.
     *
     * @param  array<int|string, array{0: string, 1: string|null}>  $rows
     * @return array<int|string, string>
     */
    public static function options(array $rows): array
    {
        $options = [];

        foreach ($rows as $key => [$title, $subtitle]) {
            $options[$key] = self::html($title, $subtitle);
        }

        return $options;
    }
}
