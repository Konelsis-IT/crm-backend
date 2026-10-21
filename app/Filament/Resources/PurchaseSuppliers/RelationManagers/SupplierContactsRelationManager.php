<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\RelationManagers;

use App\Filament\Resources\Parties\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Tedarikcinin kisileri (D-232): Taraflar'daki Kisiler sekmesinin aynisi; yetki
 * tedarikci uyeliginden (goren gorur, duzenleyebilen ekler / degistirir).
 */
class SupplierContactsRelationManager extends ContactsRelationManager
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
