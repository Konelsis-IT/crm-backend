<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Icerik metin revizyonu (B31, D-106): baslik, aciklama ya da govde
 * degistiginde ONCEKI degerler buraya yazilir (`revision_no` icerik icinde
 * artar). Yalniz ekleme yapilir; satir guncellenmez ve silinmez. Yazan kisi
 * `createdBy()` iliskisidir.
 */
#[Table('social_content_revisions')]
#[Fillable(['content_id', 'revision_no', 'title', 'caption', 'body_text', 'body_html'])]
class SocialContentRevision extends Model
{
    use AppendOnly;
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
            'revision_no' => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'content_id');
    }
}
