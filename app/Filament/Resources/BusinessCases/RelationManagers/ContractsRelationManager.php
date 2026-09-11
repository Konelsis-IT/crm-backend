<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Enums\Acquisition\ContractType;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Contract;
use App\Services\Acquisition\ContractService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contract.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contract.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('contract_type')
                            ->label(__('contract.fields.contract_type'))
                            ->options(ContractType::class)
                            ->default(ContractType::Contract->value)
                            ->required()
                            ->native(false),
                        Select::make('customer_party_id')
                            ->label(__('contract.fields.customer_party'))
                            ->relationship('customerParty', 'display_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('signed_on')
                            ->label(__('contract.fields.signed_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('effective_from')
                            ->label(__('contract.fields.effective_from'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contract.label'))
            ->heading(__('contract.relation.title'))
            ->recordTitleAttribute('contract_no')
            ->columns([
                TextColumn::make('contract_no')
                    ->label(__('contract.fields.contract_no')),
                TextColumn::make('contract_type')
                    ->label(__('contract.fields.contract_type'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('contract.fields.status'))
                    ->badge(),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('contract.fields.current_version'))
                    ->placeholder('-'),
                TextColumn::make('signed_on')
                    ->label(__('contract.fields.signed_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['business_case_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContractService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (Contract $record, array $data): Model {
                        try {
                            return app(ContractService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('contract_no')
            ->emptyStateHeading(__('contract.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentCheck);
    }
}
