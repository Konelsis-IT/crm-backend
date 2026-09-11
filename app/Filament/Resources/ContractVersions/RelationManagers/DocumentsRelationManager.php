<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\RelationManagers;

use App\Enums\Acquisition\ContractDocumentRole;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ContractDocument;
use App\Services\Acquisition\ContractDocumentService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
        return __('contract_document.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contract_document.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('document_revision_id')
                            ->label(__('contract_document.fields.document_revision'))
                            ->relationship('documentRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('document_role')
                            ->label(__('contract_document.fields.document_role'))
                            ->options(ContractDocumentRole::class)
                            ->default(ContractDocumentRole::SignedContract->value)
                            ->required()
                            ->native(false),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contract_document.label'))
            ->heading(__('contract_document.relation.title'))
            ->recordTitleAttribute('document_role')
            ->columns([
                TextColumn::make('documentRevision.document.document_no')
                    ->label(__('contract_document.fields.document_revision')),
                TextColumn::make('documentRevision.title')
                    ->label(__('contract_document.fields.title'))
                    ->limit(40),
                TextColumn::make('document_role')
                    ->label(__('contract_document.fields.document_role'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['contract_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContractDocumentService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ContractDocument $record, array $data): Model {
                        try {
                            return app(ContractDocumentService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ContractDocument $record): bool {
                        try {
                            return app(ContractDocumentService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('contract_document.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }
}
