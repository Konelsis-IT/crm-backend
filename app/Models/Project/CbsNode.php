<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Acquisition\CostCategory;
use App\Enums\Project\NodeStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\Project;
use App\Models\Project\WbsCbsMapping;
use App\Policies\CbsNodePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('cbs_nodes')]
#[Fillable(['project_id', 'parent_id', 'cost_code', 'name', 'cost_category', 'status'])]
#[UsePolicy(CbsNodePolicy::class)]
class CbsNode extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_category' => CostCategory::class,
            'status' => NodeStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function wbsMappings(): HasMany
    {
        return $this->hasMany(WbsCbsMapping::class, 'cbs_node_id');
    }
}
