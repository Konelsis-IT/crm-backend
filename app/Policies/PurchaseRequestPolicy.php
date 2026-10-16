<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseRequest;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\Personnel\OrganizationQueries;

/**
 * Kalemli satin alma talebi (D-192, B53). Kendi izin anahtari yoksa satin alma
 * kalemi (PurchaseNeed) yetkisi gecerlidir (PermissionSubjects).
 *
 * - Talebi her aktif personel acar; kisi kendi talebini ve biriminin
 *   taleplerini gorur, satin alma / ust yonetim hepsini gorur.
 * - Talep eden, hicbir kalemi islenmeden (teklif / siparis yok) talebini
 *   duzeltebilir ve iptal edebilir; satin alma her acik talebi.
 * - Silme yok, arsiv var (D-156).
 */
final class PurchaseRequestPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, PurchaseRequest $record): bool
    {
        return $this->purchasing()->seesAll($personnel)
            || $this->isRequester($personnel, $record)
            // D-218: kisinin calistigi butun departmanlar (ek gorevler dahil).
            || ($record->getAttribute('org_unit_id') !== null && in_array((int) $record->getAttribute('org_unit_id'), app(OrganizationQueries::class)->unitIdsOf((int) $personnel->getKey()), true));
    }

    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function update(Personnel $personnel, PurchaseRequest $record): bool
    {
        if ($record->isArchived() || $record->statusEnum()->isClosed()) {
            return false;
        }

        if ($this->purchasing()->managesPurchasing($personnel)) {
            return true;
        }

        return $this->isRequester($personnel, $record) && $this->untouched($record);
    }

    public function cancel(Personnel $personnel, PurchaseRequest $record): bool
    {
        return $this->update($personnel, $record);
    }

    public function archive(Personnel $personnel, PurchaseRequest $record): bool
    {
        return $this->purchasing()->managesPurchasing($personnel);
    }

    public function delete(Personnel $personnel, PurchaseRequest $record): bool
    {
        return false;
    }

    private function isRequester(Personnel $personnel, PurchaseRequest $record): bool
    {
        return (int) $record->getAttribute('requested_by_personnel_id') === (int) $personnel->getKey();
    }

    /** Satin alma henuz hicbir kalemi islemedi (hepsi Acik ya da Proje onayi bekliyor). */
    private function untouched(PurchaseRequest $record): bool
    {
        return $record->statusEnum() === PurchaseRequestStatus::Open
            && ! $record->needs()
                ->whereNotIn('status', [PurchaseNeedStatus::Open->value, PurchaseNeedStatus::AwaitingProject->value, PurchaseNeedStatus::Cancelled->value])
                ->exists();
    }

    private function purchasing(): PurchaseNeedPolicy
    {
        return app(PurchaseNeedPolicy::class);
    }
}
