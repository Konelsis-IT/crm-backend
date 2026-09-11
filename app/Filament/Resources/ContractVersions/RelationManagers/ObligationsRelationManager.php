<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\RelationManagers;

use App\Enums\Acquisition\ObligationStatus;
use App\Enums\Acquisition\ObligationType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ContractObligation;
use App\Services\Acquisition\ContractObligationService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ObligationsRelationManager extends RelationManager
{
    protected static string $relationship = 'obligations';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contract_obligation.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contract_obligation.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('obligation_code')
                            ->label(__('contract_obligation.fields.obligation_code'))
                            ->required()
                            ->maxLength(32),
                        Select::make('obligation_type')
                            ->label(__('contract_obligation.fields.obligation_type'))
                            ->options(ObligationType::class)
                            ->default(ObligationType::Delivery->value)
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('contract_obligation.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('responsible_party_id')
                            ->label(__('contract_obligation.fields.responsible_party'))
                            ->relationship('responsibleParty', 'display_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('due_on')
                            ->label(__('contract_obligation.fields.due_on'))
                            ->displayFormat('d.m.Y'),
                        Select::make('status')
                            ->label(__('contract_obligation.fields.status'))
                            ->options(ObligationStatus::class)
                            ->default(ObligationStatus::Open->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contract_obligation.label'))
            ->heading(__('contract_obligation.relation.title'))
            ->recordTitleAttribute('obligation_code')
            ->columns([
                TextColumn::make('obligation_code')
                    ->label(__('contract_obligation.fields.obligation_code')),
                TextColumn::make('obligation_type')
                    ->label(__('contract_obligation.fields.obligation_type'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('contract_obligation.fields.description'))
                    ->limit(40),
                TextColumn::make('responsibleParty.display_name')
                    ->label(__('contract_obligation.fields.responsible_party'))
                    ->placeholder('-'),
                TextColumn::make('due_on')
                    ->label(__('contract_obligation.fields.due_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('contract_obligation.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['contract_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContractObligationService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ContractObligation $record, array $data): Model {
                        try {
                            return app(ContractObligationService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ContractObligation $record): bool {
                        try {
                            return app(ContractObligationService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('due_on')
            ->emptyStateHeading(__('contract_obligation.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }
}
