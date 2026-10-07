<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Report\Report;
use App\Models\Report\ReportItem;
use App\Services\Report\ReportService;
use App\Services\Report\ReportSuggestions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Yazar taslagi ya da revizyon istenen raporu duzenler (ReportPolicy::update). */
class EditReport extends EditRecord
{
    use HasColoredFormActions;

    protected static string $resource = ReportResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Report $report */
        $report = $this->getRecord();

        $data['payload'] = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $data['items'] = $report->items
            ->map(fn (ReportItem $item): array => [
                'id' => (int) $item->getKey(),
                'title' => $item->title,
                'description' => $item->description,
                'status' => $item->status->value,
                'project_id' => $item->project_id,
                'work_hours' => $item->work_hours,
                'due_on' => $item->due_on?->format('Y-m-d'),
                'carried_from_item_id' => $item->carried_from_item_id,
                // Kaynak kart ve "sonradan eklendi" isareti duzenlemede korunur (B36).
                'work_item_id' => $item->getAttribute('work_item_id'),
                'is_late' => (bool) $item->getAttribute('is_late'),
            ])
            ->values()
            ->all();

        // D-167: onerilen kaynaklar formda isaretli anahtar listesi olarak gelir
        // (kayitta isaretli olanlar + kayittan sonra cikan yeni oneriler).
        foreach ($report->template()?->sourceFields() ?? [] as $field) {
            $data['payload'][$field->name] = app(ReportSuggestions::class)->selectedForEdit($field, $report);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ReportService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['title' => $exception->userMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ReportResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('report.messages.updated');
    }
}
