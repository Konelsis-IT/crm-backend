<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\ConsentStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\PersonProfilePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('person_profiles')]
#[Fillable([
    'party_id', 'given_name', 'family_name', 'title', 'job_title', 'preferred_locale', 'consent_status',
    'consent_at',
])]
#[UsePolicy(PersonProfilePolicy::class)]
class PersonProfile extends Model
{
    use HasAuditColumns;

    protected $primaryKey = 'party_id';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_status' => ConsentStatus::class,
            'consent_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }
}
