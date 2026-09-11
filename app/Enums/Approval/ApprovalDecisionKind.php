<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ApprovalDecisionKind: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Abstained = 'abstained';

    public function getColor(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Returned => 'warning',
            self::Abstained => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Returned => Heroicon::OutlinedArrowUturnLeft,
            self::Abstained => Heroicon::OutlinedMinusCircle,
        };
    }

    /** Ret/iade kararinda gerekce zorunlu (12 SS2.6). */
    public function requiresComment(): bool
    {
        return in_array($this, [self::Rejected, self::Returned], true);
    }
}
