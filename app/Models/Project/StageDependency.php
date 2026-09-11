<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\StageNode;
use App\Policies\StageDependencyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('stage_dependencies')]
#[Fillable(['predecessor_node_id', 'successor_node_id', 'is_hard'])]
#[UsePolicy(StageDependencyPolicy::class)]
class StageDependency extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hard' => 'boolean',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(StageNode::class, 'predecessor_node_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(StageNode::class, 'successor_node_id');
    }
}
