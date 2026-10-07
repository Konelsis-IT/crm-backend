<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Is gelistirme turu (D-167, D-170). Is Gelistirme asamasindaki kayit ya
 * Yatirimci projesi ya da Potansiyel istir. D-167: tur sicakliktan bulunur
 * (0 ya da bos = Yatirimci projesi, 0'dan buyuk = Potansiyel is). D-170 / B47:
 * `business_cases.development_kind` doluysa kayitli tur gecerlidir (listeden
 * gelen potansiyel isler sicakliktan bagimsiz Potansiyel is).
 *
 * Etiketler business_case.kinds altindadir.
 */
enum BusinessDevelopmentKind: string implements HasColor, HasLabel
{
    case InvestorProject = 'investor_project';
    case PotentialJob = 'potential_job';

    public function getLabel(): string
    {
        return (string) __('business_case.kinds.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InvestorProject => 'slate',
            self::PotentialJob => 'amber',
        };
    }

    /** Sicaklik kurali (D-167): 0 ya da bos Yatirimci projesi, 0'dan buyuk Potansiyel is. */
    public static function fromHeat(?int $heat): self
    {
        return (int) ($heat ?? 0) > 0 ? self::PotentialJob : self::InvestorProject;
    }

    /**
     * Gelen degeri (enum, metin ya da bos) kayitli degere cevirir; bos ya da
     * gecersiz deger null (Otomatik: sicakliga gore) olur.
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value instanceof self) {
            return $value->value;
        }

        return is_scalar($value) ? self::tryFrom(trim((string) $value))?->value : null;
    }
}
