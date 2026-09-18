<?php

declare(strict_types=1);

namespace App\Models\SocialMedia;

use App\Enums\SocialMedia\SocialPlatform;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Izlenen hesabin platform baglantisi (B31, D-106); hesap + platform tektir. */
#[Table('social_watch_links')]
#[Fillable(['watch_account_id', 'platform', 'url'])]
class SocialWatchLink extends Model
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialWatchAccount::class, 'watch_account_id');
    }
}
