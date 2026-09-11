<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\RelationManagers;

use App\Enums\Acquisition\ContractRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ContractParty;
use App\Services\Acquisition\ContractPartyService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartiesRelationManager extends RelationManager
{
    protected static string $relationship = 'parties';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedUserGroup;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contract_party.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contract_party.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('party_id')
                            ->label(__('contract_party.fields.party'))
                            ->relationship('party', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('contract_role')
                            ->label(__('contract_party.fields.contract_role'))
                            ->options(ContractRole::class)
                            ->default(ContractRole::Employer->value)
                            ->required()
                            ->native(false),
                        TextInput::make('signatory_name')
                            ->label(__('contract_party.fields.signatory_name'))
                            ->maxLength(255),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contract_party.label'))
            ->heading(__('contract_party.relation.title'))
            ->recordTitleAttribute('contract_role')
            ->columns([
                TextColumn::make('party.display_name')
                    ->label(__('contract_party.fields.party')),
                TextColumn::make('contract_role')
                    ->label(__('contract_party.fields.contract_role'))
                    ->badge(),
                TextColumn::make('signatory_name')
                    ->label(__('contract_party.fields.signatory_name'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['contract_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContractPartyService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ContractParty $record, array $data): Model {
                        try {
                            return app(ContractPartyService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ContractParty $record): bool {
                        try {
                            return app(ContractPartyService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('contract_party.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup);
    }
}
