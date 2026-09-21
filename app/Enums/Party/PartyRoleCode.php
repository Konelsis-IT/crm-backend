<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyRoleCode: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Customer = 'customer';
    case Supplier = 'supplier';
    case Subcontractor = 'subcontractor';
    case Partner = 'partner';
    case Employer = 'employer';
    case Investor = 'investor';
    case Consultant = 'consultant';
    case Carrier = 'carrier';
    case Authority = 'authority';
    // Dernek / oda (B33, D-107): Dernekler menusunun (AssociationResource) kayitlari;
    // Taraflar ekraninda secilmez, sekmesi yoktur.
    case Association = 'association';

    /**
     * Taraflar ekraninda secilebilen tipler. Dernek / oda burada yoktur:
     * dernekler ayri menuden (Dernekler) tip secilmeden acilir (21 Eylul 2026
     * kullanici karari).
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $code): bool => $code !== self::Association,
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function availableOptions(): array
    {
        $options = [];

        foreach (self::available() as $code) {
            $options[$code->value] = $code->getLabel();
        }

        return $options;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'primary',
            self::Supplier => 'info',
            self::Subcontractor => 'info',
            self::Partner => 'success',
            self::Employer => 'primary',
            self::Investor => 'warning',
            self::Consultant => 'gray',
            self::Carrier => 'gray',
            self::Authority => 'danger',
            self::Association => 'success',
        };
    }
}
