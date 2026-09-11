<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DistributionKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case ForAction = 'for_action';
    case ForInformation = 'for_information';
    case ControlledCopy = 'controlled_copy';

    public function getColor(): string
    {
        return match ($this) {
            self::ForAction => 'warning',
            self::ForInformation => 'gray',
            self::ControlledCopy => 'primary',
        };
    }
}
