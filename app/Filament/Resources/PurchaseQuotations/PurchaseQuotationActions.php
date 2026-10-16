<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Exceptions\AbstractException;
use App\Filament\Exports\PurchaseDocumentPdf;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseQuotation;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseQuotationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satin alma teklifi eylemleri (D-192). Ayrinti sayfasinin etiketli baslik
 * dugmeleri (D-125 / record actions reachability): en uygun teklif olarak
 * sec, secimi kaldir, reddet, iptal et, siparise donustur (teslim adresi,
 * kisi, donem, beklenen teslim, odeme sartlari, proje, not), PDF indir, arsiv;
 * listenin "Karsilastir" toplu eylemi (kalem x tedarikci matrisi). Butun
 * yazmalar PurchaseQuotationService / PurchaseOrderService'tedir; is hatalari
 * DomainNotifications ile gosterilir. Her dugme politikaya baglidir.
 */
final class PurchaseQuotationActions
{
    /**
     * Ayrinti sayfasinin baslik dugmeleri.
     *
     * @return array<int, Action|ActionGroup>
     */
    public static function viewHeader(): array
    {
        return [
            PurchaseQuotationAiActions::readDocuments(),
            PurchaseQuotationAiActions::aiCreatedItems(),
            self::select(),
            self::unselect(),
            self::convertToOrder(),
            self::reject(),
            self::pdf(),
            self::edit(),
            ActionGroup::make([
                self::cancel(),
                self::archive(),
                self::restore(),
            ])
                ->label(__('purchase_quotation.actions.more'))
                ->tooltip(__('purchase_quotation.actions.more'))
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray'),
        ];
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->visible(fn (PurchaseQuotation $record): bool => Gate::allows('update', $record));
    }

    public static function select(): Action
    {
        return Action::make('select_quotation')
            ->label(__('purchase_quotation.actions.select'))
            ->icon(Heroicon::OutlinedStar)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseQuotation $record): bool => Gate::allows('select', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_quotation.actions.select'))
            ->modalDescription(__('purchase_quotation.help.select'))
            ->modalIcon(Heroicon::OutlinedStar)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.select'))
            ->action(function (PurchaseQuotation $record): void {
                self::run($record, fn () => app(PurchaseQuotationService::class)->select($record), 'selected');
            });
    }

