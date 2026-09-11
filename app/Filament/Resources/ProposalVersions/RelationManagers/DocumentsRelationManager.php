<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\RelationManagers;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ProposalDocument;
use App\Services\Acquisition\ProposalDocumentService;
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

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPaperClip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('proposal_document.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('proposal_document.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('document_revision_id')
                            ->label(__('proposal_document.fields.document_revision'))
                            ->relationship('documentRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('document_role')
                            ->label(__('proposal_document.fields.document_role'))
                            ->options(ProposalDocumentRole::class)
                            ->default(ProposalDocumentRole::TechnicalOffer->value)
                            ->required()
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('proposal_document.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('proposal_document.label'))
            ->heading(__('proposal_document.relation.title'))
            ->recordTitleAttribute('document_role')
            ->columns([
                TextColumn::make('documentRevision.document.document_no')
                    ->label(__('proposal_document.fields.document_revision')),
                TextColumn::make('documentRevision.title')
                    ->label(__('proposal_document.fields.title'))
                    ->limit(40),
                TextColumn::make('document_role')
                    ->label(__('proposal_document.fields.document_role'))
                    ->badge(),
                TextColumn::make('sort_order')
                    ->label(__('proposal_document.fields.sort_order')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProposalDocumentService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProposalDocument $record, array $data): Model {
                        try {
                            return app(ProposalDocumentService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ProposalDocument $record): bool {
                        try {
                            return app(ProposalDocumentService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('proposal_document.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }
}
