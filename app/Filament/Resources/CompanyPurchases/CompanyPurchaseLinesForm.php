<?php

declare(strict_types=1);

namespace App\Filament\Resources\CompanyPurchases;

use App\Enums\Procurement\UsageArea;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\ActionColors;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\PurchaseLines;
use App\Filament\Support\TwoLineOption;
use App\Filament\Support\TwoLineSelect;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Procurement\CompanyPurchaseQueries;
use App\Query\Procurement\PurchaseQuotationQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Reference\ReferenceOptions;
use App\Support\DisplayTime;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Kalemli sirket ici alim (D-192, B53; kullanici: "yine sirket ici alimlar
 * kismi da ayni tarz bir arayuz"). Baslik: satin alma talebi (Sirket ici
 * talepler; secilince birim, kisi ve kalemler talepten dolar), birim, kisi
 * (iki satirli), tedarikci, alim tarihi, para birimi, not. Kalemler satin
 * alma teklifiyle ayni liste (PurchaseLines): iki satirli katalog kalemi,
 * talep kalemi, aciklama, miktar, birim fiyat, yenileme donemi, sonraki
 * yenileme, bitis. Kayit CompanyPurchaseService::createLines (her kalem ayri
 * alim satiri). B53 oncesi tek kayitli B52A formu calisir.
 */
final class CompanyPurchaseLinesForm
{
    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            Section::make(__('company_purchase.sections.purchase'))
                ->icon(Heroicon::OutlinedComputerDesktop)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('purchase_request_id')
                        ->label(__('company_purchase.fields.request'))
                        ->helperText(__('company_purchase.help.request_lines'))
                        ->options(fn (): array => app(CompanyPurchaseQueries::class)->officeRequestOptions())
                        ->getSearchResultsUsing(fn (string $search): array => app(CompanyPurchaseQueries::class)->officeRequestOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => filled($value) ? (app(CompanyPurchaseQueries::class)->officeRequestOptions('', (int) $value)[(int) $value] ?? null) : null)
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $prefill = app(CompanyPurchaseQueries::class)->requestPrefill((int) FormState::value($state));

                            foreach ($prefill['header'] as $field => $value) {
                                if ($value !== null) {
                                    $set($field, $value);
                                }
                            }

                            $lines = [];

                            foreach ($prefill['lines'] as $line) {
                                $lines[(string) Str::uuid()] = self::lineState($line);
                            }

