<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestActions;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Models\Procurement\PurchaseRequest;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

/**
 * Satin alma talebi ayrintisi (D-192): talep, gerekce ve kalemler (her
 * kalemin durumu, girdigi satin alma teklifleri ve siparisi). Tek kayit
 * islemleri basliktaki etiketli dugmelerdedir: Duzenle, Talebi iptal et,
 * Arsive al / Arsivden cikar (yetki PurchaseRequestPolicy).
 */
class ViewPurchaseRequest extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label(__('purchase_request.actions.edit'))
                ->visible(fn (PurchaseRequest $record): bool => Gate::allows('update', $record)),
            PurchaseRequestActions::cancel(),
            PurchaseRequestActions::archive(),
            PurchaseRequestActions::restore(),
        ];
    }
}
