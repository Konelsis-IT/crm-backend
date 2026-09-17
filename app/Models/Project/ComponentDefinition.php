<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\ProjectComponent;
use App\Policies\ComponentDefinitionPolicy;
use App\Models\Report\Report;
use App\Models\WorkRequest\WorkRequest;
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

    /** Bu kayda bagli raporlar (B10A, D-86). */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'subject_component_definition_id');
    }

    /** Bu kayitla ilgili talepler (B11B). */
    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class, 'component_definition_id');
    }
}
