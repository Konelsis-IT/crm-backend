<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ReviewDecision;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\HandoffReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('handoff_reviews')]
#[Fillable(['handoff_version_id', 'reviewer_personnel_id', 'decision', 'comment', 'decided_at'])]
#[UsePolicy(HandoffReviewPolicy::class)]
class HandoffReview extends Model
{
    use AppendOnly, HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(OperationHandoffVersion::class, 'handoff_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reviewer_personnel_id');
    }
}
