<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Maliyet listesi satirinin sekmesi (D-187 / D-189): Excel'deki
 * Birim_Fiyat_GES_KESIF -> Urun/Hizmet; "Idari Kadro ve Genel Giderler"
 * sayfasi -> Idari kadro ve Genel giderler.
 */
enum CostLineType: string implements HasLabel
{
    use HasTranslatedLabel;

    case ProductService = 'product_service';
    case AdminStaff = 'admin_staff';
    case GeneralExpense = 'general_expense';

    /** Excel'den yeni katalog kalemi acilirken kullanilan tur. */
    public function catalogKind(): CatalogItemKind
    {
        return match ($this) {
            self::ProductService => CatalogItemKind::Product,
            self::AdminStaff => CatalogItemKind::AdminStaff,
            self::GeneralExpense => CatalogItemKind::GeneralExpense,
        };
    }
}
