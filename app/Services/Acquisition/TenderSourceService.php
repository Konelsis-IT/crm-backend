<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Acquisition\TenderSource;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Ihale kaynagi katalogu servisi (10 SS2.7). Kod benzersizdir ve duzenlemede degistirilemez.
 */
final class TenderSourceService extends AbstractService
{
    protected string $model = TenderSource::class;

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (TenderSource::query()->where('code', $code)->exists()) {
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
