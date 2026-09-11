<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCriticality;
use App\Enums\Acquisition\BusinessSourceKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\BusinessCases\RelationManagers\ProposalsRelationManager;
use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Acquisition\BusinessCase;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Project\ProjectConversionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Livewire\Component as LivewireComponent;

/**
 * Is alim zinciri sihirbazi (D-72): "Is dosyasi -> Teklif -> Proje" uc adimi
 * olusturma, duzenleme ve goruntulemede ayni sirayla gosterilir.
 *
 * - Olusturma: 1 is dosyasi alanlari, 2 ilk teklif ve surumu (istege bagli),
 *   3 hemen projeye donusum (istege bagli) — AcquisitionIntakeService.
 * - Duzenleme: 1 alanlar (Kaydet), 2 teklifler tablosu, 3 proje/donusum.
 * - Goruntuleme: kart + asama uyarisi + ayni uc adim (1 ayrintilar).
 */
final class BusinessCaseWizard
{
    public const STEP_CASE = 'case';

    public const STEP_PROPOSAL = 'proposal';

    public const STEP_PROJECT = 'project';

    /** @var list<string> */
    public const STEP_IDS = [self::STEP_CASE, self::STEP_PROPOSAL, self::STEP_PROJECT];

    /**
     * Is dosyasi form alanlari (kaynak formu, olusturma ve duzenleme adimi).
     *
     * @return list<Component>
     */
    public function caseFields(): array
    {
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
                ->native(false),
            TextInput::make('title')
                ->label(__('business_case.fields.title'))
                ->required()
                ->maxLength(255),
            Textarea::make('short_description')
                ->label(__('business_case.fields.short_description'))
                ->columnSpanFull(),
            Select::make('country_code')
                ->label(__('business_case.fields.country'))
                ->options(fn (): array => app(ReferenceOptions::class)->countries())
                ->default((string) config('konelsis.legal_entity.country', 'TR'))
                ->searchable()
                ->required()
                ->native(false),
            Select::make('currency_code')
                ->label(__('business_case.fields.currency'))
                ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                ->default((string) config('konelsis.organization.default_currency', 'TRY'))
                ->searchable()
                ->required()
                ->native(false),
            Select::make('project_type_code')
                ->label(__('business_case.fields.project_type_code'))
                ->options(fn (): array => app(ProjectCatalogQueries::class)->componentDefinitionOptions())
                ->searchable()
                ->native(false),
            Select::make('source_kind')
                ->label(__('business_case.fields.source_kind'))
                ->options(BusinessSourceKind::class)
                ->default(BusinessSourceKind::Manual->value)
                ->required()
                ->native(false),
            Select::make('criticality')
                ->label(__('business_case.fields.criticality'))
                ->options(BusinessCriticality::class)
                ->default(BusinessCriticality::Normal->value)
                ->required()
                ->native(false),
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
                ->minValue(0),
            Select::make('classification_id')
                ->label(__('business_case.fields.classification'))
                ->relationship('classification', 'name_tr')
                ->searchable()
                ->preload()
                ->native(false),
            Select::make('legal_entity_id')
                ->label(__('business_case.fields.legal_entity'))
                ->relationship('legalEntity', 'legal_name')
                ->searchable()
                ->preload()
                ->native(false),
            Hidden::make('row_version')->hiddenOn('create'),
        ];
    }

    /**
     * Olusturma sihirbazi adimlari.
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
                ->schema($this->proposalSections()),
            Step::make(__('business_case.wizard.project'))
                ->id(self::STEP_PROJECT)
                ->description(__('business_case.wizard.project_description'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->completedIcon(Heroicon::OutlinedRocketLaunch)
                ->columns(1)
                ->schema($this->projectSections()),
        ];
    }

    /**
     * Is dosyasi alanlari bolumler halinde (D-80): musteri ve baslik,
     * siniflandirma, ticari bilgiler, sorumlular. Kaynak formu ve sihirbazin
     * 1. adimi ayni bolumleri kullanir.
     *
     * @return list<Component>
     */
    public function caseSections(): array
    {
        return FieldGrid::group($this->caseFields(), [
            'identity' => ['label' => __('business_case.sections.identity'), 'icon' => Heroicon::OutlinedBriefcase, 'fields' => ['primary_party_id', 'title', 'short_description']],
            'classification' => ['label' => __('business_case.sections.classification'), 'icon' => Heroicon::OutlinedTag, 'fields' => ['country_code', 'project_type_code', 'source_kind', 'criticality', 'classification_id']],
            'commercial' => ['label' => __('business_case.sections.commercial'), 'icon' => Heroicon::OutlinedBanknotes, 'fields' => ['currency_code', 'estimated_value', 'legal_entity_id']],
            'ownership' => ['label' => __('business_case.sections.ownership'), 'icon' => Heroicon::OutlinedUsers, 'fields' => ['owner_employee_id', 'proposal_owner_employee_id']],
        ]);
    }

    /**
     * @return list<Component>
     */
    private function proposalSections(): array
    {
        return FieldGrid::group($this->proposalFields(), [
            'proposal' => ['label' => __('business_case.sections.proposal'), 'icon' => Heroicon::OutlinedClipboardDocumentList, 'fields' => ['create_proposal', 'proposal_title', 'total_price', 'margin_pct', 'validity_until', 'is_critical_route', 'summary']],
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
            ->columns(1)
            ->schema($this->caseSections());
    }

    /** Adim 1 (goruntuleme): kartta olmayan ayrintilar + duzenleme baglantisi. */
    public function detailsStep(BusinessCase $case): Step
    {
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
                    TextEntry::make('criticality')
                        ->label(__('business_case.fields.criticality'))
                        ->state($case->criticality?->getLabel() ?? '-')
                        ->badge()
                        ->color($case->criticality?->getColor() ?? 'gray'),
                    TextEntry::make('project_type_code')
                        ->label(__('business_case.fields.project_type_code'))
                        ->state($case->project_type_code ?? '-')
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
     * Olusturma adim 2: ilk teklif ve surumu.
     *
     * @return list<Component>
     */
    private function proposalFields(): array
    {
        $whenProposal = fn (Get $get): bool => (bool) $get('create_proposal');

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
}
