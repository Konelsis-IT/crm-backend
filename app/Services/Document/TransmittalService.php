<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document\Transmittal;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Teslim tutanagi servisi.
 *
 * create override edilmistir: transmittal_no arayuzde sorulmaz, otomatik
 * uretilir (D-51'deki departman kodu deseninin aynisi).
 */
final class TransmittalService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['issuer', 'coverRevision'];

    protected string $orderBy = 'transmittal_no';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return parent::create([...$data, 'transmittal_no' => $this->generateTransmittalNo()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['transmittal_no']);

        return parent::update($record, $data);
    }

    private function generateTransmittalNo(): string
    {
        $sequence = Transmittal::query()->count() + 1;
        $transmittalNo = sprintf('TRN-%05d', $sequence);

        while (Transmittal::query()->where('transmittal_no', $transmittalNo)->exists()) {
            $sequence++;
            $transmittalNo = sprintf('TRN-%05d', $sequence);
        }

        return $transmittalNo;
    }
}
