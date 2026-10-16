<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Tek bir yuklenen belgenin Konelsis AI sonucu (D-192; kullanici: "Birden
 * fazla belge de yukleyebilir. Teklif belgesi mi diye kontrol edecek. Eger
 * teklif belgesi ise bilgileri dolduracak."). Teklif degilse belge ekte
 * kalir, alanlara dokunulmaz.
 */
enum QuotationDocumentAiStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Quotation = 'quotation';
    case NotQuotation = 'not_quotation';
    case Failed = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Quotation => 'success',
            self::NotQuotation => 'gray',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedSparkles,
            self::Quotation => Heroicon::OutlinedDocumentCheck,
            self::NotQuotation => Heroicon::OutlinedDocumentMinus,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
