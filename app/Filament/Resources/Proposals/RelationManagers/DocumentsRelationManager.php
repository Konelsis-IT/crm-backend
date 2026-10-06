<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\ProposalVersions\RelationManagers\DocumentsRelationManager as VersionDocumentsRelationManager;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DocumentLine;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Platform\SchemaReadiness;
use App\Support\UploadLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Teklifin belgeleri (22 Eylul 2026 kullanici karari: dokumanlar teklif
 * sayfasinin alt listesindedir).
 *
 * D-158 (5 Ekim 2026 kullanici talimati: "Teklif dokumani olustur'a basiyorum,
 * belge yukleme alani yok. Belgeyi yukleyebilmeliyim, her yeni yuklediğimde
 * surum guncellenmelidir ... belge revize olmadiysa dokuman kismini cogaltmanin
 * manasi yok"), B43 ile:
 * - Liste yalniz guncel surumun belgeleridir; her belge bir kez gorunur.
 *   Onceki surumlerin belgeleri sayfanin "Surumler" dugmesindeki pencerededir.
 * - "Belge yukle": tur + dosya. Turde belge varsa yeni revizyon, yoksa yeni
 *   belge; teklifin yeni surumu acilir (AcquisitionIntakeService::uploadProposalDocument).
 * - Satirda "Yeni surum yukle" ve "Indir"; satira tiklamak belgenin Dokuman
 *   sayfasini acar (revizyon gecmisi orada).
 * - Elle revizyon secme, duzenleme ve silme yok (surum gecmisini bozardi).
 *
 * B43 oncesi eski davranis (tum surumlerin satirlari, revizyon secerek ekleme) surer.
 */
class DocumentsRelationManager extends VersionDocumentsRelationManager
{
    protected static string $relationship = 'versionDocuments';

    protected function targetVersionId(): ?int
    {
        /** @var Proposal $proposal */
        $proposal = $this->getOwnerRecord();

        return $proposal->current_version_id !== null ? (int) $proposal->current_version_id : null;
    }

    public function table(Table $table): Table
    {
        if (! SchemaReadiness::hasBatch('B43')) {
            return parent::table($table)
                ->pushColumns([
                    TextColumn::make('version.version_no')
                        ->label(__('proposal_version.fields.version_no')),
                ])
                ->modifyQueryUsing(fn ($query) => $query->with(['version', 'documentRevision.document']));
        }

        return $table
            ->modelLabel(__('proposal_document.label'))
            ->heading(__('proposal_document.relation.title'))
            ->description(__('proposal_document.help.current_only'))
            ->recordTitleAttribute('document_role')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('proposal_documents.proposal_version_id', $this->targetVersionId() ?? 0)
                ->with(['documentRevision.document', 'documentRevision.files.fileObject']))
            ->columns([
                TextColumn::make('document_role')
                    ->label(__('proposal_document.fields.role'))
                    ->badge(),
                TextColumn::make('documentRevision.document.document_no')
                    ->label(__('proposal_document.fields.document'))
                    ->description(fn (ProposalDocument $record): ?string => $record->documentRevision?->document?->title),
                TextColumn::make('file')
                    ->label(__('proposal_document.fields.file'))
                    ->state(fn (ProposalDocument $record): string => self::info($record)['file'] ?? '-')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('primary')
                    ->limit(40),
                TextColumn::make('documentRevision.revision_code')
                    ->label(__('proposal_document.fields.revision'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('document.short.revision', ['code' => $state]) : '-')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('documentRevision.created_at')
                    ->label(__('proposal_document.fields.uploaded_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            // Satira tiklamak belgenin Dokuman sayfasini acar (revizyon gecmisi).
            ->recordUrl(fn (ProposalDocument $record): ?string => ($document = $record->documentRevision?->document) !== null && Gate::allows('view', $document)
                ? DocumentResource::getUrl('view', ['record' => $document])
                : null)
            ->headerActions([
                $this->uploadAction(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('proposal_document.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color(ActionColors::VIEW)
                    ->url(fn (ProposalDocument $record): ?string => self::info($record)['url'] ?? null)
                    ->openUrlInNewTab()
                    ->visible(fn (ProposalDocument $record): bool => (self::info($record)['url'] ?? null) !== null),
                $this->uploadAction(forRow: true),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->paginated(false)
            ->emptyStateHeading(__('proposal_document.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }

    /**
     * "Belge yukle" (baslik) ya da "Yeni surum yukle" (satir): dosya yeni
     * revizyon / yeni belge olur, teklifin yeni surumu acilir.
     */
    private function uploadAction(bool $forRow = false): Action
    {
        $service = app(AcquisitionIntakeService::class);

        return Action::make($forRow ? 'upload_revision' : 'upload_document')
            ->label(__($forRow ? 'proposal_document.actions.upload_revision' : 'proposal_document.actions.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color($forRow ? ActionColors::EDIT : ActionColors::CREATE)
            ->modalHeading(fn (?ProposalDocument $record): string => $forRow && $record !== null
                ? __('proposal_document.actions.upload_revision').' · '.self::roleLabel($record)
                : __('proposal_document.actions.upload'))
            ->modalDescription(__($forRow ? 'proposal_document.help.upload_revision' : 'proposal_document.help.upload'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('proposal_document.actions.upload'))
            ->visible(function (?ProposalDocument $record) use ($forRow, $service): bool {
                if ($this->targetVersionId() === null || ! Gate::allows('update', $this->getOwnerRecord())) {
                    return false;
                }

                // Sabit belgeler (Referanslar, Genel katalog) Dokumanlar'dan gelir; buradan yuklenmez.
                return ! $forRow || ($record !== null && ($role = self::role($record)) !== null && $service->uploadKeyFor($role) !== null);
            })
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components([
                    ...($forRow ? [] : [
                        Select::make('role')
                            ->label(__('proposal_document.fields.role'))
                            ->options(fn (): array => collect($service->uploadableRoles())
                                ->mapWithKeys(fn (ProposalDocumentRole $role): array => [$role->value => (string) $role->getLabel()])
                                ->all())
                            ->required()
                            ->native(false)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ]),
                    FileUpload::make('file')
                        ->label(__('proposal_document.fields.file'))
                        ->disk('local')
                        ->directory('document-uploads-tmp')
                        ->storeFileNamesIn('file_name')
                        ->maxSize(UploadLimits::documentMaxKb())
                        ->required()
                        ->columnSpan(FieldGrid::MODAL_LONG)
                        ->columnStart(1),
                    Hidden::make('file_name'),
                ]))
            ->action(function (array $data, ?ProposalDocument $record) use ($forRow, $service): void {
                /** @var Proposal $proposal */
                $proposal = $this->getOwnerRecord();
                $role = $forRow && $record !== null ? self::role($record) : ProposalDocumentRole::tryFrom((string) ($data['role'] ?? ''));
                $path = is_array($data['file'] ?? null) ? (string) reset($data['file']) : (string) ($data['file'] ?? '');
                $name = is_array($data['file_name'] ?? null) ? (string) reset($data['file_name']) : ($data['file_name'] ?? null);

                if ($role === null || $path === '') {
                    return;
                }

                try {
                    [, $version] = $service->uploadProposalDocument($proposal, $role, $path, is_string($name) ? $name : null);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success($version !== null
                    ? __('proposal_document.messages.uploaded', ['no' => $version->version_no])
                    : __('proposal_document.messages.uploaded_in_place'));

                // Teklif karti ve surum karti da yeni surumu gostersin.
                $this->redirect(ProposalResource::getUrl('view', ['record' => $proposal]));
            });
    }

    /**
     * @return array{title: string, revision: string|null, file: string|null, url: string|null}|null
     */
    private static function info(ProposalDocument $record): ?array
    {
        return DocumentLine::info($record->documentRevision?->document, $record->documentRevision);
    }

    private static function role(ProposalDocument $record): ?ProposalDocumentRole
    {
        $role = $record->getAttribute('document_role');

        return $role instanceof ProposalDocumentRole ? $role : ProposalDocumentRole::tryFrom($role instanceof BackedEnum ? (string) $role->value : (string) $role);
    }

    private static function roleLabel(ProposalDocument $record): string
    {
        return (string) (self::role($record)?->getLabel() ?? '');
    }
}
