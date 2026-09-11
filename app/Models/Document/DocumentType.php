<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentDiscipline;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\RetentionPolicy;
use App\Models\Reference\SecurityClassification;
use App\Policies\DocumentTypePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Doküman tipi katalogu (ornegin sozlesme, teknik cizim, prosedur).
 */
#[Table('document_types')]
#[Fillable([
    'code', 'name', 'discipline', 'numbering_prefix', 'is_controlled',
    'default_classification_id', 'default_retention_policy_id', 'allowed_extensions', 'max_byte_size', 'status',
])]
#[UsePolicy(DocumentTypePolicy::class)]
class DocumentType extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discipline' => DocumentDiscipline::class,
            'is_controlled' => 'boolean',
            'allowed_extensions' => 'array',
            'max_byte_size' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }

    public function defaultClassification(): BelongsTo
    {
        return $this->belongsTo(SecurityClassification::class, 'default_classification_id');
    }

    public function defaultRetentionPolicy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'default_retention_policy_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'document_type_id');
    }
}
