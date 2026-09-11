<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OpportunityStage: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Identified = 'identified';
    case Qualified = 'qualified';
    case BidDecisionPending = 'bid_decision_pending';
    case Bid = 'bid';
    case NoBid = 'no_bid';
    case ConvertedToProposal = 'converted_to_proposal';
    case Dropped = 'dropped';

    public function getColor(): string
    {
        return match ($this) {
            self::Identified => 'gray',
            self::Qualified => 'info',
            self::BidDecisionPending => 'warning',
            self::Bid => 'success',
            self::NoBid => 'danger',
            self::ConvertedToProposal => 'primary',
            self::Dropped => 'gray',
        };
    }

    /**
     * Izin verilen durum gecisleri (docs/planning/14).
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Identified => [self::Qualified, self::Dropped],
            self::Qualified => [self::BidDecisionPending, self::Dropped],
            self::BidDecisionPending => [self::Bid, self::NoBid],
            self::Bid => [self::ConvertedToProposal, self::Dropped],
            self::NoBid => [self::Dropped],
            self::ConvertedToProposal => [],
            self::Dropped => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
