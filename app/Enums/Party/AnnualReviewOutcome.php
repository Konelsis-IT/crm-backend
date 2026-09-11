<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AnnualReviewOutcome: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Conditional = 'conditional';
    case Rejected = 'rejected';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Approved => 'success',
            self::Conditional => 'warning',
            self::Rejected => 'danger',
        };
    }
}
