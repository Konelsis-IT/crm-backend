<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ComplianceState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Comply = 'comply';
    case Partial = 'partial';
    case Deviate = 'deviate';
    case NotApplicable = 'not_applicable';

    public function getColor(): string
    {
        return match ($this) {
            self::Comply => 'success',
            self::Partial => 'warning',
            self::Deviate => 'danger',
            self::NotApplicable => 'gray',
        };
    }
}
