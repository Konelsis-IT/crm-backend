<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialPlatform;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kendi hesabimizin platform baglantisi (B31, D-106); hesap + platform tektir. */
#[Table('social_profile_links')]
#[Fillable(['profile_id', 'platform', 'url', 'handle'])]
class SocialProfileLink extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialProfile::class, 'profile_id');
    }
}
