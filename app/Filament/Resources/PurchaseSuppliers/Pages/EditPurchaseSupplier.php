<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseSuppliers\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\PurchaseSuppliers\PurchaseSupplierResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Party\Party;
use App\Services\Procurement\PurchaseSupplierService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Tedarikci duzenle (D-232): firma alanlari PartyService, hizmet alanlari ve not
 * tedarikci uyeligi (PurchaseSupplierService).
 */
class EditPurchaseSupplier extends EditRecord
{
    use HasColoredFormActions;
    use HasRecordHeading;

    protected static string $resource = PurchaseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PurchaseSupplierResource::archiveAction(),
            PurchaseSupplierResource::restoreAction(),
            $this->cancelHeaderAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Party $record */
        $record = $this->getRecord();
        $data['organization_profile'] = $record->organizationProfile?->toArray() ?? [];

        // D-170: kisa ad yoksa gorunen ad uzun addir (EditParty ile ayni).
        if (blank($data['organization_profile']['trade_name'] ?? null) && $record->organizationProfile !== null) {
            $data['organization_profile']['legal_name'] = $record->display_name;
        }

        $membership = PurchaseSupplierResource::membership($record);
        $data['purchase_supplier'] = [
            'service_area_ids' => $membership?->serviceAreas()->pluck('purchase_service_areas.id')->map(static fn (mixed $id): int => (int) $id)->all() ?? [],
            'note' => $membership?->getAttribute('note'),
        ];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $supplier = (array) ($data['purchase_supplier'] ?? []);
        unset($data['purchase_supplier']);
        $rows = (array) ($data['communication_points'] ?? []);
        unset($data['communication_points']);
        $data['communication_points'] = array_values(array_filter(
            $rows,
            static fn ($row): bool => is_array($row) && filled($row['value'] ?? null),
        ));

        try {
            /** @var Party $record */
            return app(PurchaseSupplierService::class)->updateSupplier($record, $data, $supplier);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
