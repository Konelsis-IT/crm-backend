<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StageInstanceStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case NotStarted = 'not_started';
    case Preparing = 'preparing';
    case ReadyForReview = 'ready_for_review';
    case ApprovalPending = 'approval_pending';
    case Passed = 'passed';
    case ConditionallyPassed = 'conditionally_passed';
    case Rejected = 'rejected';
    case Reopened = 'reopened';

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::Preparing => 'info',
            self::ReadyForReview => 'warning',
            self::ApprovalPending => 'warning',
            self::Passed => 'success',
            self::ConditionallyPassed => 'warning',
            self::Rejected => 'danger',
            self::Reopened => 'danger',
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
            self::NotStarted => [self::Preparing],
            self::Preparing => [self::ReadyForReview],
            self::ReadyForReview => [self::ApprovalPending],
            self::ApprovalPending => [self::Passed, self::ConditionallyPassed, self::Rejected],
            self::Rejected => [self::Preparing],
            self::Passed => [self::Reopened],
            self::ConditionallyPassed => [self::Reopened],
            self::Reopened => [self::Preparing],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}
