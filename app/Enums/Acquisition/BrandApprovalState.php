<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BrandApprovalState: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Proposed = 'proposed';
    case Pending = 'pending';
    case CustomerApproved = 'customer_approved';
    case CustomerRejected = 'customer_rejected';

    public function getColor(): string
    {
        return match ($this) {
            self::Proposed => 'gray',
            self::Pending => 'warning',
            self::CustomerApproved => 'success',
            self::CustomerRejected => 'danger',
        };
    }
}
