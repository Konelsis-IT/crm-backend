<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialPlatform;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Icerigin hedef platformu (B31, D-106); icerik + platform tektir. Paylasim
 * sonrasi `published_url` dolar. Platform listesi esitlenirken paylasim
 * baglantisi dolu satir korunur. Tablo yalniz olusturma izi tasir.
 */
#[Table('social_content_platforms')]
#[Fillable(['content_id', 'platform', 'published_url'])]
class SocialContentPlatform extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

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
            'platform' => SocialPlatform::class,
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'content_id');
    }

    /** Paylasim baglantisi girilmis mi? */
    public function hasPublishedUrl(): bool
    {
        return filled($this->published_url);
    }
}
