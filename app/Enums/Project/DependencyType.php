<?php

declare(strict_types=1);

namespace App\Enums\Project;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DependencyType: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case FS = 'FS';
    case SS = 'SS';
    case FF = 'FF';
    case SF = 'SF';

    public function getColor(): string
    {
        return match ($this) {
            self::FS => 'gray',
            self::SS => 'gray',
            self::FF => 'gray',
            self::SF => 'gray',
        };
    }
}
