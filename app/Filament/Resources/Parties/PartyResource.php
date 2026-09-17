<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\ConsentStatus;
use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyRoleStatus;
use App\Enums\Party\PartyStatus;
use App\Enums\Party\VisitPriority;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Parties\Pages\CreateParty;
use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Parties\Pages\ListParties;
use App\Filament\Resources\Parties\Pages\ViewParty;
use App\Filament\Resources\Parties\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\AnnualReviewsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\LicensesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\MeetingNotesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\RolesRelationManager;
use App\Filament\Resources\Parties\Schemas\PartyInfolist;
use App\Filament\Resources\WorkRequests\RelationManagers\RelatedWorkRequestsRelationManager;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\CommunicationPoint;
use App\Models\Party\Party;
use App\Query\Party\PartyQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Party\PartyService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use UnitEnum;

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return __('party.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('party.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('acquisition.admin_ui')
            && SchemaReadiness::hasBatch('B16')
            && parent::canAccess();
    }

    /** Ayrinti sayfasi: personel ayrintisiyla ayni kart yapisi (PartyInfolist). */
    public static function infolist(Schema $schema): Schema
    {
        return PartyInfolist::configure($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('party.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('party_kind')
                            ->label(__('party.fields.party_kind'))
                            ->options(PartyKind::class)
                            ->default(PartyKind::Organization->value)
                            ->required()
                            ->native(false)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false)
                            ->live(),
                        TextInput::make('display_name')
                            ->label(__('party.fields.display_name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('country_code')
                            ->label(__('party.fields.country'))
                            ->options(fn (): array => app(ReferenceOptions::class)->countries())
                            ->default('TR')
                            ->searchable()
                            ->native(false),
                        Select::make('status')
                            ->label(__('party.fields.status'))
                            ->options(PartyStatus::class)
                            ->default(PartyStatus::Prospect->value)
                            ->required()
                            ->native(false),
                        // Network ve ziyaret onceligi (B28, 16 Eylul 2026 kullanici
                        // karari): B28 uygulanana kadar gizli ve kaydedilmez.
                        TextInput::make('network_note')
                            ->label(__('party.fields.network_note'))
                            ->helperText(__('party.help.network_note'))
                            ->maxLength(255)
                            ->columnSpan(FieldGrid::WIDE)
                            ->visible(fn (): bool => SchemaReadiness::hasBatch('B28'))
                            ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B28')),
                        Select::make('visit_priority')
                            ->label(__('party.fields.visit_priority'))
                            ->options(VisitPriority::class)
                            ->native(false)
                            ->placeholder('-')
                            ->visible(fn (): bool => SchemaReadiness::hasBatch('B28'))
                            ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B28')),
                ])),
            // Kuruma ait iletisim bilgileri (B28, 16 Eylul 2026 kullanici karari):
            // kisiden bagimsiz e-posta, telefon, web sitesi satirlari. Kisilere ait
            // kanallar "Iletisim ve kisiler" listesinden girilir. Degeri bos
            // satirlar kayitta atlanir.
            Section::make(__('party.sections.channels'))
                ->description(__('party.help.channels'))
                ->visible(fn (): bool => SchemaReadiness::hasBatch('B27'))
                ->components([
                    Repeater::make('communication_points')
                        ->hiddenLabel()
                        ->addActionLabel(__('party.actions.add_channel'))
                        ->dehydrated(fn (): bool => SchemaReadiness::hasBatch('B27'))
                        ->afterStateHydrated(function (Repeater $component, ?Party $record): void {
                            // Yeni kayitta varsayilan bos satir korunur; kayit varsa
                            // kurumun kendi kanallari yuklenir.
                            if ($record === null) {
                                return;
                            }

                            $component->state($record->ownCommunicationPoints
                                ->map(fn (CommunicationPoint $point): array => [
                                    'channel_type' => $point->channel_type?->value,
                                    'value' => $point->value,
                                    'purpose' => $point->purpose,
                                    'is_primary' => (bool) $point->is_primary,
                                ])->all());
                            $component->hydrateItems();
                        })
                        ->table([
                            TableColumn::make(__('communication_point.fields.channel_type')),
                            TableColumn::make(__('communication_point.fields.value')),
                            TableColumn::make(__('communication_point.fields.purpose')),
                            TableColumn::make(__('communication_point.fields.is_primary')),
                        ])
                        ->schema([
                            Select::make('channel_type')
                                ->label(__('communication_point.fields.channel_type'))
                                ->options(CommunicationChannelType::class)
                                ->default(CommunicationChannelType::Email->value)
                                ->required()
                                ->native(false),
                            TextInput::make('value')
                                ->label(__('communication_point.fields.value'))
                                ->maxLength(100),
                            TextInput::make('purpose')
                                ->label(__('communication_point.fields.purpose'))
                                ->maxLength(32),
                            Toggle::make('is_primary')
                                ->label(__('communication_point.fields.is_primary'))
                                ->inline(false),
                        ])
                        ->defaultItems(1)
                        ->columnSpanFull(),
                ]),
            Section::make(__('party.sections.person'))
                ->columns(FieldGrid::COLUMNS)
                ->visible(fn (Get $get): bool => self::kind($get) === PartyKind::Person)
                ->components(FieldGrid::fields([
                        TextInput::make('person_profile.given_name')
                            ->label(__('party.fields.given_name'))
                            ->maxLength(255),
                        TextInput::make('person_profile.family_name')
                            ->label(__('party.fields.family_name'))
                            ->maxLength(255),
                        TextInput::make('person_profile.title')
                            ->label(__('party.fields.person_title'))
                            ->maxLength(100),
                        TextInput::make('person_profile.job_title')
                            ->label(__('party.fields.job_title'))
                            ->maxLength(255),
                        Select::make('person_profile.consent_status')
                            ->label(__('party.fields.consent_status'))
                            ->options(ConsentStatus::class)
                            ->default(ConsentStatus::Pending->value)
                            ->required()
                            ->native(false),
                ])),
            // Arsiv bilgisi (S3, D-99): kayit silinmez, arsive alinir; yalniz
            // arsivli kayitta gorunur.
            Section::make(__('party.sections.archive'))
                ->columns(FieldGrid::COLUMNS)
                ->hiddenOn('create')
                ->visible(fn (?Party $record): bool => $record?->archived_at !== null)
                ->components(FieldGrid::fields([
                        TextEntry::make('archived_at')
                            ->label(__('party.fields.archived_at'))
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('archivedBy.full_name')
                            ->label(__('party.fields.archived_by'))
                            ->placeholder('-'),
                        TextEntry::make('archive_reason')
                            ->label(__('party.fields.archive_reason'))
                            ->placeholder('-')
                            ->columnSpan(FieldGrid::HALF),
                ])),
            // Surum damgasi her taraf turunde gonderilmeli; gizli bolum icinde
            // kalirsa dehydrate edilmez ve eski surum denetimi calismaz.
            Hidden::make('row_version')->hiddenOn('create'),
        ]);
    }

    /**
     * party_kind alani Filament'ten enum ornegi ya da metin olarak gelebilir;
     * bolum gorunurlugu her iki bicimi de tanir.
     */
    private static function kind(Get $get): ?PartyKind
    {
        $kind = $get('party_kind');

        return $kind instanceof PartyKind ? $kind : PartyKind::tryFrom((string) $kind);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party_no')
                    ->label(__('party.fields.party_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label(__('party.fields.display_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('party_kind')
                    ->label(__('party.fields.party_kind'))
                    ->badge(),
                TextColumn::make('roles.role_code')
                    ->label(__('party.fields.roles'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('country.name_tr')
                    ->label(__('party.fields.country'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('party.fields.status'))
                    ->badge(),
                TextColumn::make('archived_at')
                    ->label(__('party.fields.archived_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('visit_priority')
                    ->label(__('party.fields.visit_priority'))
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B28')),
                TextColumn::make('network_note')
                    ->label(__('party.fields.network_note'))
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B28')),
            ])
            ->filters([
                // Arsiv (S3): varsayilan yalniz aktif kayitlar.
                SelectFilter::make('archive')
                    ->label(__('party.filters.archive'))
                    ->options([
                        'active' => __('party.filters.archive_active'),
                        'archived' => __('party.filters.archive_archived'),
                        'all' => __('party.filters.archive_all'),
                    ])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->query(fn (Builder $query, array $data): Builder => app(PartyQueries::class)->archiveScope($query, (string) ($data['value'] ?? 'active'))),
                SelectFilter::make('party_kind')
                    ->label(__('party.fields.party_kind'))
                    ->options(PartyKind::class),
                SelectFilter::make('status')
                    ->label(__('party.fields.status'))
                    ->options(PartyStatus::class),
                SelectFilter::make('visit_priority')
                    ->label(__('party.fields.visit_priority'))
                    ->options(VisitPriority::class)
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B28')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::archiveAction(),
                self::restoreAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('display_name');
    }

    /**
     * Arsivle (S3, D-99): kayit silinmez; gerekceyle arsive alinir. Listede,
     * goruntuleme ve duzenleme sayfalarinda ayni eylem kullanilir.
     */
    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(__('party.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('party.actions.archive'))
            ->modalDescription(__('party.help.archive'))
            ->visible(fn (Party $record): bool => $record->archived_at === null && (auth()->user()?->can('update', $record) ?? false))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                Textarea::make('archive_reason')
                    ->label(__('party.fields.archive_reason'))
                    ->required()
                    ->maxLength(100)
                    ->rows(3)
                    ->columnSpanFull(),
            ])))
            ->action(function (Party $record, array $data, Component $livewire): void {
                try {
                    app(PartyService::class)->archive($record, (string) ($data['archive_reason'] ?? ''));
                    DomainNotifications::success(__('party.messages.archived'));
                    self::redirectToView($livewire, $record);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Arsivden cikar: archived_at temizlenir, kayit listeye doner. */
    public static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('party.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('party.actions.restore'))
            ->modalDescription(__('party.help.restore'))
            ->visible(fn (Party $record): bool => $record->archived_at !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (Party $record, Component $livewire): void {
                try {
                    app(PartyService::class)->restore($record);
                    DomainNotifications::success(__('party.messages.restored'));
                    self::redirectToView($livewire, $record);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Sayfa ustunden yapilan islemden sonra kart yeniden yuklenir; listede yerinde kalinir. */
    private static function redirectToView(Component $livewire, Party $record): void
    {
        if ($livewire instanceof ViewRecord || $livewire instanceof EditRecord) {
            $livewire->redirect(self::getUrl('view', ['record' => $record]), navigate: true);
        }
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
            AddressesRelationManager::class,
            ContactsRelationManager::class,
            ...(SchemaReadiness::hasBatch('B28') ? [MeetingNotesRelationManager::class] : []),
            LicensesRelationManager::class,
            CertificatesRelationManager::class,
            AnnualReviewsRelationManager::class,
            ...(SchemaReadiness::hasBatch('B11B') ? [RelatedWorkRequestsRelationManager::class] : []),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParties::route('/'),
            'create' => CreateParty::route('/create'),
            'view' => ViewParty::route('/{record}'),
            'edit' => EditParty::route('/{record}/edit'),
        ];
    }
}
