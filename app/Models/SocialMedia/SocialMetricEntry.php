<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialMetricSource;
use App\Enums\SocialMedia\SocialPlatform;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\FileObject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Donemlik platform istatistigi (B31, D-106): elle girilir ya da yuklenen
 * rapor dosyasiyla (`file_object_id`) belgelenir. Hesap + platform + donem
 * tektir; bitis tarihi baslangictan once olamaz. Giren kisi `createdBy()`.
 */
#[Table('social_metric_entries')]
#[Fillable([
    'profile_id', 'platform', 'period_start_on', 'period_end_on', 'followers', 'posts_count', 'impressions',
    'reach', 'engagements', 'profile_visits', 'link_clicks', 'video_views', 'note', 'source', 'file_object_id',
])]
class SocialMetricEntry extends Model
{
    use HasAuditColumns;

    /**
     * Sayisal olcum kolonlari (form, sunum ve toplamlar ayni listeyi kullanir).
     *
     * @var list<string>
     */
    public const METRICS = [
        'followers', 'posts_count', 'impressions', 'reach', 'engagements',
        'profile_visits', 'link_clicks', 'video_views',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'source' => SocialMetricSource::class,
            'period_start_on' => 'date',
            'period_end_on' => 'date',
            'followers' => 'integer',
            'posts_count' => 'integer',
            'impressions' => 'integer',
            'reach' => 'integer',
            'engagements' => 'integer',
            'profile_visits' => 'integer',
            'link_clicks' => 'integer',
            'video_views' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialProfile::class, 'profile_id');
    }

    /** Yuklenen istatistik raporu (varsa). */
    public function reportFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    public function hasReport(): bool
    {
        return $this->file_object_id !== null;
    }
}
