<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyPurchases\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\CompanyPurchases\CompanyPurchaseLinesForm;
use App\Filament\Resources\CompanyPurchases\CompanyPurchaseResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CompanyPurchaseService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Sirket ici alim ekle (D-189): yenileme donemi bos birakilirsa katalog
 * kaleminin donemi, sonraki yenileme tarihi bos birakilirsa alim tarihi +
 * donem (CompanyPurchaseService).
 *
 * D-192 (B53): form kalemlidir; her kalem ayri alim satiri olur
 * (CompanyPurchaseService::createLines). Detay sayfasi olmadigi icin kayittan
 * sonra liste acilir (D-178, HasColoredFormActions).
 */
class CreateCompanyPurchase extends CreateRecord
{
    use HasColoredFormActions;

    protected static string $resource = CompanyPurchaseResource::class;

    /** Kalemli kayitta acilan alim sayisi (bildirim icin). */
    private int $createdCount = 1;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            if (! SchemaReadiness::hasBatch('B53')) {
                return app(CompanyPurchaseService::class)->create($data);
            }

            [$header, $lines] = CompanyPurchaseLinesForm::split($data);
            $created = app(CompanyPurchaseService::class)->createLines($header, $lines);
            $this->createdCount = count($created);

            return $created[0];
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->createdCount > 1
            ? __('company_purchase.messages.created_lines', ['count' => $this->createdCount])
            : parent::getCreatedNotificationTitle();
    }
}
