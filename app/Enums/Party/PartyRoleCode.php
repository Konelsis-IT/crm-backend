<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyRoleCode: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Customer = 'customer';
    case Supplier = 'supplier';
    case Subcontractor = 'subcontractor';
    case Partner = 'partner';
    case Employer = 'employer';
    case Investor = 'investor';
    case Consultant = 'consultant';
    case Carrier = 'carrier';
    case Authority = 'authority';

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'primary',
            self::Supplier => 'info',
            self::Subcontractor => 'info',
            self::Partner => 'success',
            self::Employer => 'primary',
            self::Investor => 'warning',
            self::Consultant => 'gray',
            self::Carrier => 'gray',
            self::Authority => 'danger',
        };
    }
}
