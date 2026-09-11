<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Project\OperationGroupDefinition;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Operasyon grubu katalogu servisi (11 SS1.4, D-22). Kod benzersizdir ve duzenlemede degistirilemez.
 */
final class OperationGroupDefinitionService extends AbstractService
{
    protected string $model = OperationGroupDefinition::class;

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (OperationGroupDefinition::query()->where('code', $code)->exists()) {
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
