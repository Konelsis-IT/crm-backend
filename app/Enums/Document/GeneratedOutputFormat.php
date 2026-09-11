<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GeneratedOutputFormat: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
    case Docx = 'docx';

    public function getColor(): string
    {
        return match ($this) {
            self::Pdf => 'danger',
            self::Xlsx => 'success',
            self::Docx => 'info',
        };
    }
}
