<?php

declare(strict_types=1);

namespace App\Enums\SocialMedia;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Izlenen hesap turu (B31, D-106). Kurumsal hesapta rakip firmalar, yonetici
 * hesabinda rakip yoneticiler gorunur; resmi kurumlar iki hesapta da gorunur.
 */
enum SocialWatchKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case CompetitorCompany = 'competitor_company';
    case CompetitorExecutive = 'competitor_executive';
    case OfficialInstitution = 'official_institution';

    /**
     * Verilen hesap turunde gorunen izleme turleri (gosterim sirasiyla).
     *
     * @return list<self>
     */
    public static function forProfileKind(SocialProfileKind $kind): array
    {
        return match ($kind) {
            SocialProfileKind::Corporate => [self::CompetitorCompany, self::OfficialInstitution],
            SocialProfileKind::Executive => [self::CompetitorExecutive, self::OfficialInstitution],
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesForProfileKind(SocialProfileKind $kind): array
    {
        return array_map(static fn (self $case): string => $case->value, self::forProfileKind($kind));
    }
}
