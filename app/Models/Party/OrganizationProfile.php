<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\OrganizationProfilePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('organization_profiles')]
#[Fillable([
    'party_id', 'legal_name', 'trade_name', 'registration_no', 'tax_office', 'tax_number', 'founded_year',
    'website_url', 'sector_code', 'personnel_band', 'group_parent_party_id', 'is_public_company',
])]
#[UsePolicy(OrganizationProfilePolicy::class)]
class OrganizationProfile extends Model
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
            'founded_year' => 'integer',
            'is_public_company' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function groupParent(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'group_parent_party_id');
    }
}
