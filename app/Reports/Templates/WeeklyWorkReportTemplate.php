<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportReviewMode;
use App\Reports\ReportField;

/** Haftalik calisma raporu: haftanin panosu + ozet; dogrudan amir inceler. */
final class WeeklyWorkReportTemplate extends BoardReportTemplate
{
    public const CODE = 'weekly_work';

    public function code(): string
    {
        return self::CODE;
    }

    public function kind(): ReportKind
    {
        return ReportKind::Weekly;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Week;
    }

    public function reviewMode(): ReportReviewMode
    {
        return ReportReviewMode::LineManager;
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
            ReportField::lines('next_week_plan', 3),
        ];
    }
}
