<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\PositionStatus;
use App\Models\Authorization\Role;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\PositionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pozisyon katalogu: bir organizasyon birimine bagli, kadro tanimi.
 */
#[Table('positions')]
#[Fillable(['org_unit_id', 'code', 'title', 'role_id', 'grade', 'managerial_level', 'headcount', 'status', 'valid_from', 'valid_until'])]
#[UsePolicy(PositionPolicy::class)]
class Position extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PositionStatus::class,
            'managerial_level' => 'integer',
            'headcount' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class, 'position_id');
    }

    /** Pozisyonun kendi rolu (D-81, B03A); pozisyon sahipleri bu rolu otomatik alir. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
