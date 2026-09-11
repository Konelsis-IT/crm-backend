<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentReviewType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Check = 'check';
    case Approve = 'approve';
    case Qa = 'qa';

    public function getColor(): string
    {
        return match ($this) {
            self::Check => 'info',
            self::Approve => 'success',
            self::Qa => 'primary',
        };
    }
}
