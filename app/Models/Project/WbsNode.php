<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\NodeStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\Project;
use App\Models\Project\WbsCbsMapping;
use App\Policies\WbsNodePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('wbs_nodes')]
#[Fillable(['project_id', 'parent_id', 'wbs_code', 'name', 'level', 'sort_order', 'status'])]
#[UsePolicy(WbsNodePolicy::class)]
class WbsNode extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
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

    public function cbsMappings(): HasMany
    {
        return $this->hasMany(WbsCbsMapping::class, 'wbs_node_id');
    }
}
