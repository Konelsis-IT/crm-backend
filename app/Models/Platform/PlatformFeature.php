<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ozellik anahtari (B39, D-128). Katalog App\Enums\Platform\Feature'dadir;
 * bu satir ozelligin veritabanindaki acik / kapali durumunu tasir.
 * `is_active` yalniz veritabanindan degistirilir.
 */
#[Table('features')]
#[Fillable(['code', 'parent_id', 'name', 'description', 'decision_ref', 'sort_order', 'is_active'])]
class PlatformFeature extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
