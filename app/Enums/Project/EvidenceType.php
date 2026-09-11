<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EvidenceType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Document = 'document';
    case Approval = 'approval';
    case Checklist = 'checklist';
    case Measurement = 'measurement';
    case ExternalCheck = 'external_check';
    case Handoff = 'handoff';

    public function getColor(): string
    {
        return match ($this) {
            self::Document => 'primary',
            self::Approval => 'success',
            self::Checklist => 'gray',
            self::Measurement => 'info',
            self::ExternalCheck => 'warning',
            self::Handoff => 'info',
        };
    }
}
