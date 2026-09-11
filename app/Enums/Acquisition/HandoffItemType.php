<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HandoffItemType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Document = 'document';
    case Baseline = 'baseline';
    case Assumption = 'assumption';
    case Risk = 'risk';
    case OpenIssue = 'open_issue';
    case Checklist = 'checklist';

    public function getColor(): string
    {
        return match ($this) {
            self::Document => 'primary',
            self::Baseline => 'info',
            self::Assumption => 'gray',
            self::Risk => 'danger',
            self::OpenIssue => 'warning',
            self::Checklist => 'gray',
        };
    }
}
