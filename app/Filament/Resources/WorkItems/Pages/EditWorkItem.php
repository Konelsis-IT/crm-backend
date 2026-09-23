<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\WorkItems\WorkItemActions;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Report\WorkItem;
use App\Services\Report\WorkItemService;
use App\Support\DisplayTime;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Kart duzenleme: tarih ve saat kurum saatiyle doldurulur, bagli kayit
 * turune gore secilir; kayit WorkItemService'ten gecer.
 */
class EditWorkItem extends EditRecord
{
    protected static string $resource = WorkItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WorkItemActions::changeStatus(),
            WorkItemActions::delete(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var WorkItem $record */
        $record = $this->getRecord();
        $local = $record->work_at?->copy()->timezone(DisplayTime::zone());
        $column = $record->link_kind?->column();

        return [
            ...$data,
            'work_on' => $local?->format('Y-m-d'),
            'work_time' => $local?->format('H:i'),
            'status' => $record->status?->value,
            'waiting_kind' => $record->waiting_kind?->value,
            'link_kind' => $column !== null ? $record->link_kind?->value : null,
            'link_id' => $column !== null ? $record->getAttribute($column) : null,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(WorkItemService::class)->update($record, [
                ...$data,
                'link_kind' => $data['link_kind'] ?? null,
                'waiting_kind' => $data['waiting_kind'] ?? null,
            ]);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return WorkItemResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
