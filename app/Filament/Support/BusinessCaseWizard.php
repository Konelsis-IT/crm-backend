<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Acquisition\BusinessDevelopmentKind;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\BusinessSourceKind;
use App\Enums\Acquisition\LicenseStatus;
use App\Support\Acquisition\ChecklistTemplates;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\OfferType;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Acquisition\ProposalDocumentRole;
use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Platform\Feature;
use App\Enums\Reference\ClassificationCode;
use App\Exceptions\AbstractException;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\TenderNotice;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Query\Acquisition\TenderQueries;
use App\Query\Document\FixedDocumentQueries;
use App\Query\Party\PartyQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Acquisition\ProposalVersionScopeService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectConversionService;
use App\Support\Acquisition\ScopeTypes;
use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\Projects\ProjectNames;
use App\Support\UploadLimits;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Livewire\Component as LivewireComponent;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Is alim zinciri sihirbazi (D-72): "Is dosyasi -> Teklif -> Proje" uc adimi
 * olusturma, duzenleme ve goruntulemede ayni sirayla gosterilir.
 *
 * - Olusturma: 1 is dosyasi alanlari, 2 ilk teklif ve surumu (istege bagli),
 *   3 hemen projeye donusum (istege bagli) — AcquisitionIntakeService.
 * - Duzenleme: 1 alanlar (Kaydet), 2 teklifler tablosu, 3 proje/donusum.
 * - Goruntuleme: kart + asama uyarisi + ayni uc adim (1 ayrintilar).
 *
 * B29 (16 Eylul 2026 kullanici karari, "Is dosyalari" ekrani): kritiklik
 * yerine teklif tipi (butcesel / kat'i), proje tip secimi (GES, RES, TM, HES,
 * BES, ENH/EIH) ve secilen her tip icin kendi kapsam bolumu (sayisal alanlar
 * + kapsam listesi Excel'i), teklif adiminda teklif durumu ve teklif
 * belgeleri (firmanin beklentileri, teklif mektubu, sabit referans belgesi
 * ve genel katalog). Adim 2 ve 3 onceki adimlarin ozet kartini tasir.
 * Tum B29 ogeleri SchemaReadiness::hasBatch('B29') ile kapilidir.
 *
 * B43 (D-155, 5 Ekim 2026 kullanici talimati): zincir dort adimdir — Ihale ->
 * Potansiyel is -> Teklif -> Proje. Ihale, potansiyel is ve teklif ekranlarinin
 * olustur / duzenle sayfalari ayni dort adimi ayni duzende gosterir; her sayfa
 * kendi kaydinin adiminda acilir, diger adimlar ozet ve baglantidir.
 * - Proje tipi potansiyel iste secilir; kapsam bolumleri teklif adimindadir
 *   (ProposalScopeSchema), marj kapsamdan hesaplanir.
 * - Potansiyel is adiminda teklif oncesi kontrol listesi ve belgeler
 *   (ChecklistSchema; kendi ozellik anahtarlari).
 * - Teklif adiminda baslik yarim genislikte, belgeler kucuk kutular halinde
 *   (ProposalFilesSchema). D-186: duzenleme surum artirmaz; yeni surum
 *   "Yeni teklif surumu" dugmesiyle (proposalEditSteps $newVersion).
 * Grup uygulanmadiysa eski uc adim aynen calisir.
 */
final class BusinessCaseWizard
{
    /** B43: zincirin ilk adimi. */
    public const STEP_TENDER = 'tender';

    public const STEP_CASE = 'case';

    public const STEP_PROPOSAL = 'proposal';

    public const STEP_PROJECT = 'project';

    /** @var list<string> B43 oncesi uc adim. */
    public const STEP_IDS = [self::STEP_CASE, self::STEP_PROPOSAL, self::STEP_PROJECT];

    /** D-186: proje tipi secimi rozet boyunda cipler (resources/css/filament/konelsis.css). */
    public const TYPE_CHIPS_CLASS = 'kc-type-chips';

    /** @var list<string> B43: dort adim. */
    public const CHAIN_STEP_IDS = [self::STEP_TENDER, self::STEP_CASE, self::STEP_PROPOSAL, self::STEP_PROJECT];

    /** Ihaleden acilan potansiyel is (B43): ?ihale=ID ile ihale secili ve sabit gelir. */
    public const QUERY_TENDER = 'ihale';

    /**
     * Proje tipi basina sayisal kapsam alanlari (B29). HES / BES / ENH-EIH
     * icin alanlar henuz tanimli degildir; yalniz kapsam listesi yuklenir.
     *
     * @var array<string, list<string>>
     */
    public const SCOPE_FIELDS = [
        'ges' => ['capacity_mw', 'cost_amount', 'sales_amount', 'cost_per_mw', 'sales_per_mw'],
        'res' => ['res_material_amount', 'res_construction_amount', 'res_assembly_amount'],
        'tm' => ['tm_total_cost', 'tm_total_sales', 'tm_feeder_cost'],
    ];

    /** Gecici yukleme dizini (DocumentService dosyayi buradan alir). */
    private const UPLOAD_DIRECTORY = 'document-uploads-tmp';

    /** @var list<string> Kapsam listesi icin kabul edilen dosya turleri. */
    private const SCOPE_FILE_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        // CSV sunucuda cogu zaman duz metin olarak algilanir.
        'application/csv',
        'text/plain',
    ];

    /** Sabit belgeler (REF / KAT) istek boyunca bir kez sorgulanir. */
    private ?bool $hasReferenceDocument = null;

    private ?bool $hasCatalogDocument = null;

    /** @var array<string, string>|null Proje kategorisi kodu => ad (ozet karti). */
    private ?array $projectTypeNames = null;

    /** @var array<int, array<string, mixed>> Is dosyasi kimligi => ozet karti degerleri. */
    private array $caseSummaries = [];

    /**
     * Is dosyasi form alanlari (kaynak formu, olusturma ve duzenleme adimi).
     *
     * @return list<Component>
     */
    public function caseFields(): array
    {
        $b29 = fn (): bool => self::b29();
        $notB29 = fn (): bool => ! self::b29();

        return [
            Select::make('primary_party_id')
                ->label(__('business_case.fields.primary_party'))
                ->relationship(
                    'primaryParty',
                    'display_name',
                    modifyQueryUsing: fn ($query) => $query->where('party_kind', 'organization'),
                )
                ->searchable()
                ->preload()
                ->required()
                ->native(false)
                // Musteri + Ulke satiri tam doldursun (16 Eylul 2026 kullanici
                // karari): 4 + 2 = yarim genislikteki bolumun 6 sutunu.
                ->columnSpan(FieldGrid::WIDE),
            // Tam satir alan yok (D-157): baslik ve aciklama yarim bolumun 4/6'si.
            TextInput::make('title')
                ->label(__('business_case.fields.title'))
                ->required()
                ->maxLength(255)
                ->columnSpan(FieldGrid::WIDE)
                ->columnStart(1),
            Textarea::make('short_description')
                ->label(__('business_case.fields.short_description'))
                ->columnSpan(FieldGrid::HALF_LONG)
                ->columnStart(1),
            Select::make('country_code')
                ->label(__('business_case.fields.country'))
                ->options(fn (): array => app(ReferenceOptions::class)->countries())
                ->default((string) config('konelsis.legal_entity.country', 'TR'))
                ->searchable()
                ->required()
                ->native(false)
                ->columnSpan(FieldGrid::SHORT),
            // Teklifte yalniz TRY / USD / EUR / RON (config konelsis.offer_currencies).
            Select::make('currency_code')
                ->label(__('business_case.fields.currency'))
                ->options(fn (): array => app(ReferenceOptions::class)->offerCurrencies())
                ->default((string) config('konelsis.organization.default_currency', 'TRY'))
                ->searchable()
                ->required()
                ->native(false)
                // D-180: tutar alanlarinin simgesi secimle birlikte degisir.
                ->live()
                ->columnSpan(FieldGrid::SHORT),
            // B29: teklif tipi kritikligin yerini alir; kritiklik kolonu ve verisi
            // veritabaninda kalir, form yalniz B29 yokken gosterir.
            Select::make('offer_type')
                ->label(__('business_case.fields.offer_type'))
                ->options(OfferType::class)
                ->default('budgetary')
                ->required($b29)
                ->native(false)
                ->visible($b29)
                ->dehydrated($b29)
                ->columnSpan(FieldGrid::SHORT),
            // Is gelistirme turu (B47, D-170, 7 Ekim 2026 kullanici talimati): bos
            // = Otomatik (sicaklik 0 Yatirimci projesi, 0'dan buyuk Potansiyel is);
            // secilirse sicakliktan bagimsiz o tur gecerlidir.
            Select::make('development_kind')
                ->label(__('business_case.kind'))
                ->options(BusinessDevelopmentKind::class)
                ->placeholder(__('business_case.development_kind.auto'))
                ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: __('business_case.development_kind.help'))
                ->hintColor('gray')
                ->native(false)
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B47'))
                ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B47'))
                ->columnSpan(FieldGrid::NORMAL),
            // D-163: tipler kendi simgesi ve rengiyle secim dugmesi (coklu secim);
            // secilen dugme tipin renginde dolar.
            // D-177: secenekler ScopeTypes'tan (Otomasyon / Process B50 ve ozellikle);
            // duzenlemede "Proje tipi eklemek istiyorum" acikken bu alan gizlidir,
            // durumu yine kontrol listesini besler (asagidaki ekleme alanlari).
            ToggleButtons::make('scope_types')
                ->label(__('business_case.fields.scope_types'))
                // B43: kapsam teklif adiminda; burada kontrol listesi acilir.
                ->helperText(fn (): string => self::b43() ? __('business_case.help.scope_types_chain') : __('business_case.help.scope_types'))
                ->options(fn (?Model $record): array => self::scopeTypeOptions($record))
                ->enum(ProjectScopeType::class)
                ->multiple()
                // D-186: rozet boyunda satir ici secim cipleri (eskiden uc sutunlu
                // buyuk dugmeler); secilen tip kendi renginde dolar.
                ->inline()
                ->extraAttributes(['class' => self::TYPE_CHIPS_CLASS], merge: true)
                ->live()
                ->visible(fn (?Model $record): bool => self::b29() && ! self::addsScopeTypes($record))
                ->dehydrated(fn (?Model $record): bool => self::b29() && ! self::addsScopeTypes($record))
                ->columnSpan(FieldGrid::HALF),
            ...$this->scopeTypeAddFields(
                existing: static fn (?Model $record): array => $record instanceof BusinessCase ? self::caseScopeTypes($record) : [],
                visible: static fn (?Model $record): bool => self::addsScopeTypes($record),
                syncSelection: true,
            ),
            // D-183: potansiyel isteki "Referans listesi" alani kaldirildi; referanslar
            // teklifin kapsam bolumlerinin basligindaki "Referanslar" dugmesinde.
            // Proje durumu (B43; D-157, 5 Ekim 2026 kullanici talimati: "Siniflandirma
            // alanina koyalim, dropdown olsun, Teklif tipi ile Proje tipi altinda 1
            // satirda, ayni Teklif tipi uzunlugunda, yanlari bos kalsin"). Yalniz GES
            // listesine bakan proje tiplerinde; lisansli projede Cagri mektubu opsiyonel.
            Select::make('license_status')
                ->label(__('checklist.license_status'))
                ->options(LicenseStatus::class)
                ->placeholder(__('checklist.license_placeholder'))
                ->native(false)
                ->live()
                ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: __('checklist.license_help'))
                ->hintColor('gray')
                ->visible(fn (Get $get): bool => ChecklistSchema::enabled() && in_array(ChecklistTemplates::GES, ChecklistTemplates::forScopeTypes(self::selectedScopeTypes($get)), true))
                ->columnSpan(FieldGrid::SHORT)
                ->columnStart(1),
            Select::make('source_kind')
                ->label(__('business_case.fields.source_kind'))
                ->options(BusinessSourceKind::class)
                ->default(BusinessSourceKind::Manual->value)
                ->required()
                ->native(false)
                ->columnSpan(FieldGrid::SHORT),
            Select::make('criticality')
                ->label(__('business_case.fields.criticality'))
                ->options(BusinessCriticality::class)
                ->default(BusinessCriticality::Normal->value)
                ->required($notB29)
                ->native(false)
                ->visible($notB29)
                ->dehydrated($notB29)
                ->columnSpan(FieldGrid::NORMAL),
            Select::make('owner_employee_id')
                ->label(__('business_case.fields.owner'))
                ->relationship('owner', 'full_name')
                ->searchable()
                ->preload()
                ->native(false),
            Select::make('proposal_owner_employee_id')
                ->label(__('business_case.fields.proposal_owner'))
                ->relationship('proposalOwner', 'full_name')
                ->searchable()
                ->preload()
                ->native(false),
            MoneyInput::make('estimated_value')
                ->label(__('business_case.fields.estimated_value'))
                ->columnSpan(FieldGrid::SHORT),
            // Kisitli (restricted) gizlilik sinifi is dosyasinda secilemez.
            Select::make('classification_id')
                ->label(__('business_case.fields.classification'))
                ->relationship(
                    'classification',
                    'name_tr',
                    modifyQueryUsing: fn ($query) => $query->where('code', '!=', ClassificationCode::Restricted->value),
                )
                ->searchable()
                ->preload()
                ->native(false)
                ->columnSpan(FieldGrid::SHORT),
            Select::make('legal_entity_id')
                ->label(__('business_case.fields.legal_entity'))
                ->relationship('legalEntity', 'legal_name')
                ->searchable()
                ->preload()
                ->native(false)
                ->columnSpan(FieldGrid::WIDE)
                ->columnStart(1),
            Hidden::make('row_version')->hiddenOn('create'),
        ];
    }

    /** Sayfanin adim kimlikleri (B43 ile dort, oncesinde uc). */
    public static function stepIds(): array
    {
        return self::b43() ? self::CHAIN_STEP_IDS : self::STEP_IDS;
    }

    /** Adim kimliginin sihirbazdaki sirasi (1'den); bilinmiyorsa null. */
    public static function stepNumber(?string $id): ?int
    {
        $index = $id === null ? false : array_search($id, self::stepIds(), true);

        return $index === false ? null : $index + 1;
    }

    /**
     * Olusturma sihirbazi adimlari. Adim 2 ve 3 onceki adimlarin ozet kartiyla
     * baslar (16 Eylul 2026 kullanici istegi). B43: ilk adim ihale (yok / mevcut /
     * yeni); $tenderLocked ise ihaleden acildi, secim sabit.
     *
     * @return list<Step>
     */
    public function createSteps(bool $tenderLocked = false): array
    {
        return [
            ...(self::b43() ? [app(TenderSchema::class)->choiceStep(locked: $tenderLocked)] : []),
            // "Kaydet" alt satirdadir (SaveableWizard, CreateBusinessCase::getStepSaveMethods).
            $this->caseStep(),
            Step::make(__('business_case.wizard.proposal'))
                ->id(self::STEP_PROPOSAL)
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
                ->columns(1)
                ->schema([
                    $this->summaryCard(self::STEP_PROPOSAL),
                    ...$this->proposalSections(),
                ]),
            Step::make(__('business_case.wizard.project'))
                ->id(self::STEP_PROJECT)
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->completedIcon(Heroicon::OutlinedRocketLaunch)
                ->columns(1)
                ->schema([
                    $this->summaryCard(self::STEP_PROJECT),
                    ...$this->projectSections(),
                ]),
        ];
    }

    /**
     * Is dosyasi alanlari bolumler halinde (D-80): musteri ve baslik,
     * siniflandirma, ticari bilgiler, sorumlular; B29 ile secilen her proje
     * tipi icin ayri kapsam bolumu. Kaynak formu ve sihirbazin 1. adimi ayni
     * bolumleri kullanir.
     *
     * @return list<Component>
     */
    public function caseSections(): array
    {
        $sections = FieldGrid::group($this->caseFields(), [
            // Musteri yaninda Ulke, Baslik alt satirda (16 Eylul 2026 kullanici
            // karari): Siniflandirma'dan Ulke cikinca kalan alanlar rahatlar.
            'identity' => ['label' => __('business_case.sections.identity'), 'icon' => Heroicon::OutlinedBriefcase, 'fields' => ['primary_party_id', 'country_code', 'title', 'short_description'], 'columns' => FieldGrid::HALF_COLUMNS],
            'classification' => ['label' => __('business_case.sections.classification'), 'icon' => Heroicon::OutlinedTag, 'fields' => ['offer_type', 'development_kind', 'criticality', 'source_kind', 'classification_id', 'scope_types', 'existing_scope_types', 'add_scope_types', 'added_scope_types', 'license_status'], 'columns' => FieldGrid::HALF_COLUMNS],
            'commercial' => ['label' => __('business_case.sections.commercial'), 'icon' => Heroicon::OutlinedBanknotes, 'fields' => ['currency_code', 'estimated_value', 'legal_entity_id'], 'columns' => FieldGrid::HALF_COLUMNS],
            'ownership' => ['label' => __('business_case.sections.ownership'), 'icon' => Heroicon::OutlinedUsers, 'fields' => ['owner_employee_id', 'proposal_owner_employee_id'], 'columns' => FieldGrid::HALF_COLUMNS],
        ]);

        [$identity, $classification, $commercial, $ownership] = $sections;

        // Sag sutun: Siniflandirma'nin altinda, secilen proje tipine gore
        // acilan kapsam bolumleri (16 Eylul 2026 kullanici karari).
        $right = [$classification];

        // B43 (D-155): kapsam bolumleri teklif adimina tasindi; potansiyel iste
        // proje tipi secimi ve kontrol listesi kalir. D-157: Ek belgeler sag
        // sutunda Siniflandirma'nin altinda; kontrol listesi tahtasi altta.
        if (self::b43()) {
            $checklist = app(ChecklistSchema::class);

            return [
                Grid::make(['default' => 1, 'xl' => 2])->components([
                    Group::make([$identity, $commercial, $ownership]),
                    Group::make([...$right, ...array_filter([$checklist->documentsSection()])]),
                ]),
                ...$checklist->formSections(),
            ];
        }

        if (self::b29()) {
            $right = [...$right, ...$this->scopeSections()];
        }

        // Gercek iki sutun (16 Eylul 2026 kullanici karari): sol sutunda
        // Musteri/baslik ustte, altinda Ticari bilgiler, en altta Sorumlular;
        // sag sutunda Siniflandirma ve proje tipine gore kapsamlar. Her iki
        // Group da kendi hucresinde tek sutun (varsayilan), bolumler alt
        // alta durur; gercek yarilanma yalniz xl kesme noktasinda olusur
        // (bkz. FieldGrid::HALF/HALF_COLUMNS ayni kurali).
        return [
            Grid::make(['default' => 1, 'xl' => 2])->components([
                Group::make([$identity, $commercial, $ownership]),
                Group::make($right),
            ]),
        ];
    }

    /**
     * Teklif adiminin bolumleri. B43: teklif alanlari, secili proje tiplerinin
     * kapsam bolumleri (marj kapsamdan) ve kucuk belge kutulari; $editing ise
     * teklif sorumlusu ve "yeni surum" notu da gelir.
     *
     * @return list<Component>
     */
    private function proposalSections(bool $standalone = false, ?Proposal $editing = null): array
    {
        if (! self::b43()) {
            return FieldGrid::group($this->proposalFields($standalone), [
                'proposal' => ['label' => __('business_case.sections.proposal'), 'icon' => Heroicon::OutlinedClipboardDocumentList, 'fields' => [
                    'create_proposal', 'proposal_title', 'total_price', 'margin_pct', 'validity_until', 'is_critical_route', 'summary',
                    'offer_status', 'customer_expectations_file', 'proposal_letter_file', 'attach_references', 'attach_catalog',
                ]],
            ]);
        }

        // Sihirbazda tipler potansiyel is adimindan, teklif ekraninda secilen
        // (ya da duzenlenen teklifin) potansiyel isinden gelir.
        $types = $standalone
            ? fn (Get $get): array => self::selectedScopeTypes(fn (string $path): mixed => data_get($this->caseSummaryData($get('business_case_id')), $path))
            : static fn (Get $get): array => self::selectedScopeTypes($get);
        // D-183: Teklif olustur ekraninda teklif alanlari potansiyel is secilir
        // secilmez ayni ekranda acilir (Ileri gerekmez); secim yokken gizlidir.
        $creating = $standalone && $editing === null;
        $whenProposal = $creating
            ? static fn (Get $get): bool => (bool) $get('create_proposal') && filled($get('business_case_id'))
            : static fn (Get $get): bool => (bool) $get('create_proposal');
        $scope = app(ProposalScopeSchema::class);

        // D-177: teklifte "Yeni proje tipi eklemek istiyorum" isaretlenince secilen
        // yeni tipler de kapsam bolumlerini acar; kaydedince potansiyel ise ve
        // teklifin guncel surumune eklenir (D-186: surum artmaz). D-186: Teklif
        // olustur ekraninda da ayni bolum (secili potansiyel isin tipleri).
        $typeSection = null;

        if ($standalone && self::scopeTypeAddEnabled()) {
            $caseTypes = $types;
            $types = static fn (Get $get): array => array_values(array_unique([
                ...$caseTypes($get),
                ...((bool) $get('add_scope_types') ? ScopeTypes::values((array) $get('added_scope_types')) : []),
            ]));
            // D-186 (9 Ekim 2026 kullanici talimati: "Teklif bilgileri kismini w-3/4
            // yapalim, Proje tipi de sagda w-1/4 olsun"): dar bolum, tek sutun.
            $typeSection = Section::make(__('business_case.scope_add.section'))
                ->key('proposal-scope-type-add')
                ->icon(Heroicon::OutlinedTag)
                ->compact()
                ->columns(1)
                ->columnSpan(['default' => 1, 'xl' => 1])
                ->visible($whenProposal)
                ->components($this->scopeTypeAddFields(
                    existing: $caseTypes,
                    visible: $whenProposal,
                    syncSelection: false,
                ));
        }

        $grouped = FieldGrid::group([
            ...$this->proposalFields($standalone, $editing),
            $scope->totalSalesEntry($types)->visible($whenProposal)->columnSpan(FieldGrid::NORMAL),
            $scope->marginEntry($types)->visible($whenProposal)->columnSpan(FieldGrid::NORMAL),
            // D-183: ayri "Referans listesi" alani yok; her kapsam bolumunun
            // basliginda "Referanslar" ve indir simgesi (ProposalScopeSchema).
        ], [
            'proposal' => [
                'label' => $editing !== null ? __('proposal.sections.main') : __('business_case.sections.proposal'),
                'icon' => Heroicon::OutlinedClipboardDocumentList,
                'fields' => [
                    'create_proposal', 'proposal_title', 'owner_employee_id', 'offer_status', 'total_price', 'scope_total_sales', 'scope_margin_pct',
                    'validity_until', 'is_critical_route', 'summary',
                ],
                ...($creating ? ['visible' => static fn (Get $get): bool => filled($get('business_case_id'))] : []),
                ...($typeSection !== null ? ['columnSpan' => ['default' => 1, 'xl' => 3]] : []),
            ],
        ]);

        // Teklif bilgileri (3/4) ve Proje tipi (1/4) yan yana; dar ekranda alt alta.
        $infoRow = $grouped;

        if ($typeSection !== null) {
            $sections = array_values(array_filter($grouped, static fn (Component $component): bool => ! $component instanceof Hidden));
            $hidden = array_values(array_filter($grouped, static fn (Component $component): bool => $component instanceof Hidden));
            $infoRow = [
                Grid::make(['default' => 1, 'xl' => 4])->components([...$sections, $typeSection]),
                ...$hidden,
            ];
        }

        return [
            ...$infoRow,
            Text::make(__('business_case.help.scope_types_first'))
                ->color('warning')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->visible(fn (Get $get): bool => $whenProposal($get) && $types($get) === []),
            // D-167: kapsam bolumleri potansiyel is adimindaki gibi yarim genislikte
            // yan yana (tam satir yok, D-157).
            Grid::make(['default' => 1, 'xl' => 2])
                ->components($scope->sections($types, $whenProposal)),
            app(ProposalFilesSchema::class)->section($whenProposal),
        ];
    }

    /**
     * $available: donusum bolumu gorunur mu (Teklif olustur ekraninda is
     * dosyasinin henuz projesi yoksa).
     *
     * @param  (Closure(Get): bool)|null  $available
     * @return list<Component>
     */
    private function projectSections(?Closure $available = null): array
    {
        $available ??= static fn (): bool => true;
        $whenConvert = fn (Get $get): bool => (bool) $get('create_proposal') && (bool) $get('convert_now') && $available($get);

        return FieldGrid::group($this->projectFields(), [
            'conversion' => ['label' => __('business_case.sections.conversion'), 'icon' => Heroicon::OutlinedRocketLaunch, 'fields' => ['convert_now', 'project_name', 'project_manager_employee_id', 'planned_start_on', 'planned_finish_on'], 'visible' => $available],
            'site' => ['label' => __('business_case.sections.site'), 'icon' => Heroicon::OutlinedMapPin, 'fields' => ['site_address_line1', 'site_city', 'site_district'], 'visible' => $whenConvert],
        ]);
    }

    /**
     * Teklif olustur ekrani (22 Eylul 2026 kullanici karari): olusturma
     * sihirbazinin 2. adimi tek sayfada. Ustte zorunlu is dosyasi secimi,
     * altinda secilen is dosyasinin ozet karti (sihirbazdaki kartin aynisi,
     * kayitli degerlerle), teklif bolumu ve istenirse hemen projeye donusum.
     * Alanlar sihirbazla ayni adlari tasir; AcquisitionIntakeService::addProposal
     * ayni yazma yolunu kullanir.
     *
     * @return list<Component>
     */
    public function proposalCreateComponents(): array
    {
        $reader = fn (Get $get): Closure => fn (string $path): mixed => data_get($this->caseSummaryData($get('business_case_id')), $path);
        $syncCountry = fn (Set $set, mixed $state): mixed => $set('country_code', data_get($this->caseSummaryData($state), 'country_code'));

        return [
            Section::make(__('proposal.sections.business_case'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->columns(FieldGrid::COLUMNS)
                ->components([
                    Select::make('business_case_id')
                        ->label(__('proposal.fields.business_case'))
                        ->helperText(__('proposal.help.business_case'))
                        ->relationship('businessCase', 'title')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        // ?business_case_id= ile acilirsa is dosyasi secili gelir.
                        ->default(fn (): ?int => request()->integer('business_case_id') ?: null)
                        ->live()
                        ->afterStateHydrated($syncCountry)
                        ->afterStateUpdated($syncCountry)
                        ->columnSpan(FieldGrid::HALF),
                    // Santiye il / ilce secimi is dosyasinin ulkesine gore (TurkiyeAddressFields).
                    Hidden::make('country_code')->dehydrated(false),
                ]),
            $this->caseSummarySection('standalone', $reader)
                ->visible(fn (Get $get): bool => filled($get('business_case_id'))),
            ...$this->proposalSections(standalone: true),
            ...$this->projectSections(fn (Get $get): bool => filled($get('business_case_id')) && ! $reader($get)('has_project')),
        ];
    }

    /**
     * Teklif olustur (B43, D-155; "Teklif olustur'a bastigimizda ... adimli olan
     * arayuz acilmalidir"): potansiyel is sihirbaziyla ayni dort adim. Ihale
     * adimi secilen potansiyel isin ihalelerini gosterir; proje adimi istenirse
     * hemen donusum.
     *
     * D-183 (9 Ekim 2026 kullanici talimati: "Potansiyel is karti ve teklifin
     * olusturma ekrani ayni ekrandi; bunu neden ayirdin? Ayirma."): sayfa Teklif
     * adiminda acilir. Potansiyel is secimi, potansiyel is karti ve butun teklif
     * alanlari bu adimda tek ekrandadir; secim yapilir yapilmaz teklif alanlari
     * acilir ve potansiyel isten tureyen alanlar dolar (proposalAutofill).
     * Potansiyel is adimi yalniz salt okunur ozettir (zorunlu secim orada yok).
     *
     * @return list<Step>
     */
    public function proposalCreateSteps(): array
    {
        $reader = fn (Get $get): Closure => fn (string $path): mixed => data_get($this->caseSummaryData($get('business_case_id')), $path);
        $selected = static fn (Get $get): bool => filled($get('business_case_id'));
        $caseUrl = static fn (Get $get): ?string => is_numeric($get('business_case_id'))
            ? BusinessCaseResource::getUrl('view', ['record' => (int) $get('business_case_id')])
            : null;

        return [
            app(TenderSchema::class)->infoStep(static fn (Get $get): ?int => is_numeric($get('business_case_id')) ? (int) $get('business_case_id') : null),
            Step::make(__('business_case.wizard.case'))
                ->id(self::STEP_CASE)
                ->icon(Heroicon::OutlinedBriefcase)
                ->completedIcon(Heroicon::OutlinedBriefcase)
                ->columns(1)
                ->schema([
                    Text::make(__('proposal.help.case_in_proposal_step'))
                        ->color('gray')
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->visible(static fn (Get $get): bool => ! $selected($get)),
                    $this->caseSummarySection('standalone', $reader)
                        ->visible($selected),
                    Actions::make([
                        Action::make('open_case')
                            ->label(__('deal_track.go_case'))
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->color(ActionColors::VIEW)
                            ->url($caseUrl),
                    ])->visible($selected),
                ]),
            Step::make(__('business_case.wizard.proposal'))
                ->id(self::STEP_PROPOSAL)
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
                ->columns(1)
                ->schema([
                    Section::make(__('proposal.sections.business_case'))
                        ->key('proposal-case-select')
                        ->icon(Heroicon::OutlinedBriefcase)
                        ->compact()
                        ->columns(FieldGrid::COLUMNS)
                        ->components([
                            Select::make('business_case_id')
                                ->label(__('proposal.fields.business_case'))
                                ->helperText(__('proposal.help.business_case'))
                                ->relationship('businessCase', 'title')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false)
                                // ?business_case_id= ile acilirsa potansiyel is secili gelir;
                                // o durumda alanlar CreateProposal::afterFill ile dolar.
                                ->default(fn (): ?int => request()->integer('business_case_id') ?: null)
                                ->live()
                                // D-183: secim degisince potansiyel isten tureyen alanlar
                                // hemen dolar; kullanicinin yazdigi deger ezilmez.
                                ->afterStateUpdated(function (Set $set, Get $get, mixed $state, mixed $old): void {
                                    foreach ($this->proposalAutofill($state, $get, $old) as $path => $value) {
                                        $set($path, $value);
                                    }
                                })
                                ->columnSpan(FieldGrid::HALF),
                            // Santiye il / ilce ve tutar bicimi potansiyel isin ulke / para birimine gore.
                            Hidden::make('country_code')->dehydrated(false),
                            Hidden::make('currency_code')->dehydrated(false),
                        ]),
                    // D-183: potansiyel is karti teklif alanlarinin hemen ustunde (kompakt,
                    // katlanabilir, acik gelir), basliginda "Potansiyel ise git".
                    $this->caseSummarySection('proposal_top', $reader)
                        ->collapsible()
                        ->afterHeader([
                            Action::make('open_case_from_proposal')
                                ->label(__('deal_track.go_case'))
                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                ->color(ActionColors::VIEW)
                                ->link()
                                ->url($caseUrl),
                        ])
                        ->visible($selected),
                    ...$this->proposalSections(standalone: true),
                ]),
            Step::make(__('business_case.wizard.project'))
                ->id(self::STEP_PROJECT)
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->completedIcon(Heroicon::OutlinedRocketLaunch)
                ->columns(1)
                ->schema($this->projectSections(fn (Get $get): bool => filled($get('business_case_id')) && ! $reader($get)('has_project'))),
        ];
    }

    /**
     * Teklif duzenle (B43, D-155): teklif olusturla ayni dort adim, teklif
     * adiminda acilir. Ihale ve potansiyel is adimlari ozet ve baglanti, teklif
     * adimi kayitli degerlerle dolu form, proje adimi proje ya da "Projeye
     * donustur". D-186: $newVersion ise ayni form "Yeni teklif surumu" icin
     * (adim aciklamasi "Yeni surum (Surum N+1)"); Duzenle surum artirmaz.
     *
     * @return list<Step>
     */
    public function proposalEditSteps(Proposal $proposal, bool $newVersion = false): array
    {
        /** @var BusinessCase $case */
        $case = $proposal->businessCase;
        $caseId = (int) $case->getKey();
        $reader = fn (): Closure => fn (string $path): mixed => data_get($this->caseSummaryData($caseId), $path);

        return [
            app(TenderSchema::class)->infoStep(static fn (): int => $caseId),
            Step::make(__('business_case.wizard.case'))
                ->id(self::STEP_CASE)
                ->description(($case->caseCode()?->formatted_code ?? '').' · '.$case->title)
                ->icon(Heroicon::OutlinedBriefcase)
                ->completedIcon(Heroicon::OutlinedBriefcase)
                ->columns(1)
                ->schema([
                    Hidden::make('business_case_id')->dehydrated(false),
                    Hidden::make('country_code')->dehydrated(false),
                    Hidden::make('currency_code')->dehydrated(false),
                    $this->caseSummarySection('standalone', $reader),
                    Actions::make([
                        Action::make('open_case')
                            ->label(__('deal_track.go_case'))
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->color(ActionColors::VIEW)
                            ->url(BusinessCaseResource::getUrl('view', ['record' => $case]))
                            ->visible(Gate::allows('view', $case)),
                    ]),
                ]),
            Step::make(__('business_case.wizard.proposal'))
                ->id(self::STEP_PROPOSAL)
                // D-182: teklif duzenlemede gorunen durum Teklif durumudur (Verilen
                // teklif...), surum durumu (Gonderildi...) degil. D-186: yeni surum
                // ekraninda "Yeni surum (Surum N+1)".
                ->description(fn (): string => $newVersion
                    ? __('proposal.new_version.heading', ['no' => (int) $proposal->versions()->max('version_no') + 1])
                    : __('proposal.steps.version', [
                    'no' => $proposal->currentVersion?->version_no ?? '-',
                    'status' => (string) ($proposal->offer_status?->getLabel() ?? $proposal->currentVersion?->status?->getLabel() ?? '-'),
                ]))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
                ->columns(1)
                ->schema([
                    // D-183 ("Teklif duzenle dedigimizde potansiyel is karti gorunuyordu,
                    // detaylari goruyorduk"): Teklif bilgileri'nin ustunde potansiyel is
                    // karti (kompakt, katlanabilir, acik gelir) ve "Potansiyel ise git".
                    $this->caseSummarySection('proposal_top', $reader)
                        ->collapsible()
                        ->afterHeader([
                            Action::make('open_case_from_proposal')
                                ->label(__('deal_track.go_case'))
                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                ->color(ActionColors::VIEW)
                                ->link()
                                ->url(BusinessCaseResource::getUrl('view', ['record' => $case]))
                                ->visible(Gate::allows('view', $case)),
                        ]),
                    ...$this->proposalSections(standalone: true, editing: $proposal),
                ]),
            $this->projectStep($case, $proposal),
        ];
    }

    /**
     * Teklif duzenle formunun dolu degerleri (B43): baslik, sorumlu, guncel
     * surumun alanlari, kapsamlari ve sabit belge secimleri.
     *
     * @return array<string, mixed>
     */
    public function proposalFormData(Proposal $proposal): array
    {
        $version = $proposal->currentVersion;
        $case = $proposal->businessCase;

        return [
            'create_proposal' => true,
            'business_case_id' => $proposal->business_case_id,
            'country_code' => $case?->country_code,
            'currency_code' => $version?->currency_code ?? $case?->currency_code,
            'proposal_title' => $proposal->title,
            'owner_employee_id' => $proposal->owner_employee_id,
            // D-182: Teklif durumu formda yok (baslik dugmesi).
            'total_price' => $version?->total_price === null ? null : (string) $version->total_price,
            'validity_until' => $version?->validity_until?->toDateString(),
            'is_critical_route' => (bool) $version?->is_critical_route,
            'summary' => $version?->summary,
            'scopes' => ProposalScopeSchema::formData($version),
            ...ProposalFilesSchema::formData($proposal),
        ];
    }

    /**
     * D-183 (9 Ekim 2026 kullanici talimati: "Teklif basligi potansiyel isten
     * otomatik gelsin ... Doldurulabilir olanlar hizlica otomatik dolmalidir"):
     * Teklif olustur ekraninda potansiyel is secilince (ya da ?business_case_id=
     * ile secili gelince) potansiyel isten tureyen alanlar. Doner: yol => deger.
     *
     * - Ulke ve para birimi (gizli alanlar: santiye il / ilce, tutar simgesi):
     *   her zaman isin degeri.
     * - Teklif basligi = isin basligi; teklif sorumlusu = isin teklif sorumlusu,
     *   yoksa giris yapan kisi (isin sahibi degil, D-175); GES kurulu gucu (MWp)
     *   = isin GES kapsamindaki kurulu guc. Bu uc alan yalniz bossa ya da bir
     *   onceki secimden otomatik gelmis degeri hala tasiyorsa yazilir; kullanicinin
     *   yazdigi deger ezilmez.
     * - Proje tipleri ve kapsam bolumleri ayrica yazilmaz: secili isin tiplerinden
     *   canli acilir (proposalSections).
     *
     * $get: formun okuyucusu (Get ya da "yol => deger" closure'u).
     *
     * @return array<string, mixed>
     */
    public function proposalAutofill(mixed $caseId, callable $get, mixed $previousCaseId = null): array
    {
        $data = $this->caseSummaryData($caseId);
        $previous = $previousCaseId !== null && (string) $previousCaseId !== (string) $caseId ? $this->caseSummaryData($previousCaseId) : [];

        $values = [
            'country_code' => $data['country_code'] ?? null,
            'currency_code' => $data['currency_code'] ?? null,
        ];

        if ($data === []) {
            return $values;
        }

        // Bos ya da onceki isten otomatik gelmis (degistirilmemis) deger yenilenir.
        $replaceable = static function (string $path, mixed $previousValue) use ($get): bool {
            $current = $get($path);

            return blank($current) || ($previousValue !== null && (string) $current === (string) $previousValue);
        };

        if ($replaceable('proposal_title', $previous['title'] ?? null)) {
            $values['proposal_title'] = $data['title'] ?? null;
        }

        if (self::b43()) {
            $owner = $this->defaultProposalOwnerId($data);

            if ($owner !== null && $replaceable('owner_employee_id', $previous === [] ? null : $this->defaultProposalOwnerId($previous))) {
                $values['owner_employee_id'] = $owner;
            }

            $capacity = $data['ges_capacity_mw'] ?? null;
            $current = $get('scopes.ges.capacity_mwp');
            $previousCapacity = $previous['ges_capacity_mw'] ?? null;

            if ($capacity !== null && (blank($current) || ($previousCapacity !== null && is_numeric($current) && (float) $current === $previousCapacity))) {
                $values['scopes.ges.capacity_mwp'] = $capacity;
            }
        }

        return $values;
    }

    /**
     * D-183: yeni teklifin varsayilan sorumlusu. Potansiyel isin "Teklif
     * sorumlusu" seciliyse o, yoksa giris yapan kisi; potansiyel isin sahibi
     * hicbir zaman (D-175: is gelistirme sahibi teklif sahibi olmaz). Gizli
     * hesap secenekte olmadigi icin yazilmaz.
     *
     * @param  array<string, mixed>  $caseData
     */
    private function defaultProposalOwnerId(array $caseData): ?int
    {
        $personnel = app(PersonnelQueries::class);

        foreach ([$caseData['proposal_owner_employee_id'] ?? null, auth()->id()] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0 && $personnel->name((int) $candidate) !== null) {
                return (int) $candidate;
            }
        }

        return null;
    }

    /**
     * Ihale ekranlari (B43, D-155): ayni dort adim. Ihale adimi formdur; potansiyel
     * is, teklif ve proje adimlari bagli potansiyel isin ozeti ve baglantilari.
     * Ihalenin potansiyel isi yoksa ihaleden potansiyel is acma yolu gosterilir.
     *
     * @return list<Step>
     */
    public function tenderSteps(?TenderNotice $notice): array
    {
        $case = $notice?->businessCase;

        return [
            Step::make(__('business_case.wizard.tender'))
                ->id(self::STEP_TENDER)
                ->description(__('business_case.wizard.tender_form_description'))
                ->icon(Heroicon::OutlinedMegaphone)
                ->completedIcon(Heroicon::OutlinedMegaphone)
                ->columns(1)
                ->schema(app(TenderSchema::class)->sections(editing: $notice !== null)),
            Step::make(__('business_case.wizard.case'))
                ->id(self::STEP_CASE)
                ->description($case !== null ? trim(($case->caseCode()?->formatted_code ?? '').' · '.$case->title, ' ·') : __('tender_notice.steps.no_case'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->completedIcon(Heroicon::OutlinedBriefcase)
                ->columns(1)
                ->schema($this->tenderCaseComponents($notice)),
            $case !== null ? $this->proposalTableStep($case) : Step::make(__('business_case.wizard.proposal'))
                ->id(self::STEP_PROPOSAL)
                ->description(__('business_case.steps.no_proposal'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
                ->schema([Text::make(__('tender_notice.help.proposal_after_case'))->color('gray')]),
            $case !== null ? $this->projectStep($case) : Step::make(__('business_case.wizard.project'))
                ->id(self::STEP_PROJECT)
                ->description(__('business_case.steps.no_project'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->completedIcon(Heroicon::OutlinedRocketLaunch)
                ->schema([Text::make(__('tender_notice.help.project_after_case'))->color('gray')]),
        ];
    }

    /**
     * Ihale ekraninin potansiyel is adimi: bagli is varsa ozeti; olusturmada
     * "kaydettikten sonra potansiyel is ac" secimi; duzenlemede ihaleden
     * potansiyel is acma dugmesi.
     *
     * @return list<Component>
     */
    private function tenderCaseComponents(?TenderNotice $notice): array
    {
        $case = $notice?->businessCase;

        if ($case !== null) {
            $caseId = (int) $case->getKey();

            return [
                $this->caseSummarySection('tender', fn (): Closure => fn (string $path): mixed => data_get($this->caseSummaryData($caseId), $path)),
                Actions::make([
                    Action::make('open_case')
                        ->label(__('deal_track.go_case'))
                        ->icon(Heroicon::OutlinedBriefcase)
                        ->color(ActionColors::VIEW)
                        ->url(BusinessCaseResource::getUrl('view', ['record' => $case]))
                        ->visible(Gate::allows('view', $case)),
                ]),
            ];
        }

        if ($notice === null) {
            return [
                Callout::make(__('tender_notice.help.case_after_save'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->info(),
                Toggle::make('continue_to_case')
                    ->label(__('tender_notice.fields.continue_to_case'))
                    ->default(true)
                    ->dehydrated(false),
            ];
        }

        return [
            Callout::make(__('tender_notice.help.no_case_yet'))
                ->icon(Heroicon::OutlinedInformationCircle)
                ->warning(),
            Actions::make([
                Action::make('create_case_from_tender')
                    ->label(__('tender_notice.actions.create_case'))
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->color(ActionColors::CREATE)
                    ->url(BusinessCaseResource::getUrl('create', [self::QUERY_TENDER => $notice->getKey()]))
                    ->visible(Gate::allows('create', BusinessCase::class)),
            ]),
        ];
    }

    /** Adim 1 (form): is dosyasi alanlari. */
    public function caseStep(): Step
    {
        return Step::make(__('business_case.wizard.case'))
            ->id(self::STEP_CASE)
            ->description(__('business_case.wizard.case_description'))
            ->icon(Heroicon::OutlinedBriefcase)
            ->completedIcon(Heroicon::OutlinedBriefcase)
            // Iki sutun caseSections() icindeki Grid::make(['xl' => 2]) ile
            // kurulur (16 Eylul 2026 kullanici karari); adimin kendisi tek
            // sutun kalir, tek cocugu (o Grid) her zaman tam genislik alir.
            ->columns(1)
            ->schema($this->caseSections());
    }

    /**
     * Is dosyasi ayrintilari: kartta olmayan ve olusturmada girilen her sey
     * (aciklama, kaynak, teklif tipi, proje kategorisi, ulke, para birimi,
     * tuzel kisilik, gizlilik sinifi, olusturulma, proje kapsamlari). Sayfanin
     * ustunde kartin yaninda yarim genislikte durur (22 Eylul 2026).
     */
    public function detailsCard(BusinessCase $case): Component
    {
        $offerType = self::enumCase($case->getAttribute('offer_type'), OfferType::class);

        $typeEntry = self::b29()
            ? TextEntry::make('offer_type')
                ->label(__('business_case.fields.offer_type'))
                ->state($offerType?->getLabel() ?? '-')
                ->badge()
                ->color($offerType?->getColor() ?? 'gray')
            : TextEntry::make('criticality')
                ->label(__('business_case.fields.criticality'))
                ->state($case->criticality?->getLabel() ?? '-')
                ->badge()
                ->color($case->criticality?->getColor() ?? 'gray');

        return Section::make(__('business_case.sections.details'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->compact()
            ->components([
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components([
                    TextEntry::make('short_description')
                        ->label(__('business_case.fields.short_description'))
                        ->state($case->short_description ?? '-')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->iconColor('gray')
                        ->columnSpanFull(),
                    TextEntry::make('source_kind')
                        ->label(__('business_case.fields.source_kind'))
                        ->state($case->source_kind?->getLabel() ?? '-')
                        ->badge()
                        ->color('gray'),
                    $typeEntry,
                    TextEntry::make('project_type_code')
                        ->label(__('business_case.fields.project_type_code'))
                        ->state($this->projectTypeName($case->project_type_code))
                        ->icon(Heroicon::OutlinedCube)
                        ->iconColor('gray'),
                    TextEntry::make('country')
                        ->label(__('business_case.fields.country'))
                        ->state($case->country?->localizedName() ?? $case->country_code ?? '-')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->iconColor('gray'),
                    TextEntry::make('currency')
                        ->label(__('business_case.fields.currency'))
                        ->state(Money::label($case->currency_code))
                        ->icon(Heroicon::OutlinedCurrencyDollar)
                        ->iconColor('gray'),
                    TextEntry::make('legal_entity')
                        ->label(__('business_case.fields.legal_entity'))
                        ->state($case->legalEntity?->legal_name ?? '-')
                        ->icon(Heroicon::OutlinedBuildingLibrary)
                        ->iconColor('gray'),
                    TextEntry::make('classification')
                        ->label(__('business_case.fields.classification'))
                        ->state($case->classification?->name_tr ?? '-')
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->iconColor('gray'),
                    TextEntry::make('created_at')
                        ->label(__('business_case.fields.created_at'))
                        ->state(DisplayTime::format($case->created_at))
                        ->icon(Heroicon::OutlinedClock)
                        ->iconColor('gray'),
                    ...(self::b43() ? $this->chainDetailEntries($case) : $this->scopeDetailEntries($case)),
                ]),
            ]);
    }

    /**
     * B43 ayrintilari: bagli ihaleler, proje durumu ve teklif sicakligi
     * (kapsam tutarlari artik teklif sayfasinda).
     *
     * @return list<Component>
     */
    private function chainDetailEntries(BusinessCase $case): array
    {
        $tenders = app(TenderQueries::class)->forCase((int) $case->getKey());
        $first = $tenders->first();
        $entries = [
            TextEntry::make('tenders')
                ->label(__('business_case.sections.tender'))
                ->state($tenders->isEmpty() ? [__('business_case.help.no_tender')] : $tenders->map(fn (TenderNotice $notice): string => app(TenderSchema::class)->summaryLine($notice))->all())
                ->listWithLineBreaks()
                ->icon(Heroicon::OutlinedMegaphone)
                ->iconColor($tenders->isEmpty() ? 'gray' : 'primary')
                ->color($tenders->isEmpty() ? 'gray' : 'primary')
                ->url($first !== null && Gate::allows('view', $first) ? TenderNoticeResource::getUrl('view', ['record' => $first]) : null)
                ->columnSpanFull(),
        ];

        // Teklif sicakligi yalniz kontrol listesi tahtasinda (D-158: "3 tane teklif
        // sicakligi alani var, 1 tane yeterli").
        if (ChecklistSchema::enabled()) {
            $license = $case->license_status;
            $entries[] = TextEntry::make('license_status')
                ->label(__('checklist.license_status'))
                ->state($license?->getLabel() ?? '-')
                ->badge()
                ->color($license?->getColor() ?? 'gray');
        }

        return $entries;
    }

    /**
     * Adim 2 (duzenleme): tekliflerin ozeti. Tam tablo (ac, duzenle, teklif
     * olustur) sayfanin altindaki "Teklifler" sekmesindedir (D-143); burada
     * ayrica gomulu tablo yok, iki kez gorunmesin (29 Eylul 2026). D-181: ozet
     * ve "Teklifi duzenle" en son teklifi kullanir (secili teklif kavrami yok).
     */
    public function proposalTableStep(BusinessCase $case): Step
    {
        $count = $case->proposals()->count();
        $latest = $case->latestProposal();

        // B43 (D-155): teklif olustur / duzenle ayni adimli ekrana gider.
        $actions = self::b43() ? array_values(array_filter([
            Gate::allows('create', Proposal::class)
                ? Action::make('step_create_proposal')
                    ->label(__('deal_track.create_proposal'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->color(ActionColors::CREATE)
                    ->url(ProposalResource::getUrl('create', ['business_case_id' => $case->getKey()]))
                : null,
            $latest !== null && Gate::allows('update', $latest)
                ? Action::make('step_edit_proposal')
                    ->label(__('proposal.actions.edit_latest', ['no' => $latest->proposal_no]))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color(ActionColors::EDIT)
                    ->url(ProposalResource::getUrl('edit', ['record' => $latest]))
                : null,
        ])) : [];

        // D-167: teklife donusmus iste adimin basligi isin burada oldugunu soyler.
        return Step::make(__('business_case.wizard.proposal'))
            ->id(self::STEP_PROPOSAL)
            ->description($count === 0
                ? __('business_case.steps.no_proposal')
                : __('business_case.steps.now_here').' · '.__('business_case.steps.proposal_summary', ['count' => $count, 'latest' => $latest?->proposal_no ?? '-']))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
            ->formWrapper(false)
            ->schema([
                Callout::make(__('business_case.help.proposal_table'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->info(),
                ...($actions !== [] ? [Actions::make($actions)] : []),
                // Olusturmada 2. adimda girilenler (22 Eylul 2026): en son teklifin ozeti (D-181).
                ...($latest !== null ? [$this->recordProposalSummary($latest)] : []),
            ]);
    }

    /**
     * Adim 3 (duzenleme/goruntuleme): proje varsa baglanti, yoksa donusum.
     * Teklif sayfasinda $proposal verilir; donusum o teklifle yapilir.
     */
    public function projectStep(BusinessCase $case, ?Proposal $proposal = null): Step
    {
        $project = $case->project;
        $handoff = $case->operationHandoff;
        $hasProposal = $proposal !== null || $case->proposals()->exists();

        $components = [];

        if ($project !== null) {
            $components[] = Grid::make(['default' => 1, 'md' => 3])->components([
                TextEntry::make('project_name')
                    ->label(__('business_case.fields.project'))
                    ->state((string) $project->display_name)
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->iconColor('primary')
                    ->color('primary')
                    ->weight(FontWeight::SemiBold)
                    ->url(ProjectResource::getUrl('view', ['record' => $project])),
                TextEntry::make('project_status')
                    ->label(__('project.fields.status'))
                    ->state($project->status->getLabel())
                    ->badge()
                    ->color($project->status->getColor()),
                TextEntry::make('project_focus')
                    ->label(__('project.fields.current_focus'))
                    ->state($project->primaryFocusWorkstream?->group?->localizedName() ?? '-')
                    ->badge()
                    ->color('primary'),
                // Donusumde girilenler (22 Eylul 2026): yonetici, tarihler, santiye.
                TextEntry::make('project_manager')
                    ->label(__('project.fields.project_manager'))
                    ->state($project->projectManager?->full_name ?? '-')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->iconColor('primary'),
                TextEntry::make('project_planned_start_on')
                    ->label(__('project.fields.planned_start_on'))
                    ->state($project->planned_start_on?->format('d.m.Y') ?? '-')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->iconColor('gray'),
                TextEntry::make('project_planned_finish_on')
                    ->label(__('project.fields.planned_finish_on'))
                    ->state($project->planned_finish_on?->format('d.m.Y') ?? '-')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->iconColor('gray'),
                TextEntry::make('project_site')
                    ->label(__('business_case.sections.site'))
                    ->state(implode(' · ', array_filter([
                        $project->site_address_line1,
                        trim(implode(' / ', array_filter([$project->site_district, $project->site_city]))),
                    ])) ?: '-')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->iconColor('gray')
                    ->columnSpanFull(),
            ]);
            $components[] = Actions::make([
                Action::make('open_project')
                    ->label(__('project.actions.open_workspace'))
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->url(ProjectResource::getUrl('view', ['record' => $project])),
            ]);
        } else {
            $components[] = Callout::make(__('business_case.help.project_missing'))
                ->icon(Heroicon::OutlinedInformationCircle)
                ->color($hasProposal ? 'warning' : 'gray');

            if (! $hasProposal) {
                $components[] = Text::make(__('business_case.help.convert_requires_proposal'))->color('warning');
            }

            if ($handoff !== null) {
                $components[] = TextEntry::make('handoff_status')
                    ->label(__('business_case.fields.handoff_status'))
                    ->state($handoff->status->getLabel())
                    ->badge()
                    ->color($handoff->status->getColor())
                    ->url(OperationHandoffResource::getUrl('view', ['record' => $handoff]));
            }

            $components[] = Actions::make([$this->convertAction($case, proposal: $proposal)]);
        }

        return Step::make(__('business_case.wizard.project'))
            ->id(self::STEP_PROJECT)
            ->description($project !== null
                ? __('business_case.steps.project_created', ['code' => $project->businessCode?->formatted_code ?? '-'])
                : __('business_case.steps.no_project'))
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->completedIcon(Heroicon::OutlinedRocketLaunch)
            ->formWrapper(false)
            ->schema($components);
    }

    /**
     * "Projeye donustur" islemi; Teklif/Is Dosyasi sayfalarindaki ile ayni
     * girdi (ProjectConversionForm) ve servis. Basarida projeye yonlendirir.
     */
    public function convertAction(BusinessCase $case, string $name = 'convert_to_project', ?Proposal $proposal = null): Action
    {
        // Teklif sayfasinda o teklif, potansiyel iste en son teklif donusur (D-181: secili teklif yok).
        $target = static fn () => $proposal ?? $case->latestProposal();

        return Action::make($name)
            ->label(__('project.actions.convert'))
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color(ActionColors::SAVE)
            ->modalHeading(__('project.actions.convert'))
            ->modalDescription(__('project.help.convert_intro'))
            ->modalSubmitActionLabel(__('project.actions.convert'))
            // Kaybedilen / iptal edilen isten projeye gecis yok (durum gecisi izin vermez, D-143).
            ->visible(fn (): bool => Gate::allows('update', $case)
                && $case->project()->doesntExist()
                && ! in_array($case->outcome, [BusinessOutcome::Lost, BusinessOutcome::Cancelled], true)
                && $target() !== null)
            ->schema(function () use ($target): array {
                $proposal = $target();

                return $proposal === null ? [] : ProjectConversionForm::components($proposal);
            })
            ->action(function (array $data, LivewireComponent $livewire) use ($target): void {
                $proposal = $target();

                if ($proposal === null) {
                    return;
                }

                try {
                    $project = app(ProjectConversionService::class)->convertProposal($proposal, $data);
                    DomainNotifications::success(__('project.messages.converted', ['code' => $project->businessCode?->formatted_code ?? '-']));
                    $livewire->redirect(ProjectResource::getUrl('view', ['record' => $project]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Is dosyasi karti: baslik, rozetler ve baglantili alanlar. */
    public function headerCard(BusinessCase $case): Component
    {
        $customer = $case->primaryParty;
        $owner = $case->owner;
        $proposalOwner = $case->proposalOwner;
        $project = $case->project;
        $stage = $case->acquisition_stage;

        $badges = [
            Text::make($case->caseCode()?->formatted_code ?? '-')->badge()->color('gray')->icon(Heroicon::OutlinedHashtag),
            Text::make($stage->getLabel())->badge()->color($stage->getColor()),
            Text::make($case->outcome?->getLabel() ?? '-')->badge()->color($case->outcome?->getColor() ?? 'gray'),
        ];

        // D-167: Is Gelistirme durumunda tur (sicaklik 0 = Yatirimci projesi, > 0 = Potansiyel is).
        $kind = $case->developmentKind();

        if ($kind !== null) {
            $badges[] = Text::make(__('business_case.kinds.'.$kind))->badge()->color($kind === 'potential_job' ? 'amber' : 'slate')->icon(Heroicon::OutlinedSparkles);
        }

        if (self::b29()) {
            $offerType = self::enumCase($case->getAttribute('offer_type'), OfferType::class);

            if ($offerType !== null) {
                $badges[] = Text::make($offerType->getLabel())->badge()->color($offerType->getColor())->icon(Heroicon::OutlinedTag);
            }

            foreach ($case->scopes as $scope) {
                $type = self::scopeType($scope);
                $badges[] = Text::make($type?->getLabel() ?? self::scopeTypeValue($scope))
                    ->badge()
                    ->color($type?->getColor() ?? 'gray')
                    ->icon($type?->getIcon() ?? Heroicon::OutlinedCube);
            }
        }

        if ($project !== null) {
            $badges[] = Text::make($project->businessCode?->formatted_code ?? '-')->badge()->color('success')->icon(Heroicon::OutlinedRocketLaunch);
        }

        // B43 (D-155): taslak isareti ve teklif sicakligi (kalp atisi).
        if (DraftSupport::enabled() && (bool) $case->getAttribute('is_draft')) {
            $badges[] = Text::make(DraftSupport::label($case->getAttribute('draft_step')))->badge()->color('gray')->icon(Heroicon::OutlinedPencilSquare);
        }

        $entries = [
            TextEntry::make('primary_party')
                ->label(__('business_case.fields.primary_party'))
                ->state($customer?->display_name ?? '-')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->iconColor('primary')
                ->color($customer !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($customer !== null && Gate::allows('view', $customer) ? PartyResource::getUrl('view', ['record' => $customer]) : null),
            TextEntry::make('owner')
                ->label(__('business_case.fields.owner'))
                ->state($owner?->full_name ?? '-')
                ->icon(Heroicon::OutlinedUserCircle)
                ->iconColor('primary')
                ->color($owner !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($owner !== null && Gate::allows('view', $owner) ? PersonnelResource::getUrl('view', ['record' => $owner]) : null),
            TextEntry::make('proposal_owner')
                ->label(__('business_case.fields.proposal_owner'))
                ->state($proposalOwner?->full_name ?? '-')
                ->icon(Heroicon::OutlinedUser)
                ->iconColor('gray')
                ->color($proposalOwner !== null ? 'primary' : 'gray')
                ->url($proposalOwner !== null && Gate::allows('view', $proposalOwner) ? PersonnelResource::getUrl('view', ['record' => $proposalOwner]) : null),
            TextEntry::make('estimated_value')
                ->label(__('business_case.fields.estimated_value'))
                ->state(Money::format($case->estimated_value, $case->currency_code))
                ->icon(Heroicon::OutlinedBanknotes)
                ->iconColor('success')
                ->weight(FontWeight::SemiBold),
            TextEntry::make('proposal_count')
                ->label(__('business_case.fields.proposal_count'))
                ->state((string) $case->proposals()->count())
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->iconColor('gray'),
            TextEntry::make('project')
                ->label(__('business_case.fields.project'))
                ->state($project?->display_name ?? __('business_case.steps.no_project'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->iconColor($project !== null ? 'success' : 'gray')
                ->color($project !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($project !== null ? ProjectResource::getUrl('view', ['record' => $project]) : null),
        ];

        return Section::make(__('business_case.sections.header'))
            ->icon(Heroicon::OutlinedBriefcase)
            ->compact()
            ->components([
                Text::make((string) $case->title)->size(TextSize::Large)->weight(FontWeight::Bold),
                Flex::make($badges),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries),
            ]);
    }

    /**
     * Acilacak adim: istenen kimlik, taslaksa kaldigi adim (B43), yoksa
     * zincirde gelinen nokta.
     */
    /**
     * Duzenleme sihirbazinin acilacagi adim. D-160 (6 Ekim 2026 kullanici
     * talimati: "Potansiyel is duzenleye basiyorum, teklif duzenleme adimina
     * atiyor ... hangi ekranda duzenleye bastiysam onun duzenleme adimina
     * atmalidir"): adim istenmediyse her zaman potansiyel is adimi; zincirdeki
     * en ileri adim (teklif / proje) acilmaz. ?step= ile istenen adim gecerlidir
     * (taslak listesinden "kaldigi yerden devam": resumeStepId).
     */
    public function startStep(BusinessCase $case, ?string $requestedId): int
    {
        return self::stepNumber($requestedId) ?? (int) self::stepNumber(self::STEP_CASE);
    }

    /**
     * Taslagin kaldigi adim (B43: "hangi adimda kaldiysa devam edilebilir");
     * taslak degilse potansiyel is adimi. Yalniz taslak satirinin
     * tiklanmasinda kullanilir; "Duzenle" dugmesi potansiyel is adimini acar.
     */
    public function resumeStepId(BusinessCase $case): string
    {
        if (DraftSupport::enabled() && (bool) $case->getAttribute('is_draft') && self::stepNumber($case->getAttribute('draft_step')) !== null) {
            return (string) $case->getAttribute('draft_step');
        }

        return self::STEP_CASE;
    }

    /**
     * Duzenleme formu icin kayitli kapsamlar (B29): secili tipler ve tip
     * basina sayisal alanlar. Dosya alanlari bos birakilir; yeni yukleme
     * kapsam listesinin yeni surumu olur (BusinessCaseScopeService::sync).
     *
     * @return array{scope_types: list<string>, scopes: array<string, array<string, string|null>>}
     */
    public function scopeFormData(BusinessCase $case): array
    {
        $types = [];
        $scopes = [];

        foreach ($case->scopes as $scope) {
            $value = self::scopeTypeValue($scope);
            $types[] = $value;
            $row = [];

            // B43: tutarlar teklif kapsamindadir; potansiyel iste yalniz tip.
            if (self::b43()) {
                continue;
            }

            foreach (self::SCOPE_FIELDS[$value] ?? [] as $field) {
                $raw = $scope->getAttribute($field);
                $row[$field] = $raw === null ? null : (string) $raw;
            }

            $scopes[$value] = $row;
        }

        return ['scope_types' => $types, 'scopes' => $scopes];
    }

    /**
     * Onceki adimlarin ozet karti (16 Eylul 2026 kullanici istegi): sihirbazda
     * "Is dosyasi -> Teklif -> Proje" ilerlerken onceki adimlarin verisi kayit
     * karti gorunumunde ve formdaki guncel degerlerle (Get) gosterilir.
     * $stage 'proposal' ise yalniz is dosyasi ozeti, 'project' ise teklif
     * ozeti de eklenir.
     */
    public function summaryCard(string $stage): Component
    {
        if ($stage !== self::STEP_PROJECT) {
            return $this->caseSummarySection($stage);
        }

        return Group::make([
            $this->caseSummarySection($stage),
            $this->proposalSummarySection(),
        ])->columnSpanFull();
    }

    /**
     * Olusturma adim 2: ilk teklif ve surumu; B29 ile teklif durumu ve
     * teklif belgeleri (firmanin beklentileri, teklif mektubu, sabit
     * referans belgesi ve genel katalog baglantisi).
     *
     * B43 (D-155): baslik yarim genislikte, marj girilmez (kapsamdan), belgeler
     * ProposalFilesSchema'da; duzenlemede ($editing) teklif sorumlusu ve "yeni
     * surum" notu eklenir.
     *
     * @return list<Component>
     */
    private function proposalFields(bool $standalone = false, ?Proposal $editing = null): array
    {
        $whenProposal = fn (Get $get): bool => (bool) $get('create_proposal');
        $whenProposalB29 = fn (Get $get): bool => (bool) $get('create_proposal') && self::b29();
        $b29 = fn (): bool => self::b29();
        $b43 = self::b43();

        $documentFields = $b43 ? [] : [
            FileUpload::make('customer_expectations_file')
                ->label(__('business_case.fields.customer_expectations_file'))
                ->helperText(__('business_case.help.customer_expectations_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn('customer_expectations_file_name')
                ->maxSize(UploadLimits::documentMaxKb())
                ->visible($whenProposalB29)
                ->columnSpan(FieldGrid::LONG),
            Hidden::make('customer_expectations_file_name'),
            FileUpload::make('proposal_letter_file')
                ->label(__('business_case.fields.proposal_letter_file'))
                ->helperText(__('business_case.help.proposal_letter_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn('proposal_letter_file_name')
                ->maxSize(UploadLimits::documentMaxKb())
                ->visible($whenProposalB29)
                ->columnSpan(FieldGrid::LONG),
            Hidden::make('proposal_letter_file_name'),
            Toggle::make('attach_references')
                ->label(__('business_case.fields.attach_references'))
                ->helperText(__('business_case.help.attach_references'))
                ->default(true)
                ->inline(false)
                ->visible(fn (Get $get): bool => $whenProposalB29($get) && $this->hasReferenceDocument()),
            Toggle::make('attach_catalog')
                ->label(__('business_case.fields.attach_catalog'))
                ->helperText(__('business_case.help.attach_catalog'))
                ->default(true)
                ->inline(false)
                ->visible(fn (Get $get): bool => $whenProposalB29($get) && $this->hasCatalogDocument()),
            // D-183: "sabit belge yuklenmedi" uyari metni kullanici istegiyle kaldirildi.
        ];

        // D-186: "Kaydettiginizde ... yeni surum olusur" notu kaldirildi; Duzenle
        // surum artirmaz, yeni surum "Yeni teklif surumu" dugmesiyle acilir.
        return [
            // Teklif olustur ekraninda teklif her zaman olusur; anahtar gizli ve acik.
            $standalone
                ? Hidden::make('create_proposal')->default(true)
                : Toggle::make('create_proposal')
                    ->label(__('business_case.fields.create_proposal'))
                    ->helperText(__('business_case.help.proposal_step'))
                    ->default(true)
                    ->live()
                    ->columnSpan(FieldGrid::HALF)
                    ->columnStart(1),
            TextInput::make('proposal_title')
                ->label(__('business_case.fields.proposal_title'))
                // D-185: "Bos birakilirsa potansiyel is basligi kullanilir" yardim metni
                // kaldirildi (kullanici talebi); davranis ayni.
                ->maxLength(255)
                ->visible($whenProposal)
                // B43 (D-155): baslik yarim genislik; tam satir hic yok (D-157).
                ->columnSpan($b43 ? FieldGrid::HALF : FieldGrid::WIDE)
                ->columnStart(1),
            // D-183: Teklif olustur ekraninda da teklif sorumlusu var; potansiyel is
            // secilince proposalAutofill doldurur (isin teklif sorumlusu, yoksa
            // giris yapan kisi; isin sahibi hicbir zaman, D-175).
            ...($b43 && ($editing !== null || $standalone) ? [
                Select::make('owner_employee_id')
                    ->label(__('proposal.fields.owner'))
                    ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                    ->searchable()
                    ->native(false)
                    ->columnSpan(FieldGrid::NORMAL),
            ] : []),
            MoneyInput::make('total_price')
                ->label(__('proposal_version.fields.total_price'))
                // D-185: kapsam toplami yardim metni kaldirildi; bos tutar yine kapsamdan dolar.
                ->visible($whenProposal),
            ...($b43 ? [] : [
                TextInput::make('margin_pct')
                    ->label(__('proposal_version.fields.margin_pct'))
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0)
                    ->maxValue(100)
                    ->visible($whenProposal),
            ]),
            DatePicker::make('validity_until')
                ->label(__('proposal_version.fields.validity_until'))
                ->displayFormat('d.m.Y')
                ->visible($whenProposal),
            Toggle::make('is_critical_route')
                ->label(__('proposal_version.fields.is_critical_route'))
                ->inline(false)
                ->visible($whenProposal),
            Textarea::make('summary')
                ->label(__('proposal_version.fields.summary'))
                ->visible($whenProposal)
                ->columnSpan(FieldGrid::LONG),
            // D-182: teklif duzenlemede Teklif durumu formda yok; baslikta
            // "Degisiklikleri kaydet"in yanindaki acilir dugmeyle degisir.
            ...($editing === null ? [
                Select::make('offer_status')
                    ->label(__('proposal.fields.offer_status'))
                    ->options(OfferStatus::class)
                    ->default('to_be_submitted')
                    ->native(false)
                    ->visible($whenProposalB29)
                    ->dehydrated($b29),
            ] : []),
            ...$documentFields,
        ];
    }

    /**
     * Olusturma adim 3: hemen projeye donusum.
     *
     * @return list<Component>
     */
    private function projectFields(): array
    {
        $whenConvert = fn (Get $get): bool => (bool) $get('create_proposal') && (bool) $get('convert_now');

        return [
            Toggle::make('convert_now')
                ->label(__('business_case.fields.convert_now'))
                ->helperText(__('business_case.help.project_step'))
                ->default(false)
                ->live()
                ->disabled(fn (Get $get): bool => ! $get('create_proposal'))
                ->columnSpan(FieldGrid::HALF),
            Text::make(__('business_case.help.convert_requires_proposal'))
                ->color('warning')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->visible(fn (Get $get): bool => ! $get('create_proposal'))
                ->columnSpanFull(),
            TextInput::make('project_name')
                // D-174: projenin adi Lisans adidir.
                ->label(ProjectNames::shortNameEnabled() ? __('project.fields.license_name') : __('business_case.fields.project_name'))
                ->helperText(__('business_case.help.project_name'))
                ->maxLength(255)
                ->visible($whenConvert)
                ->columnSpan(FieldGrid::WIDE)
                ->columnStart(1),
            Select::make('project_manager_employee_id')
                ->label(__('project.fields.project_manager'))
                ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                ->searchable()
                ->required($whenConvert)
                ->visible($whenConvert)
                ->native(false),
            DatePicker::make('planned_start_on')
                ->label(__('project.fields.planned_start_on'))
                ->displayFormat('d.m.Y')
                ->visible($whenConvert),
            DatePicker::make('planned_finish_on')
                ->label(__('project.fields.planned_finish_on'))
                ->displayFormat('d.m.Y')
                ->afterOrEqual('planned_start_on')
                ->visible($whenConvert),
            TextInput::make('site_address_line1')
                ->label(__('project.fields.site_address_line1'))
                ->maxLength(255)
                ->visible($whenConvert)
                ->columnSpan(FieldGrid::WIDE),
            ...TurkiyeAddressFields::make('site_city', 'site_district', __('project.fields.site_city'), __('project.fields.site_district'), 'country_code', visible: $whenConvert),
        ];
    }

    // ---------------------------------------------------------------------
    // B29: proje kapsam bolumleri
    // ---------------------------------------------------------------------

    /**
     * Secilen her proje tipi icin ayri kapsam bolumu (B29): tipin sayisal
     * alanlari, hesaplanan toplam ve kapsam listesi (Excel) yuklemesi. Bolum
     * yalniz tip "Proje tip secimi"nde isaretliyken gorunur; gizliyken
     * alanlari kaydedilmez.
     *
     * @return list<Component>
     */
    private function scopeSections(): array
    {
        $sections = [];

        foreach (ProjectScopeType::cases() as $type) {
            $sections[] = Section::make(__('business_case_scope.sections.'.$type->value))
                ->icon($type->getIcon())
                ->iconColor($type->getColor())
                // Bu bolum artik sag sutunda (yarim genislikte) durur; kendi ic
                // izgarasi da HALF_COLUMNS olmali, yoksa kisa alanlar ezilir.
                ->columns(FieldGrid::HALF_COLUMNS)
                ->visible(fn (Get $get): bool => in_array($type->value, self::selectedScopeTypes($get), true))
                ->components(FieldGrid::fields([
                    ...$this->scopeTypeFields($type),
                    ...$this->scopeFileFields($type),
                ]));
        }

        return $sections;
    }

    /**
     * Tipin kendi alanlari: GES (kurulu guc, maliyet, satis, MW basi
     * maliyet/satis + hesaplanan toplam satis), RES (uc Respark kalemi +
     * toplam), TM (toplam maliyet/satis, fider basi maliyet); digerleri icin
     * yalniz "alanlar sonra" notu.
     *
     * @return list<Component>
     */
    private function scopeTypeFields(ProjectScopeType $type): array
    {
        return match ($type->value) {
            'ges' => [
                $this->scopeAmountInput($type, 'capacity_mw', '0.001', live: true),
                $this->scopeAmountInput($type, 'cost_amount'),
                $this->scopeAmountInput($type, 'sales_amount', live: true),
                $this->scopeAmountInput($type, 'cost_per_mw'),
                $this->scopeAmountInput($type, 'sales_per_mw', live: true),
                $this->scopeComputedEntry('scopes.ges.total_sales', __('business_case_scope.fields.total_sales'), fn (Get $get): string => $this->gesTotalSales($get))
                    ->columnSpan(FieldGrid::SHORT),
            ],
            'res' => [
                $this->scopeAmountInput($type, 'res_material_amount', live: true),
                $this->scopeAmountInput($type, 'res_construction_amount', live: true),
                $this->scopeAmountInput($type, 'res_assembly_amount', live: true),
                $this->scopeComputedEntry('scopes.res.total', __('business_case_scope.fields.total'), fn (Get $get): string => $this->resTotal($get)),
            ],
            'tm' => [
                $this->scopeAmountInput($type, 'tm_total_cost'),
                $this->scopeAmountInput($type, 'tm_total_sales'),
                $this->scopeAmountInput($type, 'tm_feeder_cost'),
            ],
            // HES (16 Eylul 2026 kullanici talimati): Maliyet/Satis GES ile
            // ayni kolonlari paylasir (scopeAmountInput 'scopes.hes.*' yolunu
            // kullanir, satirlar karismaz); ucuncu kalem B30 ile eklenen
            // hes_unit_cost'tur ve yalniz o grup uygulaninca gorunur.
            'hes' => [
                $this->scopeAmountInput($type, 'cost_amount'),
                $this->scopeAmountInput($type, 'sales_amount', live: true),
                $this->scopeAmountInput($type, 'hes_unit_cost')
                    ->visible(fn (): bool => self::b30())
                    ->dehydrated(fn (): bool => self::b30()),
            ],
            default => [
                Text::make(__('business_case_scope.help.fields_later'))
                    ->color('gray')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->columnSpanFull(),
            ],
        };
    }

    /**
     * Kapsam listesi: kayitli dosya (duzenleme/goruntuleme), yeni yukleme ve
     * orijinal dosya adi. Yeni yukleme eskisinin yerine yeni surum olur.
     *
     * @return list<Component>
     */
    private function scopeFileFields(ProjectScopeType $type): array
    {
        $path = 'scopes.'.$type->value;

        return [
            TextEntry::make($path.'.current_file')
                ->label(__('business_case_scope.fields.current_file'))
                ->state(fn (?Model $record): string => self::documentLine($this->scopeDocumentInfo($this->recordScope($record, $type))))
                ->url(fn (?Model $record): ?string => $this->scopeDocumentInfo($this->recordScope($record, $type))['url'] ?? null)
                ->visible(fn (?Model $record): bool => $this->recordScope($record, $type)?->scopeDocument !== null)
                ->dehydrated(false)
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->iconColor('primary')
                ->color('primary')
                ->weight(FontWeight::Medium)
                ->columnSpan(FieldGrid::HALF_LONG)
                ->columnStart(1),
            // Yarim genislikteki kapsam bolumunde 4/6 (D-157: tam satir yok).
            // D-183: aciklama metni kullanici istegiyle kaldirildi.
            FileUpload::make($path.'.scope_file')
                ->label(__('business_case_scope.fields.scope_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn($path.'.scope_file_name')
                ->acceptedFileTypes(self::SCOPE_FILE_TYPES)
                ->maxSize(UploadLimits::documentMaxKb())
                ->columnSpan(FieldGrid::HALF_LONG)
                ->columnStart(1),
            Hidden::make($path.'.scope_file_name'),
        ];
    }

    private function scopeAmountInput(ProjectScopeType $type, string $field, string $step = '0.01', bool $live = false): TextInput
    {
        // D-180: tutarlar MoneyInput (Turkce maske + para birimi simgesi);
        // kurulu guc (MW) duz sayi kalir.
        $input = ($field === 'capacity_mw'
            ? TextInput::make('scopes.'.$type->value.'.'.$field)->numeric()->step($step)->minValue(0)
            : MoneyInput::make('scopes.'.$type->value.'.'.$field))
            ->label(__('business_case_scope.fields.'.$field))
            // Kisa alan (SHORT) etiketleri sikistirip harf harf sardiriyordu
            // (16 Eylul 2026 kullanici bildirimi, ornek: "MW başı maliyet",
            // "Fider başı maliyet"); tutar alanlari yarim genislikteki
            // bolumde satir basina iki alan (NORMAL) olacak sekilde genisler.
            ->columnSpan(FieldGrid::NORMAL);

        return $live ? $input->live(onBlur: true) : $input;
    }

    /** Formda hesaplanan, kaydedilmeyen deger (toplam satis / toplam). */
    private function scopeComputedEntry(string $name, string $label, Closure $state): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state($state)
            ->dehydrated(false)
            ->icon(Heroicon::OutlinedCalculator)
            ->iconColor('success')
            ->weight(FontWeight::SemiBold)
            ->columnSpan(FieldGrid::NORMAL);
    }

    /** GES toplam satis: kurulu guc x MW basi satis; ikisi de yoksa satis tutari. */
    private function gesTotalSales(callable $get): string
    {
        $capacity = self::number($get('scopes.ges.capacity_mw'));
        $perMw = self::number($get('scopes.ges.sales_per_mw'));
        $sales = self::number($get('scopes.ges.sales_amount'));

        $total = ($capacity !== null && $perMw !== null) ? $capacity * $perMw : $sales;

        return self::money($total, $get('currency_code'));
    }

    /** RES toplam: malzeme + insaat + montaj (dolu olanlar). */
    private function resTotal(callable $get): string
    {
        $total = null;

        foreach (self::SCOPE_FIELDS['res'] as $field) {
            $value = self::number($get('scopes.res.'.$field));

            if ($value !== null) {
                $total = ($total ?? 0.0) + $value;
            }
        }

        return self::money($total, $get('currency_code'));
    }

    /**
     * Goruntuleme adimi: kayitli her kapsam icin tek satir (temel tutarlar
     * ve kapsam listesi dokumani; dosya varsa indirme baglantisi).
     *
     * @return list<Component>
     */
    private function scopeDetailEntries(BusinessCase $case): array
    {
        if (! self::b29()) {
            return [];
        }

        $entries = [];

        foreach ($case->scopes as $scope) {
            $value = self::scopeTypeValue($scope);
            $type = self::scopeType($scope);
            $info = $this->scopeDocumentInfo($scope);
            $parts = $this->scopeAmountParts($scope, $case->currency_code);

            if ($info !== null) {
                $parts[] = __('business_case_scope.fields.scope_document').': '.self::documentLine($info);
            }

            $entries[] = TextEntry::make('scope_'.$value)
                ->label($type?->getLabel() ?? $value)
                ->state($parts === [] ? '-' : implode(' · ', $parts))
                ->icon($type?->getIcon() ?? Heroicon::OutlinedCube)
                ->iconColor($type?->getColor() ?? 'gray')
                ->color(($info['url'] ?? null) !== null ? 'primary' : null)
                ->url($info['url'] ?? null)
                ->columnSpanFull();
        }

        return $entries;
    }

    /**
     * Kayitli kapsamin dolu tutarlari ("Maliyet: 1.000 [simge]" ..., D-180).
     *
     * @return list<string>
     */
    private function scopeAmountParts(BusinessCaseScope $scope, ?string $currency): array
    {
        $parts = [];

        foreach (self::SCOPE_FIELDS[self::scopeTypeValue($scope)] ?? [] as $field) {
            $value = self::number($scope->getAttribute($field));

            if ($value === null) {
                continue;
            }

            $parts[] = __('business_case_scope.fields.'.$field).': '.($field === 'capacity_mw' ? self::quantity($value) : self::money($value, $currency));
        }

        return $parts;
    }

    /** Kayittaki ilgili tipin kapsam satiri (sayfada yuklu scopes iliskisi). */
    private function recordScope(?Model $record, ProjectScopeType $type): ?BusinessCaseScope
    {
        if (! $record instanceof BusinessCase || ! self::b29()) {
            return null;
        }

        foreach ($record->scopes as $scope) {
            if (self::scopeTypeValue($scope) === $type->value) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * Kapsam listesi dokumaninin basligi, en yeni revizyonunun dosya adi ve
     * indirme baglantisi.
     *
     * @return array{title: string, file: string|null, url: string|null}|null
     */
    private function scopeDocumentInfo(?BusinessCaseScope $scope): ?array
    {
        $document = $scope?->scopeDocument;

        if ($document === null) {
            return null;
        }

        /** @var DocumentRevision|null $revision */
        $revision = $document->revisions->sortByDesc('revision_no')->first();
        $file = $revision?->files
            ->first(static fn (DocumentRevisionFile $row): bool => $row->file_role === DocumentRevisionFileRole::Original)
            ?->fileObject;

        return [
            'title' => (string) $document->title,
            'file' => $file?->original_name,
            'url' => $revision !== null ? FileLinks::revisionOriginal($revision, 'download') : null,
        ];
    }

    /**
     * @param  array{title: string, file: string|null, url: string|null}|null  $info
     */
    private static function documentLine(?array $info): string
    {
        if ($info === null) {
            return '-';
        }

        return filled($info['file']) ? $info['title'].' · '.$info['file'] : $info['title'];
    }

    // ---------------------------------------------------------------------
    // Ozet kartlari (adim 2 ve 3)
    // ---------------------------------------------------------------------

    /**
     * Is dosyasi ozeti: 1. adimdaki alanlarin canli degerleri. Ayni kart 2. ve
     * 3. adimda tekrarlandigi icin bolum anahtari ve alan adlari adimla
     * ayrisir (ayni formda ayni Livewire anahtari iki kez bulunmaz).
     *
     * $reader verilirse degerler formdan degil, onun dondurdugu okuyucudan
     * gelir: Teklif olustur ekrani secilen is dosyasinin kayitli degerlerini
     * ayni kartla gosterir (22 Eylul 2026 kullanici karari).
     *
     * @param  (Closure(Get): callable)|null  $reader
     */
    private function caseSummarySection(string $stage, ?Closure $reader = null): Section
    {
        $name = static fn (string $field): string => 'case_summary_'.$stage.'_'.$field;
        $read = $reader ?? static fn (Get $get): Get => $get;

        $entries = [
            $this->summaryEntry($name('customer'), __('business_case.fields.primary_party'), Heroicon::OutlinedBuildingOffice2, 'primary',
                fn (Get $get): string => $this->partyName($read($get)('primary_party_id'))),
            $this->summaryEntry($name('title'), __('business_case.fields.title'), Heroicon::OutlinedBriefcase, 'gray',
                fn (Get $get): string => self::text($read($get)('title'))),
            $this->summaryEntry($name('offer_type'), __('business_case.fields.offer_type'), Heroicon::OutlinedTag, 'gray',
                fn (Get $get): string => self::enumLabel($read($get)('offer_type'), OfferType::class))
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry($name('project_type'), __('business_case.fields.project_type_code'), Heroicon::OutlinedCube, 'gray',
                fn (Get $get): string => $this->projectTypeName($read($get)('project_type_code'))),
            TextEntry::make($name('scope_types'))
                ->label(__('business_case.fields.scope_types'))
                ->state(fn (Get $get): array => array_values(array_filter(array_map(
                    static fn (string $value): ?ProjectScopeType => ProjectScopeType::tryFrom($value),
                    self::selectedScopeTypes($read($get)),
                ))))
                ->formatStateUsing(static fn (mixed $state): string => $state instanceof HasLabel ? (string) $state->getLabel() : (string) $state)
                ->color(static fn (mixed $state): string => $state instanceof HasColor ? (string) $state->getColor() : 'gray')
                ->badge()
                ->placeholder('-')
                ->dehydrated(false)
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry($name('estimated_value'), __('business_case.fields.estimated_value'), Heroicon::OutlinedBanknotes, 'success',
                fn (Get $get): string => self::money(self::number($read($get)('estimated_value')), $read($get)('currency_code'))),
            $this->summaryEntry($name('owner'), __('business_case.fields.owner'), Heroicon::OutlinedUserCircle, 'primary',
                fn (Get $get): string => $this->personnelName($read($get)('owner_employee_id'))),
            $this->summaryEntry($name('proposal_owner'), __('business_case.fields.proposal_owner'), Heroicon::OutlinedUser, 'gray',
                fn (Get $get): string => $this->personnelName($read($get)('proposal_owner_employee_id'))),
        ];

        // B43: kapsam tutarlari teklif adimindadir; potansiyel is ozetinde proje
        // durumu ve teklif sicakligi yazar.
        if (self::b29() && ! self::b43()) {
            foreach (ProjectScopeType::cases() as $type) {
                $entries[] = $this->summaryEntry($name('scope_'.$type->value), $type->getLabel(), $type->getIcon(), $type->getColor(),
                    fn (Get $get): string => $this->scopeSummaryLine($type, $read($get)))
                    ->visible(fn (Get $get): bool => in_array($type->value, self::selectedScopeTypes($read($get)), true))
                    ->columnSpanFull();
            }
        }

        if (self::b43() && ChecklistSchema::enabled()) {
            $entries[] = $this->summaryEntry($name('license_status'), __('checklist.license_status'), Heroicon::OutlinedShieldCheck, 'gray',
                fn (Get $get): string => self::enumLabel($read($get)('license_status'), LicenseStatus::class));
            $entries[] = TextEntry::make($name('heat'))
                ->label(__('checklist.heat'))
                ->state(fn (Get $get, ?Model $record): HtmlString => ChecklistSchema::heatHtml(ChecklistSchema::heatFrom($read($get), $record instanceof BusinessCase ? $record : null)))
                ->html()
                ->dehydrated(false);
        }

        return $this->summarySection('summary-case-'.$stage, __('business_case.sections.summary_case'), Heroicon::OutlinedBriefcase, [
            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])
                ->components($entries)
                ->extraAttributes(['class' => 'konelsis-card-entries']),
        ]);
    }

    /**
     * Kayitli teklifin ozeti (is dosyasi sayfasinin teklif adimi, 22 Eylul
     * 2026): sihirbazdaki "Teklif ozeti" kartinin aynisi; guncel surumun
     * tutarlari ve olusturmada eklenen belgeler.
     */
    public function recordProposalSummary(Proposal $proposal): Component
    {
        $version = $proposal->currentVersion;
        $documents = $version?->documents()->with('documentRevision.document')->get() ?? collect();
        // D-176: bir rolde birden fazla belge olabilir; hepsinin basligi virgulle.
        $documentTitle = static function (ProposalDocumentRole $role) use ($documents): ?string {
            $titles = $documents
                ->filter(static fn (ProposalDocument $document): bool => $document->document_role === $role)
                ->map(static fn (ProposalDocument $row): ?string => $row->documentRevision?->document?->title ?? $row->documentRevision?->title)
                ->filter()
                ->unique()
                ->implode(', ');

            return $titles !== '' ? $titles : null;
        };

        $data = [
            'create_proposal' => true,
            'proposal_title' => $proposal->title,
            'title' => $proposal->businessCase?->title,
            'currency_code' => $version?->currency_code ?? $proposal->businessCase?->currency_code,
            'total_price' => $version?->total_price,
            'margin_pct' => $version?->margin_pct,
            'validity_until' => $version?->validity_until?->toDateString(),
            'offer_status' => $proposal->offer_status?->value,
            'is_critical_route' => (bool) $version?->is_critical_route,
            'customer_expectations_file_name' => $documentTitle(ProposalDocumentRole::CustomerExpectations),
            'proposal_letter_file_name' => $documentTitle(ProposalDocumentRole::ProposalLetter),
            'attach_references' => $documentTitle(ProposalDocumentRole::References) !== null,
            'attach_catalog' => $documentTitle(ProposalDocumentRole::Catalog) !== null,
        ];

        // B43: surumun kapsamlari ve diger belgeler.
        if (self::b43() && $version !== null) {
            $version->loadMissing('scopes.scopeDocument', 'scopes.scopeDocumentRevision.files.fileObject');
            $data['scopes'] = ProposalScopeSchema::formData($version);
            $data['scope_types'] = array_keys($data['scopes']);

            foreach ($version->scopes as $scope) {
                $type = $scope->scope_type instanceof BackedEnum ? (string) $scope->scope_type->value : (string) $scope->scope_type;
                $info = DocumentLine::info($scope->scopeDocument, $scope->scopeDocumentRevision);
                $data['scopes'][$type]['scope_file_name'] = $info === null ? null : DocumentLine::text($info);
                // D-181: kayitli maliyet listeleri ozet satirinda adlariyla.
                $data['scopes'][$type][ProposalVersionScopeService::COST_FILES_NAME_KEY] = array_map(
                    static fn (array $cost): string => DocumentLine::text($cost),
                    ProposalScopeSchema::costInfos($scope),
                );
            }

            foreach (ProposalFilesSchema::DOCUMENTS as $key => $role) {
                $data[$key.'_file_name'] = $documentTitle($role);
            }
        }

        return $this->proposalSummarySection(static fn (): Closure => static fn (string $path): mixed => data_get($data, $path));
    }

    /**
     * Kayitli is dosyasinin ozet karti degerleri, sihirbaz formuyla ayni
     * anahtarlarla (kapsam dosyasi yerine kapsam listesi dokumaninin guncel
     * dosyasi). Istek boyunca is dosyasi basina bir kez okunur.
     *
     * @return array<string, mixed>
     */
    private function caseSummaryData(mixed $caseId): array
    {
        $id = is_numeric($caseId) ? (int) $caseId : 0;

        if ($id <= 0) {
            return [];
        }

        if (array_key_exists($id, $this->caseSummaries)) {
            return $this->caseSummaries[$id];
        }

        $case = app(BusinessCaseQueries::class)->forSummary($id);

        if ($case === null) {
            return $this->caseSummaries[$id] = [];
        }

        $scopes = self::b29() ? $this->scopeFormData($case) : ['scope_types' => [], 'scopes' => []];

        if (self::b29() && ! self::b43()) {
            foreach ($case->scopes as $scope) {
                $info = $this->scopeDocumentInfo($scope);
                $scopes['scopes'][self::scopeTypeValue($scope)]['scope_file_name'] = $info === null ? null : self::documentLine($info);
            }
        }

        // B43: proje durumu ve kayitli teklif sicakligi (ozet kartinda).
        if (self::b43()) {
            $scopes['license_status'] = $case->getAttribute('license_status');
            $scopes['heat_score'] = $case->getAttribute('heat_score');

            // D-183: GES kapsamindaki kurulu guc yeni teklifin MWp alanina gelir
            // (B43 tasimasindaki eslesme: capacity_mw -> capacity_mwp).
            foreach (self::b29() ? $case->scopes : [] as $scope) {
                $capacity = $scope->getAttribute('capacity_mw');

                if (self::scopeTypeValue($scope) === ProjectScopeType::Ges->value && is_numeric($capacity) && (float) $capacity > 0) {
                    $scopes['ges_capacity_mw'] = (float) $capacity;
                }
            }
        }

        return $this->caseSummaries[$id] = [
            'primary_party_id' => $case->primary_party_id,
            'title' => $case->title,
            'offer_type' => $case->getAttribute('offer_type'),
            'project_type_code' => $case->project_type_code,
            'estimated_value' => $case->estimated_value,
            'currency_code' => $case->currency_code,
            'country_code' => $case->country_code,
            'owner_employee_id' => $case->owner_employee_id,
            'proposal_owner_employee_id' => $case->proposal_owner_employee_id,
            'has_project' => $case->project !== null,
            ...$scopes,
        ];
    }

    /**
     * Teklif ozeti (3. adim): 2. adimdaki alanlarin canli degerleri. $reader
     * verilirse degerler kayitli tekliften gelir (is dosyasi sayfasi).
     *
     * @param  (Closure(Get): callable)|null  $reader
     */
    private function proposalSummarySection(?Closure $reader = null): Section
    {
        $read = $reader ?? static fn (Get $get): Get => $get;
        $whenProposal = fn (Get $get): bool => (bool) $read($get)('create_proposal');

        // B43: toplam fiyat bossa kapsamin toplam satisi, marj kapsamdan.
        $scopeRows = static fn (callable $values): array => ProposalVersionScopeService::selectedRows(self::selectedScopeTypes($values), (array) $values('scopes'));

        $entries = [
            $this->summaryEntry('proposal_summary_title', __('business_case.fields.proposal_title'), Heroicon::OutlinedClipboardDocumentList, 'primary',
                fn (Get $get): string => self::text(filled($read($get)('proposal_title')) ? $read($get)('proposal_title') : $read($get)('title'))),
            $this->summaryEntry('proposal_summary_total_price', __('proposal_version.fields.total_price'), Heroicon::OutlinedBanknotes, 'success',
                fn (Get $get): string => self::money(
                    self::number($read($get)('total_price')) ?? (self::b43() ? ProposalVersionScopeService::totalSales($scopeRows($read($get))) : null),
                    $read($get)('currency_code'),
                )),
            $this->summaryEntry('proposal_summary_margin_pct', __('proposal_version.fields.margin_pct'), Heroicon::OutlinedReceiptPercent, 'gray',
                fn (Get $get): string => self::percent(
                    self::number($read($get)('margin_pct')) ?? (self::b43() ? ProposalVersionScopeService::margin($scopeRows($read($get))) : null),
                )),
            $this->summaryEntry('proposal_summary_validity_until', __('proposal_version.fields.validity_until'), Heroicon::OutlinedCalendarDays, 'gray',
                fn (Get $get): string => self::date($read($get)('validity_until'))),
            $this->summaryEntry('proposal_summary_offer_status', __('proposal.fields.offer_status'), Heroicon::OutlinedFlag, 'gray',
                fn (Get $get): string => self::enumLabel($read($get)('offer_status'), OfferStatus::class))
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry('proposal_summary_is_critical_route', __('proposal_version.fields.is_critical_route'), Heroicon::OutlinedExclamationTriangle, 'warning',
                fn (Get $get): string => self::yesNo($read($get)('is_critical_route'))),
            ...(self::b43() ? [] : [
                $this->summaryEntry('proposal_summary_customer_expectations_file', __('business_case.fields.customer_expectations_file'), Heroicon::OutlinedDocumentText, 'gray',
                    fn (Get $get): string => self::fileName($read($get)('customer_expectations_file'), $read($get)('customer_expectations_file_name')))
                    ->visible(fn (): bool => self::b29()),
                $this->summaryEntry('proposal_summary_proposal_letter_file', __('business_case.fields.proposal_letter_file'), Heroicon::OutlinedDocumentText, 'gray',
                    fn (Get $get): string => self::fileName($read($get)('proposal_letter_file'), $read($get)('proposal_letter_file_name')))
                    ->visible(fn (): bool => self::b29()),
            ]),
            $this->summaryEntry('proposal_summary_attach_references', __('business_case.fields.attach_references'), Heroicon::OutlinedPaperClip, 'gray',
                fn (Get $get): string => self::yesNo($read($get)('attach_references')))
                ->visible(fn (): bool => self::b29() && $this->hasReferenceDocument()),
            $this->summaryEntry('proposal_summary_attach_catalog', __('business_case.fields.attach_catalog'), Heroicon::OutlinedPaperClip, 'gray',
                fn (Get $get): string => self::yesNo($read($get)('attach_catalog')))
                ->visible(fn (): bool => self::b29() && $this->hasCatalogDocument()),
        ];

        // B43: kucuk belge kutulari ve secili tiplerin kapsam satirlari.
        if (self::b43()) {
            foreach (ProposalFilesSchema::DOCUMENTS as $key => $role) {
                $entries[] = $this->summaryEntry('proposal_summary_'.$key.'_file', (string) $role->getLabel(), Heroicon::OutlinedDocumentText, 'gray',
                    fn (Get $get): string => self::fileName($read($get)($key.'_file'), $read($get)($key.'_file_name')));
            }

            foreach (ProjectScopeType::cases() as $type) {
                $entries[] = $this->summaryEntry('proposal_summary_scope_'.$type->value, (string) $type->getLabel(), $type->getIcon(), $type->getColor(),
                    fn (Get $get): string => ProposalScopeSchema::summaryLine($type, $read($get)))
                    ->visible(fn (Get $get): bool => in_array($type->value, self::selectedScopeTypes($read($get)), true))
                    ->columnSpanFull();
            }
        }

        return $this->summarySection('summary-proposal', __('business_case.sections.summary_proposal'), Heroicon::OutlinedClipboardDocumentList, [
            Text::make(__('business_case.steps.no_proposal'))
                ->color('gray')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->visible(fn (Get $get): bool => ! $whenProposal($get)),
            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])
                ->components($entries)
                ->extraAttributes(['class' => 'konelsis-card-entries'])
                ->visible($whenProposal),
        ]);
    }

    /**
     * Kayit karti gorunumunde (CSS: .konelsis-card) kompakt ozet bolumu.
     * Anahtar acikca verilir: Filament bolum anahtarini basliktan uretir ve
     * ayni baslik iki adimda tekrarlaninca anahtar cakisirdi.
     *
     * @param  list<Component>  $components
     */
    private function summarySection(string $key, string $heading, Heroicon $icon, array $components): Section
    {
        return Section::make($heading)
            ->key($key)
            ->icon($icon)
            ->compact()
            ->extraAttributes(['class' => 'konelsis-card konelsis-card-row konelsis-card-detail'])
            ->components($components)
            ->columnSpanFull();
    }

    private function summaryEntry(string $name, string $label, Heroicon $icon, string $iconColor, Closure $state): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state($state)
            ->dehydrated(false)
            ->icon($icon)
            ->iconColor($iconColor)
            ->size(TextSize::Small)
            ->weight(FontWeight::Medium);
    }

    /** Secili bir tipin ozet satiri: temel tutarlar ve secilen kapsam dosyasi. */
    private function scopeSummaryLine(ProjectScopeType $type, callable $get): string
    {
        $path = 'scopes.'.$type->value.'.';
        $currency = $get('currency_code');
        $parts = [];

        $fields = match ($type->value) {
            'ges' => ['capacity_mw', 'cost_amount', 'sales_amount'],
            'res' => self::SCOPE_FIELDS['res'],
            'tm' => self::SCOPE_FIELDS['tm'],
            default => [],
        };

        foreach ($fields as $field) {
            $value = self::number($get($path.$field));

            if ($value === null) {
                continue;
            }

            $parts[] = __('business_case_scope.fields.'.$field).': '.($field === 'capacity_mw' ? self::quantity($value) : self::money($value, $currency));
        }

        if ($type->value === 'ges') {
            $parts[] = __('business_case_scope.fields.total_sales').': '.$this->gesTotalSales($get);
        }

        if ($type->value === 'res') {
            $parts[] = __('business_case_scope.fields.total').': '.$this->resTotal($get);
        }

        $parts[] = __('business_case_scope.fields.scope_file').': '.self::fileName($get($path.'scope_file'), $get($path.'scope_file_name'));

        return implode(' · ', $parts);
    }

    // ---------------------------------------------------------------------
    // Yardimcilar
    // ---------------------------------------------------------------------

    /** B29 (kapsamlar, teklif tipi, teklif belgeleri) bu ortamda uygulandi mi? */
    private static function b29(): bool
    {
        return SchemaReadiness::hasBatch('B29');
    }

    /** HES kapsaminin kendi tutar alani (hes_unit_cost, D-101 devami) uygulandi mi? */
    private static function b30(): bool
    {
        return SchemaReadiness::hasBatch('B30');
    }

    /** Ihale -> potansiyel is -> teklif zinciri (D-155) uygulandi mi? */
    public static function b43(): bool
    {
        return SchemaReadiness::hasBatch('B43');
    }

    /**
     * Formdaki secili proje tipleri (deger listesi; enum ornekleri normalize edilir).
     *
     * @return list<string>
     */
    private static function selectedScopeTypes(callable $get): array
    {
        return array_values(array_map(
            static fn (mixed $value): string => $value instanceof BackedEnum ? (string) $value->value : (string) $value,
            (array) $get('scope_types'),
        ));
    }

    /**
     * "Proje tipi eklemek istiyorum" (D-177, 8 Ekim 2026 kullanici talimati:
     * "hem potansiyel iste hem teklif duzenle kisminda, onu da ilk basta
     * gorulmesin. Bir checkbox alani olsun ... Check edilince proje tipi alani
     * ve ona bagli gelebilecek kapsamlar bu ekranda eklenebilir hale gelecek").
     *
     * Kayitli tipler rozet olarak gorunur; kutu isaretlenince yalniz kayitta
     * olmayan tipler secilebilir (`added_scope_types`). Kayitli tip bu yoldan
     * kaldirilmaz; kaydetmede servis yalniz ekler (AcquisitionIntakeService).
     * $syncSelection: potansiyel iste secim `scope_types` durumuna da yazilir
     * (kontrol listesi ve proje durumu alani canli guncellenir; alan gizli
     * oldugu icin kaydedilmez).
     *
     * @param  Closure(mixed...): list<string>  $existing
     * @param  Closure(mixed...): bool  $visible
     * @return list<Component>
     */
    private function scopeTypeAddFields(Closure $existing, Closure $visible, bool $syncSelection): array
    {
        $types = static fn (Component $component): array => ScopeTypes::values((array) $component->evaluate($existing));

        return [
            TextEntry::make('existing_scope_types')
                ->label(__('business_case.fields.scope_types'))
                ->state(fn (TextEntry $component): array => array_values(array_filter(array_map(
                    static fn (string $value): ?ProjectScopeType => ProjectScopeType::tryFrom($value),
                    $types($component),
                ))))
                ->badge()
                ->placeholder(__('business_case.scope_add.none'))
                ->visible($visible)
                ->dehydrated(false)
                ->columnSpan(FieldGrid::WIDE),
            Checkbox::make('add_scope_types')
                ->label(__('business_case.scope_add.checkbox'))
                // D-185: aciklama metni kaldirildi (kullanici talebi).
                ->default(false)
                ->live()
                ->dehydrated(false)
                ->visible($visible)
                ->afterStateUpdated(function (Set $set, mixed $state, Checkbox $component) use ($types, $syncSelection): void {
                    if ((bool) $state) {
                        return;
                    }

                    $set('added_scope_types', []);

                    if ($syncSelection) {
                        $set('scope_types', $types($component));
                    }
                })
                ->columnSpan(FieldGrid::WIDE)
                ->columnStart(1),
            // D-186 (9 Ekim 2026 kullanici talimati: "Eklenecek proje tipi alani gereksiz
            // buyuk alan kapliyor ... Proje tipi'nde gorulen badge GES yazisi gibi kucuk
            // compact secilebilir yapalim"): rozet boyunda satir ici secim cipleri
            // (kc-type-chips); secilen tip kendi renginde dolar. Aciklama metni yok.
            ToggleButtons::make('added_scope_types')
                ->label(__('business_case.scope_add.types'))
                ->options(fn (ToggleButtons $component): array => ScopeTypes::options($types($component)))
                ->enum(ProjectScopeType::class)
                ->multiple()
                ->inline()
                ->live()
                ->extraAttributes(['class' => self::TYPE_CHIPS_CLASS], merge: true)
                ->visible(fn (Get $get, ToggleButtons $component): bool => (bool) $component->evaluate($visible) && (bool) $get('add_scope_types'))
                ->afterStateUpdated(function (Set $set, mixed $state, ToggleButtons $component) use ($types, $syncSelection): void {
                    if ($syncSelection) {
                        $set('scope_types', array_values(array_unique([...$types($component), ...ScopeTypes::values((array) $state)])));
                    }
                })
                ->columnSpan(FieldGrid::HALF),
        ];
    }

    /** D-177: duzenlenen potansiyel iste tip secimi onay kutusunun arkasinda mi? */
    private static function addsScopeTypes(?Model $record): bool
    {
        return $record instanceof BusinessCase && self::scopeTypeAddEnabled();
    }

    /** "Proje tipi eklemek istiyorum" ozelligi acik ve zincir (B43) uygulandi mi? */
    public static function scopeTypeAddEnabled(): bool
    {
        return self::b43() && FeatureFlags::enabled(Feature::ScopeTypeAdd);
    }

    /**
     * Tip secenekleri (ScopeTypes); kayitli tipler (or. ozellik kapaliyken
     * Otomasyon) secenekte kalir ki kayit dogrulamadan gecsin.
     *
     * @return array<string, string>
     */
    private static function scopeTypeOptions(?Model $record): array
    {
        $options = ScopeTypes::options();

        foreach ($record instanceof BusinessCase ? self::caseScopeTypes($record) : [] as $value) {
            $options[$value] ??= (string) ProjectScopeType::from($value)->getLabel();
        }

        return $options;
    }

    /**
     * Potansiyel isin kayitli proje tipleri (deger listesi).
     *
     * @return list<string>
     */
    private static function caseScopeTypes(BusinessCase $case): array
    {
        return ScopeTypes::values($case->scopes->map(static fn (BusinessCaseScope $scope): string => self::scopeTypeValue($scope))->all());
    }

    private static function scopeTypeValue(BusinessCaseScope $scope): string
    {
        $type = $scope->getAttribute('scope_type');

        return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
    }

    private static function scopeType(BusinessCaseScope $scope): ?ProjectScopeType
    {
        return ProjectScopeType::tryFrom(self::scopeTypeValue($scope));
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T|null
     */
    private static function enumCase(mixed $value, string $enumClass): ?BackedEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return $enumClass::tryFrom((string) $value);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    private static function enumLabel(mixed $value, string $enumClass): string
    {
        $case = self::enumCase($value, $enumClass);

        return $case instanceof HasLabel ? (string) ($case->getLabel() ?? '-') : '-';
    }

    private function hasReferenceDocument(): bool
    {
        return $this->hasReferenceDocument ??= app(FixedDocumentQueries::class)->referenceDocument() !== null;
    }

    private function hasCatalogDocument(): bool
    {
        return $this->hasCatalogDocument ??= app(FixedDocumentQueries::class)->catalogDocument() !== null;
    }

    private function partyName(mixed $id): string
    {
        $id = (int) $id;

        return $id > 0 ? (app(PartyQueries::class)->displayName($id) ?? '-') : '-';
    }

    private function personnelName(mixed $id): string
    {
        $id = (int) $id;

        return $id > 0 ? (app(PersonnelQueries::class)->name($id) ?? '-') : '-';
    }

    /** Proje kategorisi kodunun adi; kod ekranda gosterilmez. */
    private function projectTypeName(mixed $code): string
    {
        if (! is_string($code) || $code === '') {
            return '-';
        }

        $this->projectTypeNames ??= app(ProjectCatalogQueries::class)->componentDefinitionOptions();

        return (string) ($this->projectTypeNames[$code] ?? $code);
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

    private static function quantity(float $value): string
    {
        return Number::format($value, precision: 3, locale: 'tr').' MW';
    }

    private static function percent(?float $value): string
    {
        return $value === null ? '-' : '% '.Number::format($value, precision: 2, locale: 'tr');
    }

    private static function date(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return Carbon::parse(is_string($value) ? $value : (string) $value)->format('d.m.Y');
        } catch (Throwable) {
            return '-';
        }
    }

    private static function text(mixed $value): string
    {
        return filled($value) && is_scalar($value) ? (string) $value : '-';
    }

    private static function yesNo(mixed $value): string
    {
        return $value ? __('business_case.values.yes') : __('business_case.values.no');
    }

    /**
     * Yuklenen dosyanin adi: gonderimden once FileUpload durumu gecici dosya
     * nesnesidir (ad nesnenin icindedir), kaydettikten sonra yol + ayri ad
     * alani. Ikisi de okunur; hicbiri yoksa "Secilmedi".
     */
    private static function fileName(mixed $file, mixed $storedName = null): string
    {
        $names = [];

        foreach (is_array($storedName) ? $storedName : [$storedName] as $name) {
            if (is_scalar($name) && filled($name)) {
                $names[] = (string) $name;
            }
        }

        if ($names === []) {
            foreach (is_array($file) ? $file : [$file] as $candidate) {
                if ($candidate instanceof TemporaryUploadedFile) {
                    $names[] = $candidate->getClientOriginalName();
                } elseif (is_string($candidate) && $candidate !== '') {
                    $names[] = basename($candidate);
                }
            }
        }

        return $names === [] ? __('business_case.values.none') : implode(', ', array_unique($names));
    }
}
