<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseOrder;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Siparis (D-192, B53). Satin alma acar, onaya gonderir, tedarikciye iletir,
 * lojistik asamalarini yurutur ve tamamlar. Ust yonetim, denetci ve satin
 * alma kalemlerini goren herkes okur; kalemlerinden birini talep eden kisi
 * kendi siparisini gorur. Silme yok, arsiv var.
 */
final class PurchaseOrderPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, PurchaseOrder $record): bool
    {
        if ($this->purchasing()->seesAll($personnel) || $this->purchasing()->managesPurchasing($personnel)) {
            return true;
        }

        // D-196: proje muduru kendi projesinin siparisini (proje sayfasindaki Lojistik) acar.
        $managesProject = static fn ($project) => $project->where('project_manager_employee_id', $personnel->getKey());

        if ($record->project()->where('project_manager_employee_id', $personnel->getKey())->exists()) {
            return true;
        }

        return $record->lines()
            ->whereHas('need', fn ($need) => $need->where('requested_by_personnel_id', $personnel->getKey())
                ->orWhereHas('project', $managesProject))
            ->exists();
    }

    public function create(Personnel $personnel): bool
    {
        return $this->purchasing()->managesPurchasing($personnel);
    }

    /** Teslim bilgileri, lojistik asamalari, onaya gonderme, tamamlama, iptal. */
    public function update(Personnel $personnel, PurchaseOrder $record): bool
    {
        return ! $record->isArchived()
            && ! $record->statusEnum()->isClosed()
            && $this->purchasing()->managesPurchasing($personnel);
    }

    /** Fiyat ve kalemler yalniz taslakta (onaydan once) degisir. */
    public function editLines(Personnel $personnel, PurchaseOrder $record): bool
    {
        return $this->update($personnel, $record) && $record->statusEnum() === PurchaseOrderStatus::Draft;
    }

    public function archive(Personnel $personnel, PurchaseOrder $record): bool
    {
        return $this->purchasing()->managesPurchasing($personnel);
    }

    public function delete(Personnel $personnel, PurchaseOrder $record): bool
    {
        return false;
    }

    private function purchasing(): PurchaseNeedPolicy
    {
        return app(PurchaseNeedPolicy::class);
    }
}
