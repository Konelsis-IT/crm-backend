<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Models\Acquisition\ContractVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ContractMilestonePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('contract_milestones')]
#[Fillable([
    'contract_version_id', 'milestone_code', 'name', 'planned_on', 'payment_pct', 'payment_amount', 'description',
])]
#[UsePolicy(ContractMilestonePolicy::class)]
class ContractMilestone extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_on' => 'date',
            'payment_pct' => 'decimal:4',
            'payment_amount' => 'decimal:4',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }
}
