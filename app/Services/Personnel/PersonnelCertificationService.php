<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Enums\Personnel\CertificationStatus;
use App\Exceptions\DuplicateRecordException;
use App\Models\Personnel\PersonnelCertification;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Personelin aldigi sertifika kaydi servisi.
 *
 * create ve update override edilmistir: ayni personel/sertifika/tarih
 * ikinci kez kaydedilemez ve durum (gecerli/suresi yaklasiyor/doldu)
 * gecerlilik tarihinden hesaplanir. Iptal (revoked) yalniz elle secilir.
 * Periyodik yeniden degerlendirme (scheduler) ileride eklenir; simdilik
 * durum yalniz kayit olusturulup guncellendiginde hesaplanir.
 */
final class PersonnelCertificationService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['certification', 'verifier'];

    protected string $orderBy = 'issued_on';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertNotDuplicate(
            (int) ($data['personnel_id'] ?? 0),
            (int) ($data['certification_id'] ?? 0),
            (string) ($data['issued_on'] ?? ''),
            null,
        );

        return parent::create($this->withComputedStatus($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $current = $this->show($record);

        $personnelId = (int) ($data['personnel_id'] ?? $current->personnel_id);
        $certificationId = (int) ($data['certification_id'] ?? $current->certification_id);
        $issuedOn = (string) ($data['issued_on'] ?? $current->issued_on);

        $this->assertNotDuplicate($personnelId, $certificationId, $issuedOn, $current);

        return parent::update($current, $this->withComputedStatus($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withComputedStatus(array $data): array
    {
        if (($data['status'] ?? null) === CertificationStatus::Revoked->value) {
            return $data;
        }

        if (array_key_exists('valid_until', $data)) {
            $data['status'] = $this->computeStatus($data['valid_until']);
        }

        return $data;
    }

    private function computeStatus(mixed $validUntil): string
    {
        if (blank($validUntil)) {
            return CertificationStatus::Valid->value;
        }

        $until = Carbon::parse((string) $validUntil)->endOfDay();
        $now = Carbon::now();

        if ($until->isPast()) {
            return CertificationStatus::Expired->value;
        }

        if ($now->diffInDays($until) <= 30) {
            return CertificationStatus::Expiring->value;
        }

        return CertificationStatus::Valid->value;
    }

    private function assertNotDuplicate(int $personnelId, int $certificationId, string $issuedOn, ?Model $except): void
    {
        $query = PersonnelCertification::query()
            ->where('personnel_id', $personnelId)
            ->where('certification_id', $certificationId)
            ->whereDate('issued_on', $issuedOn);

        if ($except !== null) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw DuplicateRecordException::make();
        }
    }
}
