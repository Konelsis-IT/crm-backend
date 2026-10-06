<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Services\Acquisition\ProposalVersionScopeService;
use App\Support\UploadLimits;
use BackedEnum;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Teklif adiminin proje kapsami (B43, D-155; 5 Ekim 2026 kullanici talimati ve
 * ekran goruntuleri): kapsam potansiyel isten teklife tasindi. Proje tipi
 * potansiyel iste secilir; teklif adiminda secili her tip icin kendi kapsam
 * bolumu acilir. Izgara (sol | sag):
 *
 * - TM: Toplam maliyet | Toplam satis; Maliyet/Fider.
 * - BESS: MWe | MWh; Maliyet/MWh | Toplam maliyet; Satis/MWh | Toplam satis.
 * - HES: Maliyet | Satis; Maliyet/Jenerator-Turbin.
 * - GES: MWp; Maliyet/MWp | Toplam maliyet; Satis/MWp | Toplam satis tutari.
 * - ENH/EIH: Km; Maliyet/Km | Toplam maliyet; Satis/Km | Toplam satis.
 * - RES: degismedi (Respark malzeme / insaat / montaj + toplam).
 *
 * Miktar ve birim fiyat girilince toplam kendiliginden yazilir (kullanici
 * degistirebilir). Marj kapsamdan hesaplanir (elle girilmez). Her tipin kapsam
 * listesi (Excel) yuklemesi korunur; yeni yukleme ayni belgenin yeni revizyonudur.
 */
final class ProposalScopeSchema
{
    /** Gecici yukleme dizini (DocumentService dosyayi buradan alir). */
    private const UPLOAD_DIRECTORY = 'document-uploads-tmp';

