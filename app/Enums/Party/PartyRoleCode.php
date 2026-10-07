<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PartyRoleCode: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    // Musteri ve Yatirimci (D-167, 6 Ekim 2026 kullanici karari: "musteri,
    // isveren, yatirimci 3'u de ayni anlami tasiyor ... tek isimde topla"):
    // ikisi de Isveren'dir. Durumlar eski kayitlar ve gecmis icin kalir, etiketi
    // "Isveren"dir; secilemez (available) ve servis yeni satiri Isveren yazar
    // (PartyRoleService, normalize). B46 eski satirlari Isveren'e cevirir.
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
     * kullanici karari). Musteri ve Yatirimci da yoktur; ikisi Isveren'de
     * toplandi (D-167) ve Isveren, Musteri'nin yerini alarak ilk siradadir
     * (Taraflar sekmeleri, secim listeleri).
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return [self::Employer, ...array_values(array_filter(
            self::cases(),
            static fn (self $code): bool => $code !== self::Association && $code !== self::Employer && ! $code->isLegacy(),
        ))];
    }

    /**
     * Secim listesi. $current eski bir kodsa (Musteri / Yatirimci) duzenleme
     * penceresi bos kalmasin diye listeye eklenir (D-167).
     *
     * @return array<string, string>
     */
    public static function availableOptions(self|string|null $current = null): array
    {
        $options = [];

        foreach (self::available() as $code) {
            $options[$code->value] = $code->getLabel();
        }

        $current = $current instanceof self ? $current : self::tryFrom((string) $current);

        if ($current !== null && ! isset($options[$current->value])) {
            $options[$current->value] = $current->getLabel();
        }

        return $options;
    }

    /** Isveren'de toplanan eski kod mu (Musteri / Yatirimci, D-167). */
    public function isLegacy(): bool
    {
        return $this === self::Customer || $this === self::Investor;
    }

    /** Ekranda ve sayimda kullanilan asil tip: eski kodlar Isveren'dir (D-167). */
    public function canonical(): self
    {
        return $this->isLegacy() ? self::Employer : $this;
    }

    /**
     * Veritabaninda bu tipi temsil eden kodlar. Isveren icin B46 uygulanmamis
     * ortamdaki eski Musteri / Yatirimci satirlari da sayilir (D-167).
     *
     * @return list<string>
     */
    public function storedValues(): array
    {
        return $this === self::Employer
            ? [self::Employer->value, self::Customer->value, self::Investor->value]
            : [$this->value];
    }

    /**
     * Gelen tip kodunu yazilacak koda indirger: Musteri / Yatirimci -> Isveren
     * (D-167). Enum ornegi ya da metin kabul eder; bos ise null.
     */
    public static function normalize(mixed $code): ?string
    {
        $value = $code instanceof BackedEnum ? (string) $code->value : (is_scalar($code) ? trim((string) $code) : '');

        if ($value === '') {
            return null;
        }

        return self::tryFrom($value)?->canonical()->value ?? $value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'primary',
            self::Supplier => 'info',
            self::Subcontractor => 'info',
            self::Partner => 'success',
            self::Employer => 'primary',
            self::Investor => 'primary',
            self::Consultant => 'gray',
            self::Carrier => 'gray',
            self::Authority => 'danger',
            self::Association => 'success',
        };
    }
}
