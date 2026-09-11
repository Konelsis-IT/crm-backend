<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClarificationStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Open = 'open';
    case UnderReview = 'under_review';
    case Answered = 'answered';
    case Closed = 'closed';
    case ConvertedToChange = 'converted_to_change';

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::UnderReview => 'info',
            self::Answered => 'success',
            self::Closed => 'gray',
            self::ConvertedToChange => 'primary',
        };
    }
}
