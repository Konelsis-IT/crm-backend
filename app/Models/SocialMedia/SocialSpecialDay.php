<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ozel gun (B31, D-106): ay + gun; `year` bos ise her yil tekrarlar,
 * `profile_id` bos ise iki hesap icin de gecerlidir. Silinmez, pasife alinir.
 */
#[Table('social_special_days')]
#[Fillable(['name', 'month', 'day', 'year', 'profile_id', 'note', 'status'])]
class SocialSpecialDay extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'day' => 'integer',
            'year' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }

    /** Gunun ait oldugu hesap; bos ise butun hesaplar. */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialProfile::class, 'profile_id');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    /** Her yil tekrarlayan gun mu? */
    public function isRecurring(): bool
    {
        return $this->year === null;
    }

    /** Verilen hesap icin gecerli mi (hesabi bos olan gun herkese gecerlidir)? */
    public function appliesToProfile(int $profileId): bool
    {
        return $this->profile_id === null || (int) $this->profile_id === $profileId;
    }

    /**
     * Gunun verilen yildaki tarihi (`Y-m-d`). Gun o yila ait degilse ya da o
     * yil takvimde yoksa (29 Subat) null doner.
     */
    public function dateInYear(int $year): ?string
    {
        if ($this->year !== null && (int) $this->year !== $year) {
            return null;
        }

        if (! checkdate((int) $this->month, (int) $this->day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $this->month, (int) $this->day);
    }
}
