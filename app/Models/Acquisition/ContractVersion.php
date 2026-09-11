<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ContractVersionStatus;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\ContractDocument;
use App\Models\Acquisition\ContractMilestone;
use App\Models\Acquisition\ContractObligation;
use App\Models\Acquisition\ContractParty;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Reference\Currency;
use App\Policies\ContractVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('contract_versions')]
#[Fillable([
    'contract_id', 'version_no', 'locale', 'currency_code', 'contract_value', 'version_hash', 'summary', 'status',
    'effective_from', 'effective_until', 'approved_by_personnel_id', 'approved_at', 'executed_at',
])]
#[UsePolicy(ContractVersionPolicy::class)]
class ContractVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'contract_value' => 'decimal:4',
            'status' => ContractVersionStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(ContractParty::class, 'contract_version_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class, 'contract_version_id');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(ContractObligation::class, 'contract_version_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class, 'contract_version_id');
    }
}
