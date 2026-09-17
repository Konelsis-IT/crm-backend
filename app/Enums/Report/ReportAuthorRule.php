<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Taslagi kim yazabilir (D-86):
 *  - anyone          : her aktif personel.
 *  - subject_manager : konu personelin dogrudan amiri ya da departman yoneticisi.
 *  - hr              : Insan Kaynaklari yetkisi (Shield izni `AuthorHrEvaluation:Report`).
 *  - unit_manager    : en az bir departmani yoneten personel.
 * Sistem yoneticisi her taslagi yazabilir.
 */
enum ReportAuthorRule: string implements HasLabel
{
    use HasTranslatedLabel;

    case Anyone = 'anyone';
    case SubjectManager = 'subject_manager';
    case Hr = 'hr';
    case UnitManager = 'unit_manager';
}
