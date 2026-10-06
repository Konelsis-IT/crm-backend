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
        // Her durumun kendi rengi (D-161); teklif surumu durumlariyla ayni tonlar.
        return match ($this) {
            self::Draft => 'sky',
            self::InReview => 'indigo',
            self::Approved => 'success',
            self::Submitted => 'violet',
            self::Negotiation => 'amber',
            self::Accepted => 'emerald',
            self::Rejected => 'danger',
            self::Withdrawn => 'rose',
            self::Superseded => 'gray',
        };
    }
}
