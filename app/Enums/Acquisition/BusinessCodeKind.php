<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Is kodu turu. D-132 (B40): potansiyel isin kodu POTIS-YYYY-NNNN, projenin
 * PRJ-YYYY-NNNN; teklifin TKLF-YYYY-NNNN numarasi teklifin kendisindedir
 * (proposals.proposal_no). `Offer` B40 oncesi eski kayitlar icindir.
 */
enum BusinessCodeKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Potential = 'potential';
    case Offer = 'offer';
    case Project = 'project';

    /** Yillik kodun on eki (B40). */
    public function prefix(): string
    {
        return match ($this) {
            self::Potential => 'POTIS',
            self::Offer => 'TKLF',
            self::Project => 'PRJ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Potential => 'warning',
            self::Offer => 'info',
            self::Project => 'success',
        };
    }
}
