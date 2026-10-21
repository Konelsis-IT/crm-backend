<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Reference\Currency;
use App\Policies\CompanyPurchasePolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sirket ici alinmis urun / hizmet (B52, D-189): bilgisayar, yazici, yazilim,
 * sunucu, abonelik, kirtasiye. Yenilenen kalemde `renewal_months` ve
 * `next_renewal_on` ile gelecek giderler hesaplanir. Departman talebinden
 * gelen siparis tamamlaninca kendiliginden acilir. Silinmez, arsive alinir.
 *
 * D-192 (B53): kalemli alimda her kalem bir satirdir; satirlar ayni satin
 * alma talebine (`purchase_request_id`) ve varsa siparise
 * (`purchase_order_id`) baglanir.
 */
#[Table('company_purchases')]
#[Fillable([
    'catalog_item_id', 'purchase_need_id', 'org_unit_id', 'personnel_id', 'supplier_party_id', 'supplier_name',
    'description', 'purchased_on', 'quantity', 'unit_price', 'currency_code', 'renewal_months', 'next_renewal_on',
    'ends_on', 'note',
    // D-192 (B53): kalemli sirket ici alim talebe ve siparise baglanir
    'purchase_request_id', 'purchase_order_id',
])]
#[UsePolicy(CompanyPurchasePolicy::class)]
class CompanyPurchase extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:6',
            'renewal_months' => 'integer',
            'next_renewal_on' => 'date',
            'ends_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(PurchaseNeed::class, 'purchase_need_id');
    }

    /** Kalemli satin alma talebi (D-192, B53). */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** Alimin geldigi siparis (D-192, B53). */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function supplier(): BelongsTo
    {
        // D-232: tedarikci listesindeki firma (yalniz tedarikci olsa da).
        return $this->belongsTo(Party::class, 'supplier_party_id')->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B52') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }

    public function totalAmount(): float
    {
        return (float) $this->getAttribute('quantity') * (float) $this->getAttribute('unit_price');
    }
}
