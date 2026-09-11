<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalHolds\RelationManagers;

use App\Filament\Support\FieldGrid;
use App\Models\Document\LegalHoldDocument;
use App\Services\Audit\ActorContext;
use App\Services\Document\LegalHoldDocumentService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hukuki tutma kapsamindaki dokumanlar (ve istege bagli belirli revizyon).
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentText;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('legal_hold.relation.documents.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('legal_hold.relation.documents.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('document_id')
                        ->label(__('legal_hold_document.fields.document'))
                        ->relationship('document', 'title')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->document_no.' - '.$record->title)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('document_revision_id')
                        ->label(__('legal_hold_document.fields.revision'))
                        ->helperText(__('legal_hold_document.help.revision'))
                        ->relationship('revision', 'revision_code')
                        ->searchable()
                        ->preload()
                        ->native(false),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('legal_hold_document.label'))
            ->heading(__('legal_hold.relation.documents.title'))
            ->recordTitleAttribute('document.title')
            ->columns([
                TextColumn::make('document.document_no')
                    ->label(__('document.fields.document_no')),
                TextColumn::make('document.title')
                    ->label(__('legal_hold_document.fields.document'))
                    ->limit(50),
                TextColumn::make('revision.revision_code')
                    ->label(__('legal_hold_document.fields.revision'))
                    ->placeholder(__('legal_hold_document.messages.all_revisions')),
                TextColumn::make('adder.full_name')
                    ->label(__('legal_hold_document.fields.adder'))
                    ->placeholder('-'),
                TextColumn::make('added_at')
                    ->label(__('legal_hold_document.fields.added_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['legal_hold_id'] = $this->getOwnerRecord()->getKey();
                        $data['added_by_personnel_id'] = app(ActorContext::class)->personnelId();
                        $data['added_at'] = Carbon::now('UTC');

                        return app(LegalHoldDocumentService::class)->create($data);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->using(fn (LegalHoldDocument $record): bool => app(LegalHoldDocumentService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('added_at', 'desc')
            ->emptyStateHeading(__('legal_hold.relation.documents.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }
}
