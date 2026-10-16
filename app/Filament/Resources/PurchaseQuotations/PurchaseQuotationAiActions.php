<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\QuotationAiStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\UnitSelect;
use App\Livewire\Procurement\QuotationAiProgress;
use App\Models\Procurement\CatalogCategory;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Procurement\CatalogQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Procurement\QuotationAiQueries;
use App\Services\Procurement\PurchaseQuotationAiService;
use App\Support\UploadLimits;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Throwable;

/**
 * Satin alma teklifinin Konelsis AI eylemleri (D-192, u-mv0njps9-98cc;
 * ai-service planlama kaydi 29). Teklif sayfalari (detay ve duzenleme) iki
 * eylemi baslik eylemi, ilerleme bilesenini sayfa semasinda kullanir:
 *
 * - readDocuments(): "Konelsis AI ile belgeden doldur". Birden fazla belge
 *   (PDF / Word / Excel / fotograf) yuklenir, Dokuman yonetimine eklenir ve tek
 *   istekte Konelsis AI'ye gider (PurchaseQuotationAiService::read). Sonra
 *   detay sayfasi acilir; okuma orada progress() ile izlenir.
 * - aiCreatedItems(): "Konelsis AI olarak yeni olusturdugum urunler". AI'nin
 *   actigi kalemler satir satir: Uygun, ekle / Duzenle ve ekle / Listeden
 *   baska kalem sec; "Hepsi uygun, ekle" karar verilmemis satirlari kabul eder.
 *   Kabul = satirin ai_created_item isareti kalkar (yeni kolon yok).
 * - progress(): okuma suruyorken gorunen ve sonucu yoklayan Livewire bileseni
 *   (QuotationAiProgress); sonuc yazilinca sayfa yenilenir.
 *
 * Feature::PurchaseQuotationsAi (ve ust ozelligi) kapaliyken, B53 uygulanmamis
 * ya da ai-service baglantisi tanimli degilken ucu de gizlidir.
 */
