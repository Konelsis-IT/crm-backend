<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRequests;

use App\Enums\Platform\Feature;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\UsageArea;
use App\Filament\Support\ActionColors;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FormState;
use App\Filament\Support\PurchaseLines;
use App\Filament\Support\UnitSelect;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseRequest;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Procurement\CatalogQueries;
use App\Query\Procurement\PurchasingQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Kalemli satin alma talebi formu (D-192, B53; kullanici: "Birden fazla urun
 * talebi olabilir. O yuzden kalemli bir yapi olmasi lazim. Urun talebinde
 * urunler eklenirken 3. 4. ekran goruntusundeki gibi bir arayuzle
 * eklenebilir").
 *
 * - Talep: kullanim yeri (Sirket ici / Saha; Saha'da proje), departman,
 *   termin, acil, gerekce (zorunlu), "var olan neden kullanilamiyor", not.
 * - Kalemler: PurchaseLines (Kalem ekle, Tumunu kucult / genislet, surukleyerek
 *   siralama, baslikta sira no + miktar + Listede / Listede yok rozetleri) ve
 *   "Toplu kalem ekle" (listeden coklu secim ya da "ad; miktar; birim"
 *   yapistirma). Urun / hizmet iki satirli secimdir (ad / silik kategori);
 *   listede yoksa "Listede yok" ile adi yazilir. Birim ve miktar kisa alandir.
 * - Duzenlemede satin almanin isledigi kalem kilitlidir (servis de yazmaz).
 *
 * Ayni form "Satin alma talebi ac" penceresinde (Talepler ve Satin
 * alinacaklar listesi) ve Satin alma talepleri olustur / duzenle sayfalarinda
 * kullanilir. Yazma PurchaseRequestService'tedir.
 */
final class PurchaseRequestForm
{
    /** @var array<int, string|null> Istek ici kalem adlari (kalem basligi). */
    private static array $itemNames = [];

    /** Kalemli talep acik mi (B53 + ozellik)? Degilse eski tek kalemli form kullanilir. */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B53') && FeatureFlags::enabled(Feature::PurchaseRequests);
    }

    /**
     * Formun bolumleri (sayfa ve pencere ayni).
     *
     * @return array<int, mixed>
     */
    public static function components(bool $editing = false): array
    {
        return [
            self::requestSection(),
            self::linesSection($editing),
            ...($editing ? [Hidden::make('row_version')] : []),
        ];
    }

    /**
     * Duzenleme sayfasinin ilk degerleri: baslik alanlari ve kalemler (kayitli
     * kalem kucultulmus gelir, islenmis kalem kilitli).
     *
     * @return array<string, mixed>
     */
    public static function data(PurchaseRequest $request): array
    {
        $lines = [];

        foreach ($request->needs()->get() as $need) {
            /** @var PurchaseNeed $need */
            if ($need->statusEnum() === PurchaseNeedStatus::Cancelled) {
                continue;
            }

            $locked = ! in_array($need->statusEnum(), [PurchaseNeedStatus::Open, PurchaseNeedStatus::AwaitingProject], true) || $need->isArchived();

            $lines['record-'.$need->getKey()] = [
                'id' => (int) $need->getKey(),
                'locked' => $locked,
                'catalog_item_id' => $need->getAttribute('catalog_item_id'),
                'not_in_list' => $need->getAttribute('catalog_item_id') === null,
                'requested_item_name' => $need->getAttribute('catalog_item_id') === null
                    ? ($need->getAttribute('requested_item_name') ?? $need->getAttribute('title'))
                    : $need->getAttribute('requested_item_name'),
                'brand_model' => $need->getAttribute('brand_model'),
                'specification' => $need->getAttribute('specification'),
                'quantity' => (float) $need->getAttribute('quantity'),
                'unit' => $need->getAttribute('unit'),
                // D-194 (B54): islenmemis kalemde urunun birimi (kilitli alan onu gosterir).
                ...(UnitSelect::enabled() ? [
                    'uom_id' => ($locked ? null : app(UnitResolver::class)->itemUnitId($need->getAttribute('catalog_item_id'))) ?? $need->getAttribute('uom_id'),
                ] : []),
                'note' => $need->getAttribute('note'),
            ];
        }

        $usage = $request->usageEnum();

        return [
            'usage_area' => $usage?->value ?? UsageArea::Office->value,
            'project_id' => $request->getAttribute('project_id'),
            'org_unit_id' => $request->getAttribute('org_unit_id'),
            'needed_by' => $request->getAttribute('needed_by')?->toDateString(),
            'is_urgent' => (bool) $request->getAttribute('is_urgent'),
            'justification' => $request->getAttribute('justification'),
            'existing_check' => $request->getAttribute('existing_check'),
            'note' => $request->getAttribute('note'),
            'row_version' => $request->getAttribute('row_version'),
            'lines' => $lines,
        ];
    }

    private static function requestSection(): Section
    {
        $user = auth()->user();
        $ownUnit = $user instanceof Personnel ? $user->getAttribute('org_unit_id') : null;

        return Section::make(__('purchase_request.sections.request'))
            ->icon(Heroicon::OutlinedHandRaised)
            ->columns(FieldGrid::COLUMNS)
            ->components(FieldGrid::fields([
                ToggleButtons::make('usage_area')
                    ->label(__('purchase_request.fields.usage_area'))
                    ->helperText(__('purchase_request.help.usage_area'))
                    ->options(UsageArea::class)
                    ->default(UsageArea::Office->value)
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('project_id')
                    ->label(__('purchase_request.fields.project'))
                    ->options(fn (): array => app(ProjectCatalogQueries::class)->projectOptions())
                    ->searchable()
                    ->visible(fn (Get $get): bool => FormState::value($get('usage_area')) === UsageArea::Field->value)
                    ->native(false),
                Select::make('org_unit_id')
                    ->label(__('purchase_request.fields.org_unit'))
                    ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                    ->default($ownUnit)
                    ->searchable()
                    ->native(false),
                DatePicker::make('needed_by')
                    ->label(__('purchase_request.fields.needed_by'))
                    ->displayFormat('d.m.Y'),
                Toggle::make('is_urgent')
                    ->label(__('purchase_request.fields.is_urgent'))
                    ->inline(false),
                Textarea::make('justification')
                    ->label(__('purchase_request.fields.justification'))
                    ->helperText(__('purchase_request.help.justification'))
                    ->rows(3)
                    ->maxLength(4000)
                    ->required()
                    ->columnStart(1),
                Textarea::make('existing_check')
                    ->label(__('purchase_request.fields.existing_check'))
                    ->helperText(__('purchase_request.help.existing_check'))
                    ->rows(3)
                    ->maxLength(4000),
                Textarea::make('note')
                    ->label(__('purchase_request.fields.note'))
                    ->rows(3)
                    ->maxLength(4000),
            ]));
    }

    private static function linesSection(bool $editing): Section
    {
        return Section::make(__('purchase_request.sections.lines'))
            ->icon(Heroicon::OutlinedListBullet)
            ->headerActions([self::bulkAddAction()])
            ->components([
                PurchaseLines::configure(Repeater::make('lines'))
                    ->label(__('purchase_request.sections.lines'))
                    ->columns(FieldGrid::COLUMNS)
                    ->defaultItems($editing ? 0 : 1)
                    ->minItems(1)
                    ->itemLabel(fn (array $state, ?int $index): HtmlString => self::lineHeader($state, $index))
                    ->deleteAction(static fn (Action $action): Action => $action
                        ->tooltip(__('procurement.lines.delete'))
                        // Islenmis kalem kaldirilamaz (servis de iptal etmez).
                        ->visible(static fn (array $arguments, Repeater $component): bool => blank($arguments['item'] ?? null)
                            || ! (bool) data_get($component->getRawItemState((string) $arguments['item']), 'locked')))
                    ->schema(self::lineFields())
                    ->columnSpan(FieldGrid::FULL),
            ]);
    }

    /**
     * Kalem alanlari (ekran goruntusu 5): iki satirli urun / hizmet ya da
     * "Listede yok" + ad; marka / model; kisa miktar ve birim; ozellikler, not.
     *
     * @return array<int, mixed>
     */
    private static function lineFields(): array
    {
        $locked = static fn (Get $get): bool => (bool) $get('locked');
        $usage = static fn (Get $get): ?UsageArea => UsageArea::tryFrom((string) FormState::value($get('../../usage_area')));

        return FieldGrid::fields([
            Hidden::make('id'),
            Hidden::make('locked')->dehydrated(false),
            Select::make('catalog_item_id')
                ->label(__('purchase_request.fields.catalog_item'))
                ->helperText(fn (Get $get): ?string => $locked($get) ? __('purchase_request.help.locked_line') : null)
                // D-192: iki satirli secenek (ad / silik kategori yolu); arama duz metinde.
                ->options(fn (Get $get): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml('', $usage($get)))
                ->getSearchResultsUsing(fn (string $search, Get $get): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml($search, $usage($get)))
                ->getOptionLabelUsing(fn (mixed $value): ?string => app(CatalogQueries::class)->itemLabelHtml($value))
                ->allowHtml()
                ->searchable()
                ->native(false)
                ->live()
                ->required(fn (Get $get): bool => ! (bool) $get('not_in_list'))
                ->visible(fn (Get $get): bool => ! (bool) $get('not_in_list'))
                ->disabled($locked)
                ->dehydrated()
                // D-194 (B54): urunun birimi gelir ve kilitlenir; oncesinde bos birim metni dolar.
                ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => UnitSelect::fillFromItem($state, $get, $set))
                ->columnSpan(FieldGrid::WIDE),
            TextInput::make('requested_item_name')
                ->label(__('purchase_request.fields.requested_item_name'))
                ->maxLength(300)
                ->required(fn (Get $get): bool => (bool) $get('not_in_list'))
                ->visible(fn (Get $get): bool => (bool) $get('not_in_list'))
                ->disabled($locked)
                ->dehydrated()
                ->live(onBlur: true)
                ->columnSpan(FieldGrid::WIDE),
            Toggle::make('not_in_list')
                ->label(__('purchase_request.fields.not_in_list'))
                ->inline(false)
                ->live()
                ->disabled($locked)
                ->dehydrated(false)
                ->afterStateUpdated(fn (mixed $state, Set $set) => $state ? $set('catalog_item_id', null) : $set('requested_item_name', null)),
            TextInput::make('brand_model')
                ->label(__('purchase_request.fields.brand_model'))
                ->maxLength(200)
                ->disabled($locked)
                ->dehydrated()
                ->columnSpan(FieldGrid::WIDE),
            TextInput::make('quantity')
                ->label(__('purchase_request.fields.quantity'))
                ->numeric()
                ->minValue(0.0001)
                ->default(1)
                ->required()
                ->live(onBlur: true)
                ->disabled($locked)
                ->dehydrated()
                ->columnStart(1),
            // D-194 (B54): sabit birim listesi; listedeki urunde urunun birimi (kilitli),
            // "Listede yok" kaleminde listeden secilir.
            UnitSelect::enabled()
                ? UnitSelect::forItem(__('purchase_request.fields.unit'), 'catalog_item_id', $locked)->live()
                : TextInput::make('unit')
                    ->label(__('purchase_request.fields.unit'))
                    ->maxLength(40)
                    ->live(onBlur: true)
                    ->disabled($locked)
                    ->dehydrated()
                    // "birim alaninin oyle buyuk olmasi cok sacma": kisa alan.
                    ->columnSpan(FieldGrid::SHORT),
            Textarea::make('specification')
                ->label(__('purchase_request.fields.specification'))
                ->placeholder(__('purchase_request.help.specification'))
                ->rows(2)
                ->maxLength(4000)
                ->disabled($locked)
                ->dehydrated(),
            Textarea::make('note')
                ->label(__('purchase_request.fields.line_note'))
                ->rows(2)
                ->maxLength(4000)
                ->disabled($locked)
                ->dehydrated(),
        ]);
    }

    /**
     * Kalem basligi (ekran goruntusu 5): sira no, ad (yoksa "Yeni kalem") ve
     * rozetler: miktar + birim, Listede / Listede yok, islenmis kalemde
     * "Islendi". Gorunum PurchaseLines ile aynidir (.kcp-*).
     *
     * @param  array<string, mixed>  $state
     */
    public static function lineHeader(array $state, ?int $index): HtmlString
    {
        $catalogId = FormState::value($state['catalog_item_id'] ?? null);
        $inList = filled($catalogId) && ! (bool) ($state['not_in_list'] ?? false);
        $title = $inList ? self::itemName((int) $catalogId) : trim((string) ($state['requested_item_name'] ?? ''));
        $title = filled($title) ? (string) $title : __('procurement.lines.new_item');

        $badges = [
            PurchaseLines::badge(PurchaseLines::quantityText(Money::parse($state['quantity'] ?? null) ?? 0, UnitSelect::stateText($state))),
            $inList
                ? PurchaseLines::badge(__('procurement.lines.badges.in_catalog'), PurchaseLines::TONE_GREEN)
                : PurchaseLines::badge(__('procurement.lines.badges.not_in_catalog'), PurchaseLines::TONE_AMBER),
        ];

        if ((bool) ($state['locked'] ?? false)) {
            $badges[] = PurchaseLines::badge(__('purchase_request.lines.processed'), PurchaseLines::TONE_BLUE);
        }

        $number = $index !== null ? '<span class="kcp-line-no">'.e((string) ($index + 1)).'</span>' : '';

        return new HtmlString(
            '<span class="kcp-line-head">'
            .'<span class="kcp-line-top">'.$number.'<span class="kcp-line-title">'.e($title).'</span></span>'
            .'<span class="kcp-badges">'.implode('', $badges).'</span>'
            .'</span>'
        );
    }

    /**
     * "Toplu kalem ekle" (ekran goruntusu 3): listeden birden fazla malzeme /
     * hizmet secilir ya da her satira "ad; miktar; birim" yapistirilir. Ayni
     * adla listede duran kalem eslesir; digerleri "Listede yok" gelir.
     */
    private static function bulkAddAction(): Action
    {
        return Action::make('bulk_add_request_lines')
            ->label(__('procurement.lines.bulk_add'))
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->color(ActionColors::CREATE)
            ->modalHeading(__('procurement.lines.bulk_add'))
            ->modalDescription(__('purchase_request.help.bulk_add'))
            ->modalIcon(Heroicon::OutlinedSquaresPlus)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('procurement.lines.add'))
            ->schema(fn (Schema $schema, Get $get): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components([
                    Select::make('catalog_item_ids')
                        ->label(__('purchase_request.bulk.items'))
                        ->multiple()
                        ->options(fn (): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml('', UsageArea::tryFrom((string) FormState::value($get('usage_area')))))
                        ->getSearchResultsUsing(fn (string $search): array => app(PurchasingQueries::class)->purchasableItemOptionsHtml($search, UsageArea::tryFrom((string) FormState::value($get('usage_area')))))
                        ->getOptionLabelsUsing(fn (mixed $values): array => app(CatalogQueries::class)->itemLabelsHtml((array) $values))
                        ->allowHtml()
                        ->searchable()
                        ->native(false)
                        ->columnSpan(FieldGrid::MODAL_LONG),
                    Textarea::make('paste')
                        ->label(__('purchase_request.bulk.paste'))
                        ->helperText(__('purchase_request.help.bulk_paste'))
                        ->rows(5)
                        ->maxLength(20000)
                        ->columnSpan(FieldGrid::MODAL_LONG),
                ]))
            ->action(function (array $data, Get $get, Set $set): void {
                $lines = array_filter((array) ($get('lines') ?? []), static fn (mixed $line): bool => is_array($line) && ! self::isEmptyLine($line));
                $new = [
                    ...self::linesFromItems((array) ($data['catalog_item_ids'] ?? [])),
                    ...self::pastedLines((string) ($data['paste'] ?? '')),
                ];

                if ($new === []) {
                    Notification::make()->title(__('purchase_request.messages.bulk_none'))->warning()->send();

                    return;
                }

                foreach ($new as $line) {
                    $lines[(string) Str::uuid()] = $line;
                }

                $set('lines', $lines);

                Notification::make()
                    ->title(__('purchase_request.messages.bulk_added', ['count' => count($new)]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    private static function linesFromItems(array $ids): array
    {
        $catalog = app(CatalogQueries::class);
        $lines = [];

        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $id) {
            $lines[] = self::lineState($id, null, 1.0, $catalog->itemUnit($id));
        }

        return $lines;
    }

    /**
     * Yapistirilan satirlar: "ad; miktar; birim" (noktali virgul, sekme ya da
     * dikey cizgiyle ayrilmis). Miktar yoksa 1.
     *
     * @return list<array<string, mixed>>
     */
    private static function pastedLines(string $text): array
    {
        $catalog = app(CatalogQueries::class);
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $row) {
            $parts = array_map('trim', preg_split('/[;\t|]/u', trim($row)) ?: []);
            $name = Str::limit((string) ($parts[0] ?? ''), 300, '');

            if ($name === '') {
                continue;
            }

            $quantity = Money::parse($parts[1] ?? null);
            $unit = filled($parts[2] ?? null) ? Str::limit((string) $parts[2], 40, '') : null;
            $itemId = $catalog->exactItemId($name);

            $lines[] = $itemId !== null
                ? self::lineState($itemId, null, $quantity, $unit ?? $catalog->itemUnit($itemId))
                : self::lineState(null, $name, $quantity, $unit);
        }

        return $lines;
    }

    /**
     * Toplu eklenen kalem. Birim (D-194, B54): urunun birimi, yoksa yapistirilan
     * metinden cozulen sabit birim (taninmazsa bos, kalemde secilir).
     *
     * @return array<string, mixed>
     */
    private static function lineState(?int $catalogItemId, ?string $name, ?float $quantity, ?string $unit): array
    {
        $units = app(UnitResolver::class);

        return [
            'id' => null,
            'locked' => false,
            'catalog_item_id' => $catalogItemId,
            'not_in_list' => $catalogItemId === null,
            'requested_item_name' => $catalogItemId === null ? $name : null,
            'brand_model' => null,
            'specification' => null,
            'quantity' => $quantity !== null && $quantity > 0 ? $quantity : 1,
            'unit' => $unit,
            ...(UnitSelect::enabled() ? ['uom_id' => $units->itemUnitId($catalogItemId) ?? $units->resolve($unit)] : []),
            'note' => null,
        ];
    }

    /** @param  array<string, mixed>  $line */
    private static function isEmptyLine(array $line): bool
    {
        return blank($line['id'] ?? null) && blank($line['catalog_item_id'] ?? null) && blank(trim((string) ($line['requested_item_name'] ?? '')));
    }

    private static function itemName(int $id): ?string
    {
        if (! array_key_exists($id, self::$itemNames)) {
            self::$itemNames[$id] = app(CatalogQueries::class)->itemRowsFor([$id])[$id][0] ?? null;
        }

        return self::$itemNames[$id];
    }
}
