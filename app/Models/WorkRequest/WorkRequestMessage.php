<?php

declare(strict_types=1);

namespace App\Models\WorkRequest;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Talep yazismasindaki cevap (B32). `message_kind`: text = cevap,
 * forward = yonlendirme satiri (gerekce body'de).
 */
#[Table('work_request_messages')]
#[Fillable(['work_request_id', 'author_personnel_id', 'message_kind', 'body'])]
class WorkRequestMessage extends Model
{
    use HasAuditColumns;

    public const KIND_TEXT = 'text';

    public const KIND_FORWARD = 'forward';

    public const KIND_INITIAL = 'initial';

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'work_request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'author_personnel_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkRequestMessageFile::class, 'message_id')->orderBy('sort_order');
    }

    public function isForward(): bool
    {
        return $this->message_kind === self::KIND_FORWARD;
    }
}
