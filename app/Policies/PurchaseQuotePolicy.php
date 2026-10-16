<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Tedarikci teklifi (B52, D-189): siparis kaleminin alt satiridir; kendi izin
 * anahtari yoksa siparis kalemi yetkisi gecerlidir (PermissionSubjects).
 * Gormek: kalemi goren herkes; eklemek / duzenlemek / silmek: satin alma,
 * siparisi verilmemis acik kalemde.
 */
final class PurchaseQuotePolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, PurchaseQuote $record): bool
    {
        $need = $record->need;

        return $need instanceof PurchaseNeed && app(PurchaseNeedPolicy::class)->view($personnel, $need);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->hasFullAccess($personnel) || $this->permits($personnel, 'update');
    }

    public function update(Personnel $personnel, PurchaseQuote $record): bool
    {
        return $this->editable($record) && ($this->hasFullAccess($personnel) || $this->permits($personnel, 'update'));
    }

    public function delete(Personnel $personnel, PurchaseQuote $record): bool
    {
        return $this->update($personnel, $record);
    }

    private function editable(PurchaseQuote $record): bool
    {
        $need = $record->need;

        return $need instanceof PurchaseNeed
            && ! $need->isArchived()
            && ! $need->statusEnum()->isClosed()
            && ! in_array($need->statusEnum(), [PurchaseNeedStatus::Ordered, PurchaseNeedStatus::AwaitingProject], true);
    }
}
