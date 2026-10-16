<?php

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Konelsis AI teklif okuma isinin durumu (D-192, u-mv0njps9-98cc): teklifte
 * (purchase_quotations.ai_status) tum belgelerin toplu sonucu. Belge basina
 * sonuc QuotationDocumentAiStatus'tadir.
 */
enum QuotationAiStatus: string implements HasColor, HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedSparkles,
            self::Completed => Heroicon::OutlinedCheckBadge,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
