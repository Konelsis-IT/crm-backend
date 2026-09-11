<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Employer = 'employer';
    case Contractor = 'contractor';
    case Consultant = 'consultant';
    case Guarantor = 'guarantor';
    case Subcontractor = 'subcontractor';
    case Financier = 'financier';

    public function getColor(): string
    {
        return match ($this) {
            self::Employer => 'primary',
            self::Contractor => 'success',
            self::Consultant => 'info',
            self::Guarantor => 'gray',
            self::Subcontractor => 'gray',
            self::Financier => 'warning',
        };
    }
}
