<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Document\DocumentTemplate;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Sablon katalogu servisi.
 *
 * create override edilmistir: kod benzersiz olmalidir ve duzenlemede
 * degistirilemez.
 */
final class DocumentTemplateService extends AbstractService
{
    protected string $orderBy = 'name';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (DocumentTemplate::query()->where('code', $code)->exists()) {
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
