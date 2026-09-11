<?php

declare(strict_types=1);

namespace App\Enums\Personnel;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrainingKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Internal = 'internal';
    case External = 'external';
    case Online = 'online';
    case OnTheJob = 'on_the_job';

    public function getColor(): string
    {
        return match ($this) {
            self::Internal => 'primary',
            self::External => 'info',
            self::Online => 'success',
            self::OnTheJob => 'warning',
        };
    }
}
