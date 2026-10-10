<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Services\Acquisition\ProposalVersionScopeService;
use App\Support\Acquisition\CostLists;
use App\Support\Money;
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
 * degistirebilir). Marj kapsamdan hesaplanir (elle girilmez).
 *
 * D-181 (B51, ozellik acquisition.proposals.cost_lists): kapsam listesinin hemen
 * yaninda "Maliyet listesi" yuklemesi (ayni dosya turleri ve sinir, coklu dosya).
 *
 * D-183: her tipin bolum basliginda "Referanslar" (o tipe ayarli referans
 * penceresi) ve indir simgesi (ReferenceListField::scopeHeaderActions).
 *
 * D-186 (9 Ekim 2026 kullanici talimati: "duzenle'deki tasarim ile goruntule
 * tasarimi birebir ayni olmalidir ... Tek fark duzenlede input var, goruntule
 * kisminda label alani"): duzenleme ve goruntuleme ayni tanimdan kurulur.
 * layout() her tipin alanlarini, turlerini ve satir baslarini tek yerde
 * tutar; section() ayni baslik, simge, renk, baslik eylemleri ve izgarayi
 * verir. Duzenlemede alan girdi, goruntulemede (sections($types) yerine
 * recordSections($version)) ayni yerde ayni genislikte etiketli deger olur;
 * dosya kutulari ayni kutudur (duzenlemede carpili cip + Yukle, goruntulemede
 * cip). Teklifte belge revizyonu yok: yeni dosya yeni belgedir, carpi dosyayi
 * surumden cikarir (ProposalVersionScopeService).
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

    /** Alan turleri (layout). */
    private const AMOUNT = 'amount';

    private const MEASURE = 'measure';

    private const QUANTITY = 'quantity';

    private const UNIT = 'unit';

    private const RES_TOTAL = 'res_total';

    /**
     * Secili tiplerin kapsam bolumleri (duzenleme). $types formdan secili tip
     * listesini verir (sihirbazin potansiyel is adimi ya da teklif ekraninin
     * gizli alani).
     *
     * @param  Closure(Get): list<string>  $types
     * @param  Closure(Get): bool|null  $visible
     * @return list<Component>
     */
    public function sections(Closure $types, ?Closure $visible = null): array
    {
        $visible ??= static fn (): bool => true;
        $sections = [];
        $references = app(ReferenceListField::class);

        foreach (ProjectScopeType::cases() as $type) {
            $sections[] = $this->section(
                $type,
                'proposal-scope-'.$type->value,
                // D-183: basliktaki "Referanslar" ve indir simgesi (o tipin referanslari).
                $references->scopeHeaderActions($type, 'form_scope'),
                [...$this->typeComponents($type), ...$this->fileComponents($type)],
            )->visible(fn (Get $get): bool => $visible($get) && in_array($type->value, $types($get), true));
        }

        return $sections;
    }

    /**
     * Kayitli surumun kapsam bolumleri (D-186: teklif sayfasi ve Surumler
     * penceresi): duzenleme bolumleriyle ayni baslik, simge, izgara ve kutular,
     * degerler etiketli metin. $prefix: ayni sayfada iki blok olursa adlar
     * karismasin. $references: basliktaki "Referanslar" ve indir simgesi
     * (teklif sayfasinda; pencerede yok).
     *
     * @return list<Component>
     */
    public function recordSections(?ProposalVersion $version, string $prefix = 'proposal_scope', bool $references = false): array
    {
        if ($version === null || $version->scopes->isEmpty()) {
            return [];
        }

        $byType = [];

        foreach ($version->scopes as $scope) {
            /** @var ProposalVersionScope $scope */
            $byType[self::typeValue($scope)] = $scope;
        }

        $sections = [];
        $referenceField = app(ReferenceListField::class);

        // Duzenleme ekranindaki sira (ProjectScopeType::cases).
        foreach (ProjectScopeType::cases() as $type) {
            $scope = $byType[$type->value] ?? null;

            if ($scope === null) {
                continue;
            }

            $view = ['scope' => $scope, 'currency' => $version->currency_code, 'prefix' => $prefix.'_'.$type->value];

            $sections[] = $this->section(
                $type,
                $prefix.'-'.$type->value,
                $references ? $referenceField->scopeHeaderActions($type, $prefix) : [],
                [...$this->typeComponents($type, $view), ...$this->fileComponents($type, $view)],
            );
        }

        return $sections;
    }

    /**
     * Teklif sayfasindaki kapsam: bolumler duzenleme ekranindaki gibi yarim
     * genislikte yan yana (D-167, D-168). Kapsam yoksa null.
     */
    public function recordGrid(?ProposalVersion $version, string $prefix = 'proposal_scope', bool $references = false): ?Component
    {
        $sections = $this->recordSections($version, $prefix, $references);

        return $sections === [] ? null : Grid::make(['default' => 1, 'xl' => 2])->components($sections);
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
            // D-167: tek aciklama; bosken alanin kendisi nasil hesaplandigini yazar.
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
     * D-186: kaldirilmak uzere isaretlenen dosyalar (cipin "x"i) bos dizi.
     *
     * @return array<string, array<string, mixed>>
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

            // D-186: cipin "x"i ile isaretlenecek dosyalar bos baslar.
            $row[ProposalVersionScopeService::REMOVED_SCOPE_FILE_KEY] = [];
            $row[ProposalVersionScopeService::REMOVED_COST_FILES_KEY] = [];

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

            $parts[] = __('business_case_scope.labels.'.$type->value.'.'.$field).': '.self::display($type, $field, $value, $currency);
        }

        $parts[] = __('business_case_scope.fields.scope_file').': '.self::fileName($read($path.'scope_file'), $read($path.'scope_file_name'));

        // D-181: maliyet listeleri (ozellik acikken).
        if (CostLists::enabled()) {
            $parts[] = __('business_case_scope.fields.cost_files').': '.self::costFileNames($read, $path);
        }

        return implode(' · ', $parts);
    }

    /**
     * Kapsamin maliyet listeleri (B51, D-181): her biri belge satiri bilgisi.
     *
     * @return list<array{title: string, revision: string|null, file: string|null, url: string|null}>
     */
    public static function costInfos(ProposalVersionScope $scope): array
    {
        if (! CostLists::enabled()) {
            return [];
        }

        $infos = [];

        foreach ($scope->costDocuments as $row) {
            /** @var ProposalVersionScopeDocument $row */
            $info = DocumentLine::info($row->documentRevision?->document, $row->documentRevision);

            if ($info !== null) {
                $infos[] = $info;
            }
        }

        return $infos;
    }

    /**
     * Tek bolum: duzenleme ve goruntulemede ayni baslik ("GES kapsami"), simge,
     * renk, baslik eylemleri ve iki sutunlu izgara.
     *
     * @param  list<\Filament\Actions\Action>  $headerActions
     * @param  list<Component>  $components
     */
    private function section(ProjectScopeType $type, string $key, array $headerActions, array $components): Section
    {
        return Section::make(__('business_case_scope.sections.'.$type->value))
            ->key($key)
            ->icon($type->getIcon())
            ->iconColor($type->getColor())
            ->afterHeader($headerActions)
            ->compact()
            ->columns(['default' => 1, 'md' => 2])
            ->components($components);
    }

    /**
     * Tipin alanlari, izgara sirasiyla (sol | sag): [alan, tur, yeni satir mi].
     * Duzenleme ve goruntuleme ayni listeyi kullanir (D-186).
     *
     * @return list<array{0: string, 1: string, 2?: bool}>
     */
    private static function layout(ProjectScopeType $type): array
    {
        return match ($type) {
            ProjectScopeType::Tm, ProjectScopeType::Hes => [
                ['total_cost', self::AMOUNT],
                ['total_sales', self::AMOUNT],
                ['unit_cost', self::AMOUNT],
            ],
            ProjectScopeType::Bes => [
                ['power_mwe', self::MEASURE],
                ['energy_mwh', self::QUANTITY],
                ['unit_cost', self::UNIT],
                ['total_cost', self::AMOUNT],
                ['unit_sales', self::UNIT],
                ['total_sales', self::AMOUNT],
            ],
            ProjectScopeType::Ges => [
                ['capacity_mwp', self::QUANTITY],
                ['unit_cost', self::UNIT, true],
                ['total_cost', self::AMOUNT],
                ['unit_sales', self::UNIT],
                ['total_sales', self::AMOUNT],
            ],
            ProjectScopeType::EnhEih => [
                ['length_km', self::QUANTITY],
                ['unit_cost', self::UNIT, true],
                ['total_cost', self::AMOUNT],
                ['unit_sales', self::UNIT],
                ['total_sales', self::AMOUNT],
            ],
            ProjectScopeType::Res => [
                ['res_material_amount', self::AMOUNT],
                ['res_construction_amount', self::AMOUNT],
                ['res_assembly_amount', self::AMOUNT],
                ['total', self::RES_TOTAL],
            ],
            // D-177: "Toplam Maliyet - Toplam Satis seklinde 2 input", altinda kapsam belgesi.
            ProjectScopeType::Automation => [
                ['total_cost', self::AMOUNT],
                ['total_sales', self::AMOUNT],
            ],
        };
    }

    /**
     * Tipin alanlari: $view yoksa girdi (duzenleme), varsa ayni yerde etiketli
     * deger (goruntuleme).
     *
     * @param  array{scope: ProposalVersionScope, currency: mixed, prefix: string}|null  $view
     * @return list<Component>
     */
    private function typeComponents(ProjectScopeType $type, ?array $view = null): array
    {
        $components = [];

        foreach (self::layout($type) as $spec) {
            [$field, $kind] = $spec;
            $component = $view === null ? $this->input($type, $field, $kind) : $this->entry($type, $field, $kind, $view);

            if (($spec[2] ?? false) === true) {
                $component->columnStart(1);
            }

            $components[] = $component;
        }

        return $components;
    }

    private function input(ProjectScopeType $type, string $field, string $kind): Component
    {
        return match ($kind) {
            self::MEASURE => $this->measureInput($type, $field),
            self::QUANTITY => $this->quantityInput($type, $field),
            self::UNIT => $this->unitInput($type, $field, str_replace('unit_', 'total_', $field)),
            self::RES_TOTAL => TextEntry::make('scopes.res.total')
                ->label(__('business_case_scope.fields.total'))
                ->state(fn (Get $get): string => self::money(self::resTotal(static fn (string $name): mixed => $get('scopes.res.'.$name)), $get('currency_code')))
                ->icon(Heroicon::OutlinedCalculator)
                ->iconColor('success')
                ->weight(FontWeight::SemiBold)
                ->dehydrated(false),
            default => $this->amountInput($type, $field),
        };
    }

    /**
     * Goruntulemede alanin degeri (D-180 tutar bicimi, olculer birimiyle).
     *
     * @param  array{scope: ProposalVersionScope, currency: mixed, prefix: string}  $view
     */
    private function entry(ProjectScopeType $type, string $field, string $kind, array $view): TextEntry
    {
        $scope = $view['scope'];

        if ($kind === self::RES_TOTAL) {
            return TextEntry::make($view['prefix'].'_total')
                ->label(__('business_case_scope.fields.total'))
                ->state(self::money(self::resTotal(static fn (string $name): mixed => $scope->getAttribute($name)), $view['currency']))
                ->icon(Heroicon::OutlinedCalculator)
                ->iconColor('success')
                ->weight(FontWeight::SemiBold);
        }

        $value = self::number($scope->getAttribute($field));
        $total = in_array($field, ['total_cost', 'total_sales'], true);

        return TextEntry::make($view['prefix'].'_'.$field)
            ->label(__('business_case_scope.labels.'.$type->value.'.'.$field))
            ->state($value === null ? '-' : self::display($type, $field, $value, $view['currency']))
            ->weight($total ? FontWeight::SemiBold : FontWeight::Medium);
    }

    /**
     * Kapsam listesi ve maliyet listesi kutulari (D-185 iki kucuk kutu yan
     * yana). Duzenlemede kayitli dosyalar carpili cip + "Yukle" (D-186: carpi
     * dosyayi surumden cikarir, yeni dosya yeni belge); goruntulemede ayni kutu
     * ciplerle. D-183: aciklama metinleri yok.
     *
     * @param  array{scope: ProposalVersionScope, currency: mixed, prefix: string}|null  $view
     * @return list<Component>
     */
    private function fileComponents(ProjectScopeType $type, ?array $view = null): array
    {
        $scopeLabel = __('business_case_scope.fields.scope_file');
        $costLabel = __('business_case_scope.fields.cost_files');

        if ($view !== null) {
            $scope = $view['scope'];
            $info = DocumentLine::info($scope->scopeDocument, $scope->scopeDocumentRevision);

            return [
                CompactUpload::viewSlot(
                    TextEntry::make($view['prefix'].'_scope_file')
                        ->label($scopeLabel)
                        ->state(CompactUpload::chips(array_values(array_filter([$info])), withRevision: false)),
                )
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->columnStart(1),
                CompactUpload::viewSlot(
                    TextEntry::make($view['prefix'].'_cost_files')
                        ->label($costLabel)
                        ->state(CompactUpload::chips(self::costInfos($scope), withRevision: false)),
                )
                    ->visible(fn (): bool => CostLists::enabled())
                    ->columnSpan(['default' => 1, 'md' => 1]),
            ];
        }

        $path = 'scopes.'.$type->value;

        return [
            CompactUpload::editSlot(
                $path.'.'.ProposalVersionScopeService::REMOVED_SCOPE_FILE_KEY,
                $scopeLabel,
                static fn (?Model $record): array => array_values(array_filter([self::recordScopeFile($record, $type)])),
                FileUpload::make($path.'.scope_file')
                    ->label($scopeLabel)
                    ->disk('local')
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->storeFileNamesIn($path.'.scope_file_name')
                    ->acceptedFileTypes(self::SCOPE_FILE_TYPES)
                    ->maxSize(UploadLimits::documentMaxKb()),
                [Hidden::make($path.'.scope_file_name')],
            )
                ->columnSpan(['default' => 1, 'md' => 1])
                ->columnStart(1),
            // D-181: kapsam listesinin hemen yaninda Maliyet listesi (ayni dosya
            // turleri ve sinir, coklu dosya; D-186: her dosya yeni belge).
            CompactUpload::editSlot(
                $path.'.'.ProposalVersionScopeService::REMOVED_COST_FILES_KEY,
                $costLabel,
                static fn (?Model $record): array => self::recordCostFiles($record, $type),
                FileUpload::make($path.'.'.ProposalVersionScopeService::COST_FILES_KEY)
                    ->label($costLabel)
                    ->multiple()
                    ->disk('local')
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->storeFileNamesIn($path.'.'.ProposalVersionScopeService::COST_FILES_NAME_KEY)
                    ->acceptedFileTypes(self::SCOPE_FILE_TYPES)
                    ->maxSize(UploadLimits::documentMaxKb()),
                [Hidden::make($path.'.'.ProposalVersionScopeService::COST_FILES_NAME_KEY)],
            )
                ->visible(fn (): bool => CostLists::enabled())
                ->columnSpan(['default' => 1, 'md' => 1]),
        ];
    }

    /**
     * Duzenlenen teklifin guncel surumunde tipin kapsam listesi (cip bilgisi +
     * revizyon kimligi, D-186 "x").
     *
     * @return array{revision_id: int, title: string, revision: string|null, file: string|null, url: string|null}|null
     */
    private static function recordScopeFile(?Model $record, ProjectScopeType $type): ?array
    {
        $scope = self::recordScope($record, $type);

        if ($scope === null || $scope->scope_document_revision_id === null) {
            return null;
        }

        $info = DocumentLine::info($scope->scopeDocument, $scope->scopeDocumentRevision);

        return $info === null ? null : [...$info, 'revision_id' => (int) $scope->scope_document_revision_id];
    }

    /**
     * Duzenlenen teklifin guncel surumunde tipin maliyet listeleri.
     *
     * @return list<array{revision_id: int, title: string, revision: string|null, file: string|null, url: string|null}>
     */
    private static function recordCostFiles(?Model $record, ProjectScopeType $type): array
    {
        $scope = self::recordScope($record, $type);

        if ($scope === null || ! CostLists::enabled()) {
            return [];
        }

        $files = [];

        foreach ($scope->costDocuments as $row) {
            /** @var ProposalVersionScopeDocument $row */
            $info = DocumentLine::info($row->documentRevision?->document, $row->documentRevision);

            if ($info !== null) {
                $files[] = [...$info, 'revision_id' => (int) $row->document_revision_id];
            }
        }

        return $files;
    }

    private static function recordScope(?Model $record, ProjectScopeType $type): ?ProposalVersionScope
    {
        $version = $record instanceof Proposal ? $record->currentVersion : null;

        /** @var ProposalVersionScope|null $scope */
        $scope = $version?->scopes->first(static fn (ProposalVersionScope $row): bool => self::typeValue($row) === $type->value);

        return $scope;
    }

    /**
     * Ozet satirindaki maliyet listesi adlari: formda yuklenenler (gecici dosya
     * ya da ad alani) ya da kayitli ozetin verdigi metinler.
     */
    private static function costFileNames(callable $read, string $path): string
    {
        $names = [];
        $stored = $read($path.ProposalVersionScopeService::COST_FILES_NAME_KEY);

        foreach (is_array($stored) ? $stored : [$stored] as $name) {
            if (is_scalar($name) && filled($name)) {
                $names[] = (string) $name;
            }
        }

        if ($names === []) {
            $files = $read($path.ProposalVersionScopeService::COST_FILES_KEY);

            foreach (is_array($files) ? $files : [$files] as $candidate) {
                if ($candidate instanceof TemporaryUploadedFile) {
                    $names[] = $candidate->getClientOriginalName();
                } elseif (is_string($candidate) && $candidate !== '') {
                    $names[] = basename($candidate);
                }
            }
        }

        return $names === [] ? __('business_case.values.none') : implode(', ', array_unique($names));
    }

    /** Tutar (D-180): Turkce maskeli giris, arkada teklifin para birimi simgesi. */
    private function amountInput(ProjectScopeType $type, string $field): TextInput
    {
        return MoneyInput::make('scopes.'.$type->value.'.'.$field)
            ->label(__('business_case_scope.labels.'.$type->value.'.'.$field))
            ->live(onBlur: true);
    }

    /** Olcu (MWe / MWp / MWh / km): para degil, duz sayi. */
    private function measureInput(ProjectScopeType $type, string $field): TextInput
    {
        return TextInput::make('scopes.'.$type->value.'.'.$field)
            ->label(__('business_case_scope.labels.'.$type->value.'.'.$field))
            ->numeric()
            ->step('0.001')
            ->minValue(0)
            ->live(onBlur: true);
    }

    /** Miktar (MWp / MWh / km): degisince birim fiyatli toplamlar yeniden yazilir. */
    private function quantityInput(ProjectScopeType $type, string $field): TextInput
    {
        return $this->measureInput($type, $field)
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

        // D-180: MoneyInput sayiyi kendi Turkce metnine cevirir.
        $set($path.$totalField, round($quantity * $unit, 2));
    }

    /**
     * RES kalemlerinin toplami; $read alan adiyla degeri verir (form ya da kayit).
     *
     * @param  callable(string): mixed  $read
     */
    private static function resTotal(callable $read): ?float
    {
        $total = null;

        foreach (ProposalVersionScopeService::TYPE_FIELDS['res'] as $field) {
            $value = self::number($read($field));

            if ($value !== null) {
                $total = ($total ?? 0.0) + $value;
            }
        }

        return $total;
    }

    /** Olcu birimiyle ("12,5 MWp"), tutar para birimi simgesiyle (D-180). */
    private static function display(ProjectScopeType $type, string $field, float $value, mixed $currency): string
    {
        $unit = self::QUANTITIES[$type->value][$field] ?? ($field === 'power_mwe' ? 'MWe' : null);

        return $unit !== null
            ? Number::format($value, maxPrecision: 3, locale: 'tr').' '.$unit
            : self::money($value, $currency);
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

    /** Kayittaki sayi ya da tutar alaninin Turkce metni (D-180). */
    private static function number(mixed $value): ?float
    {
        return Money::parse($value);
    }

    /** Tutar + para birimi simgesi (D-180, Money::format). */
    private static function money(?float $amount, mixed $currency): string
    {
        return Money::format($amount, $currency);
    }
}
