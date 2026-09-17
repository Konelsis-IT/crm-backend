<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\CommunicationPointStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\CommunicationPointPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('communication_points')]
#[Fillable(['party_id', 'contact_relationship_id', 'channel_type', 'value', 'normalized_value', 'purpose', 'is_primary', 'status'])]
#[UsePolicy(CommunicationPointPolicy::class)]
class CommunicationPoint extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_type' => CommunicationChannelType::class,
            'is_primary' => 'boolean',
            'status' => CommunicationPointStatus::class,
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * Kanalin sahibi kisi (D-94); bos ise kanal kurumun kendisinindir
     * (santral, genel e-posta).
     */
    public function contactRelationship(): BelongsTo
    {
        return $this->belongsTo(ContactRelationship::class, 'contact_relationship_id');
    }
}
