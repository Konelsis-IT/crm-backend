<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Isin is grubu (D-219): projenin departman alt kirim tablosundaki sutunlar
 * (Insaat, Mekanik, Elektrik, Yardimci Sistemler, SCADA, ENH) ve Kabul /
 * Devreye alma.
 */
enum WorkDiscipline: string implements HasLabel
{
    use HasTranslatedLabel;

    case Construction = 'construction';
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';
    case Auxiliary = 'auxiliary';
    case Scada = 'scada';
    case Enh = 'enh';
    case Acceptance = 'acceptance';
}
