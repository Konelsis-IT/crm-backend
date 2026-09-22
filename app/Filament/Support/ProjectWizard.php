<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\ExpectationKind;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\WorkstreamStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\AutomationDocumentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\CbsNodesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ClarificationsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ComponentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Filament\Resources\Projects\RelationManagers\DelayEventsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ExposuresRelationManager;
use App\Filament\Resources\Projects\RelationManagers\IssuesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\LogisticsItemsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ProgressSnapshotsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ScheduleBaselinesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\SoftwareItemsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\SupplyItemsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TeamMembersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WbsNodesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WorkPackagesRelationManager;
use App\Models\Project\Project;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Project\ProjectStepReadiness;
use App\Query\Reference\ReferenceOptions;
use App\Services\Project\ProjectService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

/**
 * Proje sihirbazi (D-72): olusturma, duzenleme ve calisma alani ayni adim
 * yapisini kullanir.
 *
 * - Temel adimlar (Kimlik / Saha / Plan ve yonetim / Fotograf) form
 *   alanlaridir; olusturma ve duzenleme sayfalarinda birebir aynidir
 *   (yalniz olusturmaya ozgu alanlar duzenlemede kapali gelir).
 * - Departman adimlari (Proje Grubu, Satin Alma, Muhasebe, Lojistik, Saha,
 *   Yazilim) her workstream icin durum seridi, numarali "Bu adimda
 *   beklenenler" kontrol listesi (satir basina "+" ve duzenle) ve dogrudan
 *   ekrandaki tablolardir (relation manager'lar Livewire bileseni olarak
 *   gomulur); kayitlar aninda kaydedilir. Duzenleme sayfasi ve calisma alani
 *   bu govdeyi ayni kaynaktan alir.
 */
final class ProjectWizard
{
    public const STEP_IDENTITY = 'identity';

    public const STEP_SITE = 'site';

    public const STEP_PLAN = 'plan';

    public const STEP_PHOTO = 'photo';

    /** @var list<string> */
    public const BASE_STEP_IDS = [self::STEP_IDENTITY, self::STEP_SITE, self::STEP_PLAN, self::STEP_PHOTO];

    /** @var list<string> Saha adresi modalinda ve Saha adiminda ortak alanlar. */
    public const SITE_FIELDS = [
        'site_address_line1', 'site_address_line2', 'site_district', 'site_city', 'site_postal_code',
        'site_country_code', 'site_latitude', 'site_longitude', 'site_location', 'timezone', 'site_note',
    ];

    /** @var list<string> Plan tarihleri modalindaki alanlar. */
    public const DATE_FIELDS = ['planned_start_on', 'planned_finish_on', 'actual_start_on', 'actual_finish_on'];

    public function __construct(
        private readonly ProjectStepReadiness $readiness,
        private readonly ProjectWorkspace $workspace,
    ) {}

    public static function stepId(string $groupCode): string
    {
        return 'step-'.strtolower($groupCode);
    }

    /** Gomulu tablonun Livewire anahtari; kontrol listesi "+" hedefi ve capa kimligi bundan turer. */
    public static function relationKey(string $class, string $suffix = ''): string
    {
        return 'rm-'.class_basename($class).($suffix !== '' ? '-'.$suffix : '');
    }

    /** Duzenleme sayfasinin form semasinin anahtari (EditRecord: "form"). */
    public const FORM_SCHEMA_KEY = 'form';

    /**
     * Duzenleme sihirbazinin Filament anahtari ("form" semasi, kok yolu "data",
     * etiketsiz). Kontrol listesindeki "adima git" dugmeleri bu anahtarla
     * wizard'a olay yollar.
     */
    public static function wizardKey(): string
    {
        return self::FORM_SCHEMA_KEY.'.data::wizard';
    }

    /**
     * Duzenleme sihirbazindaki bir adimin Filament anahtari. Adimlara acik
     * anahtar (adim kimligi) verilir; etiketten uretilen varsayilan anahtar
     * "Saha" (adres) ile "Saha" (departman) adimlarinda cakisirdi.
     */
    public static function stepKey(string $stepId): string
    {
        return self::FORM_SCHEMA_KEY.'.'.$stepId;
    }

