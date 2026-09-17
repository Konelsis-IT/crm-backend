<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\BusinessSourceKind;
use App\Enums\Acquisition\LifecycleSegment;
use App\Enums\Acquisition\OfferType;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Acquisition\BusinessCode;
use App\Models\Acquisition\BusinessDevelopmentActivity;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\Opportunity;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderNotice;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\Country;
use App\Models\Reference\Currency;
use App\Models\Reference\LegalEntity;
use App\Models\Reference\SecurityClassification;
use App\Policies\BusinessCasePolicy;
use App\Models\Report\Report;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('business_cases')]
#[Fillable([
    'sequence_no', 'legal_entity_id', 'primary_party_id', 'title', 'short_description', 'country_code',
    'currency_code', 'project_type_code', 'source_kind', 'criticality', 'lifecycle_segment', 'acquisition_stage',
    'outcome', 'outcome_reason_code', 'outcome_at', 'owner_employee_id', 'proposal_owner_employee_id',
    'estimated_value', 'classification_id', 'offer_type',
])]
#[UsePolicy(BusinessCasePolicy::class)]
class BusinessCase extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'source_kind' => BusinessSourceKind::class,
            'criticality' => BusinessCriticality::class,
            'offer_type' => OfferType::class,
            'lifecycle_segment' => LifecycleSegment::class,
            'acquisition_stage' => AcquisitionStage::class,
            'outcome' => BusinessOutcome::class,
            'outcome_at' => 'datetime',
            'estimated_value' => 'decimal:4',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function primaryParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'primary_party_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_employee_id');
    }

    public function proposalOwner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'proposal_owner_employee_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(SecurityClassification::class, 'classification_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(BusinessCode::class, 'business_case_id');
    }

    /** Secilen proje kapsam tipleri ve tutarlari (B29, D-101). */
    public function scopes(): HasMany
    {
        return $this->hasMany(BusinessCaseScope::class, 'business_case_id');
    }

    /** TKLF-n kodu. */
    public function offerCode(): ?BusinessCode
    {
        return $this->codes->firstWhere('code_kind', BusinessCodeKind::Offer);
    }

    /** PRJ-n kodu (yalniz devir kabulunden sonra). */
    public function projectCode(): ?BusinessCode
    {
        return $this->codes->firstWhere('code_kind', BusinessCodeKind::Project);
    }

    /**
     * 1:1 firsat kaydinin Filament relation manager'da listelenebilmesi icin
     * HasMany goruntusu; opportunity() ayni satiri BelongsTo/HasOne olarak verir.
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'business_case_id');
    }

    /** Business case basina tek devir; relation manager icin HasMany goruntusu. */
    public function operationHandoffs(): HasMany
    {
        return $this->hasMany(OperationHandoff::class, 'business_case_id');
    }

    public function opportunity(): HasOne
    {
        return $this->hasOne(Opportunity::class, 'business_case_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BusinessDevelopmentActivity::class, 'business_case_id');
    }

    public function tenderNotices(): HasMany
    {
        return $this->hasMany(TenderNotice::class, 'business_case_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'business_case_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'business_case_id');
    }

    public function operationHandoff(): HasOne
    {
        return $this->hasOne(OperationHandoff::class, 'business_case_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'business_case_id');
    }

    /** Projeye donusumde kullanilacak teklif: secili olan, yoksa en son acilan. */
    public function selectedOrLatestProposal(): ?Proposal
    {
        return $this->proposals()->where('is_selected', true)->first()
            ?? $this->proposals()->orderByDesc('id')->first();
    }

    /** Bu kayda bagli raporlar (B10A, D-86). */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'subject_business_case_id');
    }
}
