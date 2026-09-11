<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties;

use App\Enums\Party\ConsentStatus;
use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Parties\Pages\CreateParty;
use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Parties\Pages\ListParties;
use App\Filament\Resources\Parties\Pages\ViewParty;
use App\Filament\Resources\Parties\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\AnnualReviewsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\CommunicationPointsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\LicensesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\RolesRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Party\Party;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                        Select::make('default_locale')
                            ->label(__('party.fields.default_locale'))
                            ->options(['tr' => 'Türkçe', 'en' => 'English'])
                            ->default('tr')
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('party.fields.status'))
                            ->options(PartyStatus::class)
                            ->default(PartyStatus::Prospect->value)
                            ->required()
                            ->native(false),
                ])),
            Section::make(__('party.sections.organization'))
                ->columns(FieldGrid::COLUMNS)
                ->visible(fn (Get $get): bool => $get('party_kind') === 'organization')
                ->components(FieldGrid::fields([
                        TextInput::make('organization_profile.legal_name')
                            ->label(__('party.fields.legal_name'))
                            ->maxLength(255),
                        TextInput::make('organization_profile.trade_name')
                            ->label(__('party.fields.trade_name'))
                            ->maxLength(255),
                        TextInput::make('organization_profile.tax_office')
                            ->label(__('party.fields.tax_office'))
                            ->maxLength(100),
                        TextInput::make('organization_profile.tax_number')
                            ->label(__('party.fields.tax_number'))
                            ->maxLength(32),
                        TextInput::make('organization_profile.registration_no')
                            ->label(__('party.fields.registration_no'))
                            ->maxLength(64),
                        TextInput::make('organization_profile.sector_code')
                            ->label(__('party.fields.sector_code'))
                            ->maxLength(32),
                        TextInput::make('organization_profile.founded_year')
                            ->label(__('party.fields.founded_year'))
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2100),
                        TextInput::make('organization_profile.website_url')
                            ->label(__('party.fields.website_url'))
                            ->maxLength(2048),
                        Toggle::make('organization_profile.is_public_company')
                            ->label(__('party.fields.is_public_company')),
                ])),
            Section::make(__('party.sections.person'))
                ->columns(FieldGrid::COLUMNS)
                ->visible(fn (Get $get): bool => $get('party_kind') === 'person')
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
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
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
            ])
            ->filters([
                SelectFilter::make('party_kind')
                    ->label(__('party.fields.party_kind'))
                    ->options(PartyKind::class),
                SelectFilter::make('status')
                    ->label(__('party.fields.status'))
                    ->options(PartyStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('display_name');
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
            AddressesRelationManager::class,
            CommunicationPointsRelationManager::class,
            ContactsRelationManager::class,
            LicensesRelationManager::class,
            CertificatesRelationManager::class,
            AnnualReviewsRelationManager::class,
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
