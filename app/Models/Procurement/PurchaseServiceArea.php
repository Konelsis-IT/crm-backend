<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\PurchaseServiceAreaPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tedarikcinin hizmet alani (D-232, B58): Salt ve Otomasyon, Kablo
 * Ureticileri, GES Inverter (Yerli)... Kod kullanicidan alinmaz (D-130).
 */
#[Table('purchase_service_areas')]
#[Fillable(['code', 'name', 'sort_order', 'archived_at'])]
#[UsePolicy(PurchaseServiceAreaPolicy::class)]
class PurchaseServiceArea extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseSupplier::class, 'purchase_supplier_areas', 'purchase_service_area_id', 'purchase_supplier_id');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }
}
