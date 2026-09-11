<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\RelationManagers;

use App\Enums\Acquisition\ResponsiblePartyRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ResponsibilityMatrixItem;
use App\Services\Acquisition\ResponsibilityMatrixItemService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class ResponsibilityItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'responsibilityItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedTableCells;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('responsibility_matrix_item.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('responsibility_matrix_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('scope_code')
                            ->label(__('responsibility_matrix_item.fields.scope_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('scope_description')
                            ->label(__('responsibility_matrix_item.fields.scope_description'))
                            ->required()
                            ->maxLength(255),
                        Select::make('responsible_party_role')
                            ->label(__('responsibility_matrix_item.fields.responsible_party_role'))
                            ->options(ResponsiblePartyRole::class)
                            ->default(ResponsiblePartyRole::Konelsis->value)
                            ->required()
                            ->native(false),
                        Textarea::make('note')
                            ->label(__('responsibility_matrix_item.fields.note'))
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label(__('responsibility_matrix_item.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('responsibility_matrix_item.label'))
            ->heading(__('responsibility_matrix_item.relation.title'))
            ->recordTitleAttribute('scope_code')
            ->columns([
                TextColumn::make('scope_code')
                    ->label(__('responsibility_matrix_item.fields.scope_code')),
                TextColumn::make('scope_description')
                    ->label(__('responsibility_matrix_item.fields.scope_description'))
                    ->limit(50),
                TextColumn::make('responsible_party_role')
                    ->label(__('responsibility_matrix_item.fields.responsible_party_role'))
                    ->badge(),
                TextColumn::make('note')
                    ->label(__('responsibility_matrix_item.fields.note'))
                    ->limit(30)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ResponsibilityMatrixItemService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ResponsibilityMatrixItem $record, array $data): Model {
                        try {
                            return app(ResponsibilityMatrixItemService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ResponsibilityMatrixItem $record): bool {
                        try {
                            return app(ResponsibilityMatrixItemService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('responsibility_matrix_item.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTableCells);
    }
}
