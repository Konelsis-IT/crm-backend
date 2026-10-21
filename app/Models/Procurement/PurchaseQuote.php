<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Scopes\HidePurchaseOnlyPartiesScope;
use App\Models\Reference\Currency;
use App\Policies\PurchaseQuotePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Siparis kalemi icin tedarikci teklifi (B52, D-189). Bir kalemde birden fazla
 * teklif olur ve kartlarla karsilastirilir; `try_rate` dovizli teklifin
 * TL karsiligi (onay siniri TL ile karsilastirilir). Kalemin alt satiridir.
 */
#[Table('purchase_quotes')]
#[Fillable([
    'purchase_need_id', 'supplier_party_id', 'supplier_name', 'brand_model', 'quantity', 'unit_price', 'currency_code',
    'try_rate', 'total_amount', 'lead_time_days', 'delivery_on', 'payment_terms', 'valid_until', 'note',
])]
#[UsePolicy(PurchaseQuotePolicy::class)]
class PurchaseQuote extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:6',
            'try_rate' => 'decimal:6',
            'total_amount' => 'decimal:4',
            'lead_time_days' => 'integer',
            'delivery_on' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(PurchaseNeed::class, 'purchase_need_id');
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

    /** Tedarikcinin gorunen adi: taraf kaydi ya da serbest ad. */
    public function supplierLabel(): string
    {
        $party = $this->supplier;

        return $party instanceof Party ? (string) $party->getAttribute('display_name') : (string) $this->getAttribute('supplier_name');
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
}
