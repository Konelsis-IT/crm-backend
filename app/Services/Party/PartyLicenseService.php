<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\PartyCredentialStatus;
use App\Models\Party\PartyLicense;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Party lisansi servisi (10 SS1.8): durum (gecerli / suresi yaklasiyor /
 * doldu) valid_until'dan yazma aninda hesaplanir; yalniz 'revoked' elle
 * secilir (sertifikalarla ayni desen, D-60).
 */
final class PartyLicenseService extends AbstractService
{
    protected string $model = PartyLicense::class;

    protected string $orderBy = 'valid_until';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return parent::create(self::withComputedStatus($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var PartyLicense $current */
        $current = $this->show($record);
        unset($data['party_id']);

        return parent::update($current, self::withComputedStatus([
            'valid_until' => $current->valid_until?->toDateString(),
            ...$data,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withComputedStatus(array $data): array
    {
        $status = $data['status'] ?? null;
        $status = $status instanceof PartyCredentialStatus ? $status->value : $status;

        if ($status === PartyCredentialStatus::Revoked->value) {
            return $data;
        }

        $validUntil = $data['valid_until'] ?? null;

        if (blank($validUntil)) {
            $data['status'] = PartyCredentialStatus::Valid;

            return $data;
        }

        $until = Carbon::parse($validUntil instanceof \DateTimeInterface ? $validUntil->format('Y-m-d') : (string) $validUntil)->endOfDay();
        $now = Carbon::now();

        $data['status'] = match (true) {
            $until->isPast() => PartyCredentialStatus::Expired,
            $until->lte($now->copy()->addDays(30)) => PartyCredentialStatus::Expiring,
            default => PartyCredentialStatus::Valid,
        };

        return $data;
    }
}
