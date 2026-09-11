<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProposalStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Submitted = 'submitted';
    case Negotiation = 'negotiation';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'info',
            self::Approved => 'success',
            self::Submitted => 'primary',
            self::Negotiation => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'gray',
            self::Superseded => 'gray',
        };
    }
}
