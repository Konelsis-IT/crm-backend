<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Teklifin ticari durumu (B29, D-101): verilecek / verilen / onaylandi /
 * kacan firsat. Ic is akisi durumu (ProposalStatus) ayri kavramdir.
 */
enum OfferStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case ToBeSubmitted = 'to_be_submitted';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Lost = 'lost';

    public function getColor(): string
    {
        return match ($this) {
            self::ToBeSubmitted => 'gray',
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Lost => 'danger',
        };
    }
}
