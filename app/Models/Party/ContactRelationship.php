<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\ContactRelationshipRole;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\ContactRelationshipPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('contact_relationships')]
#[Fillable([
    'organization_party_id', 'contact_party_id', 'relationship_role', 'department_note', 'is_primary',
    'valid_from', 'valid_until',
])]
#[UsePolicy(ContactRelationshipPolicy::class)]
class ContactRelationship extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship_role' => ContactRelationshipRole::class,
            'is_primary' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'organization_party_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contact_party_id');
    }
}
