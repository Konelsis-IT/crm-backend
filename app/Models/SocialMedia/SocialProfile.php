<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialProfileKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kendi sosyal medya hesabimiz (B31, D-106). Icerikler hesaba gore ayrilir:
 * kurumsal hesap ve yonetici hesabi. Hesaplar seeder ile gelir; arayuzden
 * yalniz tanitim yazisi ve platform baglantilari duzenlenir.
 */
#[Table('social_profiles')]
#[Fillable(['code', 'name', 'kind', 'owner_personnel_id', 'bio', 'sort_order', 'status'])]
class SocialProfile extends Model
{
    use HasAuditColumns;

    /** Kurumsal hesabin sabit kodu (SocialProfileSeeder). */
    public const CODE_KONELSIS = 'KONELSIS';

    /** Yonetici hesabinin sabit kodu (SocialProfileSeeder). */
    public const CODE_HUSEYIN_GUNES = 'HUSEYIN_GUNES';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SocialProfileKind::class,
            'status' => ActiveStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /** Hesabin platform baglantilari. */
    public function links(): HasMany
    {
        return $this->hasMany(SocialProfileLink::class, 'profile_id');
    }

    /** Hesabin sahibi (yonetici hesabinda ilgili personel). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(SocialContent::class, 'profile_id');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    public function isCorporate(): bool
    {
        return $this->kind === SocialProfileKind::Corporate;
    }

    public function isExecutive(): bool
    {
        return $this->kind === SocialProfileKind::Executive;
    }
}
