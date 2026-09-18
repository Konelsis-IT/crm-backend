<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialWatchKind;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Izlenen hesap (B31, D-106): rakip firma, rakip yonetici ya da resmi kurum.
 * Kurumsal hesapta rakip firmalar + resmi kurumlar, yonetici hesabinda rakip
 * yoneticiler + resmi kurumlar gorunur (SocialWatchKind::forProfileKind).
 * Silinmez, pasife alinir.
 */
#[Table('social_watch_accounts')]
#[Fillable(['kind', 'name', 'subtitle', 'note', 'sort_order', 'status'])]
class SocialWatchAccount extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SocialWatchKind::class,
            'status' => ActiveStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /** Hesabin platform baglantilari. */
    public function links(): HasMany
    {
        return $this->hasMany(SocialWatchLink::class, 'watch_account_id');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }
}
