<?php

declare(strict_types=1);

namespace App\Enums\Reference;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

enum UomDimension: string implements HasLabel
{
    use HasTranslatedLabel;

    case Count = 'count';
    case Length = 'length';
    case Area = 'area';
    case Volume = 'volume';
    case Mass = 'mass';
    case Time = 'time';
    case Energy = 'energy';
    case Power = 'power';
    case Voltage = 'voltage';
    case Current = 'current';
    case Temperature = 'temperature';
    case Other = 'other';
}
