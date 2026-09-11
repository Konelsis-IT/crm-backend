<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ParticipationRole;
use App\Models\Acquisition\BusinessDevelopmentActivity;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Policies\BusinessDevelopmentActivityParticipantPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('business_development_activity_participants')]
#[Fillable(['activity_id', 'personnel_id', 'contact_party_id', 'participation_role'])]
#[UsePolicy(BusinessDevelopmentActivityParticipantPolicy::class)]
class BusinessDevelopmentActivityParticipant extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'participation_role' => ParticipationRole::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(BusinessDevelopmentActivity::class, 'activity_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contact_party_id');
    }
}
