<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportReviewMode;
use App\Enums\Report\ReportSubjectKind;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use Illuminate\Support\Collection;

/** Urun / bilesen raporu: teslim ve hata sayilari (sayisal) + kalite ve tedarikci notlari. */
final class ComponentPerformanceReportTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'component_performance';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Product;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Numeric;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::Component;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Range;
    }

    public function periodRequired(): bool
    {
        return false;
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
            ReportField::integer('delivered_qty')->min(0)->required()->metric('adet'),
            ReportField::integer('defect_count')->min(0)->required()->metric('adet'),
            ReportField::decimal('unit_cost')->min(0)->metric(),
            ReportField::longText('quality_note', 4)->required()->summary(),
            ReportField::longText('supplier_note', 2),
            ReportField::longText('improvement', 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $metrics = parent::metrics($payload, $items);

        if (($metrics['delivered_qty'] ?? 0.0) > 0) {
            $metrics['defect_rate'] = round(($metrics['defect_count'] ?? 0.0) / $metrics['delivered_qty'] * 100, 2);
        }

        return $metrics;
    }

    /**
     * @return array<string, string>
     */
    protected function extraMetricUnits(): array
    {
        return ['defect_rate' => '%'];
    }
}
