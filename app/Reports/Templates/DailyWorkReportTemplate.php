<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Reports\ReportField;

/** Gunluk calisma raporu: gunun is panosu + ozet, engeller, yarin plani. Inceleme yok. */
final class DailyWorkReportTemplate extends BoardReportTemplate
{
    public function code(): string
    {
        return 'daily_work';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Daily;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Day;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::longText('summary', 3)->required()->summary(),
            ReportField::longText('blockers', 2),
            ReportField::longText('tomorrow_plan', 2),
        ];
    }
}
