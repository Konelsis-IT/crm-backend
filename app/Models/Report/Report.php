<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Enums\Report\ReportKind;
use App\Enums\Report\ReportStatus;
use App\Enums\Report\ReportSubjectKind;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\Project;
use App\Policies\ReportPolicy;
use App\Reports\ReportTemplate;
use App\Reports\ReportTemplateRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rapor (B10A, D-86). Taslak kodda tanimlidir (App\Reports\Templates);
 * taslaga ozel cevaplar `payload` JSON'undadir, pano kalemleri `items`,
 * KPI projeksiyonu `metrics`. Konu baglari ayri FK kolonlaridir.
 */
#[Table('reports')]
#[Fillable([
    'report_no', 'template_code', 'kind', 'title', 'status', 'is_confidential',
    'author_personnel_id', 'author_org_unit_id', 'subject_kind',
    'subject_personnel_id', 'subject_project_id', 'subject_component_definition_id', 'subject_proposal_id', 'subject_business_case_id',
    'period_start', 'period_end', 'summary', 'payload',
    'submitted_at', 'reviewer_personnel_id', 'reviewed_at', 'review_comment', 'revision_count',
])]
#[UsePolicy(ReportPolicy::class)]
class Report extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReportKind::class,
            'status' => ReportStatus::class,
            'subject_kind' => ReportSubjectKind::class,
            'is_confidential' => 'boolean',
            'period_start' => 'date',
            'period_end' => 'date',
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'revision_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'author_personnel_id');
    }

    public function authorOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'author_org_unit_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reviewer_personnel_id');
    }

    public function subjectPersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'subject_personnel_id');
    }

    public function subjectProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'subject_project_id');
    }

    public function subjectComponent(): BelongsTo
    {
        return $this->belongsTo(ComponentDefinition::class, 'subject_component_definition_id');
    }

    public function subjectProposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'subject_proposal_id');
    }

    public function subjectBusinessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'subject_business_case_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportItem::class, 'report_id')->orderBy('sort_order')->orderBy('id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ReportMetric::class, 'report_id')->orderBy('id');
    }

    /** Kodda tanimli taslak; kod artik kayitli degilse null. */
    public function template(): ?ReportTemplate
    {
        return app(ReportTemplateRegistry::class)->find((string) $this->template_code);
    }

    /** Taslagin ekran adi; taninmayan kod oldugu gibi gosterilir. */
    public function templateName(): string
    {
        return $this->template()?->name() ?? (string) $this->template_code;
    }

    /** Bagli konu kaydi (personel, proje, bilesen, teklif, is dosyasi), varsa. */
    public function subject(): ?Model
    {
        $relation = $this->subject_kind->relation();

        if ($relation === null) {
            return null;
        }

        $subject = $this->getRelationValue($relation);

        return $subject instanceof Model ? $subject : null;
    }

    public function subjectLabel(): ?string
    {
        return $this->subject_kind->labelFor($this->subject());
    }

    /** Donem metni: tek gun, aralik ya da bos. */
    public function periodLabel(): ?string
    {
        $start = $this->period_start;
        $end = $this->period_end;

        if ($start === null && $end === null) {
            return null;
        }

        if ($start !== null && ($end === null || $start->isSameDay($end))) {
            return $start->format('d.m.Y');
        }

        return sprintf('%s – %s', $start?->format('d.m.Y') ?? '…', $end?->format('d.m.Y') ?? '…');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Taslaga ozel cevap.
     */
    public function answer(string $key): mixed
    {
        return data_get($this->payload ?? [], $key);
    }
}
