<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransmittalPurpose: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case ForInformation = 'for_information';
    case ForReview = 'for_review';
    case ForApproval = 'for_approval';
    case ForConstruction = 'for_construction';
    case Final = 'final';

    public function getColor(): string
    {
        return match ($this) {
            self::ForInformation => 'gray',
            self::ForReview => 'info',
            self::ForApproval => 'warning',
            self::ForConstruction => 'primary',
            self::Final => 'success',
        };
    }
}
