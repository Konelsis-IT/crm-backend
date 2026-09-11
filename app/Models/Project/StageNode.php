<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\OperationGroupDefinition;
use App\Models\Project\StageDependency;
use App\Models\Project\StageRequirementDefinition;
use App\Models\Project\StageTemplateVersion;
use App\Policies\StageNodePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('stage_nodes')]
#[Fillable([
    'stage_template_version_id', 'stage_code', 'name_tr', 'name_en', 'sequence_no', 'is_hard_gate',
    'owner_group_definition_id', 'description',
])]
#[UsePolicy(StageNodePolicy::class)]
class StageNode extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'is_hard_gate' => 'boolean',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(StageTemplateVersion::class, 'stage_template_version_id');
    }

    public function ownerGroup(): BelongsTo
    {
        return $this->belongsTo(OperationGroupDefinition::class, 'owner_group_definition_id');
    }

    public function requirementDefinitions(): HasMany
    {
        return $this->hasMany(StageRequirementDefinition::class, 'stage_node_id');
    }

    public function successorDependencies(): HasMany
    {
        return $this->hasMany(StageDependency::class, 'predecessor_node_id');
    }

    public function predecessorDependencies(): HasMany
    {
        return $this->hasMany(StageDependency::class, 'successor_node_id');
    }
}
