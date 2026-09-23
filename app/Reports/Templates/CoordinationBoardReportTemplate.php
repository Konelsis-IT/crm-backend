<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Reports\ReportField;

/**
 * Yonetim panosu dondurmasi (B36, D-115): tum aktif projelerin kartlari o
 * gunku haliyle tarihli rapor olur (el yazisi koordinasyon listesinin
 * karsiligi). Rapor formundan yazilmaz; Is panosu > Yonetim panosu >
 * "Panoyu dondur" uretir. Inceleme yoktur.
 */
final class CoordinationBoardReportTemplate extends BoardReportTemplate
{
    public const CODE = 'coordination_board';

    public function code(): string
    {
        return self::CODE;
    }

    public function kind(): ReportKind
    {
        return ReportKind::Project;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Day;
    }

    public function isManualEntry(): bool
    {
        return false;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::longText('summary', 3)->summary(),
        ];
    }
}
