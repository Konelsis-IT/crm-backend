<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportReviewMode;
use App\Reports\ReportField;

/** Aylik calisma raporu: ayin panosu + basarilar, hedefler, oz degerlendirme; departman yoneticisi inceler. */
final class MonthlyWorkReportTemplate extends BoardReportTemplate
{
    public function code(): string
    {
        return 'monthly_work';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Monthly;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Month;
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
            ReportField::longText('summary', 4)->required()->summary(),
            ReportField::longText('achievements', 3),
            ReportField::longText('blockers', 2),
            ReportField::longText('next_month_targets', 3),
            ReportField::rating('self_score')->metric(),
        ];
    }
}
