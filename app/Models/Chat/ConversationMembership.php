<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Enums\Chat\MembershipRole;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sohbet uyeligi (08 SS2.2). `pinned_at` sabitleme, `history_visible_from`
 * tek tarafli silme sinirini tasir (bu andan onceki mesajlar o kisiye gorunmez).
 */
#[Table('conversation_memberships')]
#[Fillable([
    'conversation_id', 'personnel_id', 'role', 'joined_at', 'left_at', 'history_visible_from', 'is_muted', 'pinned_at',
])]
class ConversationMembership extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'history_visible_from' => 'datetime',
            'pinned_at' => 'datetime',
            'is_muted' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }
}
