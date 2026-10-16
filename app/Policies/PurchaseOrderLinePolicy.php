<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Policies\Concerns\ProcurementPolicyDefaults;

/**
 * Siparis kalemi (D-192): siparisin alt satiridir; tekliften kopyalanir.
 * Elle eklenmez ve silinmez; teslim alinan miktar siparisin yetkisiyle
 * isaretlenir.
 */
final class PurchaseOrderLinePolicy
{
    use ProcurementPolicyDefaults;

    public function viewAny(Personnel $personnel): bool
    {
        return $personnel->isActive();
    }

    public function view(Personnel $personnel, PurchaseOrderLine $record): bool
    {
        $order = $record->order;

        return $order instanceof PurchaseOrder && $this->orders()->view($personnel, $order);
    }

    public function create(Personnel $personnel): bool
    {
        return false;
    }

    public function update(Personnel $personnel, PurchaseOrderLine $record): bool
    {
        $order = $record->order;

        return $order instanceof PurchaseOrder && $this->orders()->update($personnel, $order);
    }

    public function delete(Personnel $personnel, PurchaseOrderLine $record): bool
    {
        return false;
    }

    private function orders(): PurchaseOrderPolicy
    {
        return app(PurchaseOrderPolicy::class);
    }
}
