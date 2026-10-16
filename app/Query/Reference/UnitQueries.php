<?php

declare(strict_types=1);

namespace App\Query\Reference;

use App\Enums\Reference\UomDimension;
use App\Enums\Shared\ActiveStatus;
use App\Models\Procurement\CatalogItem;
use App\Models\Reference\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sabit birim listesinin okumalari (D-194, B54; Ayarlar > Birimler). Secim
 * kutulari ve metin -> birim cozumu UnitResolver uzerinden bu sinifi okur.
 */
final class UnitQueries
{
    /**
     * Butun birimler (aktif ve pasif), ada gore.
     *
     * @return list<array{id: int, dimension: string, code: string, symbol: string, name: string, active: bool}>
     */
    public function all(): array
    {
        $rows = [];

        foreach (UnitOfMeasure::query()->orderBy('name_tr')->orderBy('id')->get(['id', 'dimension', 'code', 'symbol', 'name_tr', 'status']) as $unit) {
            /** @var UnitOfMeasure $unit */
            $dimension = $unit->getAttribute('dimension');
            $status = $unit->getAttribute('status');

            $rows[] = [
                'id' => (int) $unit->getKey(),
                'dimension' => $dimension instanceof UomDimension ? $dimension->value : (string) $dimension,
                'code' => (string) $unit->getAttribute('code'),
                'symbol' => (string) $unit->getAttribute('symbol'),
                'name' => (string) $unit->getAttribute('name_tr'),
                'active' => ($status instanceof ActiveStatus ? $status : ActiveStatus::tryFrom((string) $status)) !== ActiveStatus::Inactive,
            ];
        }

        return $rows;
    }

    /**
     * Katalog kalemlerinin birimleri (kimlik => uom_id; birimi olmayan kalem yok).
     *
     * @param  array<int, int|string>  $itemIds
     * @return array<int, int>
     */
    public function catalogItemUnits(array $itemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds))));

        if ($ids === []) {
            return [];
        }

        return CatalogItem::query()
            ->whereKey($ids)
            ->whereNotNull('uom_id')
            ->pluck('uom_id', 'id')
            ->mapWithKeys(static fn (mixed $uomId, mixed $id): array => [(int) $id => (int) $uomId])
            ->all();
    }

    /** Ayarlar > Birimler listesinin sirasi: once ture, sonra ada gore. */
    public function listOrder(Builder $query): Builder
    {
        return $query->orderBy('dimension')->orderBy('name_tr');
    }

    /** Ayni turde ayni kod var mi (kod uretiminde benzersizlik)? */
    public function codeTaken(string $dimension, string $code, ?int $exceptId = null): bool
    {
        return UnitOfMeasure::query()
            ->where('dimension', $dimension)
            ->where('code', $code)
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /** Baska birimlerin taban birimi mi (ya da kendisinin taban birimi var mi)? */
    public function hasConversions(UnitOfMeasure $unit): bool
    {
        return $unit->getAttribute('base_unit_id') !== null
            || UnitOfMeasure::query()->where('base_unit_id', $unit->getKey())->exists();
    }
}
