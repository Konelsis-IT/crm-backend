<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Exports\ReportExporter;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ExportActions;
use App\Models\Report\Report;
use App\Services\Report\ReportService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use App\Query\Report\ReportQueries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Rapor karti. Eylemler duruma ve tarafa gore: gonder / geri cek (yazar),
 * onayla / revizyon iste / reddet (inceleyen), duzenle, taslagi sil.
 */
class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    public function getTitle(): string
    {
        /** @var Report $report */
        $report = $this->getRecord();

        return $report->report_no.' · '.$report->title;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label(__('report.actions.submit'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription(__('report.help.submit'))
                ->visible(fn (): bool => Gate::allows('submit', $this->getRecord()))
                ->action(fn () => $this->run(fn (ReportService $service) => $service->submit($this->getRecord()), 'submitted')),
            Action::make('withdraw')
                ->label(__('report.actions.withdraw'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('withdraw', $this->getRecord()))
                ->action(fn () => $this->run(fn (ReportService $service) => $service->withdraw($this->getRecord()), 'withdrawn')),
            // Raporu ust amire ya da secilen yoneticiye ilet (23 Eylul 2026
            // kullanici istegi): yeni inceleyen atanir, rapor yeniden karar
            // bekler ve hedefe bildirim gider.
            Action::make('forward')
                ->label(__('report.actions.forward'))
                ->icon(Heroicon::OutlinedArrowRightCircle)
                ->color('info')
                ->visible(fn (): bool => Gate::allows('forward', $this->getRecord()))
                ->modalHeading(__('report.actions.forward'))
                ->modalDescription(__('report.help.forward'))
                ->modalSubmitActionLabel(__('report.actions.forward_submit'))
                ->schema([
                    Select::make('reviewer_personnel_id')
                        ->label(__('report.fields.forward_to'))
                        ->options(fn (): array => app(ReportQueries::class)->forwardOptions($this->getRecord()))
                        ->searchable()
                        ->required()
                        ->native(false),
                    Textarea::make('comment')
                        ->label(__('report.fields.forward_note'))
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(
                    fn (ReportService $service) => $service->forward($this->getRecord(), (int) $data['reviewer_personnel_id'], $data['comment'] ?? null),
                    'forwarded',
                )),
            Action::make('approve')
                ->label(__('report.actions.approve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => Gate::allows('review', $this->getRecord()))
                ->schema([
                    Textarea::make('comment')->label(__('report.fields.review_comment'))->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (ReportService $service) => $service->approve($this->getRecord(), $data['comment'] ?? null), 'approved')),
            Action::make('request_revision')
                ->label(__('report.actions.request_revision'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => Gate::allows('review', $this->getRecord()))
                ->schema([
                    Textarea::make('comment')->label(__('report.fields.review_comment'))->helperText(__('report.help.review_comment'))->required()->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (ReportService $service) => $service->requestRevision($this->getRecord(), $data['comment'] ?? null), 'revision_required')),
            Action::make('reject')
                ->label(__('report.actions.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => Gate::allows('review', $this->getRecord()))
                ->schema([
                    Textarea::make('comment')->label(__('report.fields.review_comment'))->helperText(__('report.help.review_comment'))->required()->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (ReportService $service) => $service->reject($this->getRecord(), $data['comment'] ?? null), 'rejected')),
            EditAction::make()->label(__('report.actions.edit')),
            Action::make('delete_draft')
                ->label(__('report.actions.delete'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('report.help.delete'))
                ->visible(fn (): bool => Gate::allows('delete', $this->getRecord()))
                ->action(function (): void {
                    try {
                        app(ReportService::class)->delete($this->getRecord());
                        DomainNotifications::success(__('report.messages.deleted'));
                        $this->redirect(ReportResource::getUrl('index'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
            ExportActions::record(ReportExporter::class),
        ];
    }

    /**
     * @param  callable(ReportService): mixed  $operation
     */
    private function run(callable $operation, string $messageKey): void
    {
        try {
            $operation(app(ReportService::class));
            DomainNotifications::success(__('report.messages.'.$messageKey));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }

        $this->redirect(ReportResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
