<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BusinessSourceKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Email = 'email';
    case Manual = 'manual';
    case TenderSource = 'tender_source';
    case Referral = 'referral';
    case ExistingCustomer = 'existing_customer';

    public function getColor(): string
    {
        return match ($this) {
            self::Email => 'gray',
            self::Manual => 'gray',
            self::TenderSource => 'info',
            self::Referral => 'success',
            self::ExistingCustomer => 'primary',
        };
    }
}
