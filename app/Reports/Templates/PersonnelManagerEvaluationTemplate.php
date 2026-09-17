<?php

declare(strict_types=1);

namespace App\Reports\Templates;

use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportPresentation;
use App\Enums\Report\ReportSubjectKind;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use Illuminate\Support\Collection;

/**
 * Yonetici degerlendirmesi: dogrudan amir ya da departman yoneticisi, bir
 * personel hakkinda puanlar ve gorus yazar. Gizlidir; personelin kendisi gormez.
 */
final class PersonnelManagerEvaluationTemplate extends ReportTemplate
{
    public function code(): string
    {
        return 'personnel_manager_evaluation';
    }

    public function kind(): ReportKind
    {
        return ReportKind::Personnel;
    }

    public function presentation(): ReportPresentation
    {
        return ReportPresentation::Evaluation;
    }

    public function subjectKind(): ReportSubjectKind
    {
        return ReportSubjectKind::Personnel;
    }

    public function periodMode(): ReportPeriodMode
    {
        return ReportPeriodMode::Range;
    }

    public function authorRule(): ReportAuthorRule
    {
        return ReportAuthorRule::SubjectManager;
    }

    public function isConfidential(): bool
    {
        return true;
    }

    /**
     * @return list<ReportField>
     */
    protected function fields(): array
    {
        return [
            ReportField::rating('performance')->required()->metric(),
            ReportField::rating('quality')->required()->metric(),
            ReportField::rating('collaboration')->required()->metric(),
            ReportField::rating('discipline')->required()->metric(),
            ReportField::rating('initiative')->required()->metric(),
            ReportField::choice('recommendation', ['retain', 'promote', 'develop', 'warn'])->required(),
            ReportField::longText('overall', 4)->required()->summary(),
            ReportField::longText('strengths', 3),
            ReportField::longText('development_areas', 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public function metrics(array $payload, ?Collection $items = null): array
    {
        $metrics = parent::metrics($payload, $items);
        $scores = array_intersect_key($metrics, array_flip(['performance', 'quality', 'collaboration', 'discipline', 'initiative']));

        if ($scores !== []) {
            $metrics['overall_score'] = round(array_sum($scores) / count($scores), 2);
        }

        return $metrics;
    }

    /**
     * @return array<string, string>
     */
    protected function extraMetricUnits(): array
    {
        return ['overall_score' => 'puan'];
    }
}
