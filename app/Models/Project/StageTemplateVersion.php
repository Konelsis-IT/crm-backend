<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\StageTemplateVersionStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\StageNode;
use App\Models\Project\StageTemplate;
use App\Policies\StageTemplateVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('stage_template_versions')]
#[Fillable([
    'stage_template_id', 'version_no', 'status', 'change_summary', 'definition_hash', 'published_by_personnel_id',
    'published_at',
])]
#[UsePolicy(StageTemplateVersionPolicy::class)]
class StageTemplateVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'status' => StageTemplateVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StageTemplate::class, 'stage_template_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'published_by_personnel_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(StageNode::class, 'stage_template_version_id');
    }
}
