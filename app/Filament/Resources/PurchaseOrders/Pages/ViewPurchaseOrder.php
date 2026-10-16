<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderActions;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use App\Query\Procurement\PurchaseOrderQueries;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Siparis ayrintisi (D-192; ekran goruntusu 7 duzeni): siparis ve fiyat
 * kartlari, kalemler, lojistik sureci, teslimat ve onay. Ust dugmeler yalniz
 * uygun asamada gorunur: Onaya gönder, Siparişi ver (onay yoksa merci
 * uyarisi), Yola çıktı, Teslim alındı, Tamamla (onay yoksa merci uyarisi),
 * PDF indir, Teslim bilgilerini düzenle; iptal ve arsiv "Diger islemler"de.
 */
class ViewPurchaseOrder extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PurchaseOrderResource::class;

    /** Tedarikci sipariste kartta yaziyor, SIP kodu ust gezinme satirinda; alt baslik yok (D-205). */
    protected function recordSubheading(): ?string
    {
        return null;
    }

    protected function resolveRecord(int | string $key): Model
    {
        /** @var PurchaseOrder $order */
        $order = parent::resolveRecord($key);

        return app(PurchaseOrderQueries::class)->loadDetail($order);
    }

    protected function getHeaderActions(): array
    {
        return PurchaseOrderActions::viewHeader();
    }
}
