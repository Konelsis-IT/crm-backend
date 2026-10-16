<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationActions;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Models\Procurement\PurchaseQuotation;
use App\Query\Procurement\PurchaseQuotationQueries;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Satin alma teklifi ayrintisi (D-192): teklif ve fiyat bilgileri, kalemler,
 * karsilastirma ve belgeler. Ust dugmeler akisi yurutur (sec, secimi kaldir,
 * siparise donustur, reddet, PDF, duzenle; iptal ve arsiv "Diger islemler"de).
 */
class ViewPurchaseQuotation extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PurchaseQuotationResource::class;

    /** Tedarikci teklif kartlarinda yaziyor, STKF kodu ust gezinme satirinda; alt baslik yok (D-205). */
    protected function recordSubheading(): ?string
    {
        return null;
    }

    protected function resolveRecord(int | string $key): Model
    {
        /** @var PurchaseQuotation $quotation */
        $quotation = parent::resolveRecord($key);

        return app(PurchaseQuotationQueries::class)->loadDetail($quotation);
    }

    protected function getHeaderActions(): array
    {
        return PurchaseQuotationActions::viewHeader();
    }
}
