<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\PartyRoleCode;
use App\Exceptions\DuplicateRecordException;
use App\Models\Party\PartyRole;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Party rolu servisi (10 SS1.2, D-28).
 *
 * Ayni party icin ayni rolde ikinci bir acik (valid_until NULL) satir
 * acilamaz; DB'deki active_guard'a takilmadan once burada anlasilir bir
 * hata verilir.
 */
final class PartyRoleService extends AbstractService
{
    protected string $model = PartyRole::class;

    protected string $orderBy = 'valid_from';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        // D-167: Musteri ve Yatirimci Isveren'de toplandi; eski kod gelirse
        // (seeder, ice aktarma) satir Isveren yazilir. Tarafta acik Isveren
        // varsa asagidaki cift satir denetimi devreye girer.
        $data['role_code'] = PartyRoleCode::normalize($data['role_code'] ?? null);
        $data['valid_from'] ??= Carbon::now('UTC');

        $code = PartyRoleCode::tryFrom((string) ($data['role_code'] ?? ''));

        $exists = PartyRole::query()
            ->where('party_id', (int) ($data['party_id'] ?? 0))
            // Isveren, B46 oncesi acik kalmis Musteri / Yatirimci satiriyla da cakisir.
            ->whereIn('role_code', $code?->storedValues() ?? [(string) ($data['role_code'] ?? '')])
            ->whereNull('valid_until')
            ->exists();

        if ($exists && blank($data['valid_until'] ?? null)) {
            throw DuplicateRecordException::make();
        }

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['party_id'], $data['role_code']);

        return parent::update($record, $data);
    }
}
