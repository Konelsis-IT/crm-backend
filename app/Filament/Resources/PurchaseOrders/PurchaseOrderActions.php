<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Exceptions\AbstractException;
use App\Filament\Exports\PurchaseDocumentPdf;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\PurchaseLines;
use App\Filament\Support\RecordLinks;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Procurement\PurchaseApprovalState;
use App\Services\Procurement\PurchaseOrderService;
use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Siparis eylemleri (D-192). Kullanicinin D-189 kurali siparise de uygulanir:
 * "bu onay sistemi olmadan siparis tamamlandiya cekilemez. Tamamlandiya
 * cekmeye calisildiginda ekranda uyari modal'i ile onaylatmaniz gereken
 * merciler ekranda gorulecek." "Siparişi ver" ve "Tamamla", onay gereken ve
 * onayi alinmamis sipariste form yerine "Onay gerekli" uyarisini acar (TL
 * tutari, merciler, eksik olan); gonderilebiliyorsa pencerenin dugmesi
 * "Onaya gönder"dir. Lojistik: Yola cikti (tasiyici, takip no), Teslim alindi
 * (kalem basina alinan miktar), Teslim bilgilerini duzenle. Butun yazmalar
 * PurchaseOrderService'tedir; dugmeler politikaya ve asamaya baglidir.
 */
final class PurchaseOrderActions
{
    /** @var array<string, PurchaseApprovalState> Istek ici onay durumu (her yazmada temizlenir). */
    private static array $states = [];

    /**
     * Ayrinti sayfasinin baslik dugmeleri.
     *
     * @return array<int, Action|ActionGroup>
     */
    public static function viewHeader(): array
    {
        return [
            self::requestApproval(),
            self::placeOrder(),
            self::markShipped(),
            self::markDelivered(),
            self::complete(),
            self::pdf(),
            self::editDelivery(),
            ActionGroup::make([
                self::cancel(),
                self::archive(),
                self::restore(),
            ])
                ->label(__('purchase_order.actions.more'))
                ->tooltip(__('purchase_order.actions.more'))
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray'),
        ];
    }

    public static function approvalState(PurchaseOrder $order): PurchaseApprovalState
    {
        $key = implode('|', [
            (string) $order->getKey(),
            $order->statusEnum()->value,
            (string) $order->getAttribute('approval_request_id'),
        ]);

        return self::$states[$key] ??= app(PurchaseOrderService::class)->approvalState($order);
    }

    /** Onay gerekiyor ve henuz saglanmadi. */
    public static function blocked(PurchaseOrder $order): bool
    {
        $state = self::approvalState($order);

        return $state->required && ! $state->satisfied;
    }

    /** Eksik olanin aciklamasi (onay bekleniyor, TL kuru / politika yok). */
    public static function missingText(PurchaseApprovalState $state): ?string
    {
        if ($state->pending) {
            return __('purchase_order.approval.missing_values.pending');
        }

        return in_array($state->missing, [PurchaseApprovalState::MISSING_RATE, PurchaseApprovalState::MISSING_POLICY], true)
            ? __('purchase_order.approval.missing_values.'.$state->missing)
            : null;
    }

    /**
     * "Onay gerekli" penceresinin icerigi: TL tutari, merciler, eksik olan.
     *
     * @return list<Component>
     */
    public static function approvalWarning(PurchaseOrder $order): array
    {
        $state = self::approvalState($order);
        $missing = self::missingText($state);

        return [
            TextEntry::make('warning_amount')
                ->label(__('purchase_order.approval.amount_try'))
                ->state(Money::format($state->amountTry, 'TRY'))
                ->weight('bold'),
            TextEntry::make('warning_approvers')
                ->label(__('purchase_order.approval.approvers'))
                ->state($state->approvers)
                ->listWithLineBreaks()
                ->icon(RecordLinks::PERSONNEL_ICON)
                ->placeholder(__('procurement.approval.policy_text')),
            TextEntry::make('warning_missing')
                ->label(__('purchase_order.approval.missing'))
                ->state($missing)
                ->color('danger')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->visible($missing !== null),
        ];
    }

