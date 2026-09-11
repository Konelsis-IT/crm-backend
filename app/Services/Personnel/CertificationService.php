<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Exceptions\CodeAlreadyInUseException;
use App\Models\Personnel\Certification;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Sertifika tanimi servisi.
 *
 * Yalniz create override edilmistir: kod benzersiz olmalidir ve
 * duzenlemede degistirilemez. Diger dort islem AbstractService'ten gelir.
 */
final class CertificationService extends AbstractService
{
    protected string $orderBy = 'name';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (Certification::query()->where('code', $code)->exists()) {
            throw CodeAlreadyInUseException::make(['code' => $code]);
        }

        return parent::create([...$data, 'code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // Kod duzenlemede degistirilmez.
        unset($data['code']);

        return parent::update($record, $data);
    }
}
