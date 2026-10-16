<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Procurement\CatalogItemAlias;
use App\Services\AbstractService;
use App\Support\Procurement\CatalogNames;
use Illuminate\Database\Eloquent\Model;

/**
 * Katalog esleme adlari (B52, D-189): Excel'deki bir yazim bir kalemle
 * eslesince yazim kaydedilir; ayni yazim bir daha geldiginde kendiliginden o
 * kaleme baglanir. Ayni normalize yazim tek kaleme baglidir; var olan yazim
 * yeniden eklenmez.
 */
final class CatalogItemAliasService extends AbstractService
{
    /**
     * @param  array<string, mixed>  $data  catalog_item_id, alias
     */
    public function create(array $data): Model
    {
        $normalized = CatalogNames::normalize((string) ($data['alias'] ?? ''));
        $existing = CatalogItemAlias::query()->where('normalized_alias', $normalized)->first();

        if ($existing instanceof CatalogItemAlias) {
            return $existing;
        }

        return parent::create([
            'catalog_item_id' => (int) $data['catalog_item_id'],
            'alias' => mb_substr(trim((string) $data['alias']), 0, 300),
            'normalized_alias' => $normalized,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return ['ad' => $record->getAttribute('alias'), 'kalem' => $record->getAttribute('catalog_item_id')];
    }
}
