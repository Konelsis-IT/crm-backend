<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Parties\Pages\CreateParty;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\PurchaseSupplierService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Tedarikci ekle (D-232): taraf formu (tedarikci modu) + hizmet alanlari ve not.
 * Firma yalniz tedarikci olarak acilir; Taraflar'da gorunmez.
 */
class CreatePurchaseSupplier extends CreateParty
{
    protected static string $resource = PurchaseSupplierResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $supplier = (array) ($data['purchase_supplier'] ?? []);
        unset($data['purchase_supplier'], $data['party_roles']);
        $data['communication_points'] = $this->pullChannelRows($data);

        try {
            return app(PurchaseSupplierService::class)->createSupplier($data, $supplier);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['data.organization_profile.legal_name' => $exception->userMessage()]);
        }
    }
}
