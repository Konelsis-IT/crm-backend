<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * SM-DELEG (14 SS2.43): pending -> active -> expired | revoked.
 */
enum DelegationStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Active => 'success',
            self::Revoked => 'danger',
            self::Expired => 'warning',
        };
    }
}
