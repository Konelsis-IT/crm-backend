<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentRevisionFileRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Original = 'original';
    case Native = 'native';
    case Pdf = 'pdf';
    case Preview = 'preview';
    case Thumbnail = 'thumbnail';
    case SignaturePage = 'signature_page';

    public function getColor(): string
    {
        return match ($this) {
            self::Original => 'primary',
            self::Native => 'gray',
            self::Pdf => 'danger',
            self::Preview => 'info',
            self::Thumbnail => 'gray',
            self::SignaturePage => 'success',
        };
    }
}
