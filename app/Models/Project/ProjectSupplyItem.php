<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\SupplyItemKind;
use App\Enums\Project\SupplyItemStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Reference\Currency;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\ProjectSupplyItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tedarik kalemi: urun, hizmet veya yazilim (11 SS1.11). */
#[Table('project_supply_items')]
#[Fillable([
    'project_id', 'workstream_id', 'item_kind', 'item_code', 'name', 'specification', 'quantity', 'uom_id',
    'unit_cost', 'currency_code', 'supplier_party_id', 'wbs_node_id', 'needed_on', 'ordered_on',
    'expected_delivery_on', 'delivered_on', 'status', 'note',
])]
#[UsePolicy(ProjectSupplyItemPolicy::class)]
class ProjectSupplyItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_kind' => SupplyItemKind::class,
            'status' => SupplyItemStatus::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'needed_on' => 'date',
            'ordered_on' => 'date',
            'expected_delivery_on' => 'date',
            'delivered_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workstream(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkstream::class, 'workstream_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'wbs_node_id');
    }

    /** Miktar x birim maliyet; birim maliyet yoksa null. */
    public function totalCost(): ?float
    {
        if ($this->unit_cost === null) {
            return null;
        }

        return round((float) $this->quantity * (float) $this->unit_cost, 4);
    }
}
