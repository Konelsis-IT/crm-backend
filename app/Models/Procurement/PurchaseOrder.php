<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Approval\ApprovalRequest;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\Currency;
use App\Policies\PurchaseOrderPolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Siparis (D-192, B53; kullanici: "Siparisler ise ilgili teklif
 * onaylandiginda olusan surec icin kullanilan bir yapidir. Tekliften gelir.
 * Secili teklif siparise donusur. Ayni zamanda logistic sureci de olmasi
 * lazim. Teslim edilecegi donem ... teslim edilecegi adres").
 *
 * Bir teklif bir kez siparise donusur (uk_purchase_orders_quotation). Tutar
 * sinirina gore onay motoru (konu turu purchase_order) siparisi acar: 4.000
 * TL'ye kadar Satin alma muduru, ustu Yonetim kurulu baskani. Lojistik:
 * teslim adresi / ilgili kisi, teslim donemi, beklenen teslim, tasiyici,
 * takip no; asamalar PurchaseOrderStatus. Kod SIP-YYYY-NNNN.
 */
#[Table('purchase_orders')]
#[Fillable([
    'order_no', 'purchase_quotation_id', 'supplier_party_id', 'supplier_name', 'project_id', 'currency_code', 'try_rate',
    'subtotal', 'discount_total', 'total_amount', 'status', 'approval_request_id', 'delivery_address',
    'delivery_contact', 'delivery_from', 'delivery_until', 'expected_delivery_on', 'carrier', 'tracking_no',
    'payment_terms', 'ordered_by_personnel_id', 'ordered_at', 'shipped_at', 'delivered_at',
    'completed_by_personnel_id', 'completed_at', 'cancelled_at', 'note',
])]
#[UsePolicy(PurchaseOrderPolicy::class)]
class PurchaseOrder extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'try_rate' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'delivery_from' => 'date',
            'delivery_until' => 'date',
            'expected_delivery_on' => 'date',
            'ordered_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class, 'purchase_quotation_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id')->orderBy('sort_order')->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        // D-232: tedarikci listesindeki firma (yalniz tedarikci olsa da).
        return $this->belongsTo(Party::class, 'supplier_party_id')->withoutGlobalScope(HidePurchaseOnlyPartiesScope::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'ordered_by_personnel_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'completed_by_personnel_id');
    }

    public function companyPurchases(): HasMany
    {
        return $this->hasMany(CompanyPurchase::class, 'purchase_order_id');
    }

    public function statusEnum(): PurchaseOrderStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof PurchaseOrderStatus ? $status : (PurchaseOrderStatus::tryFrom((string) $status) ?? PurchaseOrderStatus::Draft);
    }

    public function supplierLabel(): string
    {
        $party = $this->supplier;

        return $party instanceof Party ? (string) $party->getAttribute('display_name') : (string) ($this->getAttribute('supplier_name') ?? '');
    }

    /** Siparis toplaminin TL karsiligi (TL sipariste kendisi; kur yoksa null). */
    public function totalInTry(): ?float
    {
        $total = (float) $this->getAttribute('total_amount');

        if (strtoupper((string) $this->getAttribute('currency_code')) === 'TRY') {
            return $total;
        }

        $rate = $this->getAttribute('try_rate');

        return $rate !== null && (float) $rate > 0 ? $total * (float) $rate : null;
    }

    /** Beklenen teslim gecti mi (henuz teslim alinmamis siparis)? */
    public function isLate(?string $today = null): bool
    {
        $expected = $this->getAttribute('expected_delivery_on') ?? $this->getAttribute('delivery_until');

        if ($expected === null || ! $this->statusEnum()->isOnTheWay()) {
            return false;
        }

        return $expected->toDateString() < ($today ?? now(\App\Support\DisplayTime::zone())->toDateString());
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B53') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }
}
