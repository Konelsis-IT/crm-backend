<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Exceptions\CodeAlreadyInUseException;
use App\Exceptions\Party\ActivityAreaParentInvalidException;
use App\Models\Party\ActivityArea;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Faaliyet alani katalogu servisi (B33, D-107). Iki seviye: ana faaliyet
 * alani ve onun alt faaliyet alanlari. Kod benzersizdir, buyuk harfe
 * cevrilir ve duzenlemede degistirilemez. Ust alan yalniz ana alan olabilir;
 * alti dolu bir ana alan baska bir alanin altina tasinamaz.
 */
final class ActivityAreaService extends AbstractService
{
    protected string $model = ActivityArea::class;

    protected string $orderBy = 'sort_order';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (ActivityArea::query()->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        $data['parent_id'] = $this->validParent($data['parent_id'] ?? null, null);

        return parent::create([...$data, 'code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['code']);

        if (array_key_exists('parent_id', $data)) {
            /** @var ActivityArea $current */
            $current = $this->show($record);
            $data['parent_id'] = $this->validParent($data['parent_id'], $current);
        }

        return parent::update($record, $data);
    }

    /** Ust alan kimligini dogrular; bos ise null (ana faaliyet alani). */
    private function validParent(mixed $parentId, ?ActivityArea $current): ?int
    {
        if (blank($parentId)) {
            return null;
        }

        $parent = ActivityArea::query()->find((int) $parentId);

        $invalid = ! $parent instanceof ActivityArea
            || ! $parent->isRoot()
            || ($current !== null && ((int) $parent->getKey() === (int) $current->getKey() || $current->children()->exists()));

        if ($invalid) {
            throw ActivityAreaParentInvalidException::make();
        }

        return (int) $parent->getKey();
    }
}
