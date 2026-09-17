<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Pages;

use App\Exceptions\AbstractException;
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
            PartyResource::archiveAction(),
            PartyResource::restoreAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $data['organization_profile'] = $record->organizationProfile?->toArray() ?? [];
        $data['person_profile'] = $record->personProfile?->toArray() ?? [];

        return $data;
    }

    /**
     * Taraf kaydi ve kuruma ait iletisim bilgileri (B28) PartyService icinde
     * ayni islemde guncellenir; formdaki kanal satirlari ayiklanip servise
     * verilir. Tum is istisnalari (eski surum dahil) bildirimle gosterilir
     * ve kayit durdurulur.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['communication_points'] = $this->pullChannelRows($data);

        try {
            return app(PartyService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    /**
     * Formdaki kuruma ait iletisim satirlarini ayirir (B28); degeri bos
     * satirlar atlanir.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function pullChannelRows(array &$data): array
    {
        $rows = (array) ($data['communication_points'] ?? []);
        unset($data['communication_points']);

        return array_values(array_filter(
            $rows,
            static fn ($row): bool => is_array($row) && filled($row['value'] ?? null),
        ));
    }
}
