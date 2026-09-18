<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialContentType;
use App\Enums\SocialMedia\SocialImageFormat;
use App\Models\Activity\PersonnelActivity;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\SocialContentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sosyal medya icerigi (B31, D-106): fotograf, video, kisa metin, uzun metin
 * ya da blog. Bir hesaba (`profile_id`) aittir; tur olusturulduktan sonra
 * degismez. Silme yoktur: icerik arsive kalkar (`status_before_archive`
 * geri donus icin saklanir).
 *
 * Sistem kolonlari (`media_count`, `comment_count`, `like_count`,
 * `dislike_count`, `reminder_key`, `urgent_requested_at`,
 * `urgent_requested_by_personnel_id`) doldurulabilir DEGILDIR; yalniz
 * SocialContentService::writeSilently() ile, model olayi tetiklemeden ve satir
 * surumu artmadan yazilir.
 *
 * Alt kayitlarin (yorum, medya, tepki...) yetkisi bu kayit uzerinden verilir;
 * politika yalniz bu modelde tanimlidir.
 */
#[Table('social_contents')]
#[Fillable([
    'content_no', 'profile_id', 'category_id', 'content_type', 'title', 'caption', 'body_text', 'body_html',
    'image_format', 'video_url', 'planned_on', 'planned_time', 'status', 'status_before_archive',
    'decision_note', 'decided_by_personnel_id', 'decided_at', 'published_at', 'published_by_personnel_id',
    'publish_note',
])]
#[UsePolicy(SocialContentPolicy::class)]
class SocialContent extends Model
{
    use HasAuditColumns;

    /** Personel Hareketleri'ndeki kayit turu. */
    public const SUBJECT_TYPE = 'social_content';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => SocialContentType::class,
            'status' => SocialContentStatus::class,
            'status_before_archive' => SocialContentStatus::class,
            'image_format' => SocialImageFormat::class,
            'planned_on' => 'date',
            'decided_at' => 'datetime',
            'published_at' => 'datetime',
            'urgent_requested_at' => 'datetime',
            'media_count' => 'integer',
            'comment_count' => 'integer',
            'like_count' => 'integer',
            'dislike_count' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialProfile::class, 'profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SocialCategory::class, 'category_id');
    }

    /** Hedef platformlar ve paylasim baglantilari. */
    public function platforms(): HasMany
    {
        return $this->hasMany(SocialContentPlatform::class, 'content_id');
    }

    /**
     * Icerigin BUTUN medya satirlari: kokler, surumler, metin ici gorseller ve
     * galeriden cikarilanlar dahil. Galeri suzgeci sorgu/sunum katmanindadir
     * (usage = gallery VE removed_at bos).
     */
    public function media(): HasMany
    {
        return $this->hasMany(SocialContentMedia::class, 'content_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class, 'content_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(SocialReaction::class, 'content_id');
    }

    /** Metin degisikliklerinde saklanan onceki surumler. */
    public function revisions(): HasMany
    {
        return $this->hasMany(SocialContentRevision::class, 'content_id');
    }

    /** Onay, ret ya da revize kararini veren kisi. */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'decided_by_personnel_id');
    }

    /** Icerigi paylasildi olarak isaretleyen kisi. */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'published_by_personnel_id');
    }

    /** Son acil onay istegini yapan kisi. */
    public function urgentRequestedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'urgent_requested_by_personnel_id');
    }

    /**
     * Icerigin hareket gecmisi (Personel Hareketleri; subject_type = social_content).
     * `personnel_activities.subject_id` metin oldugu icin yerel anahtar
     * olarak metne cevrilmis kimlik (`subject_key`) kullanilir; indeks korunur.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(PersonnelActivity::class, 'subject_id', 'subject_key')
            ->where('subject_type', self::SUBJECT_TYPE);
    }

    /** Hareket kaydindaki metin kimlik. */
    public function getSubjectKeyAttribute(): string
    {
        return (string) $this->getKey();
    }

    public function isArchived(): bool
    {
        return $this->status === SocialContentStatus::Archived;
    }

    /** Paylasildi olarak isaretlenmis mi? */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Icerigi bu kisi mi olusturdu? Sistem olusturduysa (bos) kimseye ait degildir. */
    public function isCreatedBy(int $personnelId): bool
    {
        $creatorId = $this->getAttribute('created_by_personnel_id');

        return $creatorId !== null && (int) $creatorId === $personnelId;
    }
}
