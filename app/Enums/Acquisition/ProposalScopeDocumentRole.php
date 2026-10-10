<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Teklif kapsamina (proposal_version_scopes) bagli belgenin rolu (B51, D-181).
 * Bugun yalniz Maliyet listesi; dokuman turu kodu MLY.
 */
enum ProposalScopeDocumentRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case CostList = 'cost_list';

    public function getColor(): string
    {
        return match ($this) {
            self::CostList => 'warning',
        };
    }

    /** Bu roldeki yeni belgenin dokuman turu kodu. */
    public function documentTypeCode(): string
    {
        return match ($this) {
            self::CostList => 'MLY',
        };
    }
}
