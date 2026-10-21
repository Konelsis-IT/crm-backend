<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Policies\PurchaseSupplierPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Satin alma tedarikci listesindeki kayit (D-232, B58). Firmanin kendisi bir
 * taraftir (unvan, kisiler, adresler, kanallar); bu kayit hizmet alanlarini,
 * notu ve arsivi tutar. `purchase_only` = yalniz tedarikci (Taraflar'da
 * gorunmez); 0 = Taraflar'daki firma, iki listede de gorunur.
 */
#[Table('purchase_suppliers')]
#[Fillable(['party_id', 'purchase_only', 'note', 'archived_at'])]
#[UsePolicy(PurchaseSupplierPolicy::class)]
class PurchaseSupplier extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_only' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id')->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class);
    }

    public function serviceAreas(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseServiceArea::class, 'purchase_supplier_areas', 'purchase_supplier_id', 'purchase_service_area_id')
            ->orderBy('purchase_service_areas.sort_order')
            ->orderBy('purchase_service_areas.name');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }
}
