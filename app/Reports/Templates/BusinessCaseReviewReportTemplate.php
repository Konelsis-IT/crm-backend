<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportReviewMode;
use App\Enums\Report\ReportSubjectKind;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;

/** Is dosyasi degerlendirme raporu: genel degerlendirme, musteri iliskisi, risk seviyeleri, oneri ve aksiyonlar. */
final class BusinessCaseReviewReportTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'business_case_review';
    }

    public function kind(): ReportKind
    {
        return ReportKind::BusinessCase;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Narrative;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::BusinessCase;
    }

    public function reviewMode(): ReportReviewMode
    {
        return ReportReviewMode::OrgUnitManager;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::choice('commercial_risk', ['low', 'medium', 'high'])->required(),
            ReportField::choice('technical_risk', ['low', 'medium', 'high'])->required(),
            ReportField::choice('recommendation', ['pursue', 'hold', 'drop'])->required(),
            ReportField::longText('summary', 4)->required()->summary(),
            ReportField::longText('customer_relationship', 3),
            ReportField::longText('actions', 3),
        ];
    }
}
