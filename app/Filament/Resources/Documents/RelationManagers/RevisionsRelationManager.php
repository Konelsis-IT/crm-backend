<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Enums\Document\RevisionPurpose;
use App\Enums\Document\RevisionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DocumentWorkspace;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\FileLinks;
use App\Models\Document\DocumentRevision;
use App\Query\Approval\ApprovalQueries;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Document\DocumentRevisionService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Dokumanin revizyonlari; dosya yukleme / sistemde yazma ve durum gecisleri
 * burada yapilir (DocumentRevisionService). Yayimlanmis/onaylanmis bir
 * revizyon uzerine yazilmaz, degisiklik icin yeni revizyon acilir. Onay
 * motoru (B07) acikken "onaylandi" gecisi yalniz motorla yapilir.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentText;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.relation.revisions.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document_revision.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('title')
                        ->label(__('document_revision.fields.title'))
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => (string) $this->getOwnerRecord()->title)
                        ->columnSpanFull(),
                    Select::make('language')
                        ->label(__('document_revision.fields.language'))
                        ->options(['tr' => 'Türkçe', 'en' => 'English'])
                        ->default('tr')
                        ->required()
                        ->native(false),
                    Select::make('purpose')
                        ->label(__('document_revision.fields.purpose'))
                        ->options(RevisionPurpose::class)
                        ->default(RevisionPurpose::ForReview->value)
                        ->required()
                        ->native(false),
                    Select::make('checked_by_personnel_id')
                        ->label(__('document_revision.fields.checker'))
                        ->relationship('checker', 'full_name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Textarea::make('change_summary')
                        ->label(__('document_revision.fields.change_summary'))
                        ->rows(2)
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
            Section::make(__('document_revision.sections.content'))
                ->components([
                    ...DocumentWorkspace::revisionContentFields(true),
                ])
                ->visibleOn('create'),
            Section::make(__('document_revision.sections.content'))
                ->components([
                    ...DocumentWorkspace::revisionContentFields(false),
                ])
                ->visibleOn('edit')
                ->visible(fn (?DocumentRevision $record): bool => SchemaReadiness::hasBatch('B06A') && ($record?->isAuthored() ?? false)),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('document.relation.revisions.title'))
            ->recordTitleAttribute('revision_code')
            ->columns([
                TextColumn::make('revision_code')
                    ->label(__('document_revision.fields.revision_code'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('document_revision.fields.status'))
                    ->badge(),
                TextColumn::make('content_kind')
                    ->label(__('document_revision.fields.content_kind'))
                    ->badge()
                    ->visible(fn (): bool => SchemaReadiness::hasBatch('B06A')),
                TextColumn::make('title')
                    ->label(__('document_revision.fields.title'))
                    ->limit(40),
                TextColumn::make('purpose')
                    ->label(__('document_revision.fields.purpose'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('language')
                    ->label(__('document_revision.fields.language'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('thumbnail')
                    ->label(__('document_revision.fields.thumbnail'))
                    ->getStateUsing(fn (DocumentRevision $record): ?string => FileLinks::revisionThumbnail($record))
                    ->imageHeight(48)
                    ->square()
                    ->toggleable(),
                TextColumn::make('file')
                    ->label(__('document_revision.fields.file'))
                    ->getStateUsing(fn (DocumentRevision $record): ?string => $record->originalFile()?->original_name)
                    ->description(fn (DocumentRevision $record): ?string => $record->originalFile()?->humanSize())
                    ->limit(40)
                    ->placeholder(fn (DocumentRevision $record): string => $record->isAuthored() ? __('document_revision.values.authored') : '-'),
                TextColumn::make('preparer.full_name')
                    ->label(__('document_revision.fields.preparer'))
                    ->placeholder('-'),
                TextColumn::make('prepared_at')
                    ->label(__('document_revision.fields.prepared_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('approver.full_name')
                    ->label(__('document_revision.fields.approver'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('issued_at')
                    ->label(__('document_revision.fields.issued_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('document.actions.new_revision'))
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->modalWidth('4xl')
                    ->using(function (array $data): Model {
                        $data['document_id'] = $this->getOwnerRecord()->getKey();
                        $data['file_temp_path'] = $data['file'] ?? null;
                        unset($data['file']);

                        try {
                            return app(DocumentRevisionService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('view_body')
                    ->label(__('document.actions.view_body'))
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->visible(fn (DocumentRevision $record): bool => $record->isAuthored())
                    ->modalHeading(fn (DocumentRevision $record): string => 'Rev '.$record->revision_code.' · '.$record->title)
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('document.actions.close'))
                    ->schema(fn (DocumentRevision $record): array => [
                        TextEntry::make('body_html')
                            ->hiddenLabel()
                            ->state((string) $record->body_html)
                            ->html(),
                    ]),
                Action::make('preview')
                    ->label(__('document_revision.actions.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (DocumentRevision $record): bool => FileLinks::revisionPreview($record) !== null)
                    ->url(fn (DocumentRevision $record): ?string => FileLinks::revisionPreview($record))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label(__('document_revision.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (DocumentRevision $record): bool => FileLinks::revisionOriginal($record) !== null)
                    ->url(fn (DocumentRevision $record): ?string => FileLinks::revisionOriginal($record, 'download')),
                Action::make('send_to_approval')
                    ->label(__('document.actions.send_to_approval'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('document.help.approval'))
                    ->visible(fn (DocumentRevision $record): bool => $this->approvalsEnabled()
                        && $record->isEditable()
                        && Gate::allows('update', $record)
                        && app(ApprovalQueries::class)->openRequestFor(DocumentRevision::APPROVAL_SUBJECT_TYPE, (int) $record->getKey()) === null)
                    ->schema([
                        Textarea::make('note')
                            ->label(__('approval_request.fields.note'))
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (DocumentRevision $record, array $data): void {
                        try {
                            app(ApprovalRequestService::class)->request(DocumentRevision::APPROVAL_SUBJECT_TYPE, (int) $record->getKey(), null, null, $data['note'] ?? null);
                            DomainNotifications::success(__('document.messages.approval_requested'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                EditAction::make()
                    ->modalWidth('4xl')
                    ->visible(fn (DocumentRevision $record): bool => $record->isEditable())
                    ->using(function (DocumentRevision $record, array $data): Model {
                        unset($data['file']);

                        try {
                            return app(DocumentRevisionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('document_revision.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('revision_no', 'desc')
            ->emptyStateHeading(__('document.relation.revisions.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }

    /**
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];
        $engine = $this->approvalsEnabled();

        foreach (RevisionStatus::cases() as $target) {
            if ($engine && $target === RevisionStatus::Approved) {
                // Onay motoru acikken "onaylandi" yalniz motorun karariyla verilir.
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('document_revision.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->visible(fn (DocumentRevision $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (DocumentRevision $record) use ($target): void {
                    try {
                        app(DocumentRevisionService::class)->changeStatus($record, $target);
                        DomainNotifications::success(__('document_revision.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }

    private function approvalsEnabled(): bool
    {
        return FeatureFlags::enabled('approvals.admin_ui') && SchemaReadiness::hasBatch('B07');
    }
}
