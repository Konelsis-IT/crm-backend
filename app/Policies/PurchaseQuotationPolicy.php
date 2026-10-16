<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseQuotation;
use App\Policies\Concerns\ProcurementPolicyDefaults;
use App\Policies\Concerns\ResolvesInterimRoles;

/**
 * Satin alma teklifi (D-192, B53). Satin almanin is ekranidir: satin alma
 * (PurchaseNeed `update` izni ya da tam yetki) acar, fiyatlari girer, belge
 * yukler, Konelsis AI'yi calistirir ve en uygun teklifi secer. Ust yonetim,
 * denetci ve satin alma kalemlerini goren herkes (seesAll) okur. Siparise
 * donusmus, reddedilmis ya da iptal edilmis teklif degismez. Silme yok, arsiv.
 */
final class PurchaseQuotationPolicy
{
    use ProcurementPolicyDefaults;
    use ResolvesInterimRoles;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->purchasing()->seesAll($personnel) || $this->purchasing()->managesPurchasing($personnel);
    }

    public function view(Personnel $personnel, PurchaseQuotation $record): bool
    {
        return $this->viewAny($personnel);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->purchasing()->managesPurchasing($personnel);
    }

    public function update(Personnel $personnel, PurchaseQuotation $record): bool
    {
        return ! $record->isArchived()
            && $record->statusEnum()->isEditable()
            && $this->purchasing()->managesPurchasing($personnel);
    }

    /** En uygun teklif olarak secme (yalniz fiyati girilmis teklif). */
    public function select(Personnel $personnel, PurchaseQuotation $record): bool
    {
        return $this->update($personnel, $record) && $record->statusEnum() === PurchaseQuotationStatus::Received;
    }

    /** Secili tekliften siparis acma. */
    public function order(Personnel $personnel, PurchaseQuotation $record): bool
    {
        return ! $record->isArchived()
            && $record->statusEnum() === PurchaseQuotationStatus::Selected
            && $this->purchasing()->managesPurchasing($personnel);
    }

    public function archive(Personnel $personnel, PurchaseQuotation $record): bool
    {
        return $this->purchasing()->managesPurchasing($personnel);
    }

    public function delete(Personnel $personnel, PurchaseQuotation $record): bool
    {
        return false;
    }

    private function purchasing(): PurchaseNeedPolicy
    {
        return app(PurchaseNeedPolicy::class);
    }
}
