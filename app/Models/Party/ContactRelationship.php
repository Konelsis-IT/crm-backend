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
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('contact_relationships')]
#[Fillable([
    'organization_party_id', 'contact_party_id', 'contact_name', 'relationship_role',
    'department_note', 'network_note', 'is_primary', 'valid_from', 'valid_until',
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

    /** Bu kisiye ait iletisim kanallari (D-94): telefon, cep, e-posta, faks... */
    public function communicationPoints(): HasMany
    {
        return $this->hasMany(CommunicationPoint::class, 'contact_relationship_id');
    }

    /**
     * Ekranda gorunen ad (D-94): bagli taraf kaydi varsa onun adi, yoksa
     * satira yazilan serbest ad.
     */
    public function displayName(): string
    {
        return (string) ($this->contact?->display_name ?? $this->getAttribute('contact_name') ?? '-');
    }
}
