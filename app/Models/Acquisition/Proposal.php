<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ProposalPolicy;
use App\Models\Report\Report;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table('proposals')]
#[Fillable([
    'business_case_id', 'proposal_no', 'title', 'owner_employee_id', 'current_version_id', 'status', 'is_selected',
    'offer_status',
])]
#[UsePolicy(ProposalPolicy::class)]
class Proposal extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'offer_status' => OfferStatus::class,
            'is_selected' => 'boolean',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_employee_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class, 'proposal_id');
    }

    /** Tum surumlerin teklif belgeleri (teklif sayfasinin Dokumanlar tablosu, D-113). */
    public function versionDocuments(): HasManyThrough
    {
        return $this->hasManyThrough(ProposalDocument::class, ProposalVersion::class, 'proposal_id', 'proposal_version_id');
    }

    /** Bu kayda bagli raporlar (B10A, D-86). */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'subject_proposal_id');
    }
}
