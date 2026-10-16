<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyPurchases\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\CompanyPurchases\CompanyPurchaseResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\CompanyPurchaseService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Sirket ici alimi duzenle (D-189). Arsivdeki kayit duzenlenmez (politika ve
 * servis); kaydedince liste acilir (detay sayfasi yok, D-178).
 */
class EditCompanyPurchase extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = CompanyPurchaseResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(CompanyPurchaseService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
