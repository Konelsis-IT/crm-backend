<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffVersions\RelationManagers;

use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\HandoffItemType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\HandoffItem;
use App\Services\Acquisition\HandoffItemService;
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

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('handoff_item.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('handoff_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('item_code')
                            ->label(__('handoff_item.fields.item_code'))
                            ->required()
                            ->maxLength(32),
                        Select::make('item_type')
                            ->label(__('handoff_item.fields.item_type'))
                            ->options(HandoffItemType::class)
                            ->default(HandoffItemType::Checklist->value)
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('handoff_item.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('document_revision_id')
                            ->label(__('handoff_item.fields.document_revision'))
                            ->relationship('documentRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('completion_state')
                            ->label(__('handoff_item.fields.completion_state'))
                            ->options(CompletionState::class)
                            ->default(CompletionState::Pending->value)
                            ->required()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('handoff_item.fields.sort_order'))
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
            ->modelLabel(__('handoff_item.label'))
            ->heading(__('handoff_item.relation.title'))
            ->recordTitleAttribute('item_code')
            ->columns([
                TextColumn::make('item_code')
                    ->label(__('handoff_item.fields.item_code')),
                TextColumn::make('item_type')
                    ->label(__('handoff_item.fields.item_type'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('handoff_item.fields.description'))
                    ->limit(50),
                TextColumn::make('completion_state')
                    ->label(__('handoff_item.fields.completion_state'))
                    ->badge(),
                TextColumn::make('waiver.full_name')
                    ->label(__('handoff_item.fields.waiver'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['handoff_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(HandoffItemService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (HandoffItem $record, array $data): Model {
                        try {
                            return app(HandoffItemService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (HandoffItem $record): bool {
                        try {
                            return app(HandoffItemService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('handoff_item.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }
}
