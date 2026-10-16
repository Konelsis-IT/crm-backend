<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Sabit katalog turleri (D-189, B52): Urun, Hizmet, Genel gider, Idari kadro.
 * Excel maliyet listesinde Urun/Hizmet ayrilmaz; aktarim urun olarak acar,
 * kalem kartinda Hizmet'e cevrilebilir.
 *
 * D-190 (12 Ekim 2026, kullanici: "Sirket ici ayri bir menu olsa da direk
 * turu farkli olamaz ki; sirket icindeki bilgisayar ayni zamanda urundur,
 * Claude aboneligi ayni zamanda bir hizmettir"): "Sirket ici" tur degil,
 * kullanim yeridir (UsageArea, B52A).
 */
enum CatalogItemKind: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Product = 'product';
    case Service = 'service';
    case GeneralExpense = 'general_expense';
    case AdminStaff = 'admin_staff';

    public function getColor(): string
    {
        return match ($this) {
            self::Product => 'info',
            self::Service => 'primary',
            self::GeneralExpense => 'warning',
            self::AdminStaff => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Product => Heroicon::OutlinedCube,
            self::Service => Heroicon::OutlinedWrenchScrewdriver,
            self::GeneralExpense => Heroicon::OutlinedReceiptPercent,
            self::AdminStaff => Heroicon::OutlinedUserGroup,
        };
    }

    /** Ayni ad bu turlerde tek kalemdir (urun / hizmet ayrimi Excel'de yok). */
    public function family(): array
    {
        return match ($this) {
            self::Product, self::Service => [self::Product, self::Service],
            default => [$this],
        };
    }

    /** Satin almaya gonderilebilen (siparis edilen) turler. */
    public function isPurchasable(): bool
    {
        return in_array($this, [self::Product, self::Service, self::GeneralExpense], true);
    }

    /** Kullanim yeri (Sirket ici / Saha) secilen turler. */
    public function hasUsageArea(): bool
    {
        return in_array($this, [self::Product, self::Service], true);
    }
}
