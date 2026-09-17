<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportReviewMode;
use App\Enums\Report\ReportSubjectKind;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;

/** Teklif degerlendirme raporu: kazanma olasiligi, rakip/fiyat konumu (sayisal) + guclu/zayif yanlar, oneri. */
final class ProposalAssessmentReportTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'proposal_assessment';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Proposal;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Narrative;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::Proposal;
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
            ReportField::percent('win_probability_pct')->required()->metric(),
            ReportField::integer('competitor_count')->min(0)->metric('adet'),
            ReportField::choice('price_position', ['low', 'competitive', 'high']),
            ReportField::choice('recommendation', ['submit', 'revise', 'withdraw'])->required(),
            ReportField::longText('summary', 4)->required()->summary(),
            ReportField::longText('strengths', 3),
            ReportField::longText('weaknesses', 3),
        ];
    }
}
