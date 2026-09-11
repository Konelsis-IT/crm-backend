<?php

declare(strict_types=1);

namespace App\Enums\Chat;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** 08 SS2.3 `message_kind` (+ `document_share`: kontrollu dokuman paylasimi). */
enum MessageKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case Text = 'text';
    case System = 'system';
    case FileShare = 'file_share';
    case LinkShare = 'link_share';
    case DocumentShare = 'document_share';
}
