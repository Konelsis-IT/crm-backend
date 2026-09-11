<?php

declare(strict_types=1);

namespace App\Enums\WorkRequest;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** 07 SS5.1 `priority`. */
enum WorkRequestPriority: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'primary',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }
}
