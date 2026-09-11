<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentShareStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentSharePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dokumanin paylasim baglantisi (D-75). Token tahmin edilemez rastgele bir
 * anahtardir ve simdilik yetkisiz erisime aciktir; sifre/yetki mekanizmasi
 * sonraki turda (02 SS11). Iptal edilen veya suresi dolan baglanti kapanir.
 */
#[Table('document_shares')]
#[Fillable([
    'document_id', 'token', 'label', 'allow_download', 'expires_at', 'access_count', 'last_accessed_at',
    'status', 'revoked_at', 'revoked_by_personnel_id',
])]
#[UsePolicy(DocumentSharePolicy::class)]
class DocumentShare extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_download' => 'boolean',
            'expires_at' => 'datetime',
            'access_count' => 'integer',
            'last_accessed_at' => 'datetime',
            'status' => DocumentShareStatus::class,
            'revoked_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'revoked_by_personnel_id');
    }

    /** Baglanti su an acik mi (iptal edilmemis ve suresi dolmamis)? */
    public function isOpen(): bool
    {
        if ($this->status !== DocumentShareStatus::Active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isAfter(Carbon::now());
    }
}
