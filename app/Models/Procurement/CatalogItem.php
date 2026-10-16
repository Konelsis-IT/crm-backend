<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\Currency;
use App\Models\Reference\UnitOfMeasure;
use App\Policies\CatalogItemPolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sabit katalog kalemi (B52, D-189): Urun, Hizmet, Genel gider, Idari kadro,
 * Sirket ici ihtiyac. Ayni turde ayni normalize ad bir kez; Excel'den gelen
 * satir once ad, sonra esleme adlari (aliases) ile var olana baglanir (D-187
 * kurali: her yuklemede yeni kayit acilmaz). `requires_approval` kapaliysa
 * siparis onaysiz tamamlanir; `renewal_months` yenilenen urun / hizmet.
 */
#[Table('catalog_items')]
#[Fillable([
    'kind', 'usage_area', 'catalog_category_id', 'code', 'name', 'normalized_name', 'unit', 'currency_code', 'brand',
    'specification', 'requires_approval', 'renewal_months',
    // D-194 (B54): sabit birim (Ayarlar > Birimler); `unit` metni birimin adiyla esitlenir.
    'uom_id',
])]
#[UsePolicy(CatalogItemPolicy::class)]
class CatalogItem extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CatalogItemKind::class,
            'usage_area' => UsageArea::class,
            'requires_approval' => 'boolean',
            'renewal_months' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /** Sabit birim (D-194, B54). */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CatalogItemAlias::class, 'catalog_item_id')->orderBy('alias');
    }

    public function costLines(): HasMany
    {
        return $this->hasMany(CostListLine::class, 'catalog_item_id');
    }

    public function needs(): HasMany
    {
        return $this->hasMany(PurchaseNeed::class, 'catalog_item_id');
    }

    public function companyPurchases(): HasMany
    {
        return $this->hasMany(CompanyPurchase::class, 'catalog_item_id');
    }

    /** Satin alma tekliflerindeki satirlari (D-192, B53; kalem sayfasinda "Fiyat gecmisi", D-194). */
    public function quotationLines(): HasMany
    {
        return $this->hasMany(PurchaseQuotationLine::class, 'catalog_item_id');
    }

    /** Siparis satirlari (D-192, B53). */
    public function orderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'catalog_item_id');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B52') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }

    public function kindEnum(): CatalogItemKind
    {
        $kind = $this->getAttribute('kind');

        return $kind instanceof CatalogItemKind ? $kind : (CatalogItemKind::tryFrom((string) $kind) ?? CatalogItemKind::Product);
    }

    /** Ana kullanim yeri (D-190): Sirket ici / Saha; B52A oncesi ya da bos ise null. */
    public function usageEnum(): ?UsageArea
    {
        $area = $this->getAttribute('usage_area');

        return $area instanceof UsageArea ? $area : UsageArea::tryFrom((string) $area);
    }
}
