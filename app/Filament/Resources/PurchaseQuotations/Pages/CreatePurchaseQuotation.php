<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\PurchaseQuotationService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Satin alma teklifi ekle (D-192): baslik + kalemler tek formda;
 * PurchaseQuotationService kodu (STKF-YYYY-NNNN) verir, toplamlari hesaplar ve
 * fiyatli kalem varsa teklifi "Alındı" yapar. Kaydedince ayrinti sayfasi
 * acilir (D-178).
 */
class CreatePurchaseQuotation extends CreateRecord
{
    use HasColoredFormActions;

    protected static string $resource = PurchaseQuotationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseQuotationService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
