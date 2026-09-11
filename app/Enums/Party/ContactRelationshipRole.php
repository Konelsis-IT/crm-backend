<?php

declare(strict_types=1);

namespace App\Enums\Party;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactRelationshipRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case DecisionMaker = 'decision_maker';
    case TechnicalContact = 'technical_contact';
    case ProcurementContact = 'procurement_contact';
    case FinanceContact = 'finance_contact';
    case Executive = 'executive';
    case Other = 'other';

    public function getColor(): string
    {
        return match ($this) {
            self::DecisionMaker => 'primary',
            self::TechnicalContact => 'info',
            self::ProcurementContact => 'info',
            self::FinanceContact => 'warning',
            self::Executive => 'success',
            self::Other => 'gray',
        };
    }
}
