<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\DeviationStatus;
use App\Enums\Acquisition\DeviationType;
use App\Models\Acquisition\ComplianceItem;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\DeviationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('deviations')]
#[Fillable([
    'proposal_version_id', 'compliance_item_id', 'deviation_type', 'description', 'justification', 'status',
    'sort_order',
])]
#[UsePolicy(DeviationPolicy::class)]
class Deviation extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deviation_type' => DeviationType::class,
            'status' => DeviationStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function complianceItem(): BelongsTo
    {
        return $this->belongsTo(ComplianceItem::class, 'compliance_item_id');
    }
}
