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

/** Proje durum raporu: ilerleme yuzdeleri (sayisal) + durum ozeti, riskler, sonraki adimlar (yorumsal). */
final class ProjectStatusReportTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'project_status';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Project;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Narrative;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::Project;
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
            ReportField::percent('progress_pct')->required()->metric(),
            ReportField::percent('planned_pct')->metric(),
            ReportField::choice('health', ['on_track', 'at_risk', 'delayed'])->required(),
            ReportField::integer('open_issue_count')->min(0)->metric('adet'),
            ReportField::longText('summary', 4)->required()->summary(),
            ReportField::longText('risks', 3),
            ReportField::longText('next_steps', 3),
            ReportField::longText('decisions_needed', 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $metrics = parent::metrics($payload, $items);

        if (isset($metrics['progress_pct'], $metrics['planned_pct'])) {
            $metrics['progress_deviation'] = round($metrics['progress_pct'] - $metrics['planned_pct'], 2);
        }

        return $metrics;
    }

    /**
     * @return array<string, string>
     */
    protected function extraMetricUnits(): array
    {
        return ['progress_deviation' => '%'];
    }
}
