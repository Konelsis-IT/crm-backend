<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviationStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Proposed = 'proposed';
    case AcceptedByCustomer = 'accepted_by_customer';
    case RejectedByCustomer = 'rejected_by_customer';
    case Withdrawn = 'withdrawn';

    public function getColor(): string
    {
        return match ($this) {
            self::Proposed => 'gray',
            self::AcceptedByCustomer => 'success',
            self::RejectedByCustomer => 'danger',
            self::Withdrawn => 'gray',
        };
    }
}
