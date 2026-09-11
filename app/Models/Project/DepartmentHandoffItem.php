<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Acquisition\CompletionState;
use App\Enums\Project\DepartmentHandoffItemType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Project\DepartmentHandoffVersion;
use App\Policies\DepartmentHandoffItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('department_handoff_items')]
#[Fillable([
    'handoff_version_id', 'item_code', 'item_type', 'description', 'document_revision_id', 'completion_state',
    'sort_order',
])]
#[UsePolicy(DepartmentHandoffItemPolicy::class)]
class DepartmentHandoffItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => DepartmentHandoffItemType::class,
            'completion_state' => CompletionState::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DepartmentHandoffVersion::class, 'handoff_version_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
