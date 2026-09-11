<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\BdActivityType;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessDevelopmentActivityParticipant;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Policies\BusinessDevelopmentActivityPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('business_development_activities')]
#[Fillable([
    'business_case_id', 'party_id', 'activity_type', 'subject', 'occurred_at', 'timezone', 'location',
    'organizer_employee_id', 'outcome_summary', 'next_action', 'next_action_due_at',
])]
#[UsePolicy(BusinessDevelopmentActivityPolicy::class)]
class BusinessDevelopmentActivity extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_type' => BdActivityType::class,
            'occurred_at' => 'datetime',
            'next_action_due_at' => 'datetime',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'organizer_employee_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BusinessDevelopmentActivityParticipant::class, 'activity_id');
    }
}
