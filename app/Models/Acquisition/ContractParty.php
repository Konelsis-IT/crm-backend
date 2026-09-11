<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ContractRole;
use App\Models\Acquisition\ContractVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\ContractPartyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('contract_parties')]
#[Fillable(['contract_version_id', 'party_id', 'contract_role', 'signatory_name'])]
#[UsePolicy(ContractPartyPolicy::class)]
class ContractParty extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_role' => ContractRole::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
