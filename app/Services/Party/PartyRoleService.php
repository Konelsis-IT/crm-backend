<?php

declare(strict_types=1);

namespace App\Services\Party;

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
        $data['valid_from'] ??= Carbon::now('UTC');

        $exists = PartyRole::query()
            ->where('party_id', (int) ($data['party_id'] ?? 0))
            ->where('role_code', (string) ($data['role_code'] ?? ''))
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
