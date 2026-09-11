<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Enums\Document\AcknowledgementKind;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentAcknowledgement;
use App\Services\Document\DocumentAcknowledgementService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Revizyonlarin personel okundu/kabul/egitim teyitleri.
 */
class AcknowledgementsRelationManager extends RelationManager
{
    protected static string $relationship = 'acknowledgements';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.relation.acknowledgements.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document.relation.acknowledgements.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('document_revision_id')
                        ->label(__('document_revision.label'))
                        ->relationship(
                            'revision',
                            'revision_code',
                            modifyQueryUsing: fn ($query) => $query->where('document_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('personnel_id')
                        ->label(__('document_acknowledgement.fields.personnel'))
                        ->relationship('personnel', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('acknowledgement_kind')
                        ->label(__('document_acknowledgement.fields.acknowledgement_kind'))
                        ->options(AcknowledgementKind::class)
                        ->default(AcknowledgementKind::Read->value)
                        ->required()
                        ->native(false),
                    DateTimePicker::make('acknowledged_at')
                        ->label(__('document_acknowledgement.fields.acknowledged_at'))
                        ->default(fn (): Carbon => Carbon::now())
                        ->required(),
                    Textarea::make('comment')
                        ->label(__('document_acknowledgement.fields.comment'))
                        ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('document_acknowledgement.label'))
            ->heading(__('document.relation.acknowledgements.title'))
            ->recordTitleAttribute('acknowledgement_kind')
            ->columns([
                TextColumn::make('revision.revision_code')
                    ->label(__('document_revision.label'))
                    ->placeholder('-'),
                TextColumn::make('personnel.full_name')
                    ->label(__('document_acknowledgement.fields.personnel')),
                TextColumn::make('acknowledgement_kind')
                    ->label(__('document_acknowledgement.fields.acknowledgement_kind'))
                    ->badge(),
                TextColumn::make('acknowledged_at')
                    ->label(__('document_acknowledgement.fields.acknowledged_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data): Model => app(DocumentAcknowledgementService::class)->create($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (DocumentAcknowledgement $record, array $data): Model => app(DocumentAcknowledgementService::class)->update($record, $data)),
                DeleteAction::make()
                    ->using(fn (DocumentAcknowledgement $record): bool => app(DocumentAcknowledgementService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('acknowledged_at', 'desc')
            ->emptyStateHeading(__('document.relation.acknowledgements.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }
}