    public static function unselect(): Action
    {
        return Action::make('unselect_quotation')
            ->label(__('purchase_quotation.actions.unselect'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::CANCEL)
            ->visible(fn (PurchaseQuotation $record): bool => $record->statusEnum() === PurchaseQuotationStatus::Selected
                && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_quotation.actions.unselect'))
            ->modalDescription(__('purchase_quotation.help.unselect'))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.unselect'))
            ->action(function (PurchaseQuotation $record): void {
                self::run($record, fn () => app(PurchaseQuotationService::class)->unselect($record), 'unselected');
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject_quotation')
            ->label(__('purchase_quotation.actions.reject'))
            ->icon(Heroicon::OutlinedHandThumbDown)
            ->color(ActionColors::DELETE)
            ->visible(fn (PurchaseQuotation $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('purchase_quotation.actions.reject'))
            ->modalDescription(__('purchase_quotation.help.reject'))
            ->modalIcon(Heroicon::OutlinedHandThumbDown)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.reject'))
            ->schema(self::reasonFields())
            ->action(function (array $data, PurchaseQuotation $record): void {
                self::run($record, fn () => app(PurchaseQuotationService::class)->reject($record, self::reason($data)), 'rejected');
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_quotation')
            ->label(__('purchase_quotation.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color(ActionColors::CANCEL)
            ->visible(fn (PurchaseQuotation $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('purchase_quotation.actions.cancel'))
            ->modalDescription(__('purchase_quotation.help.cancel'))
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.cancel'))
            ->schema(self::reasonFields())
            ->action(function (array $data, PurchaseQuotation $record): void {
                self::run($record, fn () => app(PurchaseQuotationService::class)->cancel($record, self::reason($data)), 'cancelled');
            });
    }

    /**
     * "Siparise donustur" (yalniz secili teklif): teslim / lojistik bilgileri
     * sorulur, siparis acilir ve siparis sayfasi acilir.
     */
    public static function convertToOrder(): Action
    {
        return Action::make('convert_to_order')
            ->label(__('purchase_quotation.actions.convert'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color(ActionColors::SAVE)
            ->visible(fn (PurchaseQuotation $record): bool => FeatureFlags::enabled(Feature::PurchaseOrders)
                && Gate::allows('order', $record)
                && Gate::allows('create', PurchaseOrder::class))
            ->modalHeading(__('purchase_quotation.actions.convert'))
            ->modalDescription(__('purchase_quotation.help.convert'))
            ->modalIcon(Heroicon::OutlinedShoppingCart)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.convert'))
            ->fillForm(fn (PurchaseQuotation $record): array => [
                'expected_delivery_on' => $record->getAttribute('delivery_on')?->toDateString(),
                'payment_terms' => $record->getAttribute('payment_terms'),
                'project_id' => app(PurchaseQuotationQueries::class)->singleProjectId($record),
            ])
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Textarea::make('delivery_address')
                        ->label(__('purchase_quotation.convert.delivery_address'))
                        ->rows(3)
                        ->maxLength(2000),
                    TextInput::make('delivery_contact')
                        ->label(__('purchase_quotation.convert.delivery_contact'))
                        ->maxLength(200),
                    DatePicker::make('delivery_from')
                        ->label(__('purchase_quotation.convert.delivery_from'))
                        ->displayFormat('d.m.Y')
                        ->columnStart(1),
                    DatePicker::make('delivery_until')
                        ->label(__('purchase_quotation.convert.delivery_until'))
                        ->displayFormat('d.m.Y')
                        ->afterOrEqual('delivery_from'),
                    DatePicker::make('expected_delivery_on')
                        ->label(__('purchase_quotation.convert.expected_delivery_on'))
                        ->displayFormat('d.m.Y'),
                    TextInput::make('payment_terms')
                        ->label(__('purchase_quotation.convert.payment_terms'))
                        ->maxLength(255)
                        ->columnStart(1),
                    Select::make('project_id')
                        ->label(__('purchase_quotation.convert.project'))
                        ->options(fn (): array => app(ProjectCatalogQueries::class)->projectOptions())
                        ->searchable()
                        ->native(false),
                    Textarea::make('note')
                        ->label(__('purchase_quotation.convert.note'))
                        ->rows(3)
                        ->maxLength(4000)
                        ->columnStart(1),
                ])))
            ->action(function (array $data, PurchaseQuotation $record, Component $livewire): void {
                try {
                    $order = app(PurchaseOrderService::class)->createFromQuotation($record, $data);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('purchase_quotation.messages.converted', ['code' => (string) $order->getAttribute('order_no')]));

                $livewire->redirect(PurchaseOrderResource::canView($order)
                    ? PurchaseOrderResource::getUrl('view', ['record' => $order])
                    : PurchaseQuotationResource::getUrl('view', ['record' => $record]));
            });
    }

    public static function pdf(): Action
    {
        return Action::make('download_pdf')
            ->label(__('purchase_quotation.actions.pdf'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (PurchaseQuotation $record): bool => FeatureFlags::enabled(Feature::PurchaseQuotations) && Gate::allows('view', $record))
            ->action(fn (PurchaseQuotation $record): StreamedResponse => app(PurchaseDocumentPdf::class)->quotation($record));
    }

    public static function archive(): Action
    {
        return Action::make('archive_quotation')
            ->label(__('purchase_quotation.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->visible(fn (PurchaseQuotation $record): bool => ! $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_quotation.actions.archive'))
            ->modalDescription(__('purchase_quotation.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.archive'))
            ->action(function (PurchaseQuotation $record): void {
                self::run($record, fn () => app(PurchaseQuotationService::class)->archive($record), 'archived');
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore_quotation')
            ->label(__('purchase_quotation.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->visible(fn (PurchaseQuotation $record): bool => $record->isArchived() && Gate::allows('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_quotation.actions.restore'))
            ->modalDescription(__('purchase_quotation.help.restore'))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.restore'))
            ->action(function (PurchaseQuotation $record): void {
                self::run($record, fn () => app(PurchaseQuotationService::class)->restore($record), 'restored');
            });
    }

    /**
     * Liste: "Karsilastir" (2+ teklif): satirlar kalemler, sutunlar tedarikciler;
     * en ucuz hucre yesil, eksik hucre "–", altta toplamlar.
     */
    public static function compareBulk(): BulkAction
    {
        return BulkAction::make('compare_quotations')
            ->label(__('purchase_quotation.actions.compare'))
            ->icon(Heroicon::OutlinedScale)
            ->color(ActionColors::VIEW)
            ->modalHeading(__('purchase_quotation.compare.heading'))
            ->modalDescription(__('purchase_quotation.compare.description'))
            ->modalIcon(Heroicon::OutlinedScale)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (Collection $records): View => view('filament.purchasing.quotation-compare', [
                'matrix' => $records->count() >= 2 ? app(PurchaseQuotationQueries::class)->comparisonMatrix($records) : null,
                'tooFew' => $records->count() < 2,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('purchase_quotation.actions.close'))
            ->action(static fn (): null => null);
    }

    /**
     * @return array<int, mixed>
     */
    private static function reasonFields(): array
    {
        return [
            Textarea::make('reason')
                ->label(__('purchase_quotation.fields_reason'))
                ->rows(3)
                ->maxLength(1000),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function reason(array $data): ?string
    {
        return filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null;
    }

    private static function run(PurchaseQuotation $record, \Closure $write, string $message): void
    {
        try {
            $write();
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        $record->refresh();
        DomainNotifications::success(__('purchase_quotation.messages.'.$message));
    }
}
