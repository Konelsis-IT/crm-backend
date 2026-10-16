<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;
use App\Query\Personnel\OrganizationQueries;

/**
 * Siparis kalemi (B52, D-189).
 *
 * - Liste herkese acik; kisi kendi talebini ve biriminin taleplerini gorur
 *   (PurchaseNeedQueries::visibleTo). Satin alma (izin), ust yonetim ve tam
 *   yetkili hepsini gorur.
 * - Departman ihtiyac talebi her aktif personel acabilir; satin almanin elle
 *   actigi kalem `create` izniyle (servis kaynagi denetler).
 * - Proje onayi: `approveProject` izni (Proje birimi), tam yetki ya da kalemin
 *   projesinin proje muduru.
 * - Teklif, onerilen teklif, siparis, tamamlama, iptal: satin alma (`update`).
 * - Silme yok, arsiv var (D-156).
 */
final class PurchaseNeedPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, PurchaseNeed $record): bool
    {
        if ($this->seesAll($personnel)) {
            return true;
        }

        return (int) $record->getAttribute('requested_by_personnel_id') === (int) $personnel->getKey()
            // D-218: kisinin calistigi butun departmanlar (ek gorevler dahil).
            || ($record->getAttribute('org_unit_id') !== null && in_array((int) $record->getAttribute('org_unit_id'), app(OrganizationQueries::class)->unitIdsOf((int) $personnel->getKey()), true))
            || $this->approveProject($personnel, $record)
            || $this->isProjectManager($personnel, $record);
    }

    /** Departman ihtiyac talebi: her aktif personel. */
    public function create(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    /** Satin almanin kendi actigi kalem (kaynak: Satin alma). */
    public function createManual(Personnel $personnel): bool
    {
        return $this->managesPurchasing($personnel);
    }

    public function update(Personnel $personnel, PurchaseNeed $record): bool
    {
        if ($record->isArchived() || $record->statusEnum()->isClosed()) {
            return false;
        }

        if ($this->managesPurchasing($personnel)) {
            return true;
        }

        // Talep eden, satin alma islemeye baslamadan kendi talebini duzeltebilir.
        return (int) $record->getAttribute('requested_by_personnel_id') === (int) $personnel->getKey()
            && in_array($record->statusEnum(), [PurchaseNeedStatus::AwaitingProject, PurchaseNeedStatus::Open], true);
    }

    /** Proje onayi (teklif maliyetinden gelen kalem). */
    public function approveProject(Personnel $personnel, PurchaseNeed $record): bool
    {
        if ($record->statusEnum() !== PurchaseNeedStatus::AwaitingProject || $record->isArchived()) {
            return false;
        }

        return $this->hasFullAccess($personnel)
            || $this->permits($personnel, 'approveProject')
            || $this->isProjectManager($personnel, $record);
    }

    /** Tedarikci teklifi, onerilen teklif, onaya gonderme, siparis, tamamlama, iptal. */
    public function purchase(Personnel $personnel, PurchaseNeed $record): bool
    {
        return ! $record->isArchived() && ! $record->statusEnum()->isClosed()
            && $record->statusEnum() !== PurchaseNeedStatus::AwaitingProject
            && $this->managesPurchasing($personnel);
    }

    public function archive(Personnel $personnel, PurchaseNeed $record): bool
    {
        return $this->managesPurchasing($personnel);
    }

    public function delete(Personnel $personnel, PurchaseNeed $record): bool
    {
        return false;
    }

    /** Butun kalemleri goren: satin alma izni, ust yonetim, tam yetki, denetci. */
    public function seesAll(Personnel $personnel): bool
    {
        return $this->canRead($personnel) || $this->isExecutive($personnel) || $this->permits($personnel, 'viewAny') || $this->permits($personnel, 'approveProject');
    }

    /** Satin almayi yuruten: tam yetki ya da satin alma `update` izni (D-192 teklif / siparis de bunu kullanir). */
    public function managesPurchasing(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    private function isProjectManager(Personnel $personnel, PurchaseNeed $record): bool
    {
        $project = $record->project;

        return $project !== null && (int) $project->getAttribute('project_manager_employee_id') === (int) $personnel->getKey();
    }
}
