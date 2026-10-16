<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests;

use App\Exceptions\AbstractException;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\RecordLinks;
use App\Models\Procurement\PurchaseRequest;
use App\Services\Procurement\PurchaseRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Satin alma talebi eylemleri (D-192, B53): talebi iptal et (gerekceli),
 * arsive al / arsivden cikar (D-156, yalniz onay). Butun yazmalar
 * PurchaseRequestService'tedir; yetki PurchaseRequestPolicy'dedir.
 */
final class PurchaseRequestActions
{
    /**
     * Pencereden (Talepler / Satin alinacaklar "Satin alma talebi ac") gelen
     * veriyle talebi acar; bildirimde "Talebi ac" baglantisi vardir.
     *
     * @param  array<string, mixed>  $data
     */
    public static function store(array $data): ?PurchaseRequest
    {
        try {
            /** @var PurchaseRequest $request */
            $request = app(PurchaseRequestService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return null;
        }

        $url = RecordLinks::detailUrl($request, checkRecord: false);

        Notification::make()
            ->title(__('purchase_request.messages.created', ['code' => $request->getAttribute('request_no')]))
            ->success()
            ->actions($url === null ? [] : [
                Action::make('open_purchase_request')
                    ->label(__('purchase_request.actions.open'))
                    ->url($url)
                    ->color(ActionColors::VIEW),
            ])
            ->send();

        return $request;
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_purchase_request')
            ->label(__('purchase_request.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color(ActionColors::CANCEL)
            ->visible(fn (PurchaseRequest $record): bool => Gate::allows('cancel', $record))
            ->modalHeading(__('purchase_request.actions.cancel'))
            ->modalDescription(__('purchase_request.help.cancel'))
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalSubmitActionLabel(__('purchase_request.actions.cancel'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('purchase_request.fields.reason'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, PurchaseRequest $record): void {
                try {
                    app(PurchaseRequestService::class)->cancel($record, filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                    $record->refresh();
                    DomainNotifications::success(__('purchase_request.messages.cancelled'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function archive(): Action
    {
        return Action::make('archive_purchase_request')
            ->label(__('purchase_request.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->visible(fn (PurchaseRequest $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_request.actions.archive'))
            ->modalDescription(__('purchase_request.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->modalSubmitActionLabel(__('purchase_request.actions.archive'))
            ->action(function (PurchaseRequest $record): void {
                try {
                    app(PurchaseRequestService::class)->archive($record);
                    $record->refresh();
                    DomainNotifications::success(__('purchase_request.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore_purchase_request')
            ->label(__('purchase_request.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (PurchaseRequest $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_request.actions.restore'))
            ->modalDescription(__('purchase_request.help.restore'))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel(__('purchase_request.actions.restore'))
            ->action(function (PurchaseRequest $record): void {
                try {
                    app(PurchaseRequestService::class)->restore($record);
                    $record->refresh();
                    DomainNotifications::success(__('purchase_request.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }
}
