<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\CommunicationPointStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\CommunicationPoint;
use App\Services\Party\CommunicationPointService;
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

class CommunicationPointsRelationManager extends RelationManager
{
    protected static string $relationship = 'communicationPoints';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPhone;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('communication_point.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('communication_point.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('channel_type')
                            ->label(__('communication_point.fields.channel_type'))
                            ->options(CommunicationChannelType::class)
                            ->default(CommunicationChannelType::Email->value)
                            ->required()
                            ->native(false),
                        TextInput::make('value')
                            ->label(__('communication_point.fields.value'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('purpose')
                            ->label(__('communication_point.fields.purpose'))
                            ->maxLength(32),
                        Toggle::make('is_primary')
                            ->label(__('communication_point.fields.is_primary')),
                        Select::make('status')
                            ->label(__('communication_point.fields.status'))
                            ->options(CommunicationPointStatus::class)
                            ->default(CommunicationPointStatus::Active->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('communication_point.label'))
            ->heading(__('communication_point.relation.title'))
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('channel_type')
                    ->label(__('communication_point.fields.channel_type'))
                    ->badge(),
                TextColumn::make('value')
                    ->label(__('communication_point.fields.value'))
                    ->searchable(),
                TextColumn::make('purpose')
                    ->label(__('communication_point.fields.purpose'))
                    ->placeholder('-'),
                IconColumn::make('is_primary')
                    ->label(__('communication_point.fields.is_primary'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('communication_point.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(CommunicationPointService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (CommunicationPoint $record, array $data): Model {
                        try {
                            return app(CommunicationPointService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (CommunicationPoint $record): bool {
                        try {
                            return app(CommunicationPointService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('communication_point.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPhone);
    }
}
