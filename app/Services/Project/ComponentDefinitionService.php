<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Project\ComponentDefinition;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Proje bileseni katalogu servisi (11 SS1.2, D-20). Kod benzersizdir ve duzenlemede degistirilemez.
 */
final class ComponentDefinitionService extends AbstractService
{
    protected string $model = ComponentDefinition::class;

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (ComponentDefinition::query()->where('code', $code)->exists()) {
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
