<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentLinkRole: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case Attachment = 'attachment';
    case Evidence = 'evidence';
    case Reference = 'reference';
    case Deliverable = 'deliverable';
    case Source = 'source';

    public function getColor(): string
    {
        return match ($this) {
            self::Attachment => 'gray',
            self::Evidence => 'success',
            self::Reference => 'info',
            self::Deliverable => 'primary',
            self::Source => 'warning',
        };
    }
}
