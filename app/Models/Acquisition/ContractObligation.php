<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ObligationStatus;
use App\Enums\Acquisition\ObligationType;
use App\Models\Acquisition\ContractVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\ContractObligationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('contract_obligations')]
#[Fillable([
    'contract_version_id', 'obligation_code', 'obligation_type', 'description', 'responsible_party_id', 'due_on',
    'status',
])]
#[UsePolicy(ContractObligationPolicy::class)]
class ContractObligation extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obligation_type' => ObligationType::class,
            'due_on' => 'date',
            'status' => ObligationStatus::class,
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }

    public function responsibleParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'responsible_party_id');
    }
}
