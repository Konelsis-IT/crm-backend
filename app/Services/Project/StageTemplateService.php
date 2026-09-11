<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Project\StageTemplate;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Stage-gate sablonu katalogu servisi (11 SS2.1). Kod benzersizdir ve
 * duzenlemede degistirilemez; current_version_id yayimla islemiyle yazilir.
 */
final class StageTemplateService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['currentVersion'];

    protected string $orderBy = 'code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (StageTemplate::query()->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        unset($data['current_version_id']);

        return parent::create([...$data, 'code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['code'], $data['current_version_id']);

        return parent::update($record, $data);
    }
}
