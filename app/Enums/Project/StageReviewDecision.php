<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StageReviewDecision: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Passed = 'passed';
    case ConditionallyPassed = 'conditionally_passed';
    case Rejected = 'rejected';

    public function getColor(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::ConditionallyPassed => 'warning',
            self::Rejected => 'danger',
        };
    }
}
