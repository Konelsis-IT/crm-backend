<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ComplianceState;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\TenderRequirement;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ComplianceItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('compliance_items')]
#[Fillable([
    'proposal_version_id', 'tender_requirement_id', 'requirement_code', 'description', 'compliance_state', 'note',
    'sort_order',
])]
#[UsePolicy(ComplianceItemPolicy::class)]
class ComplianceItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'compliance_state' => ComplianceState::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function tenderRequirement(): BelongsTo
    {
        return $this->belongsTo(TenderRequirement::class, 'tender_requirement_id');
    }
}
