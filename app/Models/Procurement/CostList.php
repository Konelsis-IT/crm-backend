<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Policies\CostListPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Teklif surumune yuklenmis maliyet Excel'inin aktarimi (B52, D-189). Ayni
 * surumde ayni belge revizyonu bir kez aktarilir; satirlar `lines`.
 */
#[Table('cost_lists')]
#[Fillable([
    'proposal_id', 'proposal_version_id', 'proposal_version_scope_id', 'document_revision_id', 'source_file_name',
    'currency_code', 'total_cost', 'total_sale', 'line_count',
])]
#[UsePolicy(CostListPolicy::class)]
class CostList extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:4',
            'total_sale' => 'decimal:4',
            'line_count' => 'integer',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function proposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function versionScope(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionScope::class, 'proposal_version_scope_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CostListLine::class, 'cost_list_id')->orderBy('sort_order');
    }
}
