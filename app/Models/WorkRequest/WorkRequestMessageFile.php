<?php

declare(strict_types=1);

namespace App\Models\WorkRequest;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\FileObject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Talep cevabina eklenen dosya (B32). */
#[Table('work_request_message_files')]
#[Fillable(['message_id', 'file_object_id', 'sort_order'])]
class WorkRequestMessageFile extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WorkRequestMessage::class, 'message_id');
    }

    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }
}
