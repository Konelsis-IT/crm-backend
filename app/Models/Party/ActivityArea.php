<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ActivityAreaPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Faaliyet alani katalogu (B33, D-107): iki seviye. `parent_id` bos ise ana
 * faaliyet alani (or. Ekipman tedariki), dolu ise alt faaliyet alanidir
 * (or. Turbin). Ayarlar'dan duzenlenir, silinmez; pasife alinir.
 */
#[Table('activity_areas')]
#[Fillable(['parent_id', 'code', 'name_tr', 'name_en', 'sort_order', 'status'])]
#[UsePolicy(ActivityAreaPolicy::class)]
class ActivityArea extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name_tr');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
