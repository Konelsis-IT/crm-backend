<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\Report\ReportSubjectKind;
use App\Exceptions\Report\TemplateNotFoundException;
use App\Reports\Templates\BusinessCaseReviewReportTemplate;
use App\Reports\Templates\ComponentPerformanceReportTemplate;
use App\Reports\Templates\CoordinationBoardReportTemplate;
use App\Reports\Templates\DailyControlReportTemplate;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Reports\Templates\MonthlyWorkReportTemplate;
use App\Reports\Templates\PersonnelHrEvaluationTemplate;
use App\Reports\Templates\PersonnelManagerEvaluationTemplate;
use App\Reports\Templates\ProjectStatusReportTemplate;
use App\Reports\Templates\ProposalAssessmentReportTemplate;
use App\Reports\Templates\SystemDataReportTemplate;
use App\Reports\Templates\WeeklyWorkReportTemplate;

/**
 * Kodda tanimli rapor taslaklarinin kaydi (D-86). Arayuzden taslak
 * olusturulmaz; yeni taslak bu listeye eklenir.
 */
final class ReportTemplateRegistry
{
    /** @var list<class-string<ReportTemplate>> */
    public const TEMPLATES = [
        DailyWorkReportTemplate::class,
        WeeklyWorkReportTemplate::class,
        MonthlyWorkReportTemplate::class,
        ProjectStatusReportTemplate::class,
        ComponentPerformanceReportTemplate::class,
        ProposalAssessmentReportTemplate::class,
        BusinessCaseReviewReportTemplate::class,
        PersonnelManagerEvaluationTemplate::class,
        PersonnelHrEvaluationTemplate::class,
        SystemDataReportTemplate::class,
        // Is panosu (B36, D-115): yalniz kendi sayfalarindan yazilir.
        DailyControlReportTemplate::class,
        CoordinationBoardReportTemplate::class,
    ];

    /** @var array<string, ReportTemplate>|null */
    private ?array $instances = null;

    /**
     * @return array<string, ReportTemplate>  kod => taslak
     */
    public function all(): array
    {
        if ($this->instances !== null) {
            return $this->instances;
        }

        $instances = [];

        foreach (self::TEMPLATES as $class) {
            $template = new $class;
            $instances[$template->code()] = $template;
        }

        return $this->instances = $instances;
    }

    public function find(?string $code): ?ReportTemplate
    {
        if ($code === null || $code === '') {
            return null;
        }

        return $this->all()[$code] ?? null;
    }

    public function get(string $code): ReportTemplate
    {
        return $this->find($code) ?? throw TemplateNotFoundException::make(['code' => $code]);
    }

    /**
     * Secim listesi; istege bagli suzgec (orn. yazarin yazabilecekleri).
     *
     * @param  (callable(ReportTemplate): bool)|null  $filter
     * @return array<string, string>  kod => ad
     */
    public function options(?callable $filter = null): array
    {
        $options = [];

        foreach ($this->all() as $code => $template) {
            if ($filter !== null && ! $filter($template)) {
                continue;
            }

            $options[$code] = $template->name();
        }

        return $options;
    }

    /**
     * @return array<string, ReportTemplate>
     */
    public function forSubject(ReportSubjectKind $kind): array
    {
        return array_filter($this->all(), fn (ReportTemplate $template): bool => $template->subjectKind() === $kind);
    }
}
