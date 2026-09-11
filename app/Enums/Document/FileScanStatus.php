<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FileScanStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Quarantined = 'quarantined';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Clean => 'success',
            self::Infected => 'danger',
            self::Quarantined => 'danger',
            self::Failed => 'warning',
            self::Skipped => 'gray',
        };
    }
}
