<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ProjectReferenceScopeTypePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Referans <-> proje tipi (B50, D-177). Ayni referansta bir tip bir kez;
 * referans formundaki coklu secimle eklenir ve kaldirilir.
 */
#[Table('project_reference_scope_types')]
#[Fillable(['project_reference_id', 'scope_type'])]
#[UsePolicy(ProjectReferenceScopeTypePolicy::class)]
class ProjectReferenceScopeType extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => ProjectScopeType::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(ProjectReference::class, 'project_reference_id');
    }
}
