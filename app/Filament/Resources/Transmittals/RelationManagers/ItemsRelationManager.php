<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transmittals\RelationManagers;

use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentRevision;
use App\Models\Document\TransmittalItem;
use App\Services\Document\TransmittalItemService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Teslim tutanagina eklenen doküman revizyonlari.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentText;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('transmittal.relation.items.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('transmittal.relation.items.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('document_revision_id')
                        ->label(__('transmittal_item.fields.revision'))
                        ->relationship('revision', 'revision_code')
                        ->getOptionLabelFromRecordUsing(fn (DocumentRevision $record): string => $record->document->document_no.' - '.$record->revision_code)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    TextInput::make('copies')
                        ->label(__('transmittal_item.fields.copies'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    TextInput::make('sort_order')
                        ->label(__('transmittal_item.fields.sort_order'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('transmittal_item.label'))
            ->heading(__('transmittal.relation.items.title'))
            ->recordTitleAttribute('sort_order')
            ->columns([
                TextColumn::make('revision.document.document_no')
                    ->label(__('document.fields.document_no')),
                TextColumn::make('revision.revision_code')
                    ->label(__('transmittal_item.fields.revision')),
                TextColumn::make('copies')
                    ->label(__('transmittal_item.fields.copies')),
                TextColumn::make('sort_order')
                    ->label(__('transmittal_item.fields.sort_order'))
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['transmittal_id'] = $this->getOwnerRecord()->getKey();

                        return app(TransmittalItemService::class)->create($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (TransmittalItem $record, array $data): Model => app(TransmittalItemService::class)->update($record, $data)),
                DeleteAction::make()
                    ->using(fn (TransmittalItem $record): bool => app(TransmittalItemService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('transmittal.relation.items.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }
}