                            $set('lines', $lines !== [] ? $lines : [(string) Str::uuid() => self::lineState([])]);
                        })
                        ->native(false)
                        ->columnSpan(FieldGrid::WIDE),
                    Select::make('org_unit_id')
                        ->label(__('company_purchase.fields.org_unit'))
                        ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('personnel_id')
                        ->label(__('company_purchase.fields.personnel'))
                        ->tap(TwoLineSelect::rows(
                            fn (): array => app(PersonnelQueries::class)->personnelRows(true),
                            fn (array $ids): array => app(PersonnelQueries::class)->personnelRowsFor($ids),
                        ))
                        ->searchable()
                        ->native(false),
                    Select::make('supplier_party_id')
                        ->label(__('company_purchase.fields.supplier_party'))
                        ->options(fn (): array => app(PurchasingQueries::class)->supplierOptions())
                        ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->supplierOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->supplierLabel($value))
                        ->searchable()
                        ->live()
                        ->native(false)
                        ->columnStart(1),
                    TextInput::make('supplier_name')
                        ->label(__('company_purchase.fields.supplier_name'))
                        ->helperText(__('company_purchase.help.supplier_name'))
                        ->maxLength(200)
                        ->visible(fn (Get $get): bool => blank($get('supplier_party_id'))),
                    DatePicker::make('purchased_on')
                        ->label(__('company_purchase.fields.purchased_on'))
                        ->displayFormat('d.m.Y')
                        ->default(fn (): string => Carbon::now(DisplayTime::zone())->toDateString())
                        ->required(),
                    Select::make('currency_code')
                        ->label(__('company_purchase.fields.currency'))
                        ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                        ->default('TRY')
                        ->required()
                        ->live()
                        ->native(false),
                    Textarea::make('note')
                        ->label(__('company_purchase.fields.note'))
                        ->rows(2)
                        ->maxLength(4000)
                        ->columnStart(1),
                ])),
            Section::make(__('company_purchase.sections.lines'))
                ->icon(Heroicon::OutlinedListBullet)
                ->headerActions([self::bulkAddAction()])
                ->components([
                    PurchaseLines::configure(Repeater::make('lines'))
                        ->label(__('company_purchase.sections.lines'))
                        ->columns(FieldGrid::COLUMNS)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->itemLabel(fn (array $state, ?int $index, Get $get) => PurchaseLines::companyPurchaseHeader($state, $index, $get('currency_code')))
                        ->schema(self::lineFields())
                        ->columnSpan(FieldGrid::FULL),
                ]),
        ];
    }

    /**
     * Formdan servise: baslik ve kalemler.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    public static function split(array $data): array
    {
        $lines = array_values(array_filter((array) ($data['lines'] ?? []), 'is_array'));
        unset($data['lines']);

        return [$data, $lines];
    }

    /**
     * @return array<int, mixed>
     */
    private static function lineFields(): array
    {
        return FieldGrid::fields([
            Select::make('catalog_item_id')
                ->label(__('company_purchase.fields.catalog_item'))
                ->allowHtml()
                ->searchable()
                ->options(fn (): array => TwoLineOption::options(app(PurchaseQuotationQueries::class)->catalogRows('', UsageArea::Office)))
                ->getSearchResultsUsing(fn (string $search): array => TwoLineOption::options(app(PurchaseQuotationQueries::class)->catalogRows($search, UsageArea::Office)))
                ->getOptionLabelUsing(function (mixed $value): ?string {
                    $id = (int) FormState::value($value);
                    $row = $id > 0 ? (app(PurchaseQuotationQueries::class)->catalogRowsFor([$id])[$id] ?? null) : null;

                    return $row !== null ? TwoLineOption::html($row[0], $row[1]) : null;
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    $id = (int) FormState::value($state);

                    if ($id > 0 && blank($get('description'))) {
                        $set('description', app(PurchaseQuotationQueries::class)->catalogRowsFor([$id])[$id][0] ?? null);
                    }
                })
                ->native(false)
                ->columnSpan(FieldGrid::WIDE),
            Select::make('purchase_need_id')
                ->label(__('company_purchase.fields.request_line'))
                ->options(fn (Get $get): array => app(CompanyPurchaseQueries::class)->requestNeedOptions($get('../../purchase_request_id'), filled($get('purchase_need_id')) ? (int) $get('purchase_need_id') : null))
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => filled($get('../../purchase_request_id')))
                ->columnSpan(FieldGrid::WIDE),
            TextInput::make('description')
                ->label(__('company_purchase.fields.description'))
                ->maxLength(300)
                ->live(onBlur: true)
                ->columnStart(1)
                ->columnSpan(FieldGrid::WIDE),
            TextInput::make('quantity')
                ->label(__('company_purchase.fields.quantity'))
                ->numeric()
                ->minValue(0.0001)
                ->default(1)
                ->required()
                ->live(onBlur: true),
            MoneyInput::make('unit_price')
                ->label(__('company_purchase.fields.unit_price'))
                ->decimals(4)
                ->currencyField('../../currency_code')
                ->required()
                ->live(onBlur: true),
            TextInput::make('renewal_months')
                ->label(__('company_purchase.fields.renewal_months'))
                ->integer()
                ->minValue(1)
                ->maxValue(120)
                ->suffix(__('company_purchase.values.months'))
                ->live(onBlur: true)
                ->columnStart(1),
            DatePicker::make('next_renewal_on')
                ->label(__('company_purchase.fields.next_renewal_on'))
                ->displayFormat('d.m.Y'),
            DatePicker::make('ends_on')
                ->label(__('company_purchase.fields.ends_on'))
                ->displayFormat('d.m.Y'),
            TextInput::make('note')
                ->label(__('company_purchase.fields.note'))
                ->maxLength(1000)
                ->columnSpan(FieldGrid::WIDE),
        ]);
    }

    /**
     * "Toplu kalem ekle": secilen talebin henuz listede olmayan kalemleri
     * topluca eklenir; bos "Yeni kalem" satirlari kaldirilir.
     */
    private static function bulkAddAction(): Action
    {
        return Action::make('bulk_add_company_lines')
            ->label(__('procurement.lines.bulk_add'))
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->color(ActionColors::CREATE)
            ->modalHeading(__('procurement.lines.bulk_add'))
            ->modalDescription(__('company_purchase.help.bulk_add'))
            ->modalIcon(Heroicon::OutlinedSquaresPlus)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('procurement.lines.add'))
            ->schema(fn (Schema $schema, Get $get): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components([
                    CheckboxList::make('need_ids')
                        ->label(__('company_purchase.fields.request_lines'))
                        ->options(fn (): array => app(CompanyPurchaseQueries::class)->requestNeedOptions($get('purchase_request_id')))
                        ->bulkToggleable()
                        ->columns(2)
                        ->required()
                        ->columnSpan(FieldGrid::FULL),
                ]))
            ->action(function (array $data, Get $get, Set $set): void {
                $requestId = (int) FormState::value($get('purchase_request_id'));
                $lines = array_filter((array) ($get('lines') ?? []), static fn (mixed $line): bool => is_array($line) && ! self::isEmptyLine($line));
                $present = array_map('intval', array_filter(array_column($lines, 'purchase_need_id')));
                $ids = array_values(array_diff(array_map('intval', (array) ($data['need_ids'] ?? [])), $present));
                $new = $requestId > 0 && $ids !== [] ? app(CompanyPurchaseQueries::class)->requestPrefill($requestId, $ids)['lines'] : [];

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
     * Repeater'a dogrudan yazilan kalem: her alan dolu, tutar alanin metin bicimiyle.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function lineState(array $line): array
    {
        return [
            'catalog_item_id' => $line['catalog_item_id'] ?? null,
            'purchase_need_id' => $line['purchase_need_id'] ?? null,
            'description' => $line['description'] ?? null,
            'quantity' => (float) ($line['quantity'] ?? 1),
            'unit_price' => isset($line['unit_price']) ? Money::fixed($line['unit_price'], 4) : null,
            'renewal_months' => $line['renewal_months'] ?? null,
            'next_renewal_on' => null,
            'ends_on' => null,
            'note' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function isEmptyLine(array $line): bool
    {
        return blank($line['catalog_item_id'] ?? null)
            && blank($line['purchase_need_id'] ?? null)
            && blank($line['description'] ?? null);
    }
}
