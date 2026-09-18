<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Icerik yorumu (B31, D-106). Yorum duzenlenmez ve silinmez; yanit tek gorsel
 * seviyedir (yanita verilen yanit kok yoruma baglanir). Yazan kisi
 * `createdBy()` iliskisidir.
 *
 * Isaretli yorum: `anchor_shape` doludur ve `media_id`, isaretin cizildigi
 * ANDA goruntulenen medya satirini (surumu) gosterir; `anchor_*` o satirin
 * piksel kutusuna gore 0..1 normalize edilir (nokta icin w/h bos). Tablo
 * yalniz olusturma izi tasir; `resolved_*` alanlari yerinde guncellenir.
 */
#[Table('social_comments')]
#[Fillable([
    'content_id', 'parent_comment_id', 'media_id', 'body', 'anchor_shape',
    'anchor_x', 'anchor_y', 'anchor_w', 'anchor_h', 'resolved_at', 'resolved_by_personnel_id',
])]
class SocialComment extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public const SHAPE_POINT = 'point';

    public const SHAPE_RECT = 'rect';

    /** `anchor_shape` kolonunun izinli degerleri (migration CHECK'i ile ayni). */
    public const SHAPES = [self::SHAPE_POINT, self::SHAPE_RECT];

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchor_x' => 'float',
            'anchor_y' => 'float',
            'anchor_w' => 'float',
            'anchor_h' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'content_id');
    }

    /** Yanitin bagli oldugu kok yorum. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id');
    }

    /** Isaretin cizildigi medya satiri (surum). */
    public function media(): BelongsTo
    {
        return $this->belongsTo(SocialContentMedia::class, 'media_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'resolved_by_personnel_id');
    }

    public function isReply(): bool
    {
        return $this->parent_comment_id !== null;
    }

    /** Gorsel uzerinde isaret tasiyan yorum mu? */
    public function isMark(): bool
    {
        return $this->anchor_shape !== null;
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /** Acik (cozulmemis) isaret mi? */
    public function isOpenMark(): bool
    {
        return $this->isMark() && ! $this->isResolved();
    }
}
