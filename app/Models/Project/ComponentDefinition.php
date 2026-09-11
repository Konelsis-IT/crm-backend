<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\ProjectComponent;
use App\Policies\ComponentDefinitionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('component_definitions')]
#[Fillable(['code', 'name_tr', 'name_en', 'discipline', 'status'])]
#[UsePolicy(ComponentDefinitionPolicy::class)]
class ComponentDefinition extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }

    public function projectComponents(): HasMany
    {
        return $this->hasMany(ProjectComponent::class, 'component_definition_id');
    }
}
