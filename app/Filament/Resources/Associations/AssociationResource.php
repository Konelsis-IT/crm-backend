<?php

declare(strict_types=1);

namespace App\Filament\Resources\Associations;

use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Associations\Pages\CreateAssociation;
use App\Filament\Resources\Associations\Pages\EditAssociation;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\Associations\Pages\ViewAssociation;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Parties\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Parties\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Parties\RelationManagers\MeetingNotesRelationManager;
use App\Filament\Resources\Parties\Schemas\PartyInfolist;
use App\Models\Party\Party;
use App\Query\Party\PartyQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Dernekler (21 Eylul 2026 kullanici karari): veritabani ve model olarak taraf
 * (parties, "Dernek / oda" tipi) kullanilir ama menu kendine ozgudur. Listede
 * sekme yoktur, "Dernek olustur" ile acilir, formda taraf tipi secilmez
 * (kayit kendiliginden Dernek / oda tipiyle acilir). Dernekler Taraflar
 * listesinde yer almaz. Yetki taraf yetkisidir (PartyPolicy).
 */
class AssociationResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static ?string $slug = 'associations';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    // Is Alim menusunde Ihaleler'in (30) hemen altinda.
    protected static ?int $navigationSort = 35;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return __('association.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('association.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('acquisition.admin_ui')
            && SchemaReadiness::hasBatch('B33')
            && parent::canAccess();
    }

    /** Yalniz acik "Dernek / oda" tipindeki taraflar. */
    public static function getEloquentQuery(): Builder
    {
        return app(PartyQueries::class)->withRole(parent::getEloquentQuery(), PartyRoleCode::Association);
    }

    public static function form(Schema $schema): Schema
    {
        return PartyResource::partyForm($schema, association: true);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartyInfolist::configure($schema, self::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('association.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contacts_count')
                    ->label(__('association.fields.contacts'))
                    ->counts('contacts')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('network_note')
                    ->label(__('party.fields.network_note'))
                    ->limit(40)
                    ->placeholder('-')
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B28')),
                TextColumn::make('status')
                    ->label(__('party.fields.status'))
                    ->badge(),
                TextColumn::make('party_no')
                    ->label(__('party.fields.party_no'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                SelectFilter::make('status')
                    ->label(__('party.fields.status'))
                    ->options(PartyStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                PartyResource::archiveAction(),
                PartyResource::restoreAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('display_name');
    }

    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
            ContactsRelationManager::class,
            ...(SchemaReadiness::hasBatch('B28') ? [MeetingNotesRelationManager::class] : []),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssociations::route('/'),
            'create' => CreateAssociation::route('/create'),
            'view' => ViewAssociation::route('/{record}'),
            'edit' => EditAssociation::route('/{record}/edit'),
        ];
    }
}
