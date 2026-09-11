<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ComponentScopeState;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\Project;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\ProjectComponentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('project_components')]
#[Fillable([
    'project_id', 'component_definition_id', 'scope_state', 'capacity_value', 'capacity_uom_id', 'note',
])]
#[UsePolicy(ProjectComponentPolicy::class)]
class ProjectComponent extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_state' => ComponentScopeState::class,
            'capacity_value' => 'decimal:6',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ComponentDefinition::class, 'component_definition_id');
    }

    public function capacityUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'capacity_uom_id');
    }
}
