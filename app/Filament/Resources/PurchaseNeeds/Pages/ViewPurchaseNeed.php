<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedActions;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource;
use App\Models\Procurement\PurchaseNeed;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Siparis kalemi ayrintisi (D-189): kalem, kaynak (teklif / POTIS / proje /
 * Excel yeri), onay durumu ve tedarikci teklif kartlari. Ust dugmeler akisi
 * yurutur; "Sipariş ver" ve "Tamamla" onay gerekiyorsa uyari penceresi acar.
 */
class ViewPurchaseNeed extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PurchaseNeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseNeedActions::linkCatalog(),
            PurchaseNeedActions::approveProject(),
            // D-192 (B53): teklif akisi; eski teklif / onay / siparis dugmeleri o zaman gizlidir.
            PurchaseNeedActions::requestQuotation(),
            PurchaseNeedActions::addToQuotation(),
            PurchaseNeedActions::addQuote(),
            PurchaseNeedActions::requestApproval(),
            PurchaseNeedActions::placeOrder(),
            PurchaseNeedActions::complete(),
            EditAction::make()
                ->visible(fn (PurchaseNeed $record): bool => Gate::allows('update', $record)),
            ActionGroup::make([
                PurchaseNeedActions::cancel(),
                PurchaseNeedActions::archive(),
                PurchaseNeedActions::restore(),
            ])->icon(Heroicon::OutlinedEllipsisVertical)->color('gray'),
        ];
    }
}
