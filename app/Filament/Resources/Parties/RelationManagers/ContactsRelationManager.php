<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\ContactRelationshipRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\ContactRelationship;
use App\Services\Party\ContactRelationshipService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contact_relationship.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contact_relationship.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('contact_party_id')
                            ->label(__('contact_relationship.fields.contact_party'))
                            ->relationship(
                            'contact',
                            'display_name',
                            modifyQueryUsing: fn ($query) => $query->where('party_kind', 'person'),
                        )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('relationship_role')
                            ->label(__('contact_relationship.fields.relationship_role'))
                            ->options(ContactRelationshipRole::class)
                            ->default(ContactRelationshipRole::TechnicalContact->value)
                            ->required()
                            ->native(false),
                        TextInput::make('department_note')
                            ->label(__('contact_relationship.fields.department_note'))
                            ->maxLength(100),
                        Toggle::make('is_primary')
                            ->label(__('contact_relationship.fields.is_primary')),
                        DateTimePicker::make('valid_from')
                            ->label(__('contact_relationship.fields.valid_from')),
                        DateTimePicker::make('valid_until')
                            ->label(__('contact_relationship.fields.valid_until')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contact_relationship.label'))
            ->heading(__('contact_relationship.relation.title'))
            ->recordTitleAttribute('relationship_role')
            ->columns([
                TextColumn::make('contact.display_name')
                    ->label(__('contact_relationship.fields.contact'))
                    ->searchable(),
                TextColumn::make('relationship_role')
                    ->label(__('contact_relationship.fields.relationship_role'))
                    ->badge(),
                TextColumn::make('department_note')
                    ->label(__('contact_relationship.fields.department_note'))
                    ->placeholder('-'),
                IconColumn::make('is_primary')
                    ->label(__('contact_relationship.fields.is_primary'))
                    ->boolean(),
                TextColumn::make('valid_from')
                    ->label(__('contact_relationship.fields.valid_from'))
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('valid_until')
                    ->label(__('contact_relationship.fields.valid_until'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['organization_party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContactRelationshipService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ContactRelationship $record, array $data): Model {
                        try {
                            return app(ContactRelationshipService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ContactRelationship $record): bool {
                        try {
                            return app(ContactRelationshipService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading(__('contact_relationship.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
