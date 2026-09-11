<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\AddressType;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Reference\Country;
use App\Policies\AddressPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('addresses')]
#[Fillable([
    'party_id', 'address_type', 'line1', 'line2', 'district', 'city', 'postal_code', 'country_code', 'is_primary',
    'status',
])]
#[UsePolicy(AddressPolicy::class)]
class Address extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address_type' => AddressType::class,
            'is_primary' => 'boolean',
            'status' => ActiveStatus::class,
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