    /** "Onaya gönder" (Taslak): onay gerekmiyorsa pencere bunu soyler. */
    public static function requestApproval(): Action
    {
        return Action::make('request_order_approval')
            ->label(__('purchase_order.actions.request_approval'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color(ActionColors::SEND)
            // Onay gerekmeyen sipariste de gorunur; pencere onay gerekmedigini soyler.
            ->visible(function (PurchaseOrder $record): bool {
                if ($record->statusEnum() !== PurchaseOrderStatus::Draft || ! Gate::allows('update', $record)) {
                    return false;
                }

                $state = self::approvalState($record);

                return ! $state->required || (! $state->satisfied && ! $state->pending);
            })
            ->modalHeading(fn (PurchaseOrder $record): string => self::approvalState($record)->required
                ? __('purchase_order.actions.request_approval')
                : __('purchase_order.modals.no_approval'))
            ->modalDescription(fn (PurchaseOrder $record): string => self::approvalState($record)->required
                ? __('purchase_order.help.request_approval')
                : __('purchase_order.help.no_approval'))
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(fn (PurchaseOrder $record): array => self::approvalState($record)->required
                ? [
                    ...self::approvalWarning($record),
                    Textarea::make('note')
                        ->label(__('purchase_order.fields.approval_note'))
                        ->rows(3)
                        ->maxLength(2000)
                        ->visible(self::sendable($record)),
                ]
                : [])
            ->modalSubmitAction(fn (Action $action, PurchaseOrder $record): Action | false => self::sendable($record)
                ? $action->label(__('purchase_order.actions.request_approval'))->color(ActionColors::SEND)
                : false)
            ->action(function (array $data, PurchaseOrder $record): void {
                self::sendForApproval($record, filled($data['note'] ?? null) ? (string) $data['note'] : null);
            });
    }

    public static function placeOrder(): Action
    {
        return Action::make('place_order')
            ->label(__('purchase_order.actions.place_order'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseOrder $record): bool => in_array($record->statusEnum(), [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true)
                && Gate::allows('update', $record))
            ->modalHeading(fn (PurchaseOrder $record): string => self::blocked($record) ? __('purchase_order.modals.approval_required') : __('purchase_order.actions.place_order'))
            ->modalDescription(fn (PurchaseOrder $record): string => self::blocked($record) ? __('purchase_order.help.approval_blocked') : __('purchase_order.help.place_order'))
            ->modalIcon(fn (PurchaseOrder $record): Heroicon => self::blocked($record) ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedShoppingCart)
            ->modalIconColor(fn (PurchaseOrder $record): string => self::blocked($record) ? 'warning' : 'success')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (PurchaseOrder $record): array => [
                'expected_delivery_on' => $record->getAttribute('expected_delivery_on')?->toDateString(),
            ])
            ->schema(fn (Schema $schema, PurchaseOrder $record): Schema => self::blocked($record)
                ? $schema->components(self::approvalWarning($record))
                : $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                    DatePicker::make('expected_delivery_on')
                        ->label(__('purchase_order.fields.expected_delivery_on'))
                        ->displayFormat('d.m.Y'),
                    Textarea::make('note')
                        ->label(__('purchase_order.fields.order_note'))
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnStart(1),
                ])))
            ->modalSubmitAction(fn (Action $action, PurchaseOrder $record): Action | false => self::submitButton($action, $record, __('purchase_order.actions.place_order')))
            ->action(function (array $data, PurchaseOrder $record): void {
                if (self::blocked($record)) {
                    self::sendForApproval($record, null);

                    return;
                }

                self::run($record, fn () => app(PurchaseOrderService::class)->placeOrder($record, [
                    'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                    'note' => $data['note'] ?? null,
                ]), 'ordered');
            });
    }

    public static function markShipped(): Action
    {
        return Action::make('mark_shipped')
            ->label(__('purchase_order.actions.mark_shipped'))
            ->icon(Heroicon::OutlinedTruck)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseOrder $record): bool => $record->statusEnum() === PurchaseOrderStatus::Ordered && Gate::allows('update', $record))
            ->modalHeading(__('purchase_order.actions.mark_shipped'))
            ->modalDescription(__('purchase_order.help.mark_shipped'))
            ->modalIcon(Heroicon::OutlinedTruck)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('purchase_order.actions.mark_shipped'))
            ->fillForm(fn (PurchaseOrder $record): array => [
                'carrier' => $record->getAttribute('carrier'),
                'tracking_no' => $record->getAttribute('tracking_no'),
                'expected_delivery_on' => $record->getAttribute('expected_delivery_on')?->toDateString(),
            ])
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                TextInput::make('carrier')
                    ->label(__('purchase_order.fields.carrier'))
                    ->maxLength(200),
                TextInput::make('tracking_no')
                    ->label(__('purchase_order.fields.tracking_no'))
                    ->maxLength(100),
                DatePicker::make('expected_delivery_on')
                    ->label(__('purchase_order.fields.expected_delivery_on'))
                    ->displayFormat('d.m.Y'),
            ])))
            ->action(function (array $data, PurchaseOrder $record): void {
                self::run($record, fn () => app(PurchaseOrderService::class)->markShipped($record, [
                    'carrier' => $data['carrier'] ?? null,
                    'tracking_no' => $data['tracking_no'] ?? null,
                    'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                ]), 'shipped');
            });
    }

    /** "Teslim alındı": kalem basina alinan miktar (varsayilan siparis miktari). */
    public static function markDelivered(): Action
    {
        return Action::make('mark_delivered')
            ->label(__('purchase_order.actions.mark_delivered'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseOrder $record): bool => $record->statusEnum()->isOnTheWay() && Gate::allows('update', $record))
            ->modalHeading(__('purchase_order.actions.mark_delivered'))
            ->modalDescription(__('purchase_order.help.mark_delivered'))
            ->modalIcon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('purchase_order.actions.mark_delivered'))
            ->fillForm(function (PurchaseOrder $record): array {
                $received = [];

                foreach ($record->lines as $line) {
                    /** @var PurchaseOrderLine $line */
                    $received[(string) $line->getKey()] = (float) $line->getAttribute('received_quantity') > 0
                        ? (float) $line->getAttribute('received_quantity')
                        : (float) $line->getAttribute('quantity');
                }

                return ['received' => $received];
            })
            ->schema(function (Schema $schema, PurchaseOrder $record): Schema {
                $fields = [];

                foreach ($record->lines as $line) {
                    /** @var PurchaseOrderLine $line */
                    $fields[] = TextInput::make('received.'.$line->getKey())
                        ->label((string) $line->getAttribute('description'))
                        ->helperText(__('purchase_order.values.ordered_quantity', ['quantity' => PurchaseLines::quantityText($line->getAttribute('quantity'), app(UnitResolver::class)->display($line))]))
                        ->numeric()
                        ->minValue(0)
                        ->suffix(app(UnitResolver::class)->display($line))
                        ->columnSpan(['default' => 1, 'md' => 2]);
                }

                return $schema->columns(FieldGrid::MODAL_COLUMNS)->components($fields);
            })
            ->action(function (array $data, PurchaseOrder $record): void {
                $quantities = [];

                foreach ((array) ($data['received'] ?? []) as $id => $quantity) {
                    $quantities[(int) $id] = $quantity;
                }

                self::run($record, fn () => app(PurchaseOrderService::class)->markDelivered($record, $quantities), 'delivered');
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete_order')
            ->label(__('purchase_order.actions.complete'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseOrder $record): bool => in_array($record->statusEnum(), [
                PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Ordered,
                PurchaseOrderStatus::InTransit, PurchaseOrderStatus::Delivered,
            ], true) && Gate::allows('update', $record))
            ->modalHeading(fn (PurchaseOrder $record): string => self::blocked($record) ? __('purchase_order.modals.approval_required') : __('purchase_order.actions.complete'))
            ->modalDescription(fn (PurchaseOrder $record): string => self::blocked($record) ? __('purchase_order.help.approval_blocked') : __('purchase_order.help.complete'))
            ->modalIcon(fn (PurchaseOrder $record): Heroicon => self::blocked($record) ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedCheckCircle)
            ->modalIconColor(fn (PurchaseOrder $record): string => self::blocked($record) ? 'warning' : 'success')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => ['purchased_on' => Carbon::now(DisplayTime::zone())->toDateString()])
            ->schema(fn (Schema $schema, PurchaseOrder $record): Schema => self::blocked($record)
                ? $schema->components(self::approvalWarning($record))
                : $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                    DatePicker::make('purchased_on')
                        ->label(__('purchase_order.fields.purchased_on'))
                        ->displayFormat('d.m.Y')
                        ->required(),
                ])))
            ->modalSubmitAction(fn (Action $action, PurchaseOrder $record): Action | false => self::submitButton($action, $record, __('purchase_order.actions.complete')))
            ->action(function (array $data, PurchaseOrder $record): void {
                if (self::blocked($record)) {
                    self::sendForApproval($record, null);

                    return;
                }

                self::run($record, fn () => app(PurchaseOrderService::class)->complete($record, filled($data['purchased_on'] ?? null) ? (string) $data['purchased_on'] : null), 'completed');
            });
    }

    /** "Teslim bilgilerini düzenle": adres, kisi, donem, beklenen teslim, tasiyici, odeme, proje, not. */
    public static function editDelivery(): Action
    {
        return Action::make('edit_delivery')
            ->label(__('purchase_order.actions.edit_delivery'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color(ActionColors::EDIT)
            ->visible(fn (PurchaseOrder $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('purchase_order.actions.edit_delivery'))
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalWidth(Width::FourExtraLarge)
            ->fillForm(fn (PurchaseOrder $record): array => [
                'delivery_address' => $record->getAttribute('delivery_address'),
                'delivery_contact' => $record->getAttribute('delivery_contact'),
                'delivery_from' => $record->getAttribute('delivery_from')?->toDateString(),
                'delivery_until' => $record->getAttribute('delivery_until')?->toDateString(),
                'expected_delivery_on' => $record->getAttribute('expected_delivery_on')?->toDateString(),
                'carrier' => $record->getAttribute('carrier'),
                'tracking_no' => $record->getAttribute('tracking_no'),
                'payment_terms' => $record->getAttribute('payment_terms'),
                'project_id' => $record->getAttribute('project_id'),
                'note' => $record->getAttribute('note'),
                'row_version' => $record->getAttribute('row_version'),
            ])
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                Textarea::make('delivery_address')
                    ->label(__('purchase_order.fields.delivery_address'))
                    ->rows(3)
                    ->maxLength(2000),
                TextInput::make('delivery_contact')
                    ->label(__('purchase_order.fields.delivery_contact'))
                    ->maxLength(200),
                DatePicker::make('delivery_from')
                    ->label(__('purchase_order.fields.delivery_from'))
                    ->displayFormat('d.m.Y')
                    ->columnStart(1),
                DatePicker::make('delivery_until')
                    ->label(__('purchase_order.fields.delivery_until'))
                    ->displayFormat('d.m.Y')
                    ->afterOrEqual('delivery_from'),
                DatePicker::make('expected_delivery_on')
                    ->label(__('purchase_order.fields.expected_delivery_on'))
                    ->displayFormat('d.m.Y'),
                TextInput::make('carrier')
                    ->label(__('purchase_order.fields.carrier'))
                    ->maxLength(200)
                    ->columnStart(1),
                TextInput::make('tracking_no')
                    ->label(__('purchase_order.fields.tracking_no'))
                    ->maxLength(100),
                TextInput::make('payment_terms')
                    ->label(__('purchase_order.fields.payment_terms'))
                    ->maxLength(255),
                Select::make('project_id')
                    ->label(__('purchase_order.fields.project'))
                    ->options(fn (): array => app(ProjectCatalogQueries::class)->projectOptions())
                    ->searchable()
                    ->native(false),
                Textarea::make('note')
                    ->label(__('purchase_order.fields.note'))
                    ->rows(3)
                    ->maxLength(4000)
                    ->columnStart(1),
                Hidden::make('row_version'),
            ])))
            ->action(function (array $data, PurchaseOrder $record): void {
                self::run($record, fn () => app(PurchaseOrderService::class)->update($record, $data), 'updated');
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_order')
            ->label(__('purchase_order.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color(ActionColors::CANCEL)
            ->visible(fn (PurchaseOrder $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('purchase_order.actions.cancel'))
            ->modalDescription(__('purchase_order.help.cancel'))
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalSubmitActionLabel(__('purchase_order.actions.cancel'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('purchase_order.fields.reason'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, PurchaseOrder $record): void {
                self::run($record, fn () => app(PurchaseOrderService::class)->cancel($record, filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null), 'cancelled');
            });
    }

    /** "PDF indir" (Sipariş Formu). */
    public static function pdf(): Action
    {
        return Action::make('download_order_pdf')
            ->label(__('purchase_order.actions.pdf'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (PurchaseOrder $record): bool => FeatureFlags::enabled(Feature::PurchaseOrders) && Gate::allows('view', $record))
            ->action(fn (PurchaseOrder $record): StreamedResponse => app(PurchaseDocumentPdf::class)->order($record));
    }

    public static function archive(): Action
    {
        return Action::make('archive_order')
            ->label(__('purchase_order.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->visible(fn (PurchaseOrder $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_order.actions.archive'))
            ->modalDescription(__('purchase_order.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->modalSubmitActionLabel(__('purchase_order.actions.archive'))
            ->action(function (PurchaseOrder $record): void {
                self::run($record, fn () => app(PurchaseOrderService::class)->archive($record), 'archived');
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore_order')
            ->label(__('purchase_order.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (PurchaseOrder $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_order.actions.restore'))
            ->modalDescription(__('purchase_order.help.restore'))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel(__('purchase_order.actions.restore'))
            ->action(function (PurchaseOrder $record): void {
                self::run($record, fn () => app(PurchaseOrderService::class)->restore($record), 'restored');
            });
    }

    /** Onaya gonderilebilir mi: onay gerekli, saglanmamis, beklemede degil, eksik yok, siparis Taslak. */
    private static function sendable(PurchaseOrder $order): bool
    {
        $state = self::approvalState($order);

        return $order->statusEnum() === PurchaseOrderStatus::Draft
            && $state->required
            && ! $state->satisfied
            && ! $state->pending
            && $state->missing === null;
    }

    /**
     * Uyari penceresinin dugmesi: engel yoksa eylemin kendisi; engel varsa ve
     * gonderilebiliyorsa "Onaya gönder"; onay bekleniyor ya da eksik varsa yok.
     */
    private static function submitButton(Action $submit, PurchaseOrder $order, string $label): Action | false
    {
        if (! self::blocked($order)) {
            return ActionColors::submit($submit->label($label));
        }

        if (! self::sendable($order)) {
            return false;
        }

        return $submit->label(__('purchase_order.actions.request_approval'))->color(ActionColors::SEND);
    }

    private static function sendForApproval(PurchaseOrder $order, ?string $note): void
    {
        $approvers = self::approvalState($order)->approversText();

        try {
            $result = app(PurchaseOrderService::class)->requestApproval($order, $note);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        self::refresh($order);

        if ($result->statusEnum() === PurchaseOrderStatus::Approved) {
            DomainNotifications::success(__('purchase_order.messages.self_approved'));

            return;
        }

        if ($result->statusEnum() !== PurchaseOrderStatus::AwaitingApproval) {
            DomainNotifications::success(__('purchase_order.messages.no_approval_needed'));

            return;
        }

        Notification::make()
            ->title(__('purchase_order.messages.approval_requested'))
            ->body(__('purchase_order.messages.approval_requested_body', ['approvers' => $approvers]))
            ->success()
            ->send();
    }

    private static function run(PurchaseOrder $order, \Closure $write, string $message): void
    {
        try {
            $write();
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        self::refresh($order);
        DomainNotifications::success(__('purchase_order.messages.'.$message));
    }

    private static function refresh(PurchaseOrder $order): void
    {
        self::$states = [];
        $order->refresh();
    }
}
