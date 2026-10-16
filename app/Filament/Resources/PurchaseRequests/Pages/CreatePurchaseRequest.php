<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Procurement\PurchaseRequestService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satin alma talebi ac (D-192): talep ve kalemleri tek ekranda; kayit
 * PurchaseRequestService::create (SAT kodu, her kalem bir satin alinacak
 * kalem). Kaydedince talebin ayrinti sayfasi acilir (D-178).
 */
class CreatePurchaseRequest extends CreateRecord
{
    use HasColoredFormActions;

    protected static string $resource = PurchaseRequestResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('purchase_request.actions.create');
    }

    public function getSubheading(): ?string
    {
        return __('purchase_request.modals.description');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseRequestService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
