<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\EvidenceType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentType;
use App\Models\Project\StageNode;
use App\Policies\StageRequirementDefinitionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('stage_requirement_definitions')]
#[Fillable([
    'stage_node_id', 'requirement_code', 'name_tr', 'name_en', 'evidence_type', 'is_mandatory',
    'min_document_type_id', 'description', 'sort_order',
])]
#[UsePolicy(StageRequirementDefinitionPolicy::class)]
class StageRequirementDefinition extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence_type' => EvidenceType::class,
            'is_mandatory' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(StageNode::class, 'stage_node_id');
    }

    public function minDocumentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'min_document_type_id');
    }
}
