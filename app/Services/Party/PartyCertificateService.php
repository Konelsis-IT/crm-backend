<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Models\Party\PartyCertificate;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Party sertifikasi servisi (10 SS1.9): lisanslarla ayni durum hesabi.
 */
final class PartyCertificateService extends AbstractService
{
    protected string $model = PartyCertificate::class;

    protected string $orderBy = 'valid_until';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return parent::create(PartyLicenseService::withComputedStatus($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var PartyCertificate $current */
        $current = $this->show($record);
        unset($data['party_id']);

        return parent::update($current, PartyLicenseService::withComputedStatus([
            'valid_until' => $current->valid_until?->toDateString(),
            ...$data,
        ]));
    }
}
