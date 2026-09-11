<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Enums\Chat\MessageKind;
use App\Enums\Chat\MessageStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\MessagePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sohbet mesaji (08 SS2.3). Konusma icinde `conversation_sequence` monotondur.
 */
#[Table('messages')]
#[Fillable([
    'conversation_id', 'conversation_sequence', 'author_personnel_id', 'reply_to_message_id', 'message_kind',
    'body', 'link_url', 'status', 'sent_at', 'redacted_at', 'redacted_by_personnel_id', 'redaction_reason',
])]
#[UsePolicy(MessagePolicy::class)]
class Message extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversation_sequence' => 'integer',
            'message_kind' => MessageKind::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'redacted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'author_personnel_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id')->orderBy('sort_order');
    }

    public function hides(): HasMany
    {
        return $this->hasMany(MessageHide::class, 'message_id');
    }
}
