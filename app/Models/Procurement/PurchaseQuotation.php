<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\QuotationAiStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Models\Personnel\Personnel;
use App\Models\Reference\Currency;
use App\Policies\PurchaseQuotationPolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satin alma teklifi: bir tedarikcinin kalemli teklifi (D-192, B53;
 * kullanici: "daha kapsamlisi 'Satin alma teklifleri' kismi olusturulup orada
 * tutulmali"). Satin alinacaklar'dan secilen kalemler tek teklifte toplanir;
 * fiyatlar elle ya da Konelsis AI ile yuklenen belgelerden girilir. Secilen
 * teklif siparise donusur (`order`). Kod STKF-YYYY-NNNN.
 *
 * Toplamlar kalemlerden hesaplanir (opsiyon ve iptal kalemler haric):
 * subtotal = miktar x birim fiyat toplami, discount_total = kalem
 * indirimleri + genel indirim, total_amount = subtotal - discount_total.
 */
#[Table('purchase_quotations')]
#[Fillable([
    'quotation_no', 'supplier_party_id', 'supplier_name', 'supplier_reference', 'quote_date', 'valid_until',
    'currency_code', 'try_rate', 'payment_terms', 'delivery_terms', 'lead_time_days', 'delivery_on',
    'general_discount', 'subtotal', 'discount_total', 'total_amount', 'status', 'selected_by_personnel_id',
    'selected_at', 'ai_status', 'ai_job_reference', 'ai_requested_at', 'note',
])]
#[UsePolicy(PurchaseQuotationPolicy::class)]
class PurchaseQuotation extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseQuotationStatus::class,
            'ai_status' => QuotationAiStatus::class,
            'quote_date' => 'date',
            'valid_until' => 'date',
            'delivery_on' => 'date',
            'try_rate' => 'decimal:6',
            'lead_time_days' => 'integer',
            'general_discount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'selected_at' => 'datetime',
            'ai_requested_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseQuotationLine::class, 'purchase_quotation_id')->orderBy('sort_order')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PurchaseQuotationDocument::class, 'purchase_quotation_id')->orderBy('id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class, 'purchase_quotation_id');
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

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'selected_by_personnel_id');
    }

    public function statusEnum(): PurchaseQuotationStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof PurchaseQuotationStatus ? $status : (PurchaseQuotationStatus::tryFrom((string) $status) ?? PurchaseQuotationStatus::Draft);
    }

    public function aiStatusEnum(): ?QuotationAiStatus
    {
        $status = $this->getAttribute('ai_status');

        return $status instanceof QuotationAiStatus ? $status : QuotationAiStatus::tryFrom((string) $status);
    }

    /** Tedarikcinin gorunen adi: taraf kaydi ya da serbest ad. */
    public function supplierLabel(): string
    {
        $party = $this->supplier;

        return $party instanceof Party ? (string) $party->getAttribute('display_name') : (string) ($this->getAttribute('supplier_name') ?? '');
    }

    /** Teklif toplaminin TL karsiligi (TL teklifte kendisi; kur yoksa null). */
    public function totalInTry(): ?float
    {
        $total = (float) $this->getAttribute('total_amount');

        if (strtoupper((string) $this->getAttribute('currency_code')) === 'TRY') {
            return $total;
        }

        $rate = $this->getAttribute('try_rate');

        return $rate !== null && (float) $rate > 0 ? $total * (float) $rate : null;
    }

    /** Gecerlilik tarihi gecmis mi? */
    public function isExpired(?string $today = null): bool
    {
        $until = $this->getAttribute('valid_until');

        return $until !== null && $until->toDateString() < ($today ?? now(\App\Support\DisplayTime::zone())->toDateString());
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
