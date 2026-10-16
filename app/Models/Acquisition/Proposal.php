<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CostList;
use App\Models\Procurement\CostListLine;
use App\Policies\ProposalPolicy;
use App\Models\Report\Report;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table('proposals')]
#[Fillable([
    'business_case_id', 'proposal_no', 'title', 'owner_employee_id', 'current_version_id', 'status', 'is_selected',
    'offer_status',
    // B43 (D-155): taslak ve kaldigi adim.
    'is_draft', 'draft_step',
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
            'is_draft' => 'boolean',
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

    /**
     * Teklif durumu (D-195, 12 Ekim 2026 kullanici karari: "Her revizyonda yeni
     * surum gelsin, oncekinin durumu da kaybedilmesin. Yani her revizyon'un
     * durumu kendine ait olsun"). B55 ile her surumun kendi teklif durumu vardir;
     * teklifin durumu guncel surumun durumudur ve proposals.offer_status onun
     * kopyasidir (listeler, sekmeler, panolar kopyayi okur; ProposalService
     * ikisini birlikte yazar). B55 oncesi ya da surumsuz teklifte kopya okunur.
     */
    public function currentOfferStatus(): ?OfferStatus
    {
        if ($this->current_version_id === null || ! SchemaReadiness::hasBatch('B55')) {
            return $this->offer_status;
        }

        return $this->currentVersion?->offer_status ?? $this->offer_status;
    }

    /**
     * Teklifin maliyet kalemleri (B52, D-189): butun aktarilmis maliyet
     * listelerinin satirlari. Teklif sayfasinin Maliyet kalemleri sekmesi
     * bunu guncel surume suzer (CostListQueries::linesForVersion).
     */
    public function costItems(): HasManyThrough
    {
        return $this->hasManyThrough(CostListLine::class, CostList::class, 'proposal_id', 'cost_list_id');
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

    /** Bu teklifin konusuldugu gorusme notlari (B41, D-137). */
    public function meetingNotes(): BelongsToMany
    {
        return $this->belongsToMany(PartyMeetingNote::class, 'party_meeting_note_proposals', 'proposal_id', 'party_meeting_note_id');
    }
}