    /**
     * Temel adimlar. $project verilirse duzenleme modundadir: olusturmaya ozgu
     * alanlar kapatilir ve Fotograf adimina saha fotograflari tablosu eklenir.
     *
     * @return list<Step>
     */
    public function baseSteps(?Project $project = null, ?string $pageClass = null): array
    {
        $isEdit = $project !== null;

        $photoComponents = [
            FileUpload::make('cover_file')
                ->label(__('project.fields.cover_photo'))
                ->helperText($isEdit ? __('project.help.cover_replace') : null)
                ->image()
                ->disk('local')
                ->directory('document-uploads-tmp')
                ->storeFileNamesIn('cover_original_name')
                ->maxSize(8192)
                ->columnSpan($isEdit ? FieldGrid::FULL : FieldGrid::HALF),
            Hidden::make('cover_original_name'),
            TextInput::make('cover_caption')
                ->label(__('project.fields.cover_caption'))
                ->maxLength(255),
        ];

        if ($isEdit && $pageClass !== null) {
            $photoComponents[] = $this->relationManager(PhotosRelationManager::class, $project, $pageClass, 'photo-step');
        }

        $photoStep = Step::make(__('project.wizard.photo'))
            ->id(self::STEP_PHOTO)
            ->key(self::STEP_PHOTO)
            ->description($isEdit ? __('project.wizard.photo_description_edit') : __('project.wizard.photo_description'))
            ->icon(Heroicon::OutlinedCamera)
            ->completedIcon(Heroicon::OutlinedCamera)
            ->formWrapper(! $isEdit)
            ->schema($photoComponents);

        return [
            Step::make(__('project.wizard.identity'))
                ->id(self::STEP_IDENTITY)
                ->key(self::STEP_IDENTITY)
                ->description(__('project.wizard.identity_description'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->completedIcon(Heroicon::OutlinedRocketLaunch)
                ->columns(1)
                ->schema($this->identitySections($isEdit, $photoComponents)),
            Step::make(__('project.wizard.site'))
                ->id(self::STEP_SITE)
                ->key(self::STEP_SITE)
                ->description(__('project.wizard.site_description'))
                ->icon(Heroicon::OutlinedMapPin)
                ->completedIcon(Heroicon::OutlinedMapPin)
                ->columns(1)
                ->schema($this->siteSections()),
            Step::make(__('project.wizard.plan'))
                ->id(self::STEP_PLAN)
                ->key(self::STEP_PLAN)
                ->description(__('project.wizard.plan_description'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->completedIcon(Heroicon::OutlinedCalendarDays)
                ->columns(1)
                ->schema($this->planSections($isEdit)),
            ...($isEdit ? [$photoStep] : []),
        ];
    }

    /**
     * Kimlik adimi alanlari (duz liste).
     *
     * @param  list<Component>  $photoComponents  Olusturmada kapak alanlari (aciklamanin yanina).
     * @return list<Component>
     */
    private function identityFields(bool $isEdit, array $photoComponents): array
    {
        return [
            TextInput::make('name')
                ->label(__('project.fields.name'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('customer_party_id')
                ->label(__('project.fields.customer_party'))
                ->relationship('customerParty', 'display_name')
                ->searchable()
                ->preload()
                ->required()
                ->native(false)
                ->disabled($isEdit)
                ->dehydrated(! $isEdit),
            Select::make('project_type_code')
                ->label(__('project.fields.project_type'))
                ->options(fn (): array => app(ProjectCatalogQueries::class)->componentDefinitionOptions())
                ->searchable()
                ->native(false)
                ->visibleOn('create'),
            Select::make('criticality_profile')
                ->label(__('project.fields.criticality_profile'))
                ->options(CriticalityProfile::class)
                ->default(CriticalityProfile::Standard->value)
                ->required()
                ->native(false),
            TextInput::make('legacy_reference')
                ->label(__('project.fields.legacy_reference'))
                ->helperText(__('project.help.legacy_reference'))
                ->maxLength(64),
            Select::make('classification_id')
                ->label(__('project.fields.classification'))
                ->relationship('classification', 'name_tr')
                ->searchable()
                ->preload()
                ->native(false),
            Textarea::make('description')
                ->label(__('project.fields.description'))
                ->rows(4)
                ->columnSpan($isEdit ? FieldGrid::FULL : FieldGrid::HALF),
            // Olusturmada kapak gorseli aciklamanin yanindadir (yarim / yarim; kullanici karari 10 Eylul 2026).
            ...($isEdit ? [] : $photoComponents),
            Hidden::make('row_version')->hiddenOn('create'),
        ];
    }

    /**
     * Kimlik adimi bolumleri (D-80): kimlik bilgileri + aciklama ve kapak.
     *
     * @param  list<Component>  $photoComponents
     * @return list<Component>
     */
    public function identitySections(bool $isEdit, array $photoComponents = []): array
    {
        return FieldGrid::group($this->identityFields($isEdit, $photoComponents), [
            'identity' => ['label' => __('project.sections.identity'), 'icon' => Heroicon::OutlinedIdentification, 'fields' => ['name', 'customer_party_id', 'project_type_code', 'criticality_profile', 'classification_id', 'legacy_reference']],
            'description' => ['label' => $isEdit ? __('project.sections.description') : __('project.sections.description_cover'), 'icon' => Heroicon::OutlinedDocumentText, 'fields' => ['description', 'cover_file', 'cover_caption']],
        ]);
    }

    /**
     * Saha adimi bolumleri (D-80): adres + konum. Saha adresi modali da bunu kullanir.
     *
     * @return list<Component>
     */
    public function siteSections(): array
    {
        return FieldGrid::group($this->siteFields(), [
            'address' => ['label' => __('project.sections.site'), 'icon' => Heroicon::OutlinedMapPin, 'fields' => ['site_address_line1', 'site_address_line2', 'site_country_code', 'site_city', 'site_district', 'site_postal_code']],
            'location' => ['label' => __('project.sections.location'), 'icon' => Heroicon::OutlinedGlobeAlt, 'fields' => ['site_latitude', 'site_longitude', 'site_location', 'timezone', 'site_note']],
        ]);
    }

    /**
     * Plan adimi alanlari (duz liste).
     *
     * @return list<Component>
     */
    private function planFields(bool $isEdit): array
    {
        return [
            Select::make('project_manager_employee_id')
                ->label(__('project.fields.project_manager'))
                ->relationship('projectManager', 'full_name')
                ->searchable()
                ->preload()
                ->required()
                // Olusturmada varsayilan olusturan kisi: onceki adimlarda "Kaydet"
                // ile kesilen proje de yoneticisiyle acilir (22 Eylul 2026).
                ->default(fn (): ?int => $isEdit ? null : (auth()->id() !== null ? (int) auth()->id() : null))
                ->native(false),
            Select::make('status')
                ->label(__('project.fields.initial_status'))
                ->helperText(__('project.help.initial_status'))
                ->options(ProjectStatus::class)
                ->default(ProjectStatus::Opening->value)
                ->required()
                ->native(false)
                ->visibleOn('create'),
            Select::make('currency_code')
                ->label(__('project.fields.currency'))
                ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                ->default((string) config('konelsis.organization.default_currency', 'TRY'))
                ->searchable()
                ->required()
                ->native(false)
                ->disabled($isEdit)
                ->dehydrated(! $isEdit),
            TextInput::make('contract_value_snapshot')
                ->label(__('project.fields.contract_value'))
                ->helperText($isEdit ? __('project.help.contract_value_locked') : null)
                ->numeric()
                ->minValue(0)
                ->disabled($isEdit)
                ->dehydrated(! $isEdit),
            ...$this->dateFields(),
        ];
    }

    /**
     * Plan adimi bolumleri (D-80): yonetim, butce, takvim.
     *
     * @return list<Component>
     */
    public function planSections(bool $isEdit): array
    {
        return FieldGrid::group($this->planFields($isEdit), [
            'management' => ['label' => __('project.sections.management'), 'icon' => Heroicon::OutlinedUserCircle, 'fields' => ['project_manager_employee_id', 'status']],
            'budget' => ['label' => __('project.sections.budget'), 'icon' => Heroicon::OutlinedBanknotes, 'fields' => ['currency_code', 'contract_value_snapshot']],
            'schedule' => ['label' => __('project.sections.schedule'), 'icon' => Heroicon::OutlinedCalendarDays, 'fields' => ['planned_start_on', 'planned_finish_on', 'actual_start_on', 'actual_finish_on']],
        ]);
    }

    /**
     * Saha adresi alanlari (Saha adimi ve saha adresi modali).
     *
     * @return list<Component>
     */
    public function siteFields(): array
    {
        return [
            TextInput::make('site_address_line1')
                ->label(__('project.fields.site_address_line1'))
                ->helperText(__('project.help.site_address'))
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('site_address_line2')
                ->label(__('project.fields.site_address_line2'))
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('site_country_code')
                ->label(__('project.fields.site_country'))
                ->options(fn (): array => app(ReferenceOptions::class)->countries())
                ->default((string) config('konelsis.legal_entity.country', 'TR'))
                ->searchable()
                ->native(false)
                ->live(),
            // Turkiye'de il/ilce gercek listeden secilir; baska ulkede elle yazilir (D-80).
            ...TurkiyeAddressFields::make('site_city', 'site_district', __('project.fields.site_city'), __('project.fields.site_district'), 'site_country_code'),
            TextInput::make('site_postal_code')
                ->label(__('project.fields.site_postal_code'))
                ->maxLength(16),
            TextInput::make('site_latitude')
                ->label(__('project.fields.site_latitude'))
                ->numeric()
                ->minValue(-90)
                ->maxValue(90),
            TextInput::make('site_longitude')
                ->label(__('project.fields.site_longitude'))
                ->numeric()
                ->minValue(-180)
                ->maxValue(180),
            TextInput::make('site_location')
                ->label(__('project.fields.site_location'))
                ->maxLength(100),
            Select::make('timezone')
                ->label(__('project.fields.timezone'))
                ->options(TimezoneOptions::list())
                ->default((string) config('konelsis.organization.default_timezone', 'Europe/Istanbul'))
                ->searchable()
                ->native(false),
            Textarea::make('site_note')
                ->label(__('project.fields.site_note'))
                ->columnSpanFull(),
        ];
    }

    /**
     * Plan ve fiili tarih alanlari (Plan adimi ve plan tarihleri modali).
     *
     * @return list<Component>
     */
    public function dateFields(): array
    {
        return [
            DatePicker::make('planned_start_on')
                ->label(__('project.fields.planned_start_on'))
                ->displayFormat('d.m.Y'),
            DatePicker::make('planned_finish_on')
                ->label(__('project.fields.planned_finish_on'))
                ->displayFormat('d.m.Y')
                ->afterOrEqual('planned_start_on'),
            DatePicker::make('actual_start_on')
                ->label(__('project.fields.actual_start_on'))
                ->displayFormat('d.m.Y'),
            DatePicker::make('actual_finish_on')
                ->label(__('project.fields.actual_finish_on'))
                ->displayFormat('d.m.Y')
                ->afterOrEqual('actual_start_on'),
        ];
    }

    /**
     * Departman adimlari: her workstream icin durum seridi, kontrol listesi
     * ve departmanin tablolari. $steps verilmezse hazirlik burada hesaplanir.
     *
     * @param  list<array<string, mixed>>|null  $steps
     * @return list<Step>
     */
    public function departmentSteps(Project $project, string $pageClass, ?array $steps = null, array $eagerKeys = []): array
    {
        $steps ??= $this->readiness->forProject($project);
        $components = [];

        foreach ($steps as $step) {
            $icon = $this->workspace->stepIcon($step['group_code']);

            $components[] = Step::make($step['group_name'])
                ->id(self::stepId($step['group_code']))
                ->key(self::stepId($step['group_code']))
                ->icon($icon)
                ->completedIcon($icon)
                ->description($this->stepDescription($step))
                ->formWrapper(false)
                ->schema($this->stepBody($step, $project, $pageClass, $eagerKeys));
        }

        return $components;
    }

    /**
     * Bir departman adiminin govdesi: durum seridi, numarali "Bu adimda
     * beklenenler" kontrol listesi ve departmanin tablolari. Duzenleme
     * sihirbazinin adimi ve calisma alaninin sekmesi ayni govdeyi kullanir.
     * Mevcut adimin tablolari ve $eagerKeys ile istenenler sayfayla birlikte
     * kurulur, digerleri sayfa yuklenince arka planda gelir.
     *
     * @param  array<string, mixed>  $step
     * @param  list<string>  $eagerKeys
     * @return list<Component>
     */
    public function stepBody(array $step, Project $project, string $pageClass, array $eagerKeys = []): array
    {
        return [
            $this->stepBanner($step),
            $this->readinessChecklist($step, $project, $pageClass),
            ...$this->stepTables((string) $step['group_code'], $project, $pageClass, (bool) $step['is_current'], $eagerKeys),
        ];
    }

    /**
     * Numarali kontrol listesi: her beklenti bir satir — durum simgesi, ad,
     * sayim rozeti ve hizli islemler ("+" ekle, kalem duzenle / tabloya git).
     *
     * @param  array<string, mixed>  $step
     */
    public function readinessChecklist(array $step, Project $project, string $pageClass): Component
    {
        $rows = [];

        foreach ($step['items'] as $index => $item) {
            $rows[] = $this->checklistRow($index + 1, $item, (string) $step['group_code'], $project, $pageClass);
        }

        if ($rows === []) {
            $rows[] = Text::make(__('project.steps.none_defined'))->color('gray');
        }

        return Section::make(__('project.sections.readiness'))
            ->icon(Heroicon::OutlinedListBullet)
            ->iconColor($step['is_ready'] ? 'success' : 'warning')
            ->description(__('project.steps.summary', ['met' => $step['mandatory_met'], 'total' => $step['mandatory_total']]).' · '.__('project.help.checklist'))
            ->compact()
            ->components($rows);
    }

    /**
     * Sihirbazin acilacagi adim (1 tabanli): istenen adim kimligi, yoksa
     * projenin mevcut odagi, o da yoksa ilk adim.
     */
    public function startStep(Project $project, ?string $requestedId, bool $withBaseSteps): int
    {
        $ids = $withBaseSteps ? self::BASE_STEP_IDS : [];

        foreach ($this->readiness->orderedWorkstreams($project) as $workstream) {
            $ids[] = self::stepId((string) ($workstream->group?->code ?? ''));
        }

        if ($requestedId !== null) {
            $index = array_search($requestedId, $ids, true);

            if ($index !== false) {
                return $index + 1;
            }
        }

        $currentId = $this->currentStepId($project);

        if ($currentId !== null) {
            $index = array_search($currentId, $ids, true);

            if ($index !== false) {
                return $index + 1;
            }
        }

        return 1;
    }

    /** Mevcut odagin adim kimligi (derin baglanti icin). */
    public function currentStepId(Project $project): ?string
    {
        $current = $this->readiness->current($project);

        return $current === null ? null : self::stepId((string) ($current->group?->code ?? ''));
    }

    /**
     * Relation manager'i sayfaya Livewire bileseni olarak gomer. Kontrol
     * listesinin "+" dugmesi tabloyu Livewire anahtariyla hedefler
     * (OpensFromChecklist), kalem dugmesi ayni anahtarli capaya gider.
     *
     * @param  class-string<\Filament\Resources\RelationManagers\RelationManager>  $class
     */
    public function relationManager(string $class, Project $project, string $pageClass, string $suffix = '', bool $eager = false): Livewire
    {
        $key = self::relationKey($class, $suffix);
        $properties = [
            'ownerRecord' => $project,
            'pageClass' => $pageClass,
            ...$class::getDefaultProperties(),
        ];

        if (in_array(OpensFromChecklist::class, class_uses_recursive($class), true)) {
            $properties['checklistTarget'] = $key;
        }

        if ($eager) {
            // Mevcut adimin tablolari ve "+" ile hedeflenen tablo sayfayla birlikte kurulur.
            $properties['lazy'] = false;
        } elseif ($properties['lazy'] ?? false) {
            // Diger tablolar tembel ama gorunur alani beklemeden, sayfa yuklenir yuklenmez kurulur.
            $properties['defer'] = true;
        }

        return Livewire::make($class, $properties)
            ->key($eager ? $key.'::eager' : $key)
            ->id('anchor-'.$key);
    }

    /** Saha adresi modali (proje karti ve kontrol listesi). */
    public function quickSiteAction(Project $project, string $name = 'edit_site'): Action
    {
        return Action::make($name)
            ->label(__('project.actions.edit_site'))
            ->icon(Heroicon::OutlinedMapPin)
            ->modalHeading(__('project.actions.edit_site'))
            ->modalDescription(__('project.help.site_address'))
            ->modalSubmitActionLabel(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->visible(fn (): bool => Gate::allows('update', $project))
            ->fillForm(fn (): array => $project->only(self::SITE_FIELDS))
            ->modalWidth(Width::SixExtraLarge)
            ->schema(fn (Schema $schema): Schema => $schema->columns(1)->components($this->siteSections()))
            ->action(function (array $data, LivewireComponent $livewire) use ($project): void {
                $this->applyQuickUpdate($project, $data, __('project.messages.site_updated'), $livewire);
            });
    }

    /** Plan / fiili tarihler modali (kontrol listesi). */
    public function quickDatesAction(Project $project, string $name = 'edit_dates'): Action
    {
        return Action::make($name)
            ->label(__('project.actions.edit_dates'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->modalHeading(__('project.actions.edit_dates'))
            ->modalSubmitActionLabel(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->visible(fn (): bool => Gate::allows('update', $project))
            ->fillForm(fn (): array => [
                'planned_start_on' => $project->planned_start_on?->toDateString(),
                'planned_finish_on' => $project->planned_finish_on?->toDateString(),
                'actual_start_on' => $project->actual_start_on?->toDateString(),
                'actual_finish_on' => $project->actual_finish_on?->toDateString(),
            ])
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal($this->dateFields())))
            ->action(function (array $data, LivewireComponent $livewire) use ($project): void {
                $this->applyQuickUpdate($project, $data, __('project.messages.dates_updated'), $livewire);
            });
    }

    /**
     * Hizli modal kaydi: servis uzerinden gunceller, sonucu bildirir ve
     * calisma alanini yeniler.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyQuickUpdate(Project $project, array $data, string $message, LivewireComponent $livewire): void
    {
        try {
            app(ProjectService::class)->update($project, $data);
            DomainNotifications::success($message);
            $livewire->redirect(ProjectResource::getUrl('view', ['record' => $project]));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);
        }
    }

    /**
     * Kontrol listesi satiri: "1. Ad" + sayim rozeti + hizli islemler.
     *
     * @param  array<string, mixed>  $item
     */
    private function checklistRow(int $no, array $item, string $groupCode, Project $project, string $pageClass): Component
    {
        $met = (bool) $item['met'];
        $mandatory = (bool) $item['mandatory'];

        $title = Text::make($no.'. '.$item['name'])
            ->icon($met ? Heroicon::CheckCircle : ($mandatory ? Heroicon::OutlinedXCircle : Heroicon::OutlinedMinusCircle))
            ->color($met ? 'success' : ($mandatory ? 'danger' : 'gray'))
            ->weight($met ? FontWeight::Medium : FontWeight::SemiBold);

        if (filled($item['help'])) {
            $title->tooltip((string) $item['help']);
        }

        $meta = Text::make(
            __('project.steps.count', ['count' => $item['count'], 'min' => $item['min']])
            .(! $mandatory ? ' · '.__('project.steps.optional') : '')
        )
            ->badge()
            ->color($met ? 'success' : ($mandatory ? 'warning' : 'gray'))
            ->grow(false);

        $components = [$title, $meta];
        $actions = $this->checklistActions($item, $groupCode, $project, $pageClass);

        if ($actions !== []) {
            $components[] = Actions::make($actions)
                ->alignment(Alignment::End)
                ->grow(false);
        }

        return Flex::make($components)
            ->from('md')
            ->verticalAlignment(VerticalAlignment::Center);
    }

    /**
     * Satirin hizli islemleri. Proje alani beklentileri (saha adresi, plan
     * tarihleri) calisma alaninda modal acar, duzenleme sihirbazinda ilgili
     * adima gecer; tablo beklentileri "+" ile tablonun olusturma eylemini
     * acar, kalem ile tabloya gider.
     *
     * @param  array<string, mixed>  $item
     * @return list<Action>
     */
    private function checklistActions(array $item, string $groupCode, Project $project, string $pageClass): array
    {
        $kind = $item['kind'];
        $met = (bool) $item['met'];
        $suffix = strtolower($groupCode.'_'.$item['code']);

        if (in_array($kind, [ExpectationKind::SiteAddress, ExpectationKind::PlannedDates], true)) {
            if (! Gate::allows('update', $project)) {
                return [];
            }

            $isSite = $kind === ExpectationKind::SiteAddress;

            if (is_a($pageClass, EditProject::class, true)) {
                $stepLabel = $isSite ? __('project.wizard.site') : __('project.wizard.plan');
                $stepId = $isSite ? self::STEP_SITE : self::STEP_PLAN;
                $jump = fn (string $name, Heroicon $icon, string $label): Action => Action::make($name)
                    ->label($label)
                    ->tooltip($label)
                    ->icon($icon)
                    ->iconButton()
                    ->dispatch('go-to-wizard-step', ['key' => self::wizardKey(), 'step' => self::stepKey($stepId)]);

                return [
                    ...($met ? [] : [$jump('chk_add_'.$suffix, Heroicon::OutlinedPlus, __('project.actions.quick_add'))->color('primary')]),
                    $jump('chk_edit_'.$suffix, Heroicon::OutlinedPencilSquare, __('project.actions.go_to_step', ['step' => $stepLabel]))->color('gray'),
                ];
            }

            $modal = fn (string $name): Action => $isSite
                ? $this->quickSiteAction($project, $name)
                : $this->quickDatesAction($project, $name);

            return [
                ...($met ? [] : [$modal('chk_add_'.$suffix)->label(__('project.actions.quick_add'))->tooltip(__('project.actions.quick_add'))->icon(Heroicon::OutlinedPlus)->iconButton()->color('primary')]),
                $modal('chk_edit_'.$suffix)->tooltip($isSite ? __('project.actions.edit_site') : __('project.actions.edit_dates'))->icon(Heroicon::OutlinedPencilSquare)->iconButton()->color('gray'),
            ];
        }

        $target = $this->checklistTarget($kind, $groupCode);

        if ($target === null) {
            return [];
        }

        $key = self::relationKey($target[0], $target[1]);

        return [
            Action::make('chk_add_'.$suffix)
                ->label(__('project.actions.quick_add'))
                ->tooltip(__('project.actions.quick_add'))
                ->icon(Heroicon::OutlinedPlus)
                ->iconButton()
                ->color('primary')
                // Sayfa hedef tabloyu (gerekirse) hemen kurar ve tabloya olusturma olayini yollar.
                ->action(function (LivewireComponent $livewire) use ($key): void {
                    if (method_exists($livewire, 'checklistCreate')) {
                        $livewire->checklistCreate($key);
                    }
                }),
            Action::make('chk_edit_'.$suffix)
                ->label(__('project.actions.open_table'))
                ->tooltip(__('project.actions.open_table'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->iconButton()
                ->color('gray')
                ->url('#anchor-'.$key),
        ];
    }

    /**
     * Beklenti turunun adim icindeki tablosu: [sinif, anahtar eki].
     *
     * @return array{0: class-string<\Filament\Resources\RelationManagers\RelationManager>, 1: string}|null
     */
    private function checklistTarget(ExpectationKind $kind, string $groupCode): ?array
    {
        $group = strtoupper($groupCode);

        return match ($kind) {
            ExpectationKind::Components => [ComponentsRelationManager::class, ''],
            ExpectationKind::Documents, ExpectationKind::Drawings => [DocumentsRelationManager::class, 'project'],
            ExpectationKind::AutomationDocuments => [AutomationDocumentsRelationManager::class, ''],
            ExpectationKind::Photos => [PhotosRelationManager::class, $group === 'FIELD' ? 'field' : 'project'],
            ExpectationKind::Wbs => [WbsNodesRelationManager::class, ''],
            ExpectationKind::Milestones => [MilestonesRelationManager::class, ''],
            ExpectationKind::ScheduleBaseline => [ScheduleBaselinesRelationManager::class, ''],
            ExpectationKind::SupplyItems, ExpectationKind::SupplyOrdered => [SupplyItemsRelationManager::class, ''],
            ExpectationKind::SupplyDelivered => [$group === 'LOGISTICS' ? LogisticsItemsRelationManager::class : SupplyItemsRelationManager::class, ''],
            ExpectationKind::SoftwareItems => [$group === 'SOFTWARE' ? SoftwareItemsRelationManager::class : SupplyItemsRelationManager::class, ''],
            ExpectationKind::Cbs => [CbsNodesRelationManager::class, ''],
            ExpectationKind::Exposures => [ExposuresRelationManager::class, ''],
            ExpectationKind::TeamMembers => [TeamMembersRelationManager::class, ''],
            ExpectationKind::WorkPackages => [WorkPackagesRelationManager::class, ''],
            ExpectationKind::Progress => [ProgressSnapshotsRelationManager::class, ''],
            default => null,
        };
    }

    /**
     * Adima gore ekranda dogrudan gosterilen tablolar (departman tasarimi).
     *
     * @return list<Component>
     */
    private function stepTables(string $groupCode, Project $project, string $pageClass, bool $eagerAll = false, array $eagerKeys = []): array
    {
        $rm = fn (string $class, string $suffix = ''): Livewire => $this->relationManager(
            $class,
            $project,
            $pageClass,
            $suffix,
            $eagerAll || in_array(self::relationKey($class, $suffix), $eagerKeys, true),
        );

        return match (strtoupper($groupCode)) {
            'PROJECT' => [
                $rm(ComponentsRelationManager::class),
                $rm(DocumentsRelationManager::class, 'project'),
                $rm(PhotosRelationManager::class, 'project'),
                $rm(WbsNodesRelationManager::class),
                $rm(MilestonesRelationManager::class),
                $rm(ScheduleBaselinesRelationManager::class),
            ],
            'PROCUREMENT' => [
                $rm(SupplyItemsRelationManager::class),
            ],
            'ACCOUNTING' => [
                $rm(CbsNodesRelationManager::class),
                $rm(ExposuresRelationManager::class),
                $rm(ClarificationsRelationManager::class),
            ],
            'LOGISTICS' => [
                $this->workspace->siteAddressSection($project),
                $rm(LogisticsItemsRelationManager::class),
            ],
            'FIELD' => [
                $rm(TeamMembersRelationManager::class),
                $rm(WorkPackagesRelationManager::class),
                $rm(ProgressSnapshotsRelationManager::class),
                $rm(IssuesRelationManager::class),
                $rm(DelayEventsRelationManager::class),
                $rm(PhotosRelationManager::class, 'field'),
            ],
            'SOFTWARE' => [
                $rm(SoftwareItemsRelationManager::class),
                $rm(AutomationDocumentsRelationManager::class),
            ],
            default => [],
        };
    }

    /**
     * Adim basligi altindaki kisa durum: "Su an buradayiz · 2/3", "Tamamlandi",
     * "Sirada · 1 eksik".
     *
     * @param  array<string, mixed>  $step
     */
    private function stepDescription(array $step): string
    {
        $progress = __('project.steps.progress', ['met' => $step['mandatory_met'], 'total' => $step['mandatory_total']]);

        if ($step['is_current']) {
            return __('project.steps.here').' · '.$progress;
        }

        if (in_array($step['status'], [WorkstreamStatus::Completed, WorkstreamStatus::Waived], true)) {
            return __('project.steps.done');
        }

        $missing = $step['mandatory_total'] - $step['mandatory_met'];

        return __('project.steps.upcoming').' · '.($step['is_ready']
            ? __('project.steps.ready_badge')
            : __('project.steps.missing_badge', ['count' => $missing]));
    }

    /**
     * Adimin ust seridi: odak rozeti + workstream durumu.
     *
     * @param  array<string, mixed>  $step
     */
    private function stepBanner(array $step): Component
    {
        $status = $step['status'] instanceof WorkstreamStatus ? $step['status']->getLabel() : (string) $step['status'];
        $color = $this->workspace->stepColor($step);

        return Flex::make([
            Text::make($this->workspace->stepBadge($step))
                ->badge()
                ->color($color)
                ->icon($step['is_current'] ? Heroicon::OutlinedPlayCircle : ($color === 'success' ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock)),
            Text::make(__('project.steps.status_line', ['status' => $status]))
                ->badge()
                ->color('gray'),
        ]);
    }
}
