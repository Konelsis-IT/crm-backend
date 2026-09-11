<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Models\Party\Address;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Party adresi servisi (10 SS1.5).
 *
 * Tip basina tek "asil" adres: yeni satir asil isaretlenirse ayni party ve
 * tipteki onceki asil adres otomatik dusurulur (DB guard'i ikinci savunma).
 */
final class AddressService extends AbstractService
{
    protected string $model = Address::class;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $this->clearPrimary($data, null);

            return parent::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var Address $current */
            $current = $this->show($record);
            $this->clearPrimary([
                'party_id' => $current->party_id,
                'address_type' => $data['address_type'] ?? $current->address_type->value,
                ...$data,
            ], (int) $current->getKey());

            return parent::update($current, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clearPrimary(array $data, ?int $exceptId): void
    {
        if (! (bool) ($data['is_primary'] ?? false)) {
            return;
        }

        $type = $data['address_type'] ?? null;

        Address::query()
            ->where('party_id', (int) ($data['party_id'] ?? 0))
            ->where('address_type', $type instanceof \BackedEnum ? $type->value : (string) $type)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
