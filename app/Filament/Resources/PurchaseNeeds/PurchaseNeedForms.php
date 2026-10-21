<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseNeeds;

use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\UsageArea;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\UnitSelect;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Reference\ReferenceOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Siparis kalemi pencerelerinin alanlari (D-189): departman ihtiyac talebi /
 * satin almanin elle actigi kalem ve tedarikci teklifi. Alanlar kompakttir
 * (D-157, FieldGrid::modal) ve tarih girdisi tek tiptir (DatePicker).
 */
final class PurchaseNeedForms
{
    /** Eylem modalinda kisa alan (MODAL_COLUMNS'un 1/4'u): birim, miktar. */
    public const MODAL_SHORT = ['default' => 1, 'md' => 1];

    /**
     * "Satın alma talebi" (sat-03, D-190: genel taleplerden ayri; her aktif
     * personel) ve "Kalem ekle" (satin alma). Talepte kullanim yeri (Sirket
     * ici / Saha; Saha'da proje), urun / hizmet (katalogda varsa ayni kalem,
     * yoksa adi), istenen marka / model ve ozellikler, gerekce ve "var olan
     * neden kullanilamiyor" vardir.
     *
     * @return array<int, mixed>
     */
    public static function needFields(bool $manual): array
    {
        $user = auth()->user();
        $ownUnit = $user instanceof Personnel ? $user->getAttribute('org_unit_id') : null;

        $fields = [
            ToggleButtons::make('usage_area')
                ->label(__('purchasing.fields.usage_area'))
                ->helperText(__('purchasing.help.usage_area'))
                ->options(UsageArea::class)
                ->default(UsageArea::Office->value)
                ->inline()
                ->required()
                ->live(),
            // D-230: proje her iki kullanim yerinde secilebilir, Saha'da zorunlu (servis de denetler).
            Select::make('project_id')
                ->label(__('purchasing.fields.project'))
                ->helperText(__('purchasing.help.project'))
                ->options(fn (): array => app(ProjectCatalogQueries::class)->projectOptions())
                ->searchable()
                ->required(fn (Get $get): bool => FormState::value($get('usage_area')) === UsageArea::Field->value)
                ->native(false),
            Select::make('catalog_item_id')
                ->label(__('purchasing.fields.catalog_item'))
                ->helperText(__('purchasing.help.catalog_item'))
                // D-192: iki satirli secenek (ad / silik kategori yolu); arama duz metinde.
                ->options(fn (Get $get): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml('', UsageArea::tryFrom((string) FormState::value($get('usage_area')))))
                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml($search, UsageArea::tryFrom((string) FormState::value($get('usage_area')))))
                ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->itemLabelHtml($value))
                ->allowHtml()
                ->searchable()
                ->live()
                // D-194 (B54): urunun birimi gelir ve kilitlenir.
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    if (UnitSelect::enabled()) {
                        UnitSelect::fillFromItem($state, $get, $set);
                    }
                })
                ->native(false),
            TextInput::make('requested_item_name')
                ->label(__('purchasing.fields.requested_item_name'))
                ->helperText(__('purchasing.help.requested_item_name'))
                ->maxLength(300)
                ->required(fn (Get $get): bool => blank($get('catalog_item_id')))
                ->visible(fn (Get $get): bool => blank($get('catalog_item_id'))),
            TextInput::make('brand_model')
                ->label(__('purchasing.fields.requested_brand_model'))
                ->maxLength(200),
            TextInput::make('quantity')
                ->label(__('purchasing.fields.quantity'))
                ->numeric()
                ->minValue(0.0001)
                ->default(1)
                ->required(),
            // D-194 (B54): sabit birim listesi; urun seciliyse onun birimi (kilitli).
            UnitSelect::enabled()
                ? UnitSelect::forItem(__('purchasing.fields.unit'), 'catalog_item_id')->columnSpan(self::MODAL_SHORT)
                : TextInput::make('unit')
                    ->label(__('purchasing.fields.unit'))
                    ->maxLength(40)
                    // D-192: "birim alaninin oyle buyuk olmasi cok sacma" — kisa alan.
                    ->columnSpan(self::MODAL_SHORT),
            DatePicker::make('needed_by')
                ->label(__('purchasing.fields.needed_by'))
                ->displayFormat('d.m.Y'),
            Toggle::make('is_urgent')
                ->label(__('purchasing.fields.is_urgent'))
                ->inline(false),
            // D-230: departman degistirilemez (kisinin departmani; gonderilmez, servis yazar).
            Select::make('org_unit_id')
                ->label(__('purchasing.fields.org_unit'))
                ->helperText(__('purchasing.help.org_unit'))
                ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                ->default($ownUnit)
                ->disabled()
                ->selectablePlaceholder(false)
                ->native(false),
            Textarea::make('specification')
                ->label(__('purchasing.fields.specification'))
                ->helperText(__('purchasing.help.specification'))
                ->rows(3)
                ->maxLength(4000),
            Textarea::make('justification')
                ->label(__('purchasing.fields.justification'))
                ->helperText(__('purchasing.help.justification'))
                ->rows(3)
                ->maxLength(4000)
                ->required(! $manual),
            Textarea::make('existing_check')
                ->label(__('purchasing.fields.existing_check'))
                ->helperText(__('purchasing.help.existing_check'))
                ->rows(3)
                ->maxLength(4000)
                ->required(! $manual),
            Textarea::make('note')
                ->label(__('purchasing.fields.note'))
                ->rows(3)
                ->maxLength(4000),
        ];

        return FieldGrid::modal($fields);
    }

    /**
     * "Katalogla eşleştir": katalogda olmayan urunle acilmis talep var olan
     * kaleme baglanir ya da kataloga bir kez yeni kalem olarak eklenir.
     *
     * @return array<int, mixed>
     */
    public static function linkCatalogFields(PurchaseNeed $need): array
    {
        $usage = $need->usageEnum();

        return FieldGrid::modal([
            Select::make('catalog_item_id')
                ->label(__('purchasing.fields.catalog_link'))
                ->helperText(__('purchasing.help.link_catalog'))
                ->options(fn (): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml((string) $need->getAttribute('requested_item_name'), $usage))
                ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml($search, $usage))
                ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->itemLabelHtml($value))
                ->allowHtml()
                ->searchable()
                ->live()
                ->required(fn (Get $get): bool => ! (bool) $get('as_new'))
                ->visible(fn (Get $get): bool => ! (bool) $get('as_new'))
                ->native(false),
            Toggle::make('as_new')
                ->label(__('cost_items.match_form.as_new'))
                ->live()
                ->inline(false),
            ToggleButtons::make('new_kind')
                ->label(__('purchasing.fields.new_kind'))
                ->options([
                    CatalogItemKind::Product->value => CatalogItemKind::Product->getLabel(),
                    CatalogItemKind::Service->value => CatalogItemKind::Service->getLabel(),
                    CatalogItemKind::GeneralExpense->value => CatalogItemKind::GeneralExpense->getLabel(),
                ])
                ->default(CatalogItemKind::Product->value)
                ->inline()
                ->visible(fn (Get $get): bool => (bool) $get('as_new')),
        ]);
    }

    /**
     * Tedarikci teklifi ("Teklif ekle" / "Teklifi düzenle"). Toplam servis
     * tarafinda miktar x birim fiyattir; dovizde TL kuru zorunludur.
     *
     * @return array<int, mixed>
     */
    public static function quoteFields(PurchaseNeed $need): array
    {
        return FieldGrid::modal([
            Select::make('supplier_party_id')
                ->label(__('purchasing.fields.supplier_party'))
                ->options(fn (): array => app(PurchasingQueries::class)->supplierOptions())
                ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->supplierOptions($search))
                ->getOptionLabelUsing(fn (mixed $value): ?string => app(PurchasingQueries::class)->supplierLabel($value))
                ->searchable()
                ->live()
                ->native(false),
            TextInput::make('supplier_name')
                ->label(__('purchasing.fields.supplier_name'))
                ->helperText(__('purchasing.help.supplier_name'))
                ->maxLength(200)
                ->required(fn (Get $get): bool => blank($get('supplier_party_id')))
                ->visible(fn (Get $get): bool => blank($get('supplier_party_id'))),
            TextInput::make('brand_model')
                ->label(__('purchasing.fields.brand_model'))
                ->maxLength(200),
            TextInput::make('quantity')
                ->label(__('purchasing.fields.quantity'))
                ->numeric()
                ->minValue(0.0001)
                ->default((float) $need->getAttribute('quantity'))
                ->required(),
            MoneyInput::make('unit_price')
                ->label(__('purchasing.fields.unit_price'))
                ->decimals(4)
                ->required(),
            Select::make('currency_code')
                ->label(__('purchasing.fields.currency'))
                ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                ->default('TRY')
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set, mixed $state) => $set('try_rate', app(PurchasingQueries::class)->lastTryRate(FormState::value($state))))
                ->native(false),
            TextInput::make('try_rate')
                ->label(__('purchasing.fields.try_rate'))
                ->helperText(__('purchasing.help.try_rate'))
                ->numeric()
                ->minValue(0.000001)
                ->visible(fn (Get $get): bool => self::isForeign($get('currency_code')))
                ->required(fn (Get $get): bool => self::isForeign($get('currency_code'))),
            TextInput::make('lead_time_days')
                ->label(__('purchasing.fields.lead_time_days'))
                ->integer()
                ->minValue(0)
                ->suffix(__('purchasing.values.days')),
            DatePicker::make('delivery_on')
                ->label(__('purchasing.fields.delivery_on'))
                ->displayFormat('d.m.Y'),
            DatePicker::make('valid_until')
                ->label(__('purchasing.fields.valid_until'))
                ->displayFormat('d.m.Y'),
            TextInput::make('payment_terms')
                ->label(__('purchasing.fields.payment_terms'))
                ->maxLength(255),
            Textarea::make('note')
                ->label(__('purchasing.fields.quote_note'))
                ->rows(3)
                ->maxLength(4000),
        ]);
    }

    /**
     * Teklif duzenleme penceresinin ilk degerleri.
     *
     * @return array<string, mixed>
     */
    public static function quoteData(PurchaseQuote $quote): array
    {
        return [
            'supplier_party_id' => $quote->getAttribute('supplier_party_id'),
            'supplier_name' => $quote->getAttribute('supplier_name'),
            'brand_model' => $quote->getAttribute('brand_model'),
            'quantity' => (float) $quote->getAttribute('quantity'),
            'unit_price' => (float) $quote->getAttribute('unit_price'),
            'currency_code' => $quote->getAttribute('currency_code'),
            'try_rate' => $quote->getAttribute('try_rate') !== null ? (float) $quote->getAttribute('try_rate') : null,
            'lead_time_days' => $quote->getAttribute('lead_time_days'),
            'delivery_on' => $quote->getAttribute('delivery_on')?->toDateString(),
            'valid_until' => $quote->getAttribute('valid_until')?->toDateString(),
            'payment_terms' => $quote->getAttribute('payment_terms'),
            'note' => $quote->getAttribute('note'),
        ];
    }

    private static function isForeign(mixed $currency): bool
    {
        $code = strtoupper((string) FormState::value($currency));

        return $code !== '' && $code !== 'TRY';
    }
}
