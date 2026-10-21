<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\RelationManagers;

use App\Filament\Resources\Parties\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Tedarikcinin adresleri (D-232): Taraflar'daki Adresler sekmesinin aynisi; yetki
 * tedarikci uyeliginden.
 */
class SupplierAddressesRelationManager extends AddressesRelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PurchaseSupplierResource::canView($ownerRecord);
    }

    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $owner = $this->getOwnerRecord();
        $allowed = in_array($action, ['view', 'viewAny'], true)
            ? PurchaseSupplierResource::canView($owner)
            : PurchaseSupplierResource::canEdit($owner);

        return $allowed ? Response::allow() : Response::deny();
    }
}
