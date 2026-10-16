<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationDocument;
use App\Policies\Concerns\ProcurementPolicyDefaults;

/**
 * Satin alma teklifine yuklenen belge (D-192): yetki teklifin yetkisidir.
 * Baglanti kaldirilabilir (dosya Dokuman yonetiminde kalir).
 */
final class PurchaseQuotationDocumentPolicy
{
    use ProcurementPolicyDefaults;

    public function viewAny(Personnel $personnel): bool
    {
        return $this->quotations()->viewAny($personnel);
    }

    public function view(Personnel $personnel, PurchaseQuotationDocument $record): bool
    {
        return $this->quotations()->viewAny($personnel);
    }

    public function create(Personnel $personnel): bool
    {
        return $this->quotations()->create($personnel);
    }

    public function update(Personnel $personnel, PurchaseQuotationDocument $record): bool
    {
        $quotation = $record->quotation;

        return $quotation instanceof PurchaseQuotation && $this->quotations()->update($personnel, $quotation);
    }

    public function delete(Personnel $personnel, PurchaseQuotationDocument $record): bool
    {
        return $this->update($personnel, $record);
    }

    private function quotations(): PurchaseQuotationPolicy
    {
        return app(PurchaseQuotationPolicy::class);
    }
}
