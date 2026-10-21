<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestActions;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestForm;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\RecordLinks;
use App\Filament\Support\TwoLineSelect;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuote;
use App\Models\Procurement\PurchaseRequest;
use App\Query\Procurement\PurchaseRequestQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseApprovalState;
use App\Services\Procurement\PurchaseNeedService;
use App\Services\Procurement\PurchaseQuotationService;
use App\Services\Procurement\PurchaseQuoteService;
use App\Support\DisplayTime;
use App\Support\Money;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Siparis kalemi eylemleri (D-189). Kullanicinin kurali: "bu onay sistemi
 * olmadan siparis tamamlandiya cekilemez. Tamamlandiya cekmeye calisildiginda
 * ekranda uyari modal'i ile onaylatmaniz gereken merciler ekranda gorulecek."
 * Bu yuzden "Sipariş ver" ve "Tamamla", onay gereken ve onayi alinmamis
 * kalemde form yerine "Onay gerekli" uyarisini acar: TL tutari, merciler (her
 * biri kendi satirinda, kisi simgesiyle) ve eksik olan; gonderilebiliyorsa
 * pencerenin dugmesi "Onaya gönder"dir. Butun yazmalar servistedir.
 */
final class PurchaseNeedActions
{
    /** @var array<string, PurchaseApprovalState> Istek ici onay durumu (her yazmada temizlenir). */
    private static array $states = [];

    public static function approvalState(PurchaseNeed $need): PurchaseApprovalState
    {
        $key = implode('|', [
            (string) $need->getKey(),
            $need->statusEnum()->value,
            (string) $need->getAttribute('recommended_quote_id'),
            (string) $need->getAttribute('approval_request_id'),
        ]);

        return self::$states[$key] ??= app(PurchaseNeedService::class)->approvalState($need);
    }

    /** Onay gerekiyor ve henuz saglanmadi: siparis / tamamlama uyari penceresi acar. */
    public static function blocked(PurchaseNeed $need): bool
    {
        $state = self::approvalState($need);

        return $state->required && ! $state->satisfied;
    }

    /** Eksik olanin aciklamasi (onay bekleniyor, teklif / kur / politika yok). */
    public static function missingText(PurchaseApprovalState $state): ?string
    {
        if ($state->pending) {
            return __('purchasing.missing.pending');
        }

        return $state->missing !== null ? __('purchasing.missing.'.$state->missing) : null;
    }

    /**
     * "Onay gerekli" penceresinin icerigi.
     *
     * @return list<Component>
     */
    public static function approvalWarning(PurchaseNeed $need): array
    {
        $state = self::approvalState($need);
        $missing = self::missingText($state);

        return [
            TextEntry::make('warning_amount')
                ->label(__('purchasing.fields.amount_try'))
                ->state(Money::format($state->amountTry, 'TRY'))
                ->weight('bold'),
            TextEntry::make('warning_approvers')
                ->label(__('purchasing.modals.approvers'))
                ->state($state->approvers)
                ->listWithLineBreaks()
                ->icon(RecordLinks::PERSONNEL_ICON)
                ->placeholder(__('procurement.approval.policy_text')),
            TextEntry::make('warning_missing')
                ->label(__('purchasing.modals.missing'))
                ->state($missing)
                ->color('danger')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->visible($missing !== null),
        ];
    }

