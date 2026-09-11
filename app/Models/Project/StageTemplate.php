<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\StageTemplateProjectType;
use App\Enums\Project\StageTemplateStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\StageTemplateVersion;
use App\Policies\StageTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('stage_templates')]
#[Fillable(['code', 'name_tr', 'name_en', 'project_type', 'current_version_id', 'status'])]
#[UsePolicy(StageTemplatePolicy::class)]
class StageTemplate extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_type' => StageTemplateProjectType::class,
            'status' => StageTemplateStatus::class,
        ];
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(StageTemplateVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StageTemplateVersion::class, 'stage_template_id');
    }
}
