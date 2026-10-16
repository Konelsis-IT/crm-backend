<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestForm;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Procurement\PurchaseRequest;
use App\Services\Procurement\PurchaseRequestService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Satin alma talebini duzenle (D-192): baslik ve kalemler. Kayitli kalem
 * kucultulmus gelir; satin almanin isledigi kalem kilitlidir; formdan
 * kaldirilan islenmemis kalem iptal edilir (servis). Kaydedince ayrinti
 * sayfasi acilir (D-178).
 */
class EditPurchaseRequest extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = PurchaseRequestResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PurchaseRequest $request */
        $request = $this->getRecord();

        return PurchaseRequestForm::data($request);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PurchaseRequestService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