    public static function approveProject(): Action
    {
        return Action::make('approve_project')
            ->label(__('purchasing.actions.approve_project'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseNeed $record): bool => Gate::allows('approveProject', $record))
            ->requiresConfirmation()
            ->modalDescription(__('purchasing.help.project_approval'))
            ->action(function (PurchaseNeed $record): void {
                try {
                    $count = app(PurchaseNeedService::class)->approveProject([$record]);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.project_approved', ['count' => $count]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Listede secili kalemlere proje onayi (yalniz onay verebildikleri). */
    public static function approveProjectBulk(): BulkAction
    {
        return BulkAction::make('approve_project_bulk')
            ->label(__('purchasing.actions.approve_project'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color(ActionColors::SAVE)
            ->requiresConfirmation()
            ->modalDescription(__('purchasing.modals.approve_project'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Enumerable $records): void {
                $allowed = $records->filter(fn (mixed $record): bool => $record instanceof PurchaseNeed && Gate::allows('approveProject', $record));

                if ($allowed->isEmpty()) {
                    Notification::make()->title(__('purchasing.messages.project_approved_none'))->warning()->send();

                    return;
                }

                try {
                    $count = app(PurchaseNeedService::class)->approveProject($allowed->all());
                    self::$states = [];
                    DomainNotifications::success(__('purchasing.messages.project_approved', ['count' => $count]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function addQuote(): Action
    {
        return Action::make('add_quote')
            ->label(__('purchasing.actions.add_quote'))
            ->icon(Heroicon::OutlinedPlus)
            ->color(ActionColors::CREATE)
            ->visible(fn (PurchaseNeed $record): bool => FeatureFlags::enabled(Feature::PurchaseQuotes)
                && self::legacyFlow()
                && $record->statusEnum() !== PurchaseNeedStatus::Ordered
                && Gate::allows('purchase', $record))
            ->modalHeading(__('purchasing.actions.add_quote'))
            ->modalWidth(Width::FourExtraLarge)
            ->schema(fn (Schema $schema, PurchaseNeed $record): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(PurchaseNeedForms::quoteFields($record)))
            ->action(function (array $data, PurchaseNeed $record): void {
                try {
                    app(PurchaseQuoteService::class)->create([...$data, 'purchase_need_id' => $record->getKey()]);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.quote_created'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Teklif kartinin basligindaki dugmeler: Onerilen yap, Duzenle, Sil. Eylem
     * adlari teklife ozeldir (ayni tedarikcinin iki teklifi karismasin).
     *
     * @return Closure(PurchaseQuote): list<Action>
     */
    public static function quoteCardActions(PurchaseNeed $need): Closure
    {
        return static function (PurchaseQuote $quote) use ($need): array {
            $id = (int) $quote->getKey();

            return [
                Action::make('recommend_quote_'.$id)
                    ->label(__('purchasing.actions.recommend'))
                    ->icon(Heroicon::OutlinedStar)
                    ->color(ActionColors::SAVE)
                    ->button()
                    ->size(Size::ExtraSmall)
                    ->visible(fn (): bool => (int) $need->getAttribute('recommended_quote_id') !== $id
                        && $need->statusEnum() !== PurchaseNeedStatus::Ordered
                        && Gate::allows('purchase', $need))
                    ->requiresConfirmation()
                    ->modalHeading(__('purchasing.actions.recommend'))
                    ->modalDescription($quote->supplierLabel().' · '.Money::format($quote->getAttribute('total_amount'), $quote->getAttribute('currency_code')))
                    ->modalIcon(Heroicon::OutlinedStar)
                    ->action(function () use ($need, $quote): void {
                        try {
                            app(PurchaseNeedService::class)->recommend($need, $quote);
                            self::refresh($need);
                            DomainNotifications::success(__('purchasing.messages.recommended'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                Action::make('edit_quote_'.$id)
                    ->label(__('purchasing.actions.edit_quote'))
                    ->tooltip(__('purchasing.actions.edit_quote'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color(ActionColors::EDIT)
                    ->iconButton()
                    ->visible(fn (): bool => Gate::allows('update', $quote))
                    ->modalHeading(__('purchasing.actions.edit_quote'))
                    ->modalWidth(Width::FourExtraLarge)
                    ->fillForm(fn (): array => PurchaseNeedForms::quoteData($quote))
                    ->schema(fn (Schema $schema): Schema => $schema
                        ->columns(FieldGrid::MODAL_COLUMNS)
                        ->components(PurchaseNeedForms::quoteFields($need)))
                    ->action(function (array $data) use ($need, $quote): void {
                        try {
                            app(PurchaseQuoteService::class)->update($quote, $data);
                            self::refresh($need);
                            DomainNotifications::success(__('purchasing.messages.quote_updated'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                Action::make('delete_quote_'.$id)
                    ->label(__('purchasing.actions.delete_quote'))
                    ->tooltip(__('purchasing.actions.delete_quote'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color(ActionColors::DELETE)
                    ->iconButton()
                    ->visible(fn (): bool => Gate::allows('delete', $quote))
                    ->requiresConfirmation()
                    ->modalHeading(__('purchasing.actions.delete_quote'))
                    ->modalDescription(__('purchasing.help.delete_quote'))
                    ->action(function () use ($need, $quote): void {
                        try {
                            app(PurchaseQuoteService::class)->delete($quote);
                            self::refresh($need);
                            DomainNotifications::success(__('purchasing.messages.quote_deleted'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ];
        };
    }

    /** "Onaya gönder": onay gerekli, saglanmamis, beklemede degil, onerilen teklif var. */
    public static function requestApproval(): Action
    {
        return Action::make('request_approval')
            ->label(__('purchasing.actions.request_approval'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color(ActionColors::SEND)
            ->visible(function (PurchaseNeed $record): bool {
                if (! self::legacyFlow() || $record->getAttribute('recommended_quote_id') === null || ! Gate::allows('purchase', $record)) {
                    return false;
                }

                $state = self::approvalState($record);

                return $state->required && ! $state->satisfied && ! $state->pending;
            })
            ->modalHeading(__('purchasing.actions.request_approval'))
            ->modalDescription(__('purchasing.help.request_approval'))
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('purchasing.actions.request_approval'))
            ->schema(fn (PurchaseNeed $record): array => [
                ...self::approvalWarning($record),
                Textarea::make('note')
                    ->label(__('purchasing.fields.approval_note'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, PurchaseNeed $record): void {
                self::sendForApproval($record, filled($data['note'] ?? null) ? (string) $data['note'] : null);
            });
    }

    public static function placeOrder(): Action
    {
        return Action::make('place_order')
            ->label(__('purchasing.actions.place_order'))
            ->icon(Heroicon::OutlinedTruck)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseNeed $record): bool => self::legacyFlow()
                && in_array($record->statusEnum(), PurchaseNeedStatus::pendingOrder(), true)
                && Gate::allows('purchase', $record))
            ->modalHeading(fn (PurchaseNeed $record): string => self::blocked($record) ? __('purchasing.modals.approval_required') : __('purchasing.actions.place_order'))
            ->modalDescription(fn (PurchaseNeed $record): string => self::blocked($record) ? __('purchasing.help.approval_blocked') : __('purchasing.help.place_order'))
            ->modalIcon(fn (PurchaseNeed $record): Heroicon => self::blocked($record) ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedTruck)
            ->modalIconColor(fn (PurchaseNeed $record): string => self::blocked($record) ? 'warning' : 'success')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (PurchaseNeed $record): array => [
                'expected_delivery_on' => $record->recommendedQuote?->getAttribute('delivery_on')?->toDateString(),
            ])
            ->schema(fn (Schema $schema, PurchaseNeed $record): Schema => self::blocked($record)
                ? $schema->components(self::approvalWarning($record))
                : $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                    DatePicker::make('expected_delivery_on')
                        ->label(__('purchasing.fields.expected_delivery_on'))
                        ->displayFormat('d.m.Y'),
                    Textarea::make('note')
                        ->label(__('purchasing.fields.order_note'))
                        ->rows(3)
                        ->maxLength(2000),
                ])))
            ->modalSubmitAction(fn (Action $action, PurchaseNeed $record): Action | false => self::submitButton($action, $record, __('purchasing.actions.place_order')))
            ->action(function (array $data, PurchaseNeed $record): void {
                if (self::blocked($record)) {
                    self::sendForApproval($record, null);

                    return;
                }

                try {
                    app(PurchaseNeedService::class)->placeOrder($record, [
                        'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                        'note' => $data['note'] ?? null,
                    ]);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.ordered'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label(__('purchasing.actions.complete'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseNeed $record): bool => self::legacyFlow()
                && in_array($record->statusEnum(), [...PurchaseNeedStatus::pendingOrder(), PurchaseNeedStatus::Ordered], true)
                && Gate::allows('purchase', $record))
            ->modalHeading(fn (PurchaseNeed $record): string => self::blocked($record) ? __('purchasing.modals.approval_required') : __('purchasing.actions.complete'))
            ->modalDescription(fn (PurchaseNeed $record): string => self::blocked($record) ? __('purchasing.help.approval_blocked') : __('purchasing.help.complete'))
            ->modalIcon(fn (PurchaseNeed $record): Heroicon => self::blocked($record) ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedCheckCircle)
            ->modalIconColor(fn (PurchaseNeed $record): string => self::blocked($record) ? 'warning' : 'success')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => ['purchased_on' => Carbon::now(DisplayTime::zone())->toDateString()])
            ->schema(fn (Schema $schema, PurchaseNeed $record): Schema => self::blocked($record)
                ? $schema->components(self::approvalWarning($record))
                : $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                    DatePicker::make('purchased_on')
                        ->label(__('purchasing.fields.purchased_on'))
                        ->displayFormat('d.m.Y')
                        ->required(),
                ])))
            ->modalSubmitAction(fn (Action $action, PurchaseNeed $record): Action | false => self::submitButton($action, $record, __('purchasing.actions.complete')))
            ->action(function (array $data, PurchaseNeed $record): void {
                if (self::blocked($record)) {
                    self::sendForApproval($record, null);

                    return;
                }

                try {
                    app(PurchaseNeedService::class)->complete($record, filled($data['purchased_on'] ?? null) ? (string) $data['purchased_on'] : null);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.completed'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_need')
            ->label(__('purchasing.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color(ActionColors::CANCEL)
            ->visible(fn (PurchaseNeed $record): bool => ! $record->isArchived()
                && ! $record->statusEnum()->isClosed()
                && (Gate::allows('purchase', $record) || Gate::allows('archive', $record)))
            ->modalHeading(__('purchasing.actions.cancel'))
            ->modalDescription(__('purchasing.help.cancel'))
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalSubmitActionLabel(__('purchasing.actions.cancel'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('purchasing.fields.reason'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, PurchaseNeed $record): void {
                try {
                    app(PurchaseNeedService::class)->cancel($record, filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.cancelled'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function archive(): Action
    {
        return Action::make('archive_need')
            ->label(__('purchasing.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->visible(fn (PurchaseNeed $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchasing.actions.archive'))
            ->modalDescription(__('purchasing.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->modalSubmitActionLabel(__('purchasing.actions.archive'))
            ->action(function (PurchaseNeed $record): void {
                try {
                    app(PurchaseNeedService::class)->archive($record);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.archived'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore_need')
            ->label(__('purchasing.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (PurchaseNeed $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchasing.actions.restore'))
            ->modalDescription(__('purchasing.help.restore'))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel(__('purchasing.actions.restore'))
            ->action(function (PurchaseNeed $record): void {
                try {
                    app(PurchaseNeedService::class)->restore($record);
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.restored'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * "Katalogla eşleştir" (D-190): katalogda olmayan urunle acilmis talebi
     * satin alma var olan kaleme baglar ya da kataloga bir kez ekler.
     */
    public static function linkCatalog(): Action
    {
        return Action::make('link_catalog')
            ->label(__('purchasing.actions.link_catalog'))
            ->icon(Heroicon::OutlinedLink)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseNeed $record): bool => ! $record->hasCatalogItem()
                && ! $record->isArchived()
                && ! $record->statusEnum()->isClosed()
                && Gate::allows('update', $record)
                && Gate::allows('create', CatalogItem::class))
            ->modalHeading(__('purchasing.actions.link_catalog'))
            ->modalDescription(fn (PurchaseNeed $record): string => (string) $record->getAttribute('requested_item_name'))
            ->modalWidth(Width::TwoExtraLarge)
            ->schema(fn (Schema $schema, PurchaseNeed $record): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(PurchaseNeedForms::linkCatalogFields($record)))
            ->action(function (array $data, PurchaseNeed $record): void {
                try {
                    app(PurchaseNeedService::class)->linkCatalogItem(
                        $record,
                        (bool) ($data['as_new'] ?? false) ? null : (int) $data['catalog_item_id'],
                        (bool) ($data['as_new'] ?? false) ? (string) FormState::value($data['new_kind'] ?? null) : null,
                    );
                    self::refresh($record);
                    DomainNotifications::success(__('purchasing.messages.catalog_linked'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Liste ust dugmeleri: "Satın alma talebi" (her aktif personel; sat-03,
     * D-190) ve "Kalem ekle" (satin alma; proje secilebilir).
     */
    public static function createNeed(bool $manual): Action
    {
        // D-192 (B53 + "Kalemli satin alma talebi"): talep kalemli formla acilir
        // (PurchaseRequestService); kapaliyken eski tek kalemli form aynen kalir.
        if (! $manual && PurchaseRequestForm::enabled()) {
            return self::createRequest();
        }

        return Action::make($manual ? 'add_manual' : 'report_need')
            ->label(__($manual ? 'purchasing.actions.add_manual' : 'purchasing.actions.report_need'))
            ->icon($manual ? Heroicon::OutlinedPlus : Heroicon::OutlinedShoppingCart)
            ->color(ActionColors::CREATE)
            // Talepler listesinde de durur (D-191); ekran disinda ozellik ve B52A da denetlenir.
            ->visible(fn (): bool => self::purchasingReady() && Gate::allows($manual ? 'createManual' : 'create', PurchaseNeed::class))
            ->modalHeading(__($manual ? 'purchasing.actions.add_manual' : 'purchasing.modals.request_heading'))
            ->modalDescription($manual ? null : __('purchasing.modals.request_description'))
            ->modalWidth(Width::FourExtraLarge)
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(PurchaseNeedForms::needFields($manual)))
            ->action(function (array $data) use ($manual): void {
                try {
                    /** @var PurchaseNeed $need */
                    $need = app(PurchaseNeedService::class)->create([
                        ...$data,
                        'source' => ($manual ? PurchaseNeedSource::Manual : PurchaseNeedSource::OfficeRequest)->value,
                    ]);

                    $url = RecordLinks::detailUrl($need);

                    Notification::make()
                        ->title(__('purchasing.messages.need_created'))
                        ->success()
                        ->actions($url === null ? [] : [
                            Action::make('open_request')
                                ->label(__('purchasing.actions.open_request'))
                                ->url($url)
                                ->color(ActionColors::VIEW),
                        ])
                        ->send();
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Kalemli "Satın alma talebi aç" (D-192; ekran goruntuleri 3, 4, 5): ust
     * bolumde talep, altta kalemler (Kalem ekle, Toplu kalem ekle, Tumunu
     * kucult / genislet). Pencere kapaninca bildirimde "Talebi aç" vardir.
     */
    private static function createRequest(): Action
    {
        return Action::make('report_need')
            ->label(__('purchasing.actions.report_need'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(ActionColors::CREATE)
            // D-229: talep ilk etapta Satin alinacaklar olmadan acilir (Talepler listesinde de).
            ->visible(fn (): bool => PurchaseRequestResource::canAccess() && Gate::allows('create', PurchaseRequest::class))
            ->modalHeading(__('purchase_request.modals.heading'))
            ->modalDescription(__('purchase_request.modals.description'))
            ->modalIcon(Heroicon::OutlinedShoppingCart)
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitActionLabel(__('purchase_request.actions.create'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(1)->components(PurchaseRequestForm::components()))
            ->action(function (array $data, Action $action): void {
                if (PurchaseRequestActions::store($data) === null) {
                    $action->halt();
                }
            });
    }

    /**
     * Satin alma teklifleri akisi acik mi (D-192: B53 + "Satin alma
     * teklifleri")? Acikken satin alma teklif -> siparis yoluyla yurur; kalem
     * basina eski tedarikci teklif kartlari, "Önerilen yap", "Onaya gönder",
     * "Sipariş ver" ve "Tamamla" gizlenir. Kapaliyken B52A akisi aynen kalir.
     */
    public static function quotationFlow(): bool
    {
        return SchemaReadiness::hasBatch('B53') && FeatureFlags::enabled(Feature::PurchaseQuotations);
    }

    /**
     * Eski (B52A) kalem basina akis: tedarikci teklif kartlari, onerilen teklif,
     * kalemde "Onaya gönder", "Sipariş ver", "Tamamla". Yalniz B53 uygulanmamis
     * veritabaninda. D-229 (20 Ekim 2026: "Satin alinacaklar kismini da 2.8e
     * alalim"): Satin alinacaklar 2.8'de takip listesi olarak acilir, satin alma
     * teklifleri ve siparisler 2.9'da; aradaki surede eski akis acilmaz, siparis
     * yalniz teklif -> siparis yoluyla verilir.
     */
    public static function legacyFlow(): bool
    {
        return ! SchemaReadiness::hasBatch('B53');
    }

    /**
     * "Teklif iste" (toplu; kullanici: "topluca arayuzden checkbox olarak
     * secilip topluca teklif'te birlestirilip ortak bir siparise
     * donusturulebilmesi"): secilen kalemler tek satin alma teklifinde
     * toplanir; birden fazla tedarikci secilirse her birine ayri taslak.
     */
    public static function requestQuotationBulk(): BulkAction
    {
        return BulkAction::make('request_quotation_bulk')
            ->label(__('purchasing.actions.request_quotation'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color(ActionColors::CREATE)
            ->visible(fn (): bool => self::quotationFlow() && Gate::allows('create', PurchaseQuotation::class))
            ->modalHeading(__('purchasing.actions.request_quotation'))
            ->modalDescription(__('purchasing.help.request_quotation'))
            ->modalIcon(Heroicon::OutlinedDocumentPlus)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('purchasing.actions.request_quotation'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(self::quotationRequestFields()))
            ->deselectRecordsAfterCompletion()
            ->action(fn (array $data, Enumerable $records) => self::runRequestQuotation($records, $data));
    }

    /** "Teklif iste" (kalem sayfasinin basligi; tek kayit islemi de etiketli dugmededir). */
    public static function requestQuotation(): Action
    {
        return Action::make('request_quotation')
            ->label(__('purchasing.actions.request_quotation'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color(ActionColors::CREATE)
            ->visible(fn (PurchaseNeed $record): bool => self::quotationFlow()
                && Gate::allows('create', PurchaseQuotation::class)
                && Gate::allows('purchase', $record))
            ->modalHeading(__('purchasing.actions.request_quotation'))
            ->modalDescription(__('purchasing.help.request_quotation'))
            ->modalIcon(Heroicon::OutlinedDocumentPlus)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('purchasing.actions.request_quotation'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(self::quotationRequestFields()))
            ->action(function (array $data, PurchaseNeed $record): void {
                self::runRequestQuotation([$record], $data);
                self::refresh($record);
            });
    }

    /** "Mevcut teklife ekle" (toplu): secilen kalemler degisebilen bir satin alma teklifine eklenir. */
    public static function addToQuotationBulk(): BulkAction
    {
        return BulkAction::make('add_to_quotation_bulk')
            ->label(__('purchasing.actions.add_to_quotation'))
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color(ActionColors::CREATE)
            ->visible(fn (): bool => self::quotationFlow() && Gate::allows('create', PurchaseQuotation::class))
            ->modalHeading(__('purchasing.actions.add_to_quotation'))
            ->modalDescription(__('purchasing.help.add_to_quotation'))
            ->modalIcon(Heroicon::OutlinedDocumentArrowUp)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('purchasing.actions.add_to_quotation'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(self::addToQuotationFields()))
            ->deselectRecordsAfterCompletion()
            ->action(fn (array $data, Enumerable $records) => self::runAddToQuotation($records, $data));
    }

    public static function addToQuotation(): Action
    {
        return Action::make('add_to_quotation')
            ->label(__('purchasing.actions.add_to_quotation'))
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color(ActionColors::CREATE)
            ->visible(fn (PurchaseNeed $record): bool => self::quotationFlow()
                && Gate::allows('create', PurchaseQuotation::class)
                && Gate::allows('purchase', $record))
            ->modalHeading(__('purchasing.actions.add_to_quotation'))
            ->modalDescription(__('purchasing.help.add_to_quotation'))
            ->modalIcon(Heroicon::OutlinedDocumentArrowUp)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('purchasing.actions.add_to_quotation'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(self::addToQuotationFields()))
            ->action(function (array $data, PurchaseNeed $record): void {
                self::runAddToQuotation([$record], $data);
                self::refresh($record);
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function quotationRequestFields(): array
    {
        return FieldGrid::modal([
            Select::make('supplier_party_ids')
                ->label(__('purchasing.fields.suppliers'))
                ->helperText(__('purchasing.help.suppliers'))
                ->multiple()
                ->options(fn (): array => app(PurchasingQueries::class)->supplierOptions())
                ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->supplierOptions($search))
                ->getOptionLabelsUsing(fn (mixed $values): array => collect((array) $values)
                    ->mapWithKeys(fn (mixed $id): array => [(int) $id => (string) app(PurchasingQueries::class)->supplierLabel($id)])
                    ->all())
                ->searchable()
                ->native(false),
            DatePicker::make('quote_date')
                ->label(__('purchasing.fields.quote_date'))
                ->displayFormat('d.m.Y')
                ->default(fn (): string => Carbon::now(DisplayTime::zone())->toDateString()),
            DatePicker::make('valid_until')
                ->label(__('purchasing.fields.valid_until'))
                ->displayFormat('d.m.Y'),
            Select::make('currency_code')
                ->label(__('purchasing.fields.currency'))
                ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                ->default('TRY')
                ->required()
                ->native(false),
            Textarea::make('note')
                ->label(__('purchasing.fields.request_note'))
                ->helperText(__('purchasing.help.quotable'))
                ->rows(3)
                ->maxLength(4000),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function addToQuotationFields(): array
    {
        return FieldGrid::modal([
            Select::make('purchase_quotation_id')
                ->label(__('purchasing.fields.target_quotation'))
                ->helperText(__('purchasing.help.quotable'))
                ->tap(TwoLineSelect::rows(fn (): array => app(PurchaseRequestQueries::class)->editableQuotationRows()))
                ->searchable()
                ->required()
                ->native(false),
        ]);
    }

    /**
     * @param  iterable<mixed>  $records
     * @param  array<string, mixed>  $data
     */
    private static function runRequestQuotation(iterable $records, array $data): void
    {
        [$allowed, $skipped] = self::quotable($records);

        if ($allowed === []) {
            Notification::make()->title(__('purchasing.messages.not_quotable'))->warning()->send();

            return;
        }

        try {
            $quotations = app(PurchaseQuotationService::class)->requestFromNeeds(
                $allowed,
                array_intersect_key($data, array_flip(['quote_date', 'valid_until', 'currency_code', 'note'])),
                array_values(array_map('intval', (array) ($data['supplier_party_ids'] ?? []))),
            );
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        self::$states = [];
        $links = [];

        foreach (array_slice($quotations, 0, 5) as $quotation) {
            $url = RecordLinks::detailUrl($quotation, checkRecord: false);

            if ($url !== null) {
                $links[] = Action::make('open_quotation_'.$quotation->getKey())
                    ->label((string) $quotation->getAttribute('quotation_no'))
                    ->url($url)
                    ->color(ActionColors::VIEW);
            }
        }

        Notification::make()
            ->title(__('purchasing.messages.quotation_requested', ['count' => count($quotations)]))
            ->body(implode("\n", array_filter([
                __('purchasing.messages.quotation_requested_body', [
                    'codes' => implode(', ', array_map(static fn (PurchaseQuotation $quotation): string => (string) $quotation->getAttribute('quotation_no'), $quotations)),
                ]),
                $skipped > 0 ? __('purchasing.messages.skipped', ['count' => $skipped]) : null,
            ])))
            ->success()
            ->actions($links)
            ->persistent()
            ->send();
    }

    /**
     * @param  iterable<mixed>  $records
     * @param  array<string, mixed>  $data
     */
    private static function runAddToQuotation(iterable $records, array $data): void
    {
        $quotation = app(PurchaseRequestQueries::class)->quotation($data['purchase_quotation_id'] ?? null);

        if (! $quotation instanceof PurchaseQuotation || ! Gate::allows('update', $quotation)) {
            Notification::make()->title(__('purchasing.messages.not_quotable'))->warning()->send();

            return;
        }

        [$allowed, $skipped] = self::quotable($records);

        if ($allowed === []) {
            Notification::make()->title(__('purchasing.messages.not_quotable'))->warning()->send();

            return;
        }

        try {
            $added = app(PurchaseQuotationService::class)->addNeeds($quotation, $allowed);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        self::$states = [];
        $url = RecordLinks::detailUrl($quotation, checkRecord: false);

        Notification::make()
            ->title(__('purchasing.messages.added_to_quotation', ['count' => $added, 'code' => $quotation->getAttribute('quotation_no')]))
            ->body($skipped > 0 ? __('purchasing.messages.skipped', ['count' => $skipped]) : null)
            ->success()
            ->actions($url === null ? [] : [
                Action::make('open_quotation')
                    ->label(__('purchasing.actions.open_quotation'))
                    ->url($url)
                    ->color(ActionColors::VIEW),
            ])
            ->send();
    }

    /**
     * Teklife girebilecek kalemler (satin alma yetkisi: proje onayi bekleyen,
     * kapanmis ya da arsivli kalem haric) ve atlanan sayisi. Siparisi verilmis
     * kalemi servis ayrica reddeder.
     *
     * @param  iterable<mixed>  $records
     * @return array{0: list<PurchaseNeed>, 1: int}
     */
    private static function quotable(iterable $records): array
    {
        $allowed = [];
        $skipped = 0;

        foreach ($records as $record) {
            if ($record instanceof PurchaseNeed && Gate::allows('purchase', $record) && $record->statusEnum() !== PurchaseNeedStatus::Ordered) {
                $allowed[] = $record;

                continue;
            }

            $skipped++;
        }

        return [$allowed, $skipped];
    }

    /** Satin alma talebi ekranlari acik mi (ozellik + B52A)? */
    public static function purchasingReady(): bool
    {
        return FeatureFlags::enabled(Feature::PurchaseNeeds) && SchemaReadiness::hasBatch('B52A');
    }

    /**
     * Uyari penceresinin dugmesi: gonderilebiliyorsa "Onaya gönder", onay
     * bekleniyor ya da teklif / kur / politika eksikse dugme yok.
     */
    private static function submitButton(Action $submit, PurchaseNeed $need, string $label): Action | false
    {
        if (! self::blocked($need)) {
            return ActionColors::submit($submit->label($label));
        }

        $state = self::approvalState($need);

        if ($state->pending || $state->missing !== null) {
            return false;
        }

        return $submit->label(__('purchasing.actions.request_approval'))->color(ActionColors::SEND);
    }

    private static function sendForApproval(PurchaseNeed $need, ?string $note): void
    {
        $approvers = self::approvalState($need)->approversText();

        try {
            $result = app(PurchaseNeedService::class)->requestApproval($need, $note);
            self::refresh($need);

            if ($result->statusEnum() === PurchaseNeedStatus::Approved) {
                DomainNotifications::success(__('purchasing.messages.self_approved'));

                return;
            }

            if ($result->statusEnum() !== PurchaseNeedStatus::AwaitingApproval) {
                DomainNotifications::success(__('purchasing.messages.no_approval_needed'));

                return;
            }

            Notification::make()
                ->title(__('purchasing.messages.approval_requested'))
                ->body(__('purchasing.messages.approval_requested_body', ['approvers' => $approvers]))
                ->success()
                ->send();
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }
    }

    private static function refresh(PurchaseNeed $need): void
    {
        self::$states = [];
        $need->refresh();
    }
}
