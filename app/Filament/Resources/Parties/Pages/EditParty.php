<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Party\PartyService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditParty extends EditRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $data['organization_profile'] = $record->organizationProfile?->toArray() ?? [];
        $data['person_profile'] = $record->personProfile?->toArray() ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PartyService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
