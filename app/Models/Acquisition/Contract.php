<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ContractStatus;
use App\Enums\Acquisition\ContractType;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ContractVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\ContractPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('contracts')]
#[Fillable([
    'business_case_id', 'contract_no', 'contract_type', 'customer_party_id', 'current_version_id', 'status',
    'signed_on', 'effective_from',
])]
#[UsePolicy(ContractPolicy::class)]
class Contract extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'status' => ContractStatus::class,
            'signed_on' => 'date',
            'effective_from' => 'date',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function customerParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class, 'contract_id');
    }
}
