<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Acquisition\BusinessSourceKind;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\OfferType;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Reference\ClassificationCode;
use App\Exceptions\AbstractException;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\BusinessCases\RelationManagers\ProposalsRelationManager;
use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Query\Document\FixedDocumentQueries;
use App\Query\Party\PartyQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectConversionService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
 */
final class BusinessCaseWizard
{
    public const STEP_CASE = 'case';

    public const STEP_PROPOSAL = 'proposal';

    public const STEP_PROJECT = 'project';

    /** @var list<string> */
    public const STEP_IDS = [self::STEP_CASE, self::STEP_PROPOSAL, self::STEP_PROJECT];

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

    /** Yukleme ust siniri (KB). */
    private const UPLOAD_MAX_KB = 20480;

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
            TextInput::make('title')
                ->label(__('business_case.fields.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('short_description')
                ->label(__('business_case.fields.short_description'))
                ->columnSpanFull(),
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
            CheckboxList::make('scope_types')
                ->label(__('business_case.fields.scope_types'))
                ->helperText(__('business_case.help.scope_types'))
                ->options(ProjectScopeType::class)
                // 6 secenek iki satirda (16 Eylul 2026 kullanici karari): uc sutun.
                ->columns(['default' => 2, 'md' => 3, 'xl' => 3])
                ->live()
                ->visible($b29)
                ->dehydrated($b29)
                ->columnSpanFull(),
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
            TextInput::make('estimated_value')
                ->label(__('business_case.fields.estimated_value'))
                ->numeric()
                ->step('0.01')
                ->minValue(0)
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
                ->columnSpanFull(),
            Hidden::make('row_version')->hiddenOn('create'),
        ];
    }

    /**
     * Olusturma sihirbazi adimlari. Adim 2 ve 3 onceki adimlarin ozet kartiyla
     * baslar (16 Eylul 2026 kullanici istegi).
     *
     * @return list<Step>
     */
    public function createSteps(): array
    {
        return [
            $this->caseStep(),
            Step::make(__('business_case.wizard.proposal'))
                ->id(self::STEP_PROPOSAL)
                ->description(__('business_case.wizard.proposal_description'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
                ->columns(1)
                ->schema([
                    $this->summaryCard(self::STEP_PROPOSAL),
                    ...$this->proposalSections(),
                ]),
            Step::make(__('business_case.wizard.project'))
                ->id(self::STEP_PROJECT)
                ->description(__('business_case.wizard.project_description'))
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
            'classification' => ['label' => __('business_case.sections.classification'), 'icon' => Heroicon::OutlinedTag, 'fields' => ['offer_type', 'criticality', 'source_kind', 'classification_id', 'scope_types'], 'columns' => FieldGrid::HALF_COLUMNS],
            'commercial' => ['label' => __('business_case.sections.commercial'), 'icon' => Heroicon::OutlinedBanknotes, 'fields' => ['currency_code', 'estimated_value', 'legal_entity_id'], 'columns' => FieldGrid::HALF_COLUMNS],
            'ownership' => ['label' => __('business_case.sections.ownership'), 'icon' => Heroicon::OutlinedUsers, 'fields' => ['owner_employee_id', 'proposal_owner_employee_id'], 'columns' => FieldGrid::HALF_COLUMNS],
        ]);

        [$identity, $classification, $commercial, $ownership] = $sections;

        // Sag sutun: Siniflandirma'nin altinda, secilen proje tipine gore
        // acilan kapsam bolumleri (16 Eylul 2026 kullanici karari).
        $right = [$classification];

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
     * @return list<Component>
     */
    private function proposalSections(): array
    {
        return FieldGrid::group($this->proposalFields(), [
            'proposal' => ['label' => __('business_case.sections.proposal'), 'icon' => Heroicon::OutlinedClipboardDocumentList, 'fields' => [
                'create_proposal', 'proposal_title', 'total_price', 'margin_pct', 'validity_until', 'is_critical_route', 'summary',
                'offer_status', 'customer_expectations_file', 'proposal_letter_file', 'attach_references', 'attach_catalog',
            ]],
        ]);
    }

    /**
     * @return list<Component>
     */
    private function projectSections(): array
    {
        $whenConvert = fn (Get $get): bool => (bool) $get('create_proposal') && (bool) $get('convert_now');

        return FieldGrid::group($this->projectFields(), [
            'conversion' => ['label' => __('business_case.sections.conversion'), 'icon' => Heroicon::OutlinedRocketLaunch, 'fields' => ['convert_now', 'project_name', 'project_manager_employee_id', 'planned_start_on', 'planned_finish_on']],
            'site' => ['label' => __('business_case.sections.site'), 'icon' => Heroicon::OutlinedMapPin, 'fields' => ['site_address_line1', 'site_city', 'site_district'], 'visible' => $whenConvert],
        ]);
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

    /** Adim 1 (goruntuleme): kartta olmayan ayrintilar + duzenleme baglantisi. */
    public function detailsStep(BusinessCase $case): Step
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

        return Step::make(__('business_case.wizard.case'))
            ->id(self::STEP_CASE)
            ->description(__('business_case.wizard.case_description'))
            ->icon(Heroicon::OutlinedBriefcase)
            ->completedIcon(Heroicon::OutlinedBriefcase)
            ->formWrapper(false)
            ->schema([
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
                        ->state($case->created_at?->format('d.m.Y H:i') ?? '-')
                        ->icon(Heroicon::OutlinedClock)
                        ->iconColor('gray'),
                    ...$this->scopeDetailEntries($case),
                ]),
                Actions::make([
                    Action::make('edit_case')
                        ->label(__('filament-actions::edit.single.label'))
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->link()
                        ->visible(fn (): bool => Gate::allows('update', $case))
                        ->url(BusinessCaseResource::getUrl('edit', ['record' => $case, 'step' => self::STEP_CASE])),
                ]),
            ]);
    }

