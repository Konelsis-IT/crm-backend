<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Document\FileObject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mesaj eki (08 SS2.6): yuklenen dosya XOR kontrollu dokuman revizyonu.
 */
#[Table('message_attachments')]
#[Fillable(['message_id', 'file_object_id', 'document_revision_id', 'caption', 'sort_order'])]
class MessageAttachment extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function isDocument(): bool
    {
        return $this->document_revision_id !== null;
    }
}
