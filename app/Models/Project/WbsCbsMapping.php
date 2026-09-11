<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\CbsNode;
use App\Models\Project\WbsNode;
use App\Policies\WbsCbsMappingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('wbs_cbs_mappings')]
#[Fillable(['wbs_node_id', 'cbs_node_id', 'allocation_pct'])]
#[UsePolicy(WbsCbsMappingPolicy::class)]
class WbsCbsMapping extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocation_pct' => 'decimal:4',
        ];
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'wbs_node_id');
    }

    public function cbsNode(): BelongsTo
    {
        return $this->belongsTo(CbsNode::class, 'cbs_node_id');
    }
}
