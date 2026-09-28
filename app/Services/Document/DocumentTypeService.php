<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Document\DocumentType;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Doküman tipi katalogu servisi.
 *
 * create override edilmistir: kod benzersiz olmalidir ve duzenlemede
 * degistirilemez.
 */
final class DocumentTypeService extends AbstractService
{
    protected string $orderBy = 'name';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (DocumentType::query()->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        // Numaralandirma oneki arayuzde gosterilmez (D-130): verilmezse tipin
        // kodudur (DocumentTypeSeeder ile ayni kural); dokuman no "KOD-00001".
        $prefix = filled($data['numbering_prefix'] ?? null)
            ? strtoupper(trim((string) $data['numbering_prefix']))
            : $code;

        return parent::create([...$data, 'code' => $code, 'numbering_prefix' => $prefix]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // Kod ve numaralandirma oneki duzenlenmez (onek dokuman numaralarinda yasar).
        unset($data['code'], $data['numbering_prefix']);

        return parent::update($record, $data);
    }
}
