<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FileDerivationKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Thumbnail = 'thumbnail';
    case Preview = 'preview';
    case PdfRender = 'pdf_render';

    public function getColor(): string
    {
        return match ($this) {
            self::Thumbnail => 'gray',
            self::Preview => 'info',
            self::PdfRender => 'primary',
        };
    }
}
