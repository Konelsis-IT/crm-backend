<?php

declare(strict_types=1);

namespace App\Filament\Support\QuickActions;

use Filament\Support\Icons\Heroicon;

/** Tek bir hizli islem (D-122): ad, simge, gidilecek sayfa, sabit mi. */
final readonly class QuickAction
{
    public function __construct(
        public string $code,
        public string $label,
        public Heroicon $icon,
        public string $url,
        public bool $fixed,
    ) {}
}
