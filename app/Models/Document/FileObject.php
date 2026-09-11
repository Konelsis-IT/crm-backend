<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\FileDerivationKind;
use App\Enums\Document\FileObjectStatus;
use App\Enums\Document\FileScanStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Reference\RetentionPolicy;
use App\Policies\FileObjectPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiziksel dosya nesnesi (metadata). Icerik degismez, tarama durumu
 * degisebilir. Indirme yetkisi bagli oldugu is nesnesi uzerinden
 * hesaplanir, bu tablo dogrudan indirilebilir degildir.
 */
#[Table('file_objects')]
#[Fillable([
    'storage_disk', 'storage_key', 'original_name', 'extension', 'mime_type', 'declared_mime_type',
    'byte_size', 'sha256', 'scan_status', 'scanned_at', 'scanner_reference', 'quarantine_reason',
    'is_derived', 'derived_from_file_object_id', 'derivation_kind', 'image_width', 'image_height',
    'uploaded_by_personnel_id', 'uploaded_at', 'retention_policy_id', 'status', 'purged_at',
])]
#[UsePolicy(FileObjectPolicy::class)]
class FileObject extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'scan_status' => FileScanStatus::class,
            'scanned_at' => 'datetime',
            'is_derived' => 'boolean',
            'derivation_kind' => FileDerivationKind::class,
            'image_width' => 'integer',
            'image_height' => 'integer',
            'uploaded_at' => 'datetime',
            'status' => FileObjectStatus::class,
            'purged_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'uploaded_by_personnel_id');
    }

    public function derivedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'derived_from_file_object_id');
    }

    public function retentionPolicy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'retention_policy_id');
    }

    /** Bu dosyadan uretilen turevler (kucuk gorsel, onizleme, PDF). */
    public function derivatives(): HasMany
    {
        return $this->hasMany(self::class, 'derived_from_file_object_id');
    }

    /** Kucuk gorsel turevi (varsa). */
    public function thumbnail(): ?self
    {
        return $this->derivatives()
            ->where('derivation_kind', FileDerivationKind::Thumbnail->value)
            ->where('status', FileObjectStatus::Active->value)
            ->first();
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Tarayicida guvenle satir ici gosterilebilecek turler; digerleri her
     * zaman indirme olarak sunulur (HTML/SVG/JS gibi icerikler calistirilmaz).
     */
    public function isInlinePreviewable(): bool
    {
        return in_array((string) $this->mime_type, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'application/pdf', 'text/plain',
        ], true);
    }

    /** Insan okur boyut (KB/MB). */
    public function humanSize(): string
    {
        $bytes = (int) $this->byte_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return $bytes.' B';
    }
}
