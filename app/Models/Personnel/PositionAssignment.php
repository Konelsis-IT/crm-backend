<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\PositionAssignmentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir personelin pozisyona atanmasi (birden fazla olabilir; is_primary +
 * allocation_pct ile paylasim gosterilir).
 */
#[Table('position_assignments')]
#[Fillable(['personnel_id', 'position_id', 'is_primary', 'allocation_pct', 'valid_from', 'valid_until'])]
#[UsePolicy(PositionAssignmentPolicy::class)]
class PositionAssignment extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'allocation_pct' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}
