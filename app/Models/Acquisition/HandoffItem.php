<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\HandoffItemType;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Policies\HandoffItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('handoff_items')]
#[Fillable([
    'handoff_version_id', 'item_code', 'item_type', 'description', 'document_revision_id', 'completion_state',
    'waived_by_personnel_id', 'sort_order',
])]
#[UsePolicy(HandoffItemPolicy::class)]
class HandoffItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => HandoffItemType::class,
            'completion_state' => CompletionState::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(OperationHandoffVersion::class, 'handoff_version_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function waiver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'waived_by_personnel_id');
    }
}
