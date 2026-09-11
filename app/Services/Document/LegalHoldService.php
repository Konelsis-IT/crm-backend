<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Document\LegalHold;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Hukuki tutma servisi.
 *
 * create override edilmistir: kod benzersiz olmalidir ve duzenlemede
 * degistirilemez.
 */
final class LegalHoldService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['requester', 'approver'];

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (LegalHold::query()->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        return parent::create([...$data, 'code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['code']);

        return parent::update($record, $data);
    }
}
