<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentReviewDecision: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Approved = 'approved';
    case ApprovedWithComments = 'approved_with_comments';
    case Rejected = 'rejected';

    public function getColor(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::ApprovedWithComments => 'warning',
            self::Rejected => 'danger',
        };
    }
}
