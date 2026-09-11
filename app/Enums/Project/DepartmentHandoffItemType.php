<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DepartmentHandoffItemType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Document = 'document';
    case Evidence = 'evidence';
    case OpenIssue = 'open_issue';
    case Checklist = 'checklist';
    case Material = 'material';
    case Quantity = 'quantity';

    public function getColor(): string
    {
        return match ($this) {
            self::Document => 'primary',
            self::Evidence => 'success',
            self::OpenIssue => 'warning',
            self::Checklist => 'gray',
            self::Material => 'info',
            self::Quantity => 'info',
        };
    }
}
