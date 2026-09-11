<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ResponsiblePartyRole;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ResponsibilityMatrixItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('responsibility_matrix_items')]
#[Fillable([
    'proposal_version_id', 'scope_code', 'scope_description', 'responsible_party_role', 'note', 'sort_order',
])]
#[UsePolicy(ResponsibilityMatrixItemPolicy::class)]
class ResponsibilityMatrixItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responsible_party_role' => ResponsiblePartyRole::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }
}
