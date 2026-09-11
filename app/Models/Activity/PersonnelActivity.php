<?php

declare(strict_types=1);

namespace App\Models\Activity;

use App\Enums\Activity\ActivityChannel;
use App\Models\Concerns\AppendOnly;
use App\Models\Personnel\Personnel;
use App\Policies\PersonnelActivityPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personel hareketleri: kim, ne zaman, hangi kayitta ne yapti.
 * Kayitlar eklenir, hicbir zaman degistirilmez veya silinmez.
 */
#[Table('personnel_activities')]
#[Fillable([
    'personnel_id', 'subject_type', 'subject_id', 'action_code',
    'channel', 'changes', 'ip_address', 'occurred_at',
])]
#[UsePolicy(PersonnelActivityPolicy::class)]
class PersonnelActivity extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ActivityChannel::class,
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /** Hareketi yapan personelin adi; uygulamanin kendi isleminde "Sistem". */
    public function actorName(): string
    {
        if ($this->personnel !== null) {
            return (string) $this->personnel->full_name;
        }

        return __('activity.system');
    }
}
