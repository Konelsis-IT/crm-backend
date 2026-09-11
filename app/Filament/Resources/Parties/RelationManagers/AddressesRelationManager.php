<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\AddressType;
use App\Enums\Shared\ActiveStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\TurkiyeAddressFields;
use App\Models\Party\Address;
use App\Query\Reference\ReferenceOptions;
use App\Services\Party\AddressService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedMapPin;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('address.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('address.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('address_type')
                            ->label(__('address.fields.address_type'))
                            ->options(AddressType::class)
                            ->default(AddressType::Office->value)
                            ->required()
                            ->native(false),
                        Select::make('country_code')
                            ->label(__('address.fields.country'))
                            ->options(fn (): array => app(ReferenceOptions::class)->countries())
                            ->default('TR')
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('line1')
                            ->label(__('address.fields.line1'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('line2')
                            ->label(__('address.fields.line2'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        ...TurkiyeAddressFields::make('city', 'district', __('address.fields.city'), __('address.fields.district'), 'country_code', cityRequired: true),
                        TextInput::make('postal_code')
                            ->label(__('address.fields.postal_code'))
                            ->maxLength(32),
                        Toggle::make('is_primary')
                            ->label(__('address.fields.is_primary')),
                        Select::make('status')
                            ->label(__('address.fields.status'))
                            ->options(ActiveStatus::class)
                            ->default(ActiveStatus::Active->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('address.label'))
            ->heading(__('address.relation.title'))
            ->recordTitleAttribute('line1')
            ->columns([
                TextColumn::make('address_type')
                    ->label(__('address.fields.address_type'))
                    ->badge(),
                TextColumn::make('line1')
                    ->label(__('address.fields.line1'))
                    ->limit(40),
                TextColumn::make('city')
                    ->label(__('address.fields.city')),
                TextColumn::make('country.name_tr')
                    ->label(__('address.fields.country')),
                IconColumn::make('is_primary')
                    ->label(__('address.fields.is_primary'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('address.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(AddressService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Address $record, array $data): Model {
                        try {
                            return app(AddressService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (Address $record): bool {
                        try {
                            return app(AddressService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('address.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedMapPin);
    }
}
