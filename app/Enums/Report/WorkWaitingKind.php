<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/** "Kimden bekleniyor" alaninin turu (B36, D-115): personel, taraf ya da serbest metin. */
enum WorkWaitingKind: string implements HasLabel
{
    use HasTranslatedLabel;

    case Personnel = 'personnel';
    case Party = 'party';
    case Text = 'text';
}
