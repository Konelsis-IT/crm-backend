<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Support\Colors\Color;

/**
 * Durum renkleri (D-161, 6 Ekim 2026 kullanici talimati: "her durumun bir
 * rengi olacak"). Filament'in hazir tonlari (gray, info, success, warning,
 * danger) bir surecin butun durumlarina yetmiyor; potansiyel is, teklif ve
 * teklif surumu durumlari bu ek tonlarla birbirinden ayrilir. Panellere
 * ActionColors::panelColors() ile birlikte kaydedilir.
 */
final class StatusColors
{
    /**
     * @return array<string, array<int|string, string>>
     */
    public static function panelColors(): array
    {
        return [
            'slate' => Color::Slate,
            'sky' => Color::Sky,
            'indigo' => Color::Indigo,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'amber' => Color::Amber,
        ];
    }
}
