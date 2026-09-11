<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\RecoveryActionStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Models\Project\DelayEvent;
use App\Policies\RecoveryActionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('recovery_actions')]
#[Fillable([
    'delay_event_id', 'owner_personnel_id', 'description', 'expected_recovery_days', 'due_at', 'status',
    'completed_at',
])]
#[UsePolicy(RecoveryActionPolicy::class)]
class RecoveryAction extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_recovery_days' => 'integer',
            'due_at' => 'datetime',
            'status' => RecoveryActionStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function delayEvent(): BelongsTo
    {
        return $this->belongsTo(DelayEvent::class, 'delay_event_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }
}
