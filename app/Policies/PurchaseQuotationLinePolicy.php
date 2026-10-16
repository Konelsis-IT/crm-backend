<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Policies\Concerns\ProcurementPolicyDefaults;

/**
 * Satin alma teklifi kalemi (D-192): teklifin alt satiridir; yetki teklifin
 * yetkisidir. Degisebilen teklifte kalem eklenir, duzenlenir ve silinir (alt
 * satirlar silinebilir, D-156).
 */
final class PurchaseQuotationLinePolicy
{
    use ProcurementPolicyDefaults;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->quotations()->viewAny($personnel);
    }

    public function view(Personnel $personnel, PurchaseQuotationLine $record): bool
    {
        return $this->quotations()->viewAny($personnel);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->quotations()->create($personnel);
    }

    public function update(Personnel $personnel, PurchaseQuotationLine $record): bool
    {
        $quotation = $record->quotation;

        return $quotation instanceof PurchaseQuotation && $this->quotations()->update($personnel, $quotation);
    }

    public function delete(Personnel $personnel, PurchaseQuotationLine $record): bool
    {
        return $this->update($personnel, $record);
    }

    private function quotations(): PurchaseQuotationPolicy
    {
        return app(PurchaseQuotationPolicy::class);
    }
}
