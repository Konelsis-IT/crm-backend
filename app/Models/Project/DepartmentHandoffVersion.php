<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Acquisition\HandoffVersionStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\DepartmentHandoff;
use App\Models\Project\DepartmentHandoffItem;
use App\Models\Project\DepartmentHandoffReview;
use App\Policies\DepartmentHandoffVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('department_handoff_versions')]
#[Fillable([
    'department_handoff_id', 'version_no', 'manifest_snapshot', 'snapshot_hash', 'status',
    'submitted_by_personnel_id', 'submitted_at',
])]
#[UsePolicy(DepartmentHandoffVersionPolicy::class)]
class DepartmentHandoffVersion extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'manifest_snapshot' => 'array',
            'status' => HandoffVersionStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function handoff(): BelongsTo
    {
        return $this->belongsTo(DepartmentHandoff::class, 'department_handoff_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'submitted_by_personnel_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DepartmentHandoffItem::class, 'handoff_version_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DepartmentHandoffReview::class, 'handoff_version_id');
    }
}
