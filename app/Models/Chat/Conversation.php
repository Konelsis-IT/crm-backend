<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Enums\Chat\ConversationStatus;
use App\Enums\Chat\ConversationType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\SecurityClassification;
use App\Policies\ConversationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Sohbet (08 SS2.1, B12A): birebir ya da grup. Siralama son mesaja gore.
 */
#[Table('conversations')]
#[Fillable([
    'conversation_type', 'scope_type', 'scope_id', 'title', 'classification_id', 'history_policy',
    'status', 'last_message_sequence', 'last_message_at', 'direct_pair_key',
])]
#[UsePolicy(ConversationPolicy::class)]
class Conversation extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversation_type' => ConversationType::class,
            'status' => ConversationStatus::class,
            'last_message_sequence' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(SecurityClassification::class, 'classification_id');
    }

    /** Acik uyelikler (ayrilmamis). */
    public function memberships(): HasMany
    {
        return $this->hasMany(ConversationMembership::class, 'conversation_id')->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->ofMany('conversation_sequence', 'max');
    }

    public function isDirect(): bool
    {
        return $this->conversation_type === ConversationType::Direct;
    }

    /** Birebir sohbet anahtari: iki kimligin sirali hash'i (08 SS2.1). */
    public static function pairKey(int $first, int $second): string
    {
        [$low, $high] = $first <= $second ? [$first, $second] : [$second, $first];

        return hash('sha256', $low.'-'.$high);
    }
}
