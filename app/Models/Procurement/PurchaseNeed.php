<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\UsageArea;
use App\Models\Acquisition\Proposal;
use App\Models\Approval\ApprovalRequest;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\Currency;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\PurchaseNeedPolicy;
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
 * Siparisi gecilecek kalem (B52, D-189). Teklif maliyetinden gelen kalem
 * hangi teklif (ve varsa proje) icin istendigini tasir; proje onayina kadar
 * satin almanin is listesine dusmez. Tedarikci teklifleri `quotes`, satin
 * almanin sectigi `recommendedQuote`; tutar sinirina gore onay motoru
 * (`approvalRequest`) siparisi acar. Silinmez, arsive alinir (D-156).
 *
 * D-192 (B53): ekranda "Satin alinacaklar". Kalemli satin alma talebinin
 * satiri olabilir (`purchaseRequest`); satin alma teklifinin kalemlerine
 * (`quotationLines`) ve siparis kalemine (`orderLines`) baglanir.
 */
#[Table('purchase_needs')]
#[Fillable([
    'source', 'usage_area', 'catalog_item_id', 'requested_item_name', 'brand_model', 'specification', 'justification',
    'existing_check', 'cost_list_line_id', 'proposal_id', 'project_id', 'org_unit_id',
    'requested_by_personnel_id', 'title', 'quantity', 'unit', 'estimated_unit_cost', 'estimated_currency_code',
    'needed_by', 'is_urgent', 'status', 'project_approved_by_personnel_id', 'project_approved_at',
    'recommended_quote_id', 'recommended_by_personnel_id', 'recommended_at', 'approval_request_id',
    'ordered_by_personnel_id', 'ordered_at', 'expected_delivery_on', 'completed_by_personnel_id', 'completed_at',
    'cancelled_at', 'note',
    // D-192 (B53): kalemli talebin satiri
    'purchase_request_id', 'sort_order',
    // D-194 (B54): sabit birim
    'uom_id',
])]
#[UsePolicy(PurchaseNeedPolicy::class)]
class PurchaseNeed extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PurchaseNeedSource::class,
            'usage_area' => UsageArea::class,
            'status' => PurchaseNeedStatus::class,
            'quantity' => 'decimal:4',
            'estimated_unit_cost' => 'decimal:6',
            'needed_by' => 'date',
            'is_urgent' => 'boolean',
            'project_approved_at' => 'datetime',
            'recommended_at' => 'datetime',
            'ordered_at' => 'datetime',
            'expected_delivery_on' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function costListLine(): BelongsTo
    {
        return $this->belongsTo(CostListLine::class, 'cost_list_line_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'requested_by_personnel_id');
    }

    public function projectApprovedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'project_approved_by_personnel_id');
    }

    public function recommendedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'recommended_by_personnel_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'ordered_by_personnel_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'completed_by_personnel_id');
    }

    public function estimatedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'estimated_currency_code', 'code');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(PurchaseQuote::class, 'purchase_need_id')->orderBy('total_amount');
    }

    public function recommendedQuote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'recommended_quote_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function companyPurchase(): HasOne
    {
        return $this->hasOne(CompanyPurchase::class, 'purchase_need_id');
    }

    /** Sabit birim (D-194, B54). */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** Kalemli satin alma talebi (D-192, B53). */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** Kalemin girdigi satin alma teklifi satirlari (D-192). */
    public function quotationLines(): HasMany
    {
        return $this->hasMany(PurchaseQuotationLine::class, 'purchase_need_id');
    }

    /** Kalemin siparis satiri (D-192). */
    public function orderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_need_id');
    }

    public function statusEnum(): PurchaseNeedStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof PurchaseNeedStatus ? $status : (PurchaseNeedStatus::tryFrom((string) $status) ?? PurchaseNeedStatus::Open);
    }

    public function sourceEnum(): PurchaseNeedSource
    {
        $source = $this->getAttribute('source');

        return $source instanceof PurchaseNeedSource ? $source : (PurchaseNeedSource::tryFrom((string) $source) ?? PurchaseNeedSource::Manual);
    }

    /** Kullanim yeri: kalemin kendisi, yoksa katalog kaleminin yeri (D-190). */
    public function usageEnum(): ?UsageArea
    {
        $area = $this->getAttribute('usage_area');
        $area = $area instanceof UsageArea ? $area : UsageArea::tryFrom((string) $area);

        return $area ?? $this->catalogItem?->usageEnum();
    }

    /** Katalog kalemi bagli mi (talep katalogda olmayan bir urunle acilabilir, D-190). */
    public function hasCatalogItem(): bool
    {
        return $this->getAttribute('catalog_item_id') !== null;
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B52') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }

    /** Termin gecmis mi (kapanmamis ve ihtiyac tarihi bugunden once)? */
    public function isOverdue(?string $today = null): bool
    {
        $neededBy = $this->getAttribute('needed_by');

        if ($neededBy === null || $this->statusEnum()->isClosed() || $this->statusEnum() === PurchaseNeedStatus::Ordered) {
            return false;
        }

        return $neededBy->toDateString() < ($today ?? now(\App\Support\DisplayTime::zone())->toDateString());
    }
}
