<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use ValueError;

/**
 * Tarafin ziyaret onceligi (B28, D-98): acil ziyaret > rutin gorusme >
 * telefon. rank() listeleri oncelige gore siralamak icindir; 1 en acildir.
 */
enum VisitPriority: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case UrgentVisit = 'urgent_visit';
    case RoutineMeeting = 'routine_meeting';
    case Phone = 'phone';

    public function getColor(): string
    {
        return match ($this) {
            self::UrgentVisit => 'danger',
            self::RoutineMeeting => 'warning',
            self::Phone => 'gray',
        };
    }

    /** Oncelik sirasi; 1 en acil. */
    public function rank(): int
    {
        return match ($this) {
            self::UrgentVisit => 1,
            self::RoutineMeeting => 2,
            self::Phone => 3,
        };
    }

    /** Siradan oncelik; taninmayan sira icin from() gibi ValueError atar. */
    public static function fromRank(int $rank): self
    {
        return self::tryFromRank($rank)
            ?? throw new ValueError(sprintf('%d is not a valid rank for enum %s', $rank, self::class));
    }

    public static function tryFromRank(?int $rank): ?self
    {
        if ($rank === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->rank() === $rank) {
                return $case;
            }
        }

        return null;
    }
}
