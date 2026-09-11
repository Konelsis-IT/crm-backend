<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\HandoffStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\OperationHandoffPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('operation_handoffs')]
#[Fillable([
    'business_case_id', 'prepared_by_employee_id', 'status', 'accepted_version_id', 'accepted_by_personnel_id',
    'accepted_at',
])]
#[UsePolicy(OperationHandoffPolicy::class)]
class OperationHandoff extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HandoffStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'prepared_by_employee_id');
    }

    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(OperationHandoffVersion::class, 'accepted_version_id');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'accepted_by_personnel_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OperationHandoffVersion::class, 'operation_handoff_id');
    }
}
