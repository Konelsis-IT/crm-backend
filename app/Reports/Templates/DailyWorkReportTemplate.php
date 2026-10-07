<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Reports\ReportField;

/**
 * Gunluk calisma raporu: gunun is panosu + ozet, gorusmeler, yazilan raporlar,
 * engeller, yarin plani. Inceleme yok.
 *
 * D-167 (6 Ekim 2026 kullanici karari): Is panosundaki "Gunu kapat" yaninda
 * Raporlar > Rapor yaz ekranindan da her personel yazar. Rapor acilirken
 * sistem o gunun islerini, gorusme notlarini ve ilgili kayitlara yazilan
 * raporlari onerir; kaldirilmayan oneri rapora girer.
 */
final class DailyWorkReportTemplate extends BoardReportTemplate
{
    public const CODE = 'daily_work';

    public function code(): string
    {
        return self::CODE;
    }

    public function kind(): ReportKind
    {
        return ReportKind::Daily;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Day;
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
            ReportField::longText('summary', 3)->required()->summary(),
            ReportField::sources('meetings', ReportField::SOURCE_MEETING_NOTES),
            ReportField::sources('written_reports', ReportField::SOURCE_REPORTS),
            ReportField::longText('blockers', 2),
            ReportField::lines('tomorrow_plan', 2),
        ];
    }
}
