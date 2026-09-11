<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Models\Party\CommunicationPoint;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Iletisim noktasi servisi (10 SS1.6): normalized_value hesaplanir,
 * kanal basina tek "asil" nokta korunur.
 */
final class CommunicationPointService extends AbstractService
{
    protected string $model = CommunicationPoint::class;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $data = $this->normalize($data);
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
            /** @var CommunicationPoint $current */
            $current = $this->show($record);
            $data = $this->normalize($data);
            $this->clearPrimary([
                'party_id' => $current->party_id,
                'channel_type' => $current->channel_type->value,
                ...$data,
            ], (int) $current->getKey());

            return parent::update($current, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (array_key_exists('value', $data)) {
            $value = trim((string) $data['value']);
            $data['value'] = $value;
            $data['normalized_value'] = Str::of($value)->lower()->squish()->value();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clearPrimary(array $data, ?int $exceptId): void
    {
        if (! (bool) ($data['is_primary'] ?? false)) {
            return;
        }

        $channel = $data['channel_type'] ?? null;

        CommunicationPoint::query()
            ->where('party_id', (int) ($data['party_id'] ?? 0))
            ->where('channel_type', $channel instanceof \BackedEnum ? $channel->value : (string) $channel)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
