<?php

declare(strict_types=1);

namespace App\Enums\Acquisition;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProposalDocumentRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case TechnicalOffer = 'technical_offer';
    case CommercialOffer = 'commercial_offer';
    case SpecCompliance = 'spec_compliance';
    case BrandList = 'brand_list';
    case ResponsibilityMatrix = 'responsibility_matrix';
    case Schedule = 'schedule';
    case SiteSurvey = 'site_survey';
    case SupplierQuote = 'supplier_quote';
    case Kmz = 'kmz';
    case Photo = 'photo';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::TechnicalOffer => 'primary',
            self::CommercialOffer => 'warning',
            self::SpecCompliance => 'info',
            self::BrandList => 'gray',
            self::ResponsibilityMatrix => 'gray',
            self::Schedule => 'info',
            self::SiteSurvey => 'success',
            self::SupplierQuote => 'gray',
            self::Kmz => 'gray',
            self::Photo => 'gray',
            self::Other => 'gray',
        };
    }
}
