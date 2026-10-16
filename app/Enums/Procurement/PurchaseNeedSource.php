<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Siparis kaleminin kaynagi (D-189): teklifin maliyet listesinden (teklif
 * grubu gonderir, proje onaylar), departmanin sirket ici ihtiyac talebinden
 * ya da satin almanin elle actigi kalem.
 */
enum PurchaseNeedSource: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case ProposalCost = 'proposal_cost';
    case OfficeRequest = 'office_request';
    case Manual = 'manual';

    public function getColor(): string
    {
        return match ($this) {
            self::ProposalCost => 'info',
            self::OfficeRequest => 'success',
            self::Manual => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ProposalCost => Heroicon::OutlinedDocumentCurrencyDollar,
            self::OfficeRequest => Heroicon::OutlinedBuildingOffice2,
            self::Manual => Heroicon::OutlinedPencilSquare,
        };
    }
}
