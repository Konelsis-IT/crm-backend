<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ClarificationStatus;
use App\Enums\Project\ClarificationType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectChange;
use App\Policies\CommercialClarificationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('commercial_clarifications')]
#[Fillable([
    'project_id', 'clarification_no', 'clarification_type', 'title', 'description', 'raised_by_personnel_id',
    'customer_contact_party_id', 'status', 'response', 'responded_at', 'linked_change_id',
])]
#[UsePolicy(CommercialClarificationPolicy::class)]
class CommercialClarification extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clarification_type' => ClarificationType::class,
            'status' => ClarificationStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'raised_by_personnel_id');
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_contact_party_id');
    }

    public function linkedChange(): BelongsTo
    {
        return $this->belongsTo(ProjectChange::class, 'linked_change_id');
    }
}
