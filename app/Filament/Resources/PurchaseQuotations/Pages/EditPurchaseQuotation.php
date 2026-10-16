<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationAiActions;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationForm;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Procurement\PurchaseQuotation;
use App\Services\Procurement\PurchaseQuotationService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Satin alma teklifini duzenle (D-192): baslik ve kalemler; formdan kaldirilan
 * kalem silinir, kalem toplamlari ve teklif toplamlari serviste yeniden
 * hesaplanir. Siparise donusmus, reddedilmis ya da iptal teklif duzenlenmez
 * (politika). Konelsis AI eylemleri burada da durur.
 */
class EditPurchaseQuotation extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = PurchaseQuotationResource::class;

    /** Alt baslik: tedarikci; STKF kodu ust gezinme satirinda (D-205). */
    protected function recordSubheading(): ?string
    {
        /** @var PurchaseQuotation $quotation */
        $quotation = $this->getRecord();

        return $quotation->supplierLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            PurchaseQuotationAiActions::readDocuments(),
            PurchaseQuotationAiActions::aiCreatedItems(),
            $this->cancelHeaderAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PurchaseQuotation $quotation */
        $quotation = $this->getRecord();

        return [...$data, 'lines' => PurchaseQuotationForm::linesData($quotation)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PurchaseQuotationService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
