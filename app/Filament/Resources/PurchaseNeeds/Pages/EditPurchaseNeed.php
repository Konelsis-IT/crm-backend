<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\PurchaseNeeds\PurchaseNeedResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\PurchaseNeedService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Siparis kalemini duzenle (D-189): baslik, miktar, birim, termin, acil, not.
 * Onaya gittikten sonra miktar degismez (servis kurali). Kaydedince ayrinti
 * sayfasi acilir (D-178, HasColoredFormActions).
 */
class EditPurchaseNeed extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = PurchaseNeedResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PurchaseNeedService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
