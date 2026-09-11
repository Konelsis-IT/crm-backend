<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyRoleStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Policies\PartyRolePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('party_roles')]
#[Fillable(['party_id', 'role_code', 'status', 'approved_by_personnel_id', 'valid_from', 'valid_until'])]
#[UsePolicy(PartyRolePolicy::class)]
class PartyRole extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_code' => PartyRoleCode::class,
            'status' => PartyRoleStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }
}
