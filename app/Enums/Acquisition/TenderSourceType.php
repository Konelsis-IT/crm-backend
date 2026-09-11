<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenderSourceType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case PublicProcurement = 'public_procurement';
    case InternationalFinance = 'international_finance';
    case ProvincialBank = 'provincial_bank';
    case PrivateInvitation = 'private_invitation';
    case Marketplace = 'marketplace';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::PublicProcurement => 'primary',
            self::InternationalFinance => 'info',
            self::ProvincialBank => 'info',
            self::PrivateInvitation => 'success',
            self::Marketplace => 'gray',
            self::Other => 'gray',
        };
    }
}
