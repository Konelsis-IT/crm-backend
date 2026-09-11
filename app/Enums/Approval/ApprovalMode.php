<?php

declare(strict_types=1);

namespace App\Enums\Approval;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Adimlarin nasil islendigi: sirali (bir bir), paralel (hepsi ayni anda,
 * hepsi tamamlanmali) ya da nisap (ayni anda, quorum_count kadari yeter).
 */
enum ApprovalMode: string implements HasLabel
{
    use HasTranslatedLabel;

    case Sequential = 'sequential';
    case Parallel = 'parallel';
    case Quorum = 'quorum';
}
