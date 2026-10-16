<?php

declare(strict_types=1);

namespace App\Models\Procurement;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\CatalogItemAliasPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Katalog esleme adi (B52, D-189): Excel'deki farkli bir yazim bir kez bir
 * katalog kalemine baglaninca kaydedilir; ayni yazim bir sonraki yuklemede
 * kendiliginden o kaleme baglanir. Satirlar degismez (yalniz created_*).
 */
#[Table('catalog_item_aliases')]
#[Fillable(['catalog_item_id', 'alias', 'normalized_alias'])]
#[UsePolicy(CatalogItemAliasPolicy::class)]
class CatalogItemAlias extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }
}
