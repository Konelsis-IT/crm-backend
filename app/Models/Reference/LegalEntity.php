<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\LegalEntityKind;
use App\Enums\Reference\LegalEntityStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('legal_entities')]
#[Fillable([
    'organization_id', 'code', 'legal_name', 'short_name', 'country_code', 'currency_code', 'timezone',
    'tax_number', 'registration_number', 'entity_kind', 'status', 'valid_from', 'valid_until',
])]
class LegalEntity extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_kind' => LegalEntityKind::class,
            'status' => LegalEntityStatus::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
