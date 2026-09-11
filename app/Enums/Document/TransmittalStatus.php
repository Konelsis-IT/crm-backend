<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransmittalStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Issued = 'issued';
    case Acknowledged = 'acknowledged';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'info',
            self::Acknowledged => 'success',
            self::Cancelled => 'danger',
        };
    }
}
