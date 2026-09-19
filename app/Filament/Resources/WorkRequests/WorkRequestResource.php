<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Enums\WorkRequest\WorkRequestPriority;
use App\Enums\WorkRequest\WorkRequestStatus;
use App\Filament\Resources\WorkRequests\Pages\CreateWorkRequest;
use App\Filament\Resources\WorkRequests\Pages\EditWorkRequest;
use App\Filament\Resources\WorkRequests\Pages\ListWorkRequests;
use App\Filament\Resources\WorkRequests\Pages\ViewWorkRequest;
use App\Filament\Resources\WorkRequests\RelationManagers\ActivitiesRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Models\WorkRequest\WorkRequest;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\WorkRequest\WorkRequestQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Talepler (D-84): departman -> personel, departman -> departman,
 * personel -> departman, personel -> personel. Ilgili proje / musteri /
 * urun-bilesen / teklif / is dosyasi / sozlesme / belge istege bagli
 * baglanir. Sohbetteki bir mesajdan "Talep ac" ile onceden doldurulmus
 * gelir (?kaynak_mesaj=). Menude ust seviyede; rozet gelen kutusu.
 */
class WorkRequestResource extends Resource
{
    protected static ?string $model = WorkRequest::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = -2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('work_request.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('work_request.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('work_request.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('work_requests.admin_ui')
            && SchemaReadiness::hasBatch('B11B')
            && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $personnelId = auth()->id();

        if ($personnelId === null || ! static::canAccess()) {
            return null;
        }

        $count = app(WorkRequestQueries::class)->inboxCount((int) $personnelId);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $myUnitId = $user instanceof Personnel && $user->org_unit_id !== null ? (int) $user->org_unit_id : null;
        $isPersonnel = fn (Get $get): bool => $get('target_kind') === RequestTargetKind::Personnel->value;
        $isUnit = fn (Get $get): bool => $get('target_kind') === RequestTargetKind::OrgUnit->value;

        $half = ['default' => 1, 'md' => 2];

        return $schema->columns(1)->components([
            // "Kimden" ve "Kime" yan yana, yarim genislik (12 Eylul 2026 kullanici istegi).
            Grid::make(['default' => 1, 'lg' => 2])->components([
                Section::make(__('work_request.sections.requester'))
                    ->description(__('work_request.help.requester'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->columns($half)
                    ->components([
                        Placeholder::make('requester_name')
                            ->label(__('work_request.fields.requester'))
                            ->content(fn (?WorkRequest $record): string => $record?->requester?->full_name ?? ($user instanceof Personnel ? (string) $user->full_name : '-')),
                        Toggle::make('on_behalf_of_unit')
                            ->label(__('work_request.fields.on_behalf_of_unit'))
                            ->helperText(__('work_request.help.on_behalf_of_unit'))
                            ->live()
                            ->dehydrated()
                            ->afterStateHydrated(fn (Toggle $component, ?WorkRequest $record) => $component->state($record?->requester_org_unit_id !== null)),
                        Select::make('requester_org_unit_id')
                            ->label(__('work_request.fields.requester_org_unit'))
                            ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                            ->default($myUnitId)
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get): bool => (bool) $get('on_behalf_of_unit'))
                            ->required(fn (Get $get): bool => (bool) $get('on_behalf_of_unit'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('work_request.sections.target'))
                    ->description(__('work_request.help.target'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->columns($half)
                    ->components([
                        Select::make('target_kind')
                            ->label(__('work_request.fields.target_kind'))
                            ->options(RequestTargetKind::options())
                            ->default(RequestTargetKind::Personnel->value)
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('target_personnel_id')
                            ->label(__('work_request.fields.target_personnel'))
                            ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                            ->searchable()
                            ->native(false)
                            ->visible($isPersonnel)
                            ->required($isPersonnel),
                        Select::make('target_org_unit_id')
                            ->label(__('work_request.fields.target_org_unit'))
                            ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                            ->searchable()
                            ->native(false)
                            ->visible($isUnit)
                            ->required($isUnit),
                    ]),
            ]),
            Section::make(__('work_request.sections.request'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('title')
                        ->label(__('work_request.fields.title'))
                        ->required()
                        ->maxLength(200)
                        ->columnSpan(FieldGrid::HALF),
                    Select::make('priority')
                        ->label(__('work_request.fields.priority'))
                        ->options(WorkRequestPriority::options())
                        ->default(WorkRequestPriority::Normal->value)
                        ->required()
                        ->native(false),
                    DatePicker::make('due_on')
                        ->label(__('work_request.fields.due_on')),
                    // Onaya tabi talep (D-87, B11D): muhatap tamamlayinca secilen onay mercii onaylar.
                    Toggle::make('requires_approval')
                        ->label(__('work_request.fields.requires_approval'))
                        ->helperText(__('work_request.help.requires_approval'))
                        ->live()
                        ->visible(fn (): bool => SchemaReadiness::hasBatch('B11D') && SchemaReadiness::hasBatch('B07'))
                        ->columnSpan(FieldGrid::NORMAL),
                    Select::make('approver_personnel_id')
                        ->label(__('work_request.fields.approver'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => SchemaReadiness::hasBatch('B11D') && (bool) $get('requires_approval'))
                        ->required(fn (Get $get): bool => (bool) $get('requires_approval')),
                    Textarea::make('description')
                        ->label(__('work_request.fields.description'))
                        ->helperText(__('work_request.help.description_links'))
                        ->rows(5)
                        ->maxLength(8000),
                    // Talebin ilk mesajina dosya / fotograf eki (B32); yalniz olustururken.
                    FileUpload::make('attachments')
                        ->label(__('work_request.fields.attachments'))
                        ->helperText(__('work_request.thread.files_help'))
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(20480)
                        ->disk('local')
                        ->directory('work-request-tmp')
                        ->visibility('private')
                        ->storeFileNamesIn('attachment_names')
                        ->visible(fn (string $operation): bool => $operation === 'create' && SchemaReadiness::hasBatch('B32')),
                ])),
            Section::make(__('work_request.sections.related'))
                ->description(__('work_request.help.related'))
                ->icon(Heroicon::OutlinedLink)
                ->collapsible()
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('project_id')
                        ->label(__('work_request.fields.project'))
                        ->options(fn (): array => SchemaReadiness::hasBatch('B17') ? app(ProjectCatalogQueries::class)->projectOptions() : [])
                        ->searchable()
                        ->native(false),
                    Select::make('customer_party_id')
                        ->label(__('work_request.fields.customer'))
                        ->options(fn (): array => app(WorkRequestQueries::class)->customerOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('component_definition_id')
                        ->label(__('work_request.fields.component'))
                        ->options(fn (): array => SchemaReadiness::hasBatch('B17') ? app(ProjectCatalogQueries::class)->componentDefinitionOptions() : [])
                        ->searchable()
                        ->native(false),
                    Select::make('proposal_id')
                        ->label(__('work_request.fields.proposal'))
                        ->options(fn (): array => app(WorkRequestQueries::class)->proposalOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('business_case_id')
                        ->label(__('work_request.fields.business_case'))
                        ->options(fn (): array => app(WorkRequestQueries::class)->businessCaseOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('contract_id')
                        ->label(__('work_request.fields.contract'))
                        ->options(fn (): array => app(WorkRequestQueries::class)->contractOptions())
                        ->searchable()
                        ->native(false),
                    Select::make('document_id')
                        ->label(__('work_request.fields.document'))
                        ->options(fn (): array => app(WorkRequestQueries::class)->documentOptions())
                        ->searchable()
                        ->native(false),
                    Hidden::make('source_message_id'),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_no')
                    ->label(__('work_request.fields.request_no'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('title')
                    ->label(__('work_request.fields.title'))
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (WorkRequest $record): ?string => filled($record->description) ? Str::limit((string) $record->description, 90) : null)
                    ->wrap(),
                TextColumn::make('priority')
                    ->label(__('work_request.fields.priority'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('work_request.fields.status'))
                    ->badge(),
                TextColumn::make('requester_label')
                    ->label(__('work_request.fields.from'))
                    ->state(fn (WorkRequest $record): string => $record->requesterLabel())
                    ->wrap(),
                TextColumn::make('target_label')
                    ->label(__('work_request.fields.to'))
                    ->state(fn (WorkRequest $record): string => $record->targetLabel())
                    ->icon(fn (WorkRequest $record): Heroicon => $record->target_kind->getIcon())
                    ->wrap(),
                TextColumn::make('due_on')
                    ->label(__('work_request.fields.due_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('work_request.fields.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('work_request.fields.status'))
                    ->options(WorkRequestStatus::class),
                SelectFilter::make('priority')
                    ->label(__('work_request.fields.priority'))
                    ->options(WorkRequestPriority::class),
                SelectFilter::make('target_kind')
                    ->label(__('work_request.fields.target_kind'))
                    ->options(RequestTargetKind::class),
            ])
            ->recordActions([
                ViewAction::make()->label(__('work_request.actions.open')),
            ])
            ->toolbarActions([])
            ->modifyQueryUsing(fn ($query) => $query->with(['requester', 'requesterOrgUnit', 'targetPersonnel', 'targetOrgUnit', 'assignee']))
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        // Hareket gecmisi talep kartinin altinda tablo olarak (12 Eylul 2026).
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkRequests::route('/'),
            'create' => CreateWorkRequest::route('/create'),
            'view' => ViewWorkRequest::route('/{record}'),
            'edit' => EditWorkRequest::route('/{record}/edit'),
        ];
    }
}
