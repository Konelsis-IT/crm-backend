<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialMediaKind;
use App\Enums\SocialMedia\SocialMediaUsage;
use App\Enums\SocialMedia\SocialMediaVariant;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\FileObject;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Icerik medyasi (B31, D-106): gorsel ya da video satiri.
 *
 * "Medya grubu" = kok satir (`parent_media_id` bos) + ondan turetilen surumler
 * (bicim kirpmasi, cozunurluk). Grupta tek satir `is_selected` olur; galeri,
 * her grubun secili satirini kokun `sort_order` sirasiyla gosterir. Bu kurallar
 * SocialContentMediaService'te korunur.
 *
 * `usage` = inline satirlar metin editorune gomulen gorsellerdir (kok, ozgun,
 * sira 0) ve galeriye girmez. Galeriden cikarma `removed_at` ile grubun tum
 * satirlarina yazilir; dosya hicbir zaman silinmez. `crop_*` kok ozgun gorsele
 * gore 0..1 normalize kirpmadir. `file_object_id` tekil degildir: ayni dosya
 * (sha256) birden cok satirda paylasilabilir.
 */
#[Table('social_content_media')]
#[Fillable([
    'content_id', 'file_object_id', 'kind', 'usage', 'parent_media_id', 'variant', 'variant_label',
    'is_selected', 'sort_order', 'caption', 'width', 'height', 'byte_size', 'duration_seconds',
    'crop_x', 'crop_y', 'crop_w', 'crop_h', 'poster_file_object_id', 'preview_file_object_id',
    'removed_at', 'removed_by_personnel_id',
])]
class SocialContentMedia extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SocialMediaKind::class,
            'usage' => SocialMediaUsage::class,
            'variant' => SocialMediaVariant::class,
            'is_selected' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'byte_size' => 'integer',
            'duration_seconds' => 'integer',
            'crop_x' => 'float',
            'crop_y' => 'float',
            'crop_w' => 'float',
            'crop_h' => 'float',
            'removed_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'content_id');
    }

    /** Bu satirin dosyasi (DMS dosya nesnesi). */
    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    /** Turetilmis satirin kok satiri; kok satirda bostur. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_media_id');
    }

    /** Kok satirdan turetilen surumler (kokun kendisi dahil degildir). */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_media_id');
    }

    /** Video kapak karesi. */
    public function posterFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'poster_file_object_id');
    }

    /** Tembel uretilen 1280 px onizleme. */
    public function previewFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'preview_file_object_id');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'removed_by_personnel_id');
    }

    /** Grubun kok satiri mi? */
    public function isRoot(): bool
    {
        return $this->parent_media_id === null;
    }

    /** Grubun kok satirinin kimligi (kok satirda kendi kimligi). */
    public function rootId(): int
    {
        return (int) ($this->parent_media_id ?? $this->getKey());
    }

    /** Galeriden cikarilmis mi? */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function isImage(): bool
    {
        return $this->kind === SocialMediaKind::Image;
    }

    public function isVideo(): bool
    {
        return $this->kind === SocialMediaKind::Video;
    }

    /** Metin editorune gomulen gorsel mi (galeri disi)? */
    public function isInline(): bool
    {
        return $this->usage === SocialMediaUsage::Inline;
    }

    /**
     * Kirpma kutusu (kok ozgun gorsele gore 0..1); kirpma yoksa null.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public function cropBox(): ?array
    {
        if ($this->crop_x === null || $this->crop_y === null || $this->crop_w === null || $this->crop_h === null) {
            return null;
        }

        return [
            'x' => round((float) $this->crop_x, 6),
            'y' => round((float) $this->crop_y, 6),
            'w' => round((float) $this->crop_w, 6),
            'h' => round((float) $this->crop_h, 6),
        ];
    }
}
