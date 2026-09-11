<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\RelationManagers;

use App\Enums\Acquisition\BrandApprovalState;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\BrandItem;
use App\Query\Reference\ReferenceOptions;
use App\Services\Acquisition\BrandItemService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
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

class BrandItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'brandItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedTag;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('brand_item.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('brand_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('item_code')
                            ->label(__('brand_item.fields.item_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('item_description')
                            ->label(__('brand_item.fields.item_description'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('proposed_brand')
                            ->label(__('brand_item.fields.proposed_brand'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('alternative_brand')
                            ->label(__('brand_item.fields.alternative_brand'))
                            ->maxLength(255),
                        Select::make('origin_country_code')
                            ->label(__('brand_item.fields.origin_country'))
                            ->options(fn (): array => app(ReferenceOptions::class)->countries())
                            ->searchable()
                            ->native(false),
                        Select::make('approval_state')
                            ->label(__('brand_item.fields.approval_state'))
                            ->options(BrandApprovalState::class)
                            ->default(BrandApprovalState::Proposed->value)
                            ->required()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('brand_item.fields.sort_order'))
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
            ->modelLabel(__('brand_item.label'))
            ->heading(__('brand_item.relation.title'))
            ->recordTitleAttribute('item_code')
            ->columns([
                TextColumn::make('item_code')
                    ->label(__('brand_item.fields.item_code')),
                TextColumn::make('item_description')
                    ->label(__('brand_item.fields.item_description'))
                    ->limit(40),
                TextColumn::make('proposed_brand')
                    ->label(__('brand_item.fields.proposed_brand')),
                TextColumn::make('alternative_brand')
                    ->label(__('brand_item.fields.alternative_brand'))
                    ->placeholder('-'),
                TextColumn::make('approval_state')
                    ->label(__('brand_item.fields.approval_state'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(BrandItemService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (BrandItem $record, array $data): Model {
                        try {
                            return app(BrandItemService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (BrandItem $record): bool {
                        try {
                            return app(BrandItemService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('brand_item.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedTag);
    }
}
