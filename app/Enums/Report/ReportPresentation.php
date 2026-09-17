<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Raporun olusturulma ve goruntulenme bicimi (D-86):
 *  - board      : is kalemleri durum sutunlarinda ("is panosu"); gunluk/haftalik/aylik.
 *  - narrative  : yorumsal metin alanlari agirlikli.
 *  - numeric    : sayisal olcumler agirlikli; KPI projeksiyonu.
 *  - evaluation : kisi hakkinda puanlama + gorus; gizli.
 */
enum ReportPresentation: string implements HasLabel
{
    use HasTranslatedLabel;

    case Board = 'board';
    case Narrative = 'narrative';
    case Numeric = 'numeric';
    case Evaluation = 'evaluation';
}
