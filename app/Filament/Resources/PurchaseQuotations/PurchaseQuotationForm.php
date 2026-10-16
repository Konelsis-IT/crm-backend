<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations;

use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\ActionColors;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\PurchaseLines;
use App\Filament\Support\TwoLineOption;
use App\Filament\Support\TwoLineSelect;
use App\Filament\Support\UnitSelect;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Query\Reference\ReferenceOptions;
use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satin alma teklifi olustur / duzenle formu (D-192; kullanicinin ekran
 * goruntuleri 3-7): ustte teklif bilgileri, ortada kalem listesi ("Kalem
 * ekle", "Toplu kalem ekle", "Tumunu kucult / genislet", basliginda rozetler),
 * altta yan yana Sartlar ve Fiyat bilgileri (genel indirim, ara toplam, kalem
 * indirimleri, kirmizi toplam indirim, genel toplam; formdan canli).
 *
 * Kayit PurchaseQuotationService'tedir (sayfalar handleRecordCreation /
 * handleRecordUpdate); toplamlar orada yeniden hesaplanir. Alanlar kompakttir
 * (D-157), tarih girdisi tek tiptir (DatePicker), tutarlar MoneyInput (D-180).
 */
final class PurchaseQuotationForm
{
    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            // Konelsis AI okumasi surerken ilerleme (olusturma sayfasinda kayit yok, gorunmez).
            PurchaseQuotationAiActions::progress(),
            self::headerSection(),
            self::linesSection(),
            Grid::make(['default' => 1, 'xl' => 2])->components([
                self::termsSection(),
                self::pricesSection(),
            ]),
            Hidden::make('row_version'),
        ];
    }

    /**
     * Duzenle formunun kalemleri (kayitli satirlar).
     *
     * @return list<array<string, mixed>>
     */
    public static function linesData(PurchaseQuotation $quotation): array
    {
        $lines = [];

        foreach ($quotation->lines()->get() as $line) {
            /** @var PurchaseQuotationLine $line */
            $lines[] = [
                'id' => (int) $line->getKey(),
                'purchase_need_id' => $line->getAttribute('purchase_need_id') !== null ? (int) $line->getAttribute('purchase_need_id') : null,
                'catalog_item_id' => $line->getAttribute('catalog_item_id') !== null ? (int) $line->getAttribute('catalog_item_id') : null,
                'description' => $line->getAttribute('description'),
                'brand_model' => $line->getAttribute('brand_model'),
                'quantity' => (float) $line->getAttribute('quantity'),
                'unit' => $line->getAttribute('unit'),
                // D-194 (B54): kalemin birimi sabittir; kilitli alan kalemin birimini gosterir.
                ...(UnitSelect::enabled() ? [
                    'uom_id' => app(UnitResolver::class)->itemUnitId($line->getAttribute('catalog_item_id')) ?? $line->getAttribute('uom_id'),
                ] : []),
                'unit_price' => (float) $line->getAttribute('unit_price'),
                'discount_rate' => (float) $line->getAttribute('discount_rate'),
                'line_total' => (float) $line->getAttribute('line_total'),
                'is_option' => (bool) $line->getAttribute('is_option'),
                'is_cancelled' => (bool) $line->getAttribute('is_cancelled'),
                'ai_created_item' => (bool) $line->getAttribute('ai_created_item'),
                'note' => $line->getAttribute('note'),
            ];
        }

        return $lines;
    }

    private static function headerSection(): Section
    {
        return Section::make(__('purchase_quotation.sections.header'))
            ->icon(Heroicon::OutlinedBuildingStorefront)
            ->columns(FieldGrid::COLUMNS)
            ->components(FieldGrid::fields([
                Select::make('supplier_party_id')
                    ->label(__('purchase_quotation.fields.supplier_party'))
                    ->options(fn (): array => app(PurchasingQueries::class)->supplierOptions())
                    ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->supplierOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->supplierLabel($value))
                    ->searchable()
                    ->live()
                    ->native(false)
                    ->columnSpan(FieldGrid::WIDE),
                TextInput::make('supplier_name')
                    ->label(__('purchase_quotation.fields.supplier_name'))
                    ->helperText(__('purchase_quotation.help.supplier_name'))
                    ->maxLength(200)
                    ->visible(fn (Get $get): bool => blank($get('supplier_party_id'))),
                TextInput::make('supplier_reference')
                    ->label(__('purchase_quotation.fields.supplier_reference'))
                    ->maxLength(100)
                    ->columnSpan(FieldGrid::NORMAL),
                DatePicker::make('quote_date')
                    ->label(__('purchase_quotation.fields.quote_date'))
                    ->displayFormat('d.m.Y')
                    ->default(fn (): string => Carbon::now(DisplayTime::zone())->toDateString()),
                DatePicker::make('valid_until')
                    ->label(__('purchase_quotation.fields.valid_until'))
                    ->displayFormat('d.m.Y'),
                Select::make('currency_code')
                    ->label(__('purchase_quotation.fields.currency'))
                    ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                    ->default('TRY')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state) => $set('try_rate', app(PurchaseQuotationQueries::class)->lastTryRate(FormState::value($state))))
                    ->native(false),
                TextInput::make('try_rate')
                    ->label(__('purchase_quotation.fields.try_rate'))
                    ->helperText(__('purchase_quotation.help.try_rate'))
                    ->numeric()
                    ->minValue(0.000001)
                    ->visible(fn (Get $get): bool => self::isForeign($get('currency_code')))
                    ->required(fn (Get $get): bool => self::isForeign($get('currency_code'))),
            ]));
    }

    private static function linesSection(): Section
    {
        return Section::make(__('purchase_quotation.sections.lines'))
            ->icon(Heroicon::OutlinedListBullet)
            ->headerActions([self::bulkAddAction()])
            ->components([
                PurchaseLines::configure(Repeater::make('lines'))
                    ->label(__('purchase_quotation.sections.lines'))
                    ->columns(FieldGrid::COLUMNS)
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state, ?int $index, Get $get) => PurchaseLines::quotationHeader($state, $index, $get('currency_code')))
                    ->schema(self::lineFields())
                    ->columnSpan(FieldGrid::FULL),
            ]);
    }

    /**
     * Kalem alanlari (ekran goruntusu 5): satin alinacak kalem, iki satirli
     * malzeme / hizmet, aciklama ve marka / model; miktar, birim, birim fiyat,
     * indirim %, indirimli toplam (salt okunur, canli); opsiyon, iptal, not.
     *
     * @return array<int, mixed>
     */
    private static function lineFields(): array
    {
        $recalculate = static function (Get $get, Set $set): void {
            $set('line_total', PurchaseQuotationLine::computeTotal(
                Money::parse($get('quantity')) ?? 0.0,
                Money::parse($get('unit_price')) ?? 0.0,
                Money::parse($get('discount_rate')) ?? 0.0,
            ));
        };

        return FieldGrid::fields([
            Hidden::make('id'),
            Hidden::make('ai_created_item'),
            Select::make('purchase_need_id')
                ->label(__('purchase_quotation.line.need'))
                ->tap(TwoLineSelect::rows(
                    fn (): array => app(PurchaseQuotationQueries::class)->openNeedRows(),
                    fn (array $ids): array => app(PurchaseQuotationQueries::class)->needRowsFor($ids),
                ))
                ->searchable()
                ->native(false)
                ->live()
                // Kayitli kalemin bagli oldugu ihtiyac degismez (servis de yazmaz).
                ->disabled(fn (Get $get): bool => filled($get('id')))
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set) use ($recalculate): void {
                    if (blank($state)) {
                        return;
                    }

                    foreach (app(PurchaseQuotationQueries::class)->needPrefill((int) FormState::value($state)) as $field => $value) {
                        if ($field === 'purchase_need_id' || $value === null) {
                            continue;
                        }

                        // D-194: birim de miktar gibi kalemden gelir (kalemin birimi sabittir).
                        if ($field === 'quantity' || $field === 'uom_id' || blank($get($field))) {
                            $set($field, $value);
                        }
                    }

                    $recalculate($get, $set);
                })
                ->columnSpan(FieldGrid::WIDE),
            Select::make('catalog_item_id')
                ->label(__('purchase_quotation.line.catalog_item'))
                ->allowHtml()
                ->searchable()
                ->options(fn (): array => TwoLineOption::options(app(PurchaseQuotationQueries::class)->catalogRows()))
                ->getSearchResultsUsing(fn (string $search): array => TwoLineOption::options(app(PurchaseQuotationQueries::class)->catalogRows($search)))
                ->getOptionLabelUsing(function (mixed $value): ?string {
                    $row = blank($value) ? null : (app(PurchaseQuotationQueries::class)->catalogRowsFor([(int) FormState::value($value)])[(int) FormState::value($value)] ?? null);

                    return $row !== null ? TwoLineOption::html($row[0], $row[1]) : null;
                })
                ->live()
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    if (blank($state)) {
                        return;
                    }

                    $id = (int) FormState::value($state);
                    $queries = app(PurchaseQuotationQueries::class);

                    if (blank($get('description'))) {
                        $set('description', $queries->catalogRowsFor([$id])[$id][0] ?? null);
                    }

                    // D-194 (B54): malzeme / hizmetin birimi gelir ve kilitlenir; oncesinde bos metin dolar.
                    UnitSelect::fillFromItem($id, $get, $set);
                })
                ->native(false)
                ->columnSpan(FieldGrid::WIDE),
            TextInput::make('description')
                ->label(__('purchase_quotation.line.description'))
                ->maxLength(495)
                ->required(fn (Get $get): bool => blank($get('catalog_item_id')) && blank($get('purchase_need_id')))
                ->live(onBlur: true)
                ->columnStart(1)
                ->columnSpan(FieldGrid::WIDE),
            TextInput::make('brand_model')
                ->label(__('purchase_quotation.line.brand_model'))
                ->maxLength(200)
                ->columnSpan(FieldGrid::NORMAL),
            TextInput::make('quantity')
                ->label(__('purchase_quotation.line.quantity'))
                ->numeric()
                ->minValue(0.0001)
                ->default(1)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated($recalculate)
                ->columnStart(1),
            // D-194 (B54): sabit birim listesi; malzeme / hizmet seciliyse onun birimi (kilitli).
            UnitSelect::enabled()
                ? UnitSelect::forItem(__('purchase_quotation.line.unit'), 'catalog_item_id')->live()
                : TextInput::make('unit')
                    ->label(__('purchase_quotation.line.unit'))
                    ->maxLength(40)
                    ->live(onBlur: true)
                    ->columnSpan(FieldGrid::SHORT),
            MoneyInput::make('unit_price')
                ->label(__('purchase_quotation.line.unit_price'))
                ->decimals(4)
                ->currencyField('../../currency_code')
                ->default(0)
                ->live(onBlur: true)
                ->afterStateUpdated($recalculate),
            TextInput::make('discount_rate')
                ->label(__('purchase_quotation.line.discount_rate'))
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->default(0)
                ->suffix('%')
                ->live(onBlur: true)
                ->afterStateUpdated($recalculate),
            MoneyInput::make('line_total')
                ->label(__('purchase_quotation.line.line_total'))
                ->currencyField('../../currency_code')
                ->disabled()
                ->dehydrated(false),
            Toggle::make('is_option')
                ->label(__('purchase_quotation.line.is_option'))
                ->live()
                ->inline(false)
                ->columnStart(1),
            Toggle::make('is_cancelled')
                ->label(__('purchase_quotation.line.is_cancelled'))
                ->live()
                ->inline(false),
            TextInput::make('note')
                ->label(__('purchase_quotation.line.note'))
                ->maxLength(1000)
                ->columnSpan(FieldGrid::WIDE),
        ]);
    }

    private static function termsSection(): Section
    {
        return Section::make(__('purchase_quotation.sections.terms'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->columns(FieldGrid::HALF_COLUMNS)
            ->components(FieldGrid::fields([
                TextInput::make('payment_terms')
                    ->label(__('purchase_quotation.fields.payment_terms'))
                    ->maxLength(255)
                    ->columnSpan(FieldGrid::HALF_LONG),
                TextInput::make('delivery_terms')
                    ->label(__('purchase_quotation.fields.delivery_terms'))
                    ->maxLength(255)
                    ->columnSpan(FieldGrid::HALF_LONG),
                TextInput::make('lead_time_days')
                    ->label(__('purchase_quotation.fields.lead_time_days'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(3650)
                    ->suffix(__('purchase_quotation.values.day_unit')),
                DatePicker::make('delivery_on')
                    ->label(__('purchase_quotation.fields.delivery_on'))
                    ->displayFormat('d.m.Y'),
                Textarea::make('note')
                    ->label(__('purchase_quotation.fields.note'))
                    ->rows(3)
                    ->maxLength(4000),
            ], halfSection: true));
    }

    /** Fiyat bilgileri (ekran goruntusu 6): formdaki kalemlerden canli. */
    private static function pricesSection(): Section
    {
        $totals = static fn (Get $get): array => PurchaseLines::totals((array) ($get('lines') ?? []), $get('general_discount'));
        $money = static fn (Get $get, float $amount, bool $negative = false): string => ($negative && $amount > 0 ? '-' : '').Money::format($amount, $get('currency_code'), Money::format(0, $get('currency_code')));

        return Section::make(__('purchase_quotation.sections.prices'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->columns(FieldGrid::HALF_COLUMNS)
            ->components([
                MoneyInput::make('general_discount')
                    ->label(__('purchase_quotation.fields.general_discount'))
                    ->default(0)
                    ->live(onBlur: true)
                    ->columnSpan(FieldGrid::NORMAL),
                TextEntry::make('summary_subtotal')
                    ->label(__('purchase_quotation.fields.subtotal'))
                    ->state(fn (Get $get): string => $money($get, $totals($get)['subtotal']))
                    ->inlineLabel()
                    ->alignEnd()
                    ->columnStart(1)
                    ->columnSpan(FieldGrid::FULL),
                TextEntry::make('summary_line_discount')
                    ->label(__('purchase_quotation.fields.line_discount'))
                    ->state(fn (Get $get): string => $money($get, $totals($get)['line_discount'], true))
                    ->inlineLabel()
                    ->alignEnd()
                    ->columnSpan(FieldGrid::FULL),
                TextEntry::make('summary_discount_total')
                    ->label(__('purchase_quotation.fields.discount_total'))
                    ->state(fn (Get $get): string => $money($get, $totals($get)['discount_total'], true))
                    ->color('danger')
                    ->weight(FontWeight::SemiBold)
                    ->inlineLabel()
                    ->alignEnd()
                    ->columnSpan(FieldGrid::FULL),
                TextEntry::make('summary_total')
                    ->label(__('purchase_quotation.fields.grand_total'))
                    ->state(fn (Get $get): string => $money($get, $totals($get)['total']))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->inlineLabel()
                    ->alignEnd()
                    ->extraEntryWrapperAttributes(['class' => 'kcp-sum-total'])
                    ->columnSpan(FieldGrid::FULL),
                TextEntry::make('summary_try')
                    ->label(__('purchase_quotation.fields.total_try'))
                    ->state(function (Get $get) use ($totals): string {
                        $rate = Money::parse($get('try_rate'));

                        return $rate !== null && $rate > 0 ? Money::format($totals($get)['total'] * $rate, 'TRY') : '-';
                    })
                    ->visible(fn (Get $get): bool => self::isForeign($get('currency_code')))
                    ->inlineLabel()
                    ->alignEnd()
                    ->columnSpan(FieldGrid::FULL),
            ]);
    }

    /**
     * "Toplu kalem ekle" (ekran goruntusu 3/4): Satin alinacaklar'daki acik
     * kalemlerden secim ve / veya yapistirilan satirlar listeye eklenir;
     * teklifte zaten olan kalem tekrar eklenmez, bos "Yeni kalem" satirlari
     * kaldirilir.
     */
    private static function bulkAddAction(): Action
    {
        return Action::make('bulk_add_lines')
            ->label(__('procurement.lines.bulk_add'))
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->color(ActionColors::CREATE)
            ->modalHeading(__('procurement.lines.bulk_add'))
            ->modalDescription(__('purchase_quotation.help.bulk_add'))
            ->modalIcon(Heroicon::OutlinedSquaresPlus)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('procurement.lines.add'))
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components([
                    CheckboxList::make('need_ids')
                        ->label(__('purchase_quotation.bulk.needs'))
                        ->options(fn (): array => TwoLineOption::options(app(PurchaseQuotationQueries::class)->openNeedRows()))
                        ->allowHtml()
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->columnSpan(FieldGrid::FULL),
                    Textarea::make('paste')
                        ->label(__('purchase_quotation.bulk.paste'))
                        ->helperText(__('purchase_quotation.help.paste'))
                        ->rows(5)
                        ->maxLength(20000)
                        ->columnSpan(FieldGrid::MODAL_LONG),
                ]))
            ->action(function (array $data, Get $get, Set $set): void {
                $lines = array_filter((array) ($get('lines') ?? []), static fn (mixed $line): bool => is_array($line) && ! self::isEmptyLine($line));
                $present = array_map('intval', array_filter(array_column($lines, 'purchase_need_id')));
                $ids = array_values(array_diff(array_map('intval', (array) ($data['need_ids'] ?? [])), $present));
                $new = [
                    ...app(PurchaseQuotationQueries::class)->linesFromNeeds($ids),
                    ...self::pastedLines((string) ($data['paste'] ?? '')),
                ];

                if ($new === []) {
                    Notification::make()->title(__('purchase_quotation.messages.bulk_none'))->warning()->send();

                    return;
                }

                foreach ($new as $line) {
                    $lines[(string) Str::uuid()] = self::lineState($line);
                }

                $set('lines', $lines);

                Notification::make()
                    ->title(__('purchase_quotation.messages.bulk_added', ['count' => count($new)]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Yapistirilan satirlar: "Aciklama; miktar; birim; birim fiyat" ya da
     * sekmeyle ayrilmis Excel sutunlari.
     *
     * @return list<array<string, mixed>>
     */
    private static function pastedLines(string $text): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $row) {
            $row = trim($row);

            if ($row === '') {
                continue;
            }

            $parts = array_map('trim', str_contains($row, "\t") ? explode("\t", $row) : explode(';', $row));
            $description = Str::limit((string) ($parts[0] ?? ''), 495, '');

            if ($description === '') {
                continue;
            }

            $quantity = Money::parse($parts[1] ?? null);
            $lines[] = [
                'description' => $description,
                'quantity' => $quantity !== null && $quantity > 0 ? $quantity : 1.0,
                'unit' => filled($parts[2] ?? null) ? Str::limit((string) $parts[2], 40, '') : null,
                'unit_price' => max(0.0, Money::parse($parts[3] ?? null) ?? 0.0),
            ];
        }

        return $lines;
    }

    /**
     * Repeater'a dogrudan yazilan kalem durumu: her alan dolu (alt alanlarin
     * varsayilanlari calismaz), tutarlar alanin metin bicimiyle.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function lineState(array $line): array
    {
        $quantity = (float) ($line['quantity'] ?? 1);
        $unitPrice = (float) ($line['unit_price'] ?? 0);
        $units = app(UnitResolver::class);

        return [
            'id' => null,
            'ai_created_item' => false,
            'purchase_need_id' => $line['purchase_need_id'] ?? null,
            'catalog_item_id' => $line['catalog_item_id'] ?? null,
            'description' => $line['description'] ?? null,
            'brand_model' => $line['brand_model'] ?? null,
            'quantity' => $quantity,
            'unit' => $line['unit'] ?? null,
            // D-194 (B54): kalemin birimi > satin alinacak kalemin birimi > yapistirilan metin.
            ...(UnitSelect::enabled() ? [
                'uom_id' => $units->itemUnitId($line['catalog_item_id'] ?? null) ?? ($line['uom_id'] ?? null) ?? $units->resolve($line['unit'] ?? null),
            ] : []),
            'unit_price' => Money::fixed($unitPrice, 4),
            'discount_rate' => 0,
            'line_total' => Money::fixed(PurchaseQuotationLine::computeTotal($quantity, $unitPrice, 0.0), 2),
            'is_option' => false,
            'is_cancelled' => false,
            'note' => null,
        ];
    }

    /**
     * Hic doldurulmamis "Yeni kalem" satiri.
     *
     * @param  array<string, mixed>  $line
     */
    private static function isEmptyLine(array $line): bool
    {
        return blank($line['id'] ?? null)
            && blank($line['purchase_need_id'] ?? null)
            && blank($line['catalog_item_id'] ?? null)
            && blank($line['description'] ?? null);
    }

    private static function isForeign(mixed $currency): bool
    {
        $code = strtoupper((string) FormState::value($currency));

        return $code !== '' && $code !== 'TRY';
    }
}
