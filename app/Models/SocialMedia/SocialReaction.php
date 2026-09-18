<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialReactionType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Icerik tepkisi (B31, D-106): kisi basina tek satir (icerik + personel tek).
 * Tepki geri alininca satir kalir, deger `none` olur.
 */
#[Table('social_reactions')]
#[Fillable(['content_id', 'personnel_id', 'reaction'])]
class SocialReaction extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reaction' => SocialReactionType::class,
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'content_id');
    }

    /** Tepkiyi veren kisi. */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
