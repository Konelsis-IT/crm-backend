<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Exceptions\DuplicateRecordException;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\Party;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Iletisim noktasi servisi (10 SS1.6): normalized_value hesaplanir,
 * kanal basina tek "asil" nokta korunur. Ayni tarafta ayni kanal turu ve
 * degerle ikinci kayit (kisiye ya da kuruma ait fark etmez) alan istisnasi
 * olarak reddedilir; DB'deki tekil anahtar ikinci savunmadir.
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
            $this->assertUnique($data, null);
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
            $merged = [
                'party_id' => $current->party_id,
                'channel_type' => $current->channel_type->value,
                'normalized_value' => $current->normalized_value,
                ...$data,
            ];
            $this->assertUnique($merged, (int) $current->getKey());
            $this->clearPrimary($merged, (int) $current->getKey());

            return parent::update($current, $data);
        });
    }

    /**
     * Kurumun kendi kanallarini (kisiye bagli olmayanlar) verilen satirlarla
     * esitler (B28): eski kanallar silinir, 'value' dolu satirlar yeniden
     * yazilir. Kisiye ait kanallara dokunulmaz. channel_type Filament'ten
     * enum ornegi olarak gelebilir; deger stringe indirgenir.
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    public function syncOwnChannels(Party $party, array $rows): void
    {
        $this->transactions->run(function () use ($party, $rows): void {
            foreach ($party->ownCommunicationPoints()->get() as $point) {
                $this->delete($point);
            }

            $seen = [];

            foreach ($rows as $row) {
                if (! filled($row['value'] ?? null)) {
                    continue;
                }

                $channel = $row['channel_type'] ?? null;
                $channel = $channel instanceof \BackedEnum ? $channel->value : $channel;
                $key = $channel.'|'.Str::of(trim((string) $row['value']))->lower()->squish()->value();

                // Ayni satir iki kez girildiyse tek kayit yazilir.
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $this->create([
                    'party_id' => $party->getKey(),
                    'contact_relationship_id' => null,
                    'channel_type' => $channel,
                    'value' => $row['value'],
                    'purpose' => $row['purpose'] ?? null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]);
            }
        });

        $party->unsetRelation('ownCommunicationPoints');
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
     * Ayni taraf + kanal turu + normalize deger varsa alan istisnasi firlatir.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertUnique(array $data, ?int $exceptId): void
    {
        $channel = $data['channel_type'] ?? null;
        $normalized = $data['normalized_value'] ?? null;

        if ($channel === null || $normalized === null) {
            return;
        }

        $exists = CommunicationPoint::query()
            ->where('party_id', (int) ($data['party_id'] ?? 0))
            ->where('channel_type', $channel instanceof \BackedEnum ? $channel->value : (string) $channel)
            ->where('normalized_value', (string) $normalized)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw DuplicateRecordException::make();
        }
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