    /** @var list<string> Kapsam listesi icin kabul edilen dosya turleri. */
    private const SCOPE_FILE_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
        'text/plain',
    ];

    /** Miktar alani (birim fiyatla carpilip toplam olur) => birimi. */
    private const QUANTITIES = [
        'ges' => ['capacity_mwp' => 'MWp'],
        'bes' => ['energy_mwh' => 'MWh'],
        'enh_eih' => ['length_km' => 'km'],
    ];

    /**
     * Secili tiplerin kapsam bolumleri. $types formdan secili tip listesini
     * verir (sihirbazin potansiyel is adimi ya da teklif ekraninin gizli alani).
     *
     * @param  Closure(Get): list<string>  $types
     * @param  Closure(Get): bool|null  $visible
     * @return list<Component>
     */
    public function sections(Closure $types, ?Closure $visible = null): array
    {
        $visible ??= static fn (): bool => true;
        $sections = [];

        foreach (ProjectScopeType::cases() as $type) {
            $sections[] = Section::make(__('business_case_scope.sections.'.$type->value))
                ->key('proposal-scope-'.$type->value)
                ->icon($type->getIcon())
                ->iconColor($type->getColor())
                ->compact()
                ->columns(['default' => 1, 'md' => 2])
                ->visible(fn (Get $get): bool => $visible($get) && in_array($type->value, $types($get), true))
                ->components([
                    ...$this->typeFields($type),
                    ...$this->fileFields($type),
                ]);
        }

        return $sections;
    }

    /** Kapsamdan hesaplanan marj (teklif adiminda, kaydedilmez). */
    public function marginEntry(Closure $types): TextEntry
    {
        return TextEntry::make('scope_margin_pct')
            ->label(__('proposal_version.fields.margin_pct'))
            ->state(function (Get $get) use ($types): string {
                $margin = ProposalVersionScopeService::margin(ProposalVersionScopeService::selectedRows($types($get), (array) $get('scopes')));

                return $margin === null ? __('proposal_version.help.margin_from_scope_empty') : '% '.Number::format($margin, precision: 2, locale: 'tr');
            })
            ->helperText(__('proposal_version.help.margin_from_scope'))
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->iconColor('success')
            ->weight(FontWeight::SemiBold)
            ->dehydrated(false);
    }

    /** Kapsam toplam satisi (toplam fiyat bossa yazilacak deger). */
    public function totalSalesEntry(Closure $types): TextEntry
    {
        return TextEntry::make('scope_total_sales')
            ->label(__('proposal_version.fields.scope_total_sales'))
            ->state(function (Get $get) use ($types): string {
                $sum = ProposalVersionScopeService::totalSales(ProposalVersionScopeService::selectedRows($types($get), (array) $get('scopes')));

                return self::money($sum, $get('currency_code'));
            })
            ->icon(Heroicon::OutlinedCalculator)
            ->iconColor('success')
            ->weight(FontWeight::SemiBold)
            ->dehydrated(false);
    }

    /**
     * Duzenleme formu icin surumun kapsamlari: scopes.{tip}.{alan} => deger.
     *
     * @return array<string, array<string, string|null>>
     */
    public static function formData(?ProposalVersion $version): array
    {
        $rows = [];

        foreach ($version?->scopes ?? [] as $scope) {
            /** @var ProposalVersionScope $scope */
            $type = self::typeValue($scope);
            $row = [];

            foreach ([...ProposalVersionScopeService::TYPE_FIELDS[$type] ?? [], 'note'] as $field) {
                $raw = $scope->getAttribute($field);
                $row[$field] = $raw === null ? null : (string) $raw;
            }

            $rows[$type] = $row;
        }

        return $rows;
    }

    /**
     * Ozet kartindaki satir: secili tipin dolu alanlari ve kapsam listesi.
     * $read: Get ya da "yol => deger" okuyucusu.
     */
    public static function summaryLine(ProjectScopeType $type, callable $read): string
    {
        $path = 'scopes.'.$type->value.'.';
        $currency = $read('currency_code');
        $parts = [];

        foreach (ProposalVersionScopeService::TYPE_FIELDS[$type->value] ?? [] as $field) {
            $value = self::number($read($path.$field));

            if ($value === null) {
                continue;
            }

            $unit = self::QUANTITIES[$type->value][$field] ?? ($field === 'power_mwe' ? 'MWe' : null);
            $parts[] = __('business_case_scope.labels.'.$type->value.'.'.$field).': '.($unit !== null
                ? Number::format($value, maxPrecision: 3, locale: 'tr').' '.$unit
                : self::money($value, $currency));
        }

        $parts[] = __('business_case_scope.fields.scope_file').': '.self::fileName($read($path.'scope_file'), $read($path.'scope_file_name'));

        return implode(' · ', $parts);
    }

    /**
     * Teklif sayfasindaki (ve surum penceresindeki) kapsam karti (D-158, 5 Ekim
     * 2026 kullanici: "ekran goruntusundeki tasarim berbat"; eski kart her tipi
     * tek kirmizi satirda yaziyordu). Ustte toplamlar (marj, toplam maliyet,
     * toplam satis); altta her tip kendi cercevesinde, degerler etiketli kucuk
     * kutucuklarda, kapsam listesi indirilebilir baglanti.
     *
     * $prefix: ayni sayfada iki kart olursa alan adlari karismasin (pencere).
     */
    public function recordCard(?ProposalVersion $version, string $prefix = 'proposal_scope'): ?Component
    {
        if ($version === null || $version->scopes->isEmpty()) {
            return null;
        }

        $currency = $version->currency_code;
        $margin = ProposalVersionScopeService::margin($version->scopes);
        $cost = null;

        foreach ($version->scopes as $scope) {
            $value = self::number($scope->getAttribute('total_cost'));
            $cost = $value === null ? $cost : ($cost ?? 0.0) + $value;
        }

        $blocks = [];

        foreach ($version->scopes as $scope) {
            /** @var ProposalVersionScope $scope */
            $type = $scope->scope_type;
            $typeValue = self::typeValue($scope);
            $entries = [];

            foreach (ProposalVersionScopeService::TYPE_FIELDS[$typeValue] ?? [] as $field) {
                $value = self::number($scope->getAttribute($field));
                $unit = self::QUANTITIES[$typeValue][$field] ?? ($field === 'power_mwe' ? 'MWe' : null);
                $total = in_array($field, ['total_cost', 'total_sales'], true);

                $entries[] = TextEntry::make($prefix.'_'.$typeValue.'_'.$field)
                    ->label(__('business_case_scope.labels.'.$typeValue.'.'.$field))
                    ->state($value === null ? '-' : ($unit !== null ? Number::format($value, maxPrecision: 3, locale: 'tr').' '.$unit : self::money($value, $currency)))
                    ->weight($total ? FontWeight::Bold : FontWeight::Medium)
                    ->color($total ? 'success' : null);
            }

            $info = DocumentLine::info($scope->scopeDocument, $scope->scopeDocumentRevision);

            if ($info !== null) {
                $entries[] = TextEntry::make($prefix.'_'.$typeValue.'_file')
                    ->label(__('business_case_scope.fields.scope_document'))
                    ->state(DocumentLine::text($info))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->iconColor('primary')
                    ->color('primary')
                    ->url($info['url'] ?? null)
                    ->openUrlInNewTab()
                    ->columnSpan(['default' => 2, 'md' => 3, 'xl' => 2]);
            }

            // Her tip kendi simgesi ve rengiyle kucuk bolum (D-163; Fieldset simge almaz).
            $blocks[] = Section::make($type instanceof ProjectScopeType ? (string) $type->getLabel() : $typeValue)
                ->key($prefix.'-'.$typeValue)
                ->icon($type instanceof ProjectScopeType ? $type->getIcon() : Heroicon::OutlinedCube)
                ->iconColor($type instanceof ProjectScopeType ? $type->getColor() : 'gray')
                ->compact()
                ->secondary()
                ->columns(['default' => 2, 'md' => 3, 'xl' => 6])
                ->components($entries);
        }

        return Section::make(__('proposal.sections.scope'))
            ->key($prefix.'-card')
            ->icon(Heroicon::OutlinedCube)
            ->compact()
            ->components([
                Grid::make(['default' => 1, 'sm' => 3])->components([
                    TextEntry::make($prefix.'_margin')
                        ->label(__('proposal_version.fields.margin_pct'))
                        ->state($margin === null ? '-' : '% '.Number::format($margin, precision: 2, locale: 'tr'))
                        ->icon(Heroicon::OutlinedReceiptPercent)
                        ->iconColor('success')
                        ->badge()
                        ->color('success'),
                    TextEntry::make($prefix.'_total_cost')
                        ->label(__('proposal_version.fields.scope_total_cost'))
                        ->state(self::money($cost, $currency))
                        ->icon(Heroicon::OutlinedArrowTrendingDown)
                        ->iconColor('gray')
                        ->weight(FontWeight::SemiBold),
                    TextEntry::make($prefix.'_total_sales')
                        ->label(__('proposal_version.fields.scope_total_sales'))
                        ->state(self::money(ProposalVersionScopeService::totalSales($version->scopes), $currency))
                        ->icon(Heroicon::OutlinedArrowTrendingUp)
                        ->iconColor('success')
                        ->weight(FontWeight::SemiBold),
                ]),
                ...$blocks,
            ]);
    }

    /**
     * Tipin alanlari, izgara sirasiyla (sol | sag).
     *
     * @return list<Component>
     */
    private function typeFields(ProjectScopeType $type): array
    {
        $field = fn (string $name, string $step = '0.01'): TextInput => $this->amountInput($type, $name, $step);

        return match ($type) {
            ProjectScopeType::Tm => [
                $field('total_cost'),
                $field('total_sales'),
                $field('unit_cost'),
            ],
            ProjectScopeType::Bes => [
                $field('power_mwe', '0.001'),
                $this->quantityInput($type, 'energy_mwh'),
                $this->unitInput($type, 'unit_cost', 'total_cost'),
                $field('total_cost'),
                $this->unitInput($type, 'unit_sales', 'total_sales'),
                $field('total_sales'),
            ],
            ProjectScopeType::Hes => [
                $field('total_cost'),
                $field('total_sales'),
                $field('unit_cost'),
            ],
            ProjectScopeType::Ges => [
                $this->quantityInput($type, 'capacity_mwp'),
                $this->unitInput($type, 'unit_cost', 'total_cost')->columnStart(1),
                $field('total_cost'),
                $this->unitInput($type, 'unit_sales', 'total_sales'),
                $field('total_sales'),
            ],
            ProjectScopeType::EnhEih => [
                $this->quantityInput($type, 'length_km'),
                $this->unitInput($type, 'unit_cost', 'total_cost')->columnStart(1),
                $field('total_cost'),
                $this->unitInput($type, 'unit_sales', 'total_sales'),
                $field('total_sales'),
            ],
            ProjectScopeType::Res => [
                $field('res_material_amount')->live(onBlur: true),
                $field('res_construction_amount')->live(onBlur: true),
                $field('res_assembly_amount')->live(onBlur: true),
                TextEntry::make('scopes.res.total')
                    ->label(__('business_case_scope.fields.total'))
                    ->state(fn (Get $get): string => self::money(self::resTotal($get), $get('currency_code')))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->iconColor('success')
                    ->weight(FontWeight::SemiBold)
                    ->dehydrated(false),
            ],
        };
    }

    /**
     * Kayitli kapsam listesi ve yeni yukleme (yeni revizyon olur).
     *
     * @return list<Component>
     */
    private function fileFields(ProjectScopeType $type): array
    {
        $path = 'scopes.'.$type->value;

        return [
            TextEntry::make($path.'.current_file')
                ->label(__('business_case_scope.fields.current_file'))
                ->state(fn (?Model $record): string => DocumentLine::text(self::recordFileInfo($record, $type)))
                ->url(fn (?Model $record): ?string => self::recordFileInfo($record, $type)['url'] ?? null)
                ->visible(fn (?Model $record): bool => self::recordFileInfo($record, $type) !== null)
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->iconColor('primary')
                ->color('primary')
                ->dehydrated(false)
                ->columnSpan(['default' => 1, 'md' => 1])
                ->columnStart(1),
            // Iki sutunlu kapsam bolumunde yarim (D-157: tam satir yok).
            FileUpload::make($path.'.scope_file')
                ->label(__('business_case_scope.fields.scope_file'))
                ->helperText(__('business_case_scope.help.scope_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn($path.'.scope_file_name')
                ->acceptedFileTypes(self::SCOPE_FILE_TYPES)
                ->maxSize(UploadLimits::documentMaxKb())
                ->columnSpan(['default' => 1, 'md' => 1])
                ->columnStart(1),
            Hidden::make($path.'.scope_file_name'),
        ];
    }

    private function amountInput(ProjectScopeType $type, string $field, string $step = '0.01'): TextInput
    {
        return TextInput::make('scopes.'.$type->value.'.'.$field)
            ->label(__('business_case_scope.labels.'.$type->value.'.'.$field))
            ->numeric()
            ->step($step)
            ->minValue(0)
            ->live(onBlur: true);
    }

    /** Miktar (MWp / MWh / km): degisince birim fiyatli toplamlar yeniden yazilir. */
    private function quantityInput(ProjectScopeType $type, string $field): TextInput
    {
        return $this->amountInput($type, $field, '0.001')
            ->afterStateUpdated(function (Set $set, Get $get) use ($type): void {
                self::fillTotal($type, 'unit_cost', 'total_cost', $set, $get);
                self::fillTotal($type, 'unit_sales', 'total_sales', $set, $get);
            });
    }

    /** Birim fiyat: miktar varsa toplam = miktar x birim. */
    private function unitInput(ProjectScopeType $type, string $field, string $totalField): TextInput
    {
        return $this->amountInput($type, $field)
            ->afterStateUpdated(fn (Set $set, Get $get) => self::fillTotal($type, $field, $totalField, $set, $get));
    }

    private static function fillTotal(ProjectScopeType $type, string $unitField, string $totalField, Set $set, Get $get): void
    {
        $quantityField = array_key_first(self::QUANTITIES[$type->value] ?? []);

        if ($quantityField === null) {
            return;
        }

        $path = 'scopes.'.$type->value.'.';
        $quantity = self::number($get($path.$quantityField));
        $unit = self::number($get($path.$unitField));

        if ($quantity === null || $unit === null) {
            return;
        }

        $set($path.$totalField, number_format($quantity * $unit, 2, '.', ''));
    }

    private static function resTotal(Get $get): ?float
    {
        $total = null;

        foreach (ProposalVersionScopeService::TYPE_FIELDS['res'] as $field) {
            $value = self::number($get('scopes.res.'.$field));

            if ($value !== null) {
                $total = ($total ?? 0.0) + $value;
            }
        }

        return $total;
    }

    /**
     * Duzenlenen teklifin guncel surumundeki kapsam listesi.
     *
     * @return array{title: string, revision: string|null, file: string|null, url: string|null}|null
     */
    private static function recordFileInfo(?Model $record, ProjectScopeType $type): ?array
    {
        $version = $record instanceof Proposal ? $record->currentVersion : null;

        if ($version === null) {
            return null;
        }

        /** @var ProposalVersionScope|null $scope */
        $scope = $version->scopes->first(static fn (ProposalVersionScope $row): bool => self::typeValue($row) === $type->value);

        return $scope === null ? null : DocumentLine::info($scope->scopeDocument, $scope->scopeDocumentRevision);
    }

    /** Gonderimden once gecici dosya nesnesi, sonra ad alani; hicbiri yoksa "Secilmedi". */
    private static function fileName(mixed $file, mixed $storedName): string
    {
        if (is_scalar($storedName) && filled($storedName)) {
            return (string) $storedName;
        }

        foreach (is_array($file) ? $file : [$file] as $candidate) {
            if ($candidate instanceof TemporaryUploadedFile) {
                return $candidate->getClientOriginalName();
            }

            if (is_string($candidate) && $candidate !== '') {
                return basename($candidate);
            }
        }

        return __('business_case.values.none');
    }

    private static function typeValue(ProposalVersionScope $scope): string
    {
        $type = $scope->getAttribute('scope_type');

        return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function money(?float $amount, mixed $currency): string
    {
        if ($amount === null) {
            return '-';
        }

        return trim(Number::format($amount, precision: 2, locale: 'tr').' '.(is_string($currency) ? $currency : ''));
    }
}
