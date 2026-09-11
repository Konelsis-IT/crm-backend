<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AcquisitionStage: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case BusinessDevelopment = 'business_development';
    case OfferPreparation = 'offer_preparation';
    case OfferReview = 'offer_review';
    case Submitted = 'submitted';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case HandoverPreparing = 'handover_preparing';
    case HandoverReview = 'handover_review';
    case HandoverAccepted = 'handover_accepted';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::BusinessDevelopment => 'gray',
            self::OfferPreparation => 'info',
            self::OfferReview => 'info',
            self::Submitted => 'primary',
            self::Negotiation => 'warning',
            self::Won => 'success',
            self::HandoverPreparing => 'info',
            self::HandoverReview => 'info',
            self::HandoverAccepted => 'success',
            self::Lost => 'danger',
            self::Cancelled => 'gray',
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
            self::BusinessDevelopment => [self::OfferPreparation, self::Lost, self::Cancelled],
            self::OfferPreparation => [self::OfferReview, self::Lost, self::Cancelled],
            self::OfferReview => [self::OfferPreparation, self::Submitted, self::Lost, self::Cancelled],
            self::Submitted => [self::Negotiation, self::Won, self::Lost, self::Cancelled],
            self::Negotiation => [self::OfferPreparation, self::Won, self::Lost, self::Cancelled],
            self::Won => [self::HandoverPreparing, self::Cancelled],
            self::HandoverPreparing => [self::HandoverReview, self::Cancelled],
            self::HandoverReview => [self::HandoverPreparing, self::HandoverAccepted, self::Cancelled],
            self::HandoverAccepted => [],
            self::Lost => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
