<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\ProjectOrigin;
use App\Enums\Project\ProjectStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCode;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\Document;
use App\Models\Document\FileObject;
use App\Models\Party\Party;
use App\Models\Party\PartyLicense;
use App\Models\Personnel\Personnel;
use App\Models\Project\CbsNode;
use App\Models\Project\CommercialClarification;
use App\Models\Project\CommercialExposure;
use App\Models\Project\DelayEvent;
use App\Models\Project\DepartmentHandoff;
use App\Models\Project\Milestone;
use App\Models\Project\ProgressSnapshot;
use App\Models\Project\ProjectChange;
use App\Models\Project\ProjectComponent;
use App\Models\Project\ProjectDecision;
use App\Models\Project\ProjectFocusHistory;
use App\Models\Project\ProjectIssue;
use App\Models\Project\ProjectRisk;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\ScheduleBaseline;
use App\Models\Project\StageTemplateVersion;
use App\Models\Project\WbsNode;
use App\Models\Project\WorkPackage;
use App\Models\Reference\Country;
use App\Models\Reference\Currency;
use App\Models\Reference\LegalEntity;
use App\Models\Reference\SecurityClassification;
use App\Policies\ProjectPolicy;
use App\Models\Report\Report;
use App\Models\WorkRequest\WorkRequest;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('projects')]
#[Fillable([
    'business_case_id', 'project_business_code_id', 'accepted_handoff_version_id', 'name', 'customer_party_id',
    'legal_entity_id', 'project_manager_employee_id', 'country_code', 'timezone', 'site_location', 'currency_code',
    'contract_value_snapshot', 'stage_template_version_id', 'criticality_profile', 'status',
    'current_macro_gate_code', 'primary_focus_workstream_id', 'cover_file_object_id', 'classification_id',
    'planned_start_on', 'planned_finish_on', 'actual_start_on', 'actual_finish_on',
    'origin', 'legacy_reference', 'description', 'site_address_line1', 'site_address_line2', 'site_district',
    'site_city', 'site_postal_code', 'site_country_code', 'site_latitude', 'site_longitude', 'site_note',
])]
#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_value_snapshot' => 'decimal:4',
            'criticality_profile' => CriticalityProfile::class,
            'status' => ProjectStatus::class,
            'origin' => ProjectOrigin::class,
            'site_latitude' => 'decimal:7',
            'site_longitude' => 'decimal:7',
            'planned_start_on' => 'date',
            'planned_finish_on' => 'date',
            'actual_start_on' => 'date',
            'actual_finish_on' => 'date',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function businessCode(): BelongsTo
    {
        return $this->belongsTo(BusinessCode::class, 'project_business_code_id');
    }

    public function acceptedHandoffVersion(): BelongsTo
    {
        return $this->belongsTo(OperationHandoffVersion::class, 'accepted_handoff_version_id');
    }

    public function customerParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id');
    }

    /**
     * Musterinin (yatirimci / is veren) lisanslari (D-94): proje kartinda
     * salt okunur gorunur; kayitlar taraf kartinda yonetilir.
     */
    public function customerLicenses(): HasMany
    {
        return $this->hasMany(PartyLicense::class, 'party_id', 'customer_party_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'project_manager_employee_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function stageTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(StageTemplateVersion::class, 'stage_template_version_id');
    }

    public function primaryFocusWorkstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'primary_focus_workstream_id');
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'cover_file_object_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(SecurityClassification::class, 'classification_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProjectComponent::class, 'project_id');
    }

    public function workstreams(): HasMany
    {
        return $this->hasMany(ProjectWorkstream::class, 'project_id');
    }

    public function focusHistories(): HasMany
    {
        return $this->hasMany(ProjectFocusHistory::class, 'project_id');
    }

    public function wbsNodes(): HasMany
    {
        return $this->hasMany(WbsNode::class, 'project_id');
    }

    public function cbsNodes(): HasMany
    {
        return $this->hasMany(CbsNode::class, 'project_id');
    }

    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class, 'project_id');
    }

    public function stageInstances(): HasMany
    {
        return $this->hasMany(ProjectStageInstance::class, 'project_id');
    }

    public function departmentHandoffs(): HasMany
    {
        return $this->hasMany(DepartmentHandoff::class, 'project_id');
    }

    public function scheduleBaselines(): HasMany
    {
        return $this->hasMany(ScheduleBaseline::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'project_id');
    }

    public function progressSnapshots(): HasMany
    {
        return $this->hasMany(ProgressSnapshot::class, 'project_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProjectIssue::class, 'project_id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class, 'project_id');
    }

    public function delayEvents(): HasMany
    {
        return $this->hasMany(DelayEvent::class, 'project_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ProjectChange::class, 'project_id');
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(CommercialClarification::class, 'project_id');
    }

    public function exposures(): HasMany
    {
        return $this->hasMany(CommercialExposure::class, 'project_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ProjectDecision::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'project_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class, 'project_id')->orderBy('sort_order')->orderBy('id');
    }

    public function coverPhoto(): HasOne
    {
        return $this->hasOne(ProjectPhoto::class, 'project_id')->where('is_cover', true);
    }

    public function supplyItems(): HasMany
    {
        return $this->hasMany(ProjectSupplyItem::class, 'project_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ProjectTeamMember::class, 'project_id');
    }

    public function siteCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'site_country_code', 'code');
    }

    /** Saha adresinin tek satirlik ozeti; adres girilmemisse eski site_location. */
    public function siteAddressLine(): ?string
    {
        $parts = array_filter([
            $this->site_address_line1,
            $this->site_address_line2,
            trim(implode(' ', array_filter([$this->site_postal_code, $this->site_district]))),
            $this->site_city,
        ], static fn (?string $part): bool => filled($part));

        if ($parts === []) {
            return $this->site_location;
        }

        return implode(', ', $parts);
    }

    /** Bu kayda bagli raporlar (B10A, D-86). */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'subject_project_id');
    }

    /** Bu kayitla ilgili talepler (B11B). */
    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class, 'project_id');
    }
}
