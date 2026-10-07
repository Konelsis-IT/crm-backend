<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportReviewMode;
use App\Reports\ReportField;

/**
 * Haftalik calisma raporu: haftanin panosu + ozet, gorusmeler, yazilan
 * raporlar; dogrudan amir inceler.
 *
 * D-167 (6 Ekim 2026 kullanici karari): Is panosundaki "Haftayi kapat"
 * yaninda Raporlar > Rapor yaz ekranindan da her personel yazar; haftanin
 * isleri, gorusme notlari ve yazilan raporlari oneri olarak gelir.
 */
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

    /** D-167: Rapor yaz ekraninda da secilir (D-117'nin bu taslak icin geri alinmasi). */
    public function isManualEntry(): bool
    {
        return true;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::longText('summary', 4)->required()->summary(),
            ReportField::sources('meetings', ReportField::SOURCE_MEETING_NOTES),
            ReportField::sources('written_reports', ReportField::SOURCE_REPORTS),
            ReportField::longText('achievements', 3),
            ReportField::longText('blockers', 2),
            ReportField::lines('next_week_plan', 3),
        ];
    }
}
