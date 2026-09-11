<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Enums\Notification\AnnouncementAudience;
use App\Enums\Notification\AnnouncementPriority;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\AnnouncementPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gonderilmis bildirim/duyuru kaydi (B11A, D-82). Teslimat Filament zili
 * uzerindendir; bu tablo kimin kime ne gonderdigini tutar. Yalniz eklenir.
 */
#[Table('announcements')]
#[Fillable([
    'sender_personnel_id', 'audience_kind', 'audience_id', 'audience_ids', 'audience_label',
    'title', 'body', 'priority', 'action_url', 'recipient_count', 'sent_at',
])]
#[UsePolicy(AnnouncementPolicy::class)]
class Announcement extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience_kind' => AnnouncementAudience::class,
            'priority' => AnnouncementPriority::class,
            'audience_ids' => 'array',
            'recipient_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'sender_personnel_id');
    }
}
