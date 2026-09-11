<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\DocumentRevisionFilePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir revizyona bagli dosya (orijinal, PDF, onizleme...).
 */
#[Table('document_revision_files')]
#[Fillable(['document_revision_id', 'file_object_id', 'file_role', 'sort_order'])]
#[UsePolicy(DocumentRevisionFilePolicy::class)]
class DocumentRevisionFile extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_role' => DocumentRevisionFileRole::class,
            'sort_order' => 'integer',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }
}
