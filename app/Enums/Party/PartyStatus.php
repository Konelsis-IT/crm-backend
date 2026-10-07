<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyStatus: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
    case Prospect = 'prospect';
    // Baska bir tarafa birlestirildi (D-170, PartyMergeService): kayitlari
    // hedef taraftadir, merged_into_party_id hedefi gosterir. Formda secilmez.
    case Merged = 'merged';

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Blocked => 'danger',
            self::Prospect => 'info',
            self::Merged => 'gray',
        };
    }

    /**
     * Formda secilebilen durumlar: "Birlestirildi" yalniz birlestirme
     * servisiyle yazilir (D-170).
     *
     * @return array<string, string>
     */
    public static function selectableOptions(): array
    {
        $options = self::options();
        unset($options[self::Merged->value]);

        return $options;
    }
}
