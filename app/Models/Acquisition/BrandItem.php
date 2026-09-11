<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\BrandApprovalState;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\Country;
use App\Policies\BrandItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('brand_items')]
#[Fillable([
    'proposal_version_id', 'item_code', 'item_description', 'proposed_brand', 'alternative_brand',
    'origin_country_code', 'approval_state', 'sort_order',
])]
#[UsePolicy(BrandItemPolicy::class)]
class BrandItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_state' => BrandApprovalState::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function originCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_country_code', 'code');
    }
}
