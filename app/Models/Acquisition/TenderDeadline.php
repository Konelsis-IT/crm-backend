<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\TenderDeadlineType;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\TenderDeadlinePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tender_deadlines')]
#[Fillable([
    'tender_notice_version_id', 'deadline_type', 'local_due_date', 'local_due_time', 'timezone', 'due_at_utc',
    'alert_generated_at',
])]
#[UsePolicy(TenderDeadlinePolicy::class)]
class TenderDeadline extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline_type' => TenderDeadlineType::class,
            'local_due_date' => 'date',
            'due_at_utc' => 'datetime',
            'alert_generated_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TenderNoticeVersion::class, 'tender_notice_version_id');
    }
}
