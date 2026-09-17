<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportSubjectKind;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;

/**
 * Insan Kaynaklari gorusu: IK yetkisi olan personel (Shield izni
 * `AuthorHrEvaluation:Report`) bir personel hakkinda devam, uyum, egitim ve
 * disiplin notu yazar. Gizlidir.
 */
final class PersonnelHrEvaluationTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'personnel_hr_evaluation';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Personnel;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Evaluation;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::Personnel;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Range;
    }

    public function authorRule(): ReportAuthorRule
    {
        return ReportAuthorRule::Hr;
    }

    public function isConfidential(): bool
    {
        return true;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::rating('attendance')->required()->metric(),
            ReportField::rating('compliance')->required()->metric(),
            ReportField::choice('recommendation', ['none', 'training', 'warning', 'promotion_review'])->required(),
            ReportField::longText('overall', 4)->required()->summary(),
            ReportField::longText('training_status', 3),
            ReportField::longText('disciplinary_note', 3),
        ];
    }
}
