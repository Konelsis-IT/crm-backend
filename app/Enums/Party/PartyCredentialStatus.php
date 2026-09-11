<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyCredentialStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Expiring => 'warning',
            self::Expired => 'danger',
            self::Revoked => 'danger',
        };
    }
}
