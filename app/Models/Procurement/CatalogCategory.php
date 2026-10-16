<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Enums\Procurement\CatalogItemKind;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\CatalogCategoryPolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Katalog kategorisi (B52, D-189): tur basina agac. Excel maliyet listesinin
 * bolum ve gruplari (Insaat > Saha Duzeltme) buradan gelir. Silinmez, arsive
 * alinir (yalniz archived_at, D-156).
 */
#[Table('catalog_categories')]
#[Fillable(['kind', 'parent_id', 'code', 'name', 'sort_order'])]
#[UsePolicy(CatalogCategoryPolicy::class)]
class CatalogCategory extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CatalogItemKind::class,
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'catalog_category_id');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B52') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }

    /** "Insaat › Saha Duzeltme" bicimindeki yol. */
    public function path(): string
    {
        $parent = $this->parent;

        return $parent instanceof self ? $parent->getAttribute('name').' › '.$this->getAttribute('name') : (string) $this->getAttribute('name');
    }
}
