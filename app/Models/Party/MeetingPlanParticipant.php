<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gorusme planina katilan personel (B34, D-109). */
#[Table('meeting_plan_participants')]
#[Fillable(['meeting_plan_id', 'personnel_id'])]
class MeetingPlanParticipant extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MeetingPlan::class, 'meeting_plan_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