final class PurchaseQuotationAiActions
{
    /** Tedarikci teklif belgeleri: PDF, Word, Excel, fotograf (belge listesiyle ayni). */
    private const ACCEPTED = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function readDocuments(): Action
    {
        return Action::make('aiReadDocuments')
            ->label(__('purchase_quotation_ai.actions.read.label'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color(ActionColors::SEND)
            ->visible(fn (mixed $record): bool => self::canChange($record))
            ->disabled(fn (mixed $record): bool => $record instanceof PurchaseQuotation && self::service()->isReading($record))
            ->modalHeading(__('purchase_quotation_ai.actions.read.heading'))
            ->modalDescription(__('purchase_quotation_ai.actions.read.description'))
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    FileUpload::make('files')
                        ->label(__('purchase_quotation_ai.actions.read.files'))
                        ->helperText(__('purchase_quotation_ai.actions.read.files_hint'))
                        ->disk('local')
                        ->directory('document-uploads-tmp')
                        ->storeFileNamesIn('file_names')
                        ->multiple()
                        ->maxFiles(PurchaseQuotationAiService::MAX_DOCUMENTS)
                        ->acceptedFileTypes(self::ACCEPTED)
                        ->maxSize(UploadLimits::documentMaxKb())
                        ->required()
                        ->columnSpan(FieldGrid::MODAL_LONG),
                    Hidden::make('file_names'),
                ])))
            ->modalSubmitActionLabel(__('purchase_quotation_ai.actions.read.submit'))
            ->action(function (array $data, PurchaseQuotation $record, Component $livewire): void {
                $files = self::files($data['files'] ?? null, $data['file_names'] ?? null);

                if ($files === []) {
                    return;
                }

                try {
                    $sent = self::service()->read($record, $files);

                    Notification::make()
                        ->title(__('purchase_quotation_ai.notifications.started_title'))
                        ->body(__('purchase_quotation_ai.notifications.started_body', ['count' => $sent]))
                        ->success()
                        ->send();
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                // Belge durumlari ve ilerleme detay sayfasinda gorunur (D-178: kayittan sonra detay).
                $livewire->redirect(self::detailUrl($record));
            });
    }

    public static function aiCreatedItems(): Action
    {
        return Action::make('aiCreatedItems')
            ->label(__('purchase_quotation_ai.actions.items.label'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color(ActionColors::EDIT)
            ->badge(fn (mixed $record): ?int => $record instanceof PurchaseQuotation ? (self::queries()->aiCreatedCount($record) ?: null) : null)
            ->badgeColor('warning')
            ->visible(fn (mixed $record): bool => self::canChange($record) && self::queries()->aiCreatedCount($record) > 0)
            ->modalHeading(__('purchase_quotation_ai.actions.items.heading'))
            ->modalDescription(__('purchase_quotation_ai.actions.items.description'))
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalWidth(Width::SixExtraLarge)
            ->fillForm(fn (PurchaseQuotation $record): array => ['items' => self::reviewRows($record)])
            ->schema(fn (Schema $schema): Schema => $schema->components([
                Repeater::make('items')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): string => implode('  →  ', array_filter([
                        (string) ($state['line_label'] ?? ''),
                        (string) ($state['item_label'] ?? ''),
                    ], static fn (string $part): bool => $part !== '')))
                    ->helperText(__('purchase_quotation_ai.items.later'))
                    ->columns(FieldGrid::MODAL_COLUMNS)
                    ->columnSpanFull()
                    ->schema(FieldGrid::modal([
                        Hidden::make('line_id'),
                        Hidden::make('line_label'),
                        Hidden::make('item_label'),
                        Hidden::make('has_unit'),
                        ToggleButtons::make('decision')
                            ->label(__('purchase_quotation_ai.items.decision'))
                            ->options([
                                'accept' => __('purchase_quotation_ai.items.decisions.accept'),
                                'edit' => __('purchase_quotation_ai.items.decisions.edit'),
                                'relink' => __('purchase_quotation_ai.items.decisions.relink'),
                            ])
                            ->colors(['accept' => 'success', 'edit' => 'warning', 'relink' => 'info'])
                            ->icons([
                                'accept' => Heroicon::OutlinedCheck,
                                'edit' => Heroicon::OutlinedPencilSquare,
                                'relink' => Heroicon::OutlinedArrowsRightLeft,
                            ])
                            ->inline()
                            ->live()
                            ->columnSpan(FieldGrid::MODAL_LONG),
                        TextInput::make('name')
                            ->label(__('purchase_quotation_ai.items.name'))
                            ->maxLength(300)
                            ->required(fn (Get $get): bool => self::decision($get) === 'edit')
                            ->visible(fn (Get $get): bool => self::decision($get) === 'edit')
                            ->columnStart(1),
                        Select::make('kind')
                            ->label(__('purchase_quotation_ai.items.kind'))
                            ->options(self::kindOptions())
                            ->native(false)
                            ->live()
                            ->required(fn (Get $get): bool => self::decision($get) === 'edit')
                            ->visible(fn (Get $get): bool => self::decision($get) === 'edit'),
                        Select::make('catalog_category_id')
                            ->label(__('purchase_quotation_ai.items.category'))
                            ->options(fn (Get $get): array => app(CatalogQueries::class)->categoryOptions(CatalogItemKind::tryFrom((string) FormState::value($get('kind')))))
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get): bool => self::decision($get) === 'edit')
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        // D-194 (B54): birim sabit listeden; birimsiz AI kalemi kabul edilirken de secilir.
                        UnitSelect::enabled()
                            ? UnitSelect::make(__('purchase_quotation_ai.items.unit'))
                                ->required(fn (Get $get): bool => self::needsUnit($get))
                                ->visible(fn (Get $get): bool => self::needsUnit($get))
                                ->columnSpan(['default' => 1, 'md' => 1])
                            : TextInput::make('unit')
                                ->label(__('purchase_quotation_ai.items.unit'))
                                ->maxLength(40)
                                ->visible(fn (Get $get): bool => self::decision($get) === 'edit'),
                        TextInput::make('brand')
                            ->label(__('purchase_quotation_ai.items.brand'))
                            ->maxLength(120)
                            ->visible(fn (Get $get): bool => self::decision($get) === 'edit'),
                        Select::make('catalog_item_id')
                            ->label(__('purchase_quotation_ai.items.target'))
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(CatalogQueries::class)->itemOptions($search, self::purchasableKinds()))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->itemLabel($value))
                            ->required(fn (Get $get): bool => self::decision($get) === 'relink')
                            ->visible(fn (Get $get): bool => self::decision($get) === 'relink')
                            ->columnSpan(FieldGrid::MODAL_LONG)
                            ->columnStart(1),
                    ])),
            ]))
            ->modalSubmitActionLabel(__('purchase_quotation_ai.actions.items.submit'))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('acceptAll', ['accept_all' => true])
                    ->label(__('purchase_quotation_ai.actions.items.accept_all'))
                    ->color(ActionColors::CREATE),
            ])
            ->action(function (array $data, array $arguments, PurchaseQuotation $record, Component $livewire): void {
                $decisions = [];

                foreach (array_values((array) ($data['items'] ?? [])) as $item) {
                    $decision = FormState::value($item['decision'] ?? null);

                    if ($decision === null && ($arguments['accept_all'] ?? false)) {
                        $decision = 'accept';
                    }

                    $decisions[] = [...$item, 'decision' => $decision];
                }

                try {
                    $counts = self::service()->reviewItems($record, $decisions);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                if (array_sum($counts) === 0) {
                    Notification::make()->title(__('purchase_quotation_ai.items.nothing'))->warning()->send();

                    return;
                }

                Notification::make()
                    ->title(__('purchase_quotation_ai.items.saved_title'))
                    ->body(__('purchase_quotation_ai.items.saved_body', $counts))
                    ->success()
                    ->send();

                // Duzenleme formundaki eski kalem durumu AI kararlarini ezmesin diye sayfa yenilenir.
                $livewire->redirect(self::currentUrl($record));
            });
    }

    /**
     * Teklif detay / duzenleme semasina tek satirla eklenir:
     * `PurchaseQuotationAiActions::progress(),`
     */
    public static function progress(): LivewireComponent
    {
        return LivewireComponent::make(QuotationAiProgress::class)
            ->key('purchase-quotation-ai-progress')
            ->visible(fn (mixed $record): bool => $record instanceof PurchaseQuotation
                && $record->aiStatusEnum() === QuotationAiStatus::Pending
                && self::service()->available())
            ->columnSpanFull();
    }

    private static function canChange(mixed $record): bool
    {
        return $record instanceof PurchaseQuotation
            && self::service()->available()
            && Gate::allows('update', $record);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function reviewRows(PurchaseQuotation $quotation): array
    {
        $rows = [];

        foreach (self::queries()->aiCreatedLines($quotation) as $line) {
            /** @var PurchaseQuotationLine $line */
            $item = $line->catalogItem;
            $category = $item?->category;

            $rows[] = [
                'line_id' => (int) $line->getKey(),
                'line_label' => Str::limit((string) $line->getAttribute('description'), 120),
                'item_label' => $item instanceof CatalogItem
                    ? $item->getAttribute('name').($category instanceof CatalogCategory ? ' · '.$category->path() : '')
                    : '',
                'decision' => null,
                'name' => $item?->getAttribute('name'),
                'kind' => $item instanceof CatalogItem ? $item->kindEnum()->value : CatalogItemKind::Product->value,
                'catalog_category_id' => $item?->getAttribute('catalog_category_id'),
                'unit' => $item?->getAttribute('unit'),
                ...(UnitSelect::enabled() ? [
                    'uom_id' => $item?->getAttribute('uom_id'),
                    'has_unit' => $item?->getAttribute('uom_id') !== null,
                ] : []),
                'brand' => $item?->getAttribute('brand'),
                'catalog_item_id' => null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private static function kindOptions(): array
    {
        $options = [];

        foreach (self::purchasableKinds() as $kind) {
            $options[$kind->value] = (string) $kind->getLabel();
        }

        return $options;
    }

    /**
     * @return list<CatalogItemKind>
     */
    private static function purchasableKinds(): array
    {
        return array_values(array_filter(CatalogItemKind::cases(), static fn (CatalogItemKind $kind): bool => $kind->isPurchasable()));
    }

    private static function decision(Get $get): ?string
    {
        return FormState::value($get('decision'));
    }

    /** Birim secimi (D-194, B54): duzenlenen kalemde ya da kabul edilen birimsiz kalemde. */
    private static function needsUnit(Get $get): bool
    {
        $decision = self::decision($get);

        return $decision === 'edit' || ($decision === 'accept' && ! (bool) $get('has_unit'));
    }

    /**
     * @return list<array{path: string, name: string|null}>
     */
    private static function files(mixed $paths, mixed $names): array
    {
        $files = [];

        foreach (is_array($paths) ? $paths : [$paths] as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $name = is_array($names) ? ($names[$path] ?? (count($names) === 1 ? reset($names) : null)) : $names;
            $files[] = ['path' => $path, 'name' => is_string($name) && $name !== '' ? $name : null];
        }

        return $files;
    }

    private static function detailUrl(PurchaseQuotation $quotation): string
    {
        try {
            if (Gate::allows('view', $quotation)) {
                return PurchaseQuotationResource::getUrl('view', ['record' => $quotation]);
            }
        } catch (Throwable) {
            // Detay sayfasi yoksa bulunulan sayfa yenilenir.
        }

        return self::currentUrl($quotation);
    }

    private static function currentUrl(PurchaseQuotation $quotation): string
    {
        $url = Livewire::isLivewireRequest() ? (string) Livewire::originalUrl() : '';

        return $url !== '' ? $url : PurchaseQuotationResource::getUrl('view', ['record' => $quotation]);
    }

    private static function service(): PurchaseQuotationAiService
    {
        return app(PurchaseQuotationAiService::class);
    }

    private static function queries(): QuotationAiQueries
    {
        return app(QuotationAiQueries::class);
    }
}
