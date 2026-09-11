<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Document\DocumentDiscipline;
use App\Enums\Document\RevisionPurpose;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FileLinks;
use App\Models\Document\Document;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\DocumentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Projeye bagli DMS dokumanlari. "Doküman ekle" tek adimda dokuman kaydini
 * ve ilk revizyonu (dosya yuklemesiyle) acar; detay DMS ekranindadir.
 * Alt siniflar disiplin suzgeciyle (orn. SCADA/PLC) daraltir.
 */
class DocumentsRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'documents';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentText;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.plural');
    }

    /**
     * Alt siniflar icin disiplin suzgeci; null = tum dokumanlar.
     *
     * @return list<DocumentDiscipline>|null
     */
    protected function disciplines(): ?array
    {
        return null;
    }

    protected function heading(): string
    {
        return __('document.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('title')
                        ->label(__('document.fields.title'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('document_type_id')
                        ->label(__('document.fields.document_type'))
                        ->relationship(
                            'documentType',
                            'name',
                            modifyQueryUsing: fn ($query) => $this->disciplines() === null
                                ? $query
                                : $query->whereIn('discipline', array_map(static fn (DocumentDiscipline $d): string => $d->value, $this->disciplines())),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('owner_personnel_id')
                        ->label(__('document.fields.owner'))
                        ->relationship('owner', 'full_name')
                        ->default(fn (): ?int => $this->getOwnerRecord()->project_manager_employee_id)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('default_language')
                        ->label(__('document.fields.default_language'))
                        ->options(['tr' => 'Türkçe', 'en' => 'English'])
                        ->default('tr')
                        ->required()
                        ->native(false),
                    Textarea::make('description')
                        ->label(__('document.fields.description'))
                        ->columnSpanFull(),
                    FileUpload::make('file')
                        ->label(__('document_revision.fields.file'))
                        ->helperText(__('document_revision.help.file'))
                        ->disk('local')
                        ->directory('document-uploads-tmp')
                        ->storeFileNamesIn('file_original_name')
                        ->required()
                        ->columnSpanFull(),
                    Hidden::make('file_original_name'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->heading())
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $disciplines = $this->disciplines();

                if ($disciplines === null) {
                    return $query;
                }

                return $query->whereHas('documentType', fn ($type) => $type->whereIn(
                    'discipline',
                    array_map(static fn (DocumentDiscipline $d): string => $d->value, $disciplines),
                ));
            })
            ->columns([
                TextColumn::make('document_no')
                    ->label(__('document.fields.document_no'))
                    ->searchable(),
                TextColumn::make('title')
                    ->label(__('document.fields.title'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('documentType.name')
                    ->label(__('document.fields.document_type'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('documentType.discipline')
                    ->label(__('document_type.fields.discipline'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('currentRevision.revision_code')
                    ->label(__('document.fields.current_revision'))
                    ->placeholder('-'),
                TextColumn::make('owner.full_name')
                    ->label(__('document.fields.owner'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('document.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('document_type_id')
                    ->label(__('document.fields.document_type'))
                    ->relationship('documentType', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('document.actions.create_with_file'))
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->using(function (array $data): Model {
                        $tempPath = $data['file'] ?? null;
                        $originalName = $data['file_original_name'] ?? null;
                        unset($data['file'], $data['file_original_name']);

                        try {
                            /** @var Document $document */
                            $document = app(DocumentService::class)->create([
                                'title' => $data['title'],
                                'document_type_id' => $data['document_type_id'],
                                'owner_personnel_id' => $data['owner_personnel_id'],
                                'default_language' => $data['default_language'] ?? 'tr',
                                'description' => $data['description'] ?? null,
                                'status' => 'draft',
                                'project_id' => $this->getOwnerRecord()->getKey(),
                            ]);

                            if (filled($tempPath)) {
                                app(DocumentRevisionService::class)->create([
                                    'document_id' => $document->getKey(),
                                    'title' => $data['title'],
                                    'language' => $data['default_language'] ?? 'tr',
                                    'purpose' => RevisionPurpose::ForReview->value,
                                    'status' => 'draft',
                                    'file_temp_path' => $tempPath,
                                    'file_original_name' => $originalName,
                                ]);
                            }

                            return $document;
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('document_revision.actions.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Document $record): bool => $record->currentRevision !== null && FileLinks::revisionPreview($record->currentRevision) !== null)
                    ->url(fn (Document $record): ?string => $record->currentRevision === null ? null : FileLinks::revisionPreview($record->currentRevision))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label(__('document_revision.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Document $record): bool => $record->currentRevision !== null && FileLinks::revisionOriginal($record->currentRevision) !== null)
                    ->url(fn (Document $record): ?string => $record->currentRevision === null ? null : FileLinks::revisionOriginal($record->currentRevision, 'download')),
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->defaultSort('document_no', 'desc')
            ->emptyStateHeading(__('project.relation_documents_empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }
}