    /** Adim 2 (duzenleme/goruntuleme): teklifler tablosu. */
    public function proposalTableStep(BusinessCase $case, string $pageClass): Step
    {
        $count = $case->proposals()->count();
        $selected = $case->selectedOrLatestProposal();

        return Step::make(__('business_case.wizard.proposal'))
            ->id(self::STEP_PROPOSAL)
            ->description($count === 0
                ? __('business_case.steps.no_proposal')
                : __('business_case.steps.proposal_summary', ['count' => $count, 'selected' => $selected?->proposal_no ?? '-']))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->completedIcon(Heroicon::OutlinedClipboardDocumentList)
            ->formWrapper(false)
            ->schema([
                Callout::make(__('business_case.help.proposal_table'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->info(),
                Livewire::make(ProposalsRelationManager::class, [
                    'ownerRecord' => $case,
                    'pageClass' => $pageClass,
                    ...ProposalsRelationManager::getDefaultProperties(),
                ])->key('rm-proposals'),
            ]);
    }

    /** Adim 3 (duzenleme/goruntuleme): proje varsa baglanti, yoksa donusum. */
    public function projectStep(BusinessCase $case): Step
    {
        $project = $case->project;
        $handoff = $case->operationHandoff;
        $hasProposal = $case->proposals()->exists();

        $components = [];

        if ($project !== null) {
            $components[] = Grid::make(['default' => 1, 'md' => 3])->components([
                TextEntry::make('project_name')
                    ->label(__('business_case.fields.project'))
                    ->state((string) $project->name)
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

            $components[] = Actions::make([$this->convertAction($case)]);
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
    public function convertAction(BusinessCase $case, string $name = 'convert_to_project'): Action
    {
        return Action::make($name)
            ->label(__('project.actions.convert'))
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->modalHeading(__('project.actions.convert'))
            ->modalDescription(__('project.help.convert_intro'))
            ->modalSubmitActionLabel(__('project.actions.convert'))
            ->visible(fn (): bool => Gate::allows('update', $case)
                && $case->project()->doesntExist()
                && $case->selectedOrLatestProposal() !== null)
            ->schema(function () use ($case): array {
                $proposal = $case->selectedOrLatestProposal();

                return $proposal === null ? [] : ProjectConversionForm::components($proposal);
            })
            ->action(function (array $data, LivewireComponent $livewire) use ($case): void {
                $proposal = $case->selectedOrLatestProposal();

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
            Text::make($case->offerCode()?->formatted_code ?? '-')->badge()->color('gray')->icon(Heroicon::OutlinedHashtag),
            Text::make($stage->getLabel())->badge()->color($stage->getColor()),
            Text::make($case->outcome?->getLabel() ?? '-')->badge()->color($case->outcome?->getColor() ?? 'gray'),
        ];

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
                    ->icon(Heroicon::OutlinedCube);
            }
        }

        if ($project !== null) {
            $badges[] = Text::make($project->businessCode?->formatted_code ?? '-')->badge()->color('success')->icon(Heroicon::OutlinedRocketLaunch);
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
                ->state($case->estimated_value === null
                    ? '-'
                    : Number::format((float) $case->estimated_value, precision: 2, locale: 'tr').' '.($case->currency_code ?? ''))
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
                ->state($project?->name ?? __('business_case.steps.no_project'))
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

    /** "Su an hangi asamada / sirada ne var" uyarisi. */
    public function stageCallout(BusinessCase $case): Component
    {
        $stage = $case->acquisition_stage;
        $next = array_values(array_filter(
            $stage->allowedTargets(),
            static fn (AcquisitionStage $target): bool => ! in_array($target, [AcquisitionStage::Lost, AcquisitionStage::Cancelled], true),
        ));

        $description = $next === []
            ? __('business_case.help.stage_final')
            : __('business_case.help.stage_next', ['stages' => implode(' / ', array_map(static fn (AcquisitionStage $s): string => $s->getLabel(), $next))]);

        $callout = Callout::make(__('business_case.help.stage_current', ['stage' => $stage->getLabel()]))
            ->description($description)
            ->icon(Heroicon::OutlinedFlag);

        return match ($stage) {
            AcquisitionStage::Won, AcquisitionStage::HandoverAccepted => $callout->success(),
            AcquisitionStage::Lost, AcquisitionStage::Cancelled => $callout->danger(),
            default => $callout->info(),
        };
    }

    /** Acilacak adim: istenen kimlik, yoksa zincirde gelinen nokta. */
    public function startStep(BusinessCase $case, ?string $requestedId): int
    {
        if ($requestedId !== null) {
            $index = array_search($requestedId, self::STEP_IDS, true);

            if ($index !== false) {
                return $index + 1;
            }
        }

        if ($case->project()->exists()) {
            return 3;
        }

        if ($case->proposals()->exists()) {
            return 2;
        }

        return 1;
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
     * @return list<Component>
     */
    private function proposalFields(): array
    {
        $whenProposal = fn (Get $get): bool => (bool) $get('create_proposal');
        $whenProposalB29 = fn (Get $get): bool => (bool) $get('create_proposal') && self::b29();
        $b29 = fn (): bool => self::b29();

        return [
            Toggle::make('create_proposal')
                ->label(__('business_case.fields.create_proposal'))
                ->helperText(__('business_case.help.proposal_step'))
                ->default(true)
                ->live()
                ->columnSpanFull(),
            TextInput::make('proposal_title')
                ->label(__('business_case.fields.proposal_title'))
                ->helperText(__('business_case.help.proposal_title'))
                ->maxLength(255)
                ->visible($whenProposal)
                ->columnSpanFull(),
            TextInput::make('total_price')
                ->label(__('proposal_version.fields.total_price'))
                ->numeric()
                ->step('0.01')
                ->minValue(0)
                ->visible($whenProposal),
            TextInput::make('margin_pct')
                ->label(__('proposal_version.fields.margin_pct'))
                ->numeric()
                ->step('0.01')
                ->minValue(0)
                ->maxValue(100)
                ->visible($whenProposal),
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
                ->columnSpanFull(),
            Select::make('offer_status')
                ->label(__('proposal.fields.offer_status'))
                ->options(OfferStatus::class)
                ->default('to_be_submitted')
                ->native(false)
                ->visible($whenProposalB29)
                ->dehydrated($b29),
            FileUpload::make('customer_expectations_file')
                ->label(__('business_case.fields.customer_expectations_file'))
                ->helperText(__('business_case.help.customer_expectations_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn('customer_expectations_file_name')
                ->maxSize(self::UPLOAD_MAX_KB)
                ->visible($whenProposalB29)
                ->columnSpanFull(),
            Hidden::make('customer_expectations_file_name'),
            FileUpload::make('proposal_letter_file')
                ->label(__('business_case.fields.proposal_letter_file'))
                ->helperText(__('business_case.help.proposal_letter_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn('proposal_letter_file_name')
                ->maxSize(self::UPLOAD_MAX_KB)
                ->visible($whenProposalB29)
                ->columnSpanFull(),
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
            // Sabit belgelerden biri Dokumanlar'a hic yuklenmemisse tek uyari.
            Text::make(__('business_case.help.fixed_document_missing'))
                ->color('warning')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->visible(fn (Get $get): bool => $whenProposalB29($get) && (! $this->hasReferenceDocument() || ! $this->hasCatalogDocument()))
                ->columnSpanFull(),
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
                ->columnSpanFull(),
            Text::make(__('business_case.help.convert_requires_proposal'))
                ->color('warning')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->visible(fn (Get $get): bool => ! $get('create_proposal'))
                ->columnSpanFull(),
            TextInput::make('project_name')
                ->label(__('business_case.fields.project_name'))
                ->helperText(__('business_case.help.project_name'))
                ->maxLength(255)
                ->visible($whenConvert)
                ->columnSpanFull(),
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
                ->columnSpanFull(),
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
                ->icon(Heroicon::OutlinedCube)
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
                ->columnSpanFull(),
            FileUpload::make($path.'.scope_file')
                ->label(__('business_case_scope.fields.scope_file'))
                ->helperText(__('business_case_scope.help.scope_file'))
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->storeFileNamesIn($path.'.scope_file_name')
                ->acceptedFileTypes(self::SCOPE_FILE_TYPES)
                ->maxSize(self::UPLOAD_MAX_KB)
                ->columnSpanFull(),
            Hidden::make($path.'.scope_file_name'),
        ];
    }

    private function scopeAmountInput(ProjectScopeType $type, string $field, string $step = '0.01', bool $live = false): TextInput
    {
        $input = TextInput::make('scopes.'.$type->value.'.'.$field)
            ->label(__('business_case_scope.fields.'.$field))
            ->numeric()
            ->step($step)
            ->minValue(0)
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
    private function gesTotalSales(Get $get): string
    {
        $capacity = self::number($get('scopes.ges.capacity_mw'));
        $perMw = self::number($get('scopes.ges.sales_per_mw'));
        $sales = self::number($get('scopes.ges.sales_amount'));

        $total = ($capacity !== null && $perMw !== null) ? $capacity * $perMw : $sales;

        return self::money($total, $get('currency_code'));
    }

    /** RES toplam: malzeme + insaat + montaj (dolu olanlar). */
    private function resTotal(Get $get): string
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
                ->icon(Heroicon::OutlinedCube)
                ->iconColor($type?->getColor() ?? 'gray')
                ->color(($info['url'] ?? null) !== null ? 'primary' : null)
                ->url($info['url'] ?? null)
                ->columnSpanFull();
        }

        return $entries;
    }

    /**
     * Kayitli kapsamin dolu tutarlari ("Maliyet: 1.000,00 TRY" ...).
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
     */
    private function caseSummarySection(string $stage): Section
    {
        $name = static fn (string $field): string => 'case_summary_'.$stage.'_'.$field;

        $entries = [
            $this->summaryEntry($name('customer'), __('business_case.fields.primary_party'), Heroicon::OutlinedBuildingOffice2, 'primary',
                fn (Get $get): string => $this->partyName($get('primary_party_id'))),
            $this->summaryEntry($name('title'), __('business_case.fields.title'), Heroicon::OutlinedBriefcase, 'gray',
                fn (Get $get): string => self::text($get('title'))),
            $this->summaryEntry($name('offer_type'), __('business_case.fields.offer_type'), Heroicon::OutlinedTag, 'gray',
                fn (Get $get): string => self::enumLabel($get('offer_type'), OfferType::class))
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry($name('project_type'), __('business_case.fields.project_type_code'), Heroicon::OutlinedCube, 'gray',
                fn (Get $get): string => $this->projectTypeName($get('project_type_code'))),
            TextEntry::make($name('scope_types'))
                ->label(__('business_case.fields.scope_types'))
                ->state(fn (Get $get): array => array_values(array_filter(array_map(
                    static fn (string $value): ?ProjectScopeType => ProjectScopeType::tryFrom($value),
                    self::selectedScopeTypes($get),
                ))))
                ->formatStateUsing(static fn (mixed $state): string => $state instanceof HasLabel ? (string) $state->getLabel() : (string) $state)
                ->color(static fn (mixed $state): string => $state instanceof HasColor ? (string) $state->getColor() : 'gray')
                ->badge()
                ->placeholder('-')
                ->dehydrated(false)
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry($name('estimated_value'), __('business_case.fields.estimated_value'), Heroicon::OutlinedBanknotes, 'success',
                fn (Get $get): string => self::money(self::number($get('estimated_value')), $get('currency_code'))),
            $this->summaryEntry($name('owner'), __('business_case.fields.owner'), Heroicon::OutlinedUserCircle, 'primary',
                fn (Get $get): string => $this->personnelName($get('owner_employee_id'))),
            $this->summaryEntry($name('proposal_owner'), __('business_case.fields.proposal_owner'), Heroicon::OutlinedUser, 'gray',
                fn (Get $get): string => $this->personnelName($get('proposal_owner_employee_id'))),
        ];

        if (self::b29()) {
            foreach (ProjectScopeType::cases() as $type) {
                $entries[] = $this->summaryEntry($name('scope_'.$type->value), $type->getLabel(), Heroicon::OutlinedCube, $type->getColor(),
                    fn (Get $get): string => $this->scopeSummaryLine($type, $get))
                    ->visible(fn (Get $get): bool => in_array($type->value, self::selectedScopeTypes($get), true))
                    ->columnSpanFull();
            }
        }

        return $this->summarySection('summary-case-'.$stage, __('business_case.sections.summary_case'), Heroicon::OutlinedBriefcase, [
            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])
                ->components($entries)
                ->extraAttributes(['class' => 'konelsis-card-entries']),
        ]);
    }

    /** Teklif ozeti (3. adim): 2. adimdaki alanlarin canli degerleri. */
    private function proposalSummarySection(): Section
    {
        $whenProposal = fn (Get $get): bool => (bool) $get('create_proposal');

        $entries = [
            $this->summaryEntry('proposal_summary_title', __('business_case.fields.proposal_title'), Heroicon::OutlinedClipboardDocumentList, 'primary',
                fn (Get $get): string => self::text(filled($get('proposal_title')) ? $get('proposal_title') : $get('title'))),
            $this->summaryEntry('proposal_summary_total_price', __('proposal_version.fields.total_price'), Heroicon::OutlinedBanknotes, 'success',
                fn (Get $get): string => self::money(self::number($get('total_price')), $get('currency_code'))),
            $this->summaryEntry('proposal_summary_margin_pct', __('proposal_version.fields.margin_pct'), Heroicon::OutlinedReceiptPercent, 'gray',
                fn (Get $get): string => self::percent(self::number($get('margin_pct')))),
            $this->summaryEntry('proposal_summary_validity_until', __('proposal_version.fields.validity_until'), Heroicon::OutlinedCalendarDays, 'gray',
                fn (Get $get): string => self::date($get('validity_until'))),
            $this->summaryEntry('proposal_summary_offer_status', __('proposal.fields.offer_status'), Heroicon::OutlinedFlag, 'gray',
                fn (Get $get): string => self::enumLabel($get('offer_status'), OfferStatus::class))
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry('proposal_summary_is_critical_route', __('proposal_version.fields.is_critical_route'), Heroicon::OutlinedExclamationTriangle, 'warning',
                fn (Get $get): string => self::yesNo($get('is_critical_route'))),
            $this->summaryEntry('proposal_summary_customer_expectations_file', __('business_case.fields.customer_expectations_file'), Heroicon::OutlinedDocumentText, 'gray',
                fn (Get $get): string => self::fileName($get('customer_expectations_file'), $get('customer_expectations_file_name')))
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry('proposal_summary_proposal_letter_file', __('business_case.fields.proposal_letter_file'), Heroicon::OutlinedDocumentText, 'gray',
                fn (Get $get): string => self::fileName($get('proposal_letter_file'), $get('proposal_letter_file_name')))
                ->visible(fn (): bool => self::b29()),
            $this->summaryEntry('proposal_summary_attach_references', __('business_case.fields.attach_references'), Heroicon::OutlinedPaperClip, 'gray',
                fn (Get $get): string => self::yesNo($get('attach_references')))
                ->visible(fn (): bool => self::b29() && $this->hasReferenceDocument()),
            $this->summaryEntry('proposal_summary_attach_catalog', __('business_case.fields.attach_catalog'), Heroicon::OutlinedPaperClip, 'gray',
                fn (Get $get): string => self::yesNo($get('attach_catalog')))
                ->visible(fn (): bool => self::b29() && $this->hasCatalogDocument()),
        ];

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
    private function scopeSummaryLine(ProjectScopeType $type, Get $get): string
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

    /**
     * Formdaki secili proje tipleri (deger listesi; enum ornekleri normalize edilir).
     *
     * @return list<string>
     */
    private static function selectedScopeTypes(Get $get): array
    {
        return array_values(array_map(
            static fn (mixed $value): string => $value instanceof BackedEnum ? (string) $value->value : (string) $value,
            (array) $get('scope_types'),
        ));
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
