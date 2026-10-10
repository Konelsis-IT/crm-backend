<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\ProposalVersions\RelationManagers\DocumentsRelationManager as VersionDocumentsRelationManager;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DocumentBundleAction;
use App\Filament\Support\DocumentLine;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\ProposalDocumentRows;
use App\Filament\Support\ProposalFilesSchema;
use App\Filament\Support\ReferenceTable;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Platform\SchemaReadiness;
use App\Support\UploadLimits;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
 *
 * D-176 (8 Ekim 2026 kullanici talimati):
 * - "Belge yukle" coklu dosya alir; bir turde (or. Sartname uygunlugu) birden
 *   fazla belge olabilir. Ayni adli dosya o belgenin yeni revizyonudur; satirdaki
 *   "Yeni surum yukle" her zaman o satirin belgesinin yeni revizyonudur.
 * - Genel katalog (Dokumanlar'daki en yeni KAT, teklife kopyalanmadan) tablo
 *   basligindaki "Genel katalog" dugmesiyle yeni sekmede acilir (D-184; eski
 *   "Otomatik belgeler" bolumu kaldirildi).
 * - "Tum belgeleri indir": klasorlu ZIP (DocumentBundleAction).
 *
 * D-184 (9 Ekim 2026): Genel katalog ve Referans listesi tabloda diger belgeler
 * gibi satirdir (ProposalDocumentRows, Filament ozel veri: Table::records).
 * Teklife kopyalanmazlar; katalog satiri PDF'i yeni sekmede acar, referans
 * listesi satiri teklifin proje tiplerinin referans Excel'ini indirir.
 *
 * D-186 (9 Ekim 2026 kullanici talimati: "Dokumanlar relation kisminda Yeni
 * surum yukle kismi olmayacaktir. Indir ve Cop kutusu butonu olsun. Genel
 * katalogta da direk indir butonu da olsun"): satirda Onizle (ozellik
 * documents.office_preview), Indir ve cop kutusu (belgeyi guncel surumden
 * cikarir; Document Dokumanlar'da kalir). Genel katalog satirinda indir simgesi.
 * "Belge yukle" guncel surume ekler; teklif surumu degismez. Yukarida D-158 /
 * D-176'daki "yeni surum acilir / ayni ad yeni revizyon" kurallari kalkti.
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
            // D-184: ozel veri (Table::records). Guncel surumun belge satirlari
            // (ProposalQueries::versionDocuments; D-176: otomatik belge varken eski
            // "Genel kataloğu ekle" satirlari listelenmez, ayni belge iki kez
            // gorunmesin, D-158) ve arkasinda iki sanal satir: Genel katalog ve
            // Referans listesi (ProposalDocumentRows; teklife kopyalanmaz). Tablo
            // sayfalanmaz, aranmaz ve siralanmaz; sira sort_order, sonra kimlik.
            ->records(fn (): Collection => app(ProposalDocumentRows::class)->forProposal($this->proposal()))
            ->columns([
                TextColumn::make('document_role')
                    ->label(__('proposal_document.fields.role'))
                    ->badge()
                    // Sanal "Referans listesi" metin; rol enum'u kendi rengini verir.
                    ->color(fn (mixed $state): string => $state instanceof HasColor ? (string) $state->getColor() : 'info'),
                TextColumn::make('documentRevision.document.document_no')
                    ->label(__('proposal_document.fields.document'))
                    ->description(fn (mixed $record): ?string => $record instanceof ProposalDocument
                        ? $record->documentRevision?->document?->title
                        : (is_array($record) ? ($record['documentRevision']['document']['title'] ?? null) : null)),
                TextColumn::make('file')
                    ->label(__('proposal_document.fields.file'))
                    ->state(fn (mixed $record): string => (string) (self::info($record)['file'] ?? '-'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    // D-185: dosya adi notr renkte (kirmizi degil); satir tiklamasi acar.
                    ->limit(40),
                TextColumn::make('documentRevision.revision_code')
                    ->label(__('proposal_document.fields.revision'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('document.short.revision', ['code' => $state]) : '-')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('documentRevision.created_at')
                    ->label(__('proposal_document.fields.uploaded_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            // Satira tiklamak belgenin Dokuman sayfasini acar (revizyon gecmisi).
            // D-184: Genel katalog satiri PDF'i yeni sekmede acar; Referans listesi
            // satiri Excel'i indirir (satir eylemi "download_references").
            ->recordUrl(fn (mixed $record): ?string => self::rowUrl($record))
            ->openRecordUrlInNewTab(fn (mixed $record): bool => ProposalDocumentRows::kind($record) === ProposalDocumentRows::CATALOG)
            ->recordAction(fn (mixed $record): ?string => ProposalDocumentRows::kind($record) === ProposalDocumentRows::REFERENCES && ReferenceTable::excelEnabled()
                ? 'download_references'
                : null)
            ->headerActions([
                $this->uploadAction(),
                // D-176: guncel surum, onceki surumler, kapsam listeleri ve otomatik belgeler tek ZIP.
                DocumentBundleAction::proposal($this->proposal()),
                // D-184: "Otomatik belgeler" bolumu kaldirildi (kullanici: "alakasi yok,
                // anlamsiz alan"); Genel katalog bu dugmeyle ve tablodaki satiriyla yeni sekmede acilir.
                app(ProposalFilesSchema::class)->catalogAction('open_general_catalog_table'),
            ])
            ->recordActions([
                // D-186 (ozellik documents.office_preview): indirmeden goruntule (PDF,
                // gorsel satir ici; Excel / CSV / Word onizleme sayfasi), yeni sekmede.
                Action::make('preview')
                    ->label(__('document.short.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color(ActionColors::NEUTRAL)
                    ->url(fn (mixed $record): ?string => self::previewUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (mixed $record): bool => self::previewUrl($record) !== null),
                // D-186 (9 Ekim 2026 kullanici talimati: "Indir ve Cop kutusu butonu
                // olsun. Genel katalogta da direk indir butonu da olsun"): her satirda
                // indir simgesi; Genel katalog satirinda dosya iner (satir tiklamasi
                // PDF'i yeni sekmede acmaya devam eder).
                Action::make('download')
                    ->label(__('proposal_document.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color(ActionColors::NEUTRAL)
                    ->url(fn (mixed $record): ?string => self::downloadUrl($record))
                    ->visible(fn (mixed $record): bool => self::downloadUrl($record) !== null),
                // D-184: Referans listesi satiri; teklifin proje tiplerinin referanslari
                // kullanicinin Excel bicimiyle (ReferenceWorkbook) iner.
                Action::make('download_references')
                    ->label(__('proposal_document.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color(ActionColors::NEUTRAL)
                    ->visible(fn (mixed $record): bool => ProposalDocumentRows::kind($record) === ProposalDocumentRows::REFERENCES && ReferenceTable::excelEnabled())
                    ->action(fn (mixed $record): ?StreamedResponse => is_array($record) && ($types = (array) ($record['reference_types'] ?? [])) !== []
                        ? ReferenceTable::downloadFor(array_values($types))
                        : null),
                // D-186: "Yeni surum yukle" yok; cop kutusu belgeyi guncel surumden
                // cikarir (Document kaydi Dokumanlar'da kalir, silinmez; surum artmaz).
                $this->detachAction(),
            ])
            ->toolbarActions([])
            ->paginated(false)
            ->emptyStateHeading(__('proposal_document.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }

    /**
     * "Belge yukle" (baslik): secilen turde her dosya yeni belge olur ve teklifin
     * guncel surumune eklenir; D-186: teklif surumu degismez.
     */
    private function uploadAction(): Action
    {
        $service = app(AcquisitionIntakeService::class);

        return Action::make('upload_document')
            ->label(__('proposal_document.actions.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color(ActionColors::CREATE)
            ->modalHeading(__('proposal_document.actions.upload'))
            ->modalDescription(__('proposal_document.help.upload'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('proposal_document.actions.upload'))
            ->visible(fn (): bool => $this->targetVersionId() !== null && Gate::allows('update', $this->getOwnerRecord()))
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components([
                    Select::make('role')
                        ->label(__('proposal_document.fields.role'))
                        ->options(fn (): array => collect($service->uploadableRoles())
                            ->mapWithKeys(fn (ProposalDocumentRole $role): array => [$role->value => (string) $role->getLabel()])
                            ->all())
                        ->required()
                        ->native(false)
                        ->columnSpan(['default' => 1, 'md' => 2]),
                    // D-176: coklu dosya (bir turde birden fazla belge).
                    FileUpload::make('file')
                        ->label(__('document_bundle.add.files'))
                        ->disk('local')
                        ->directory('document-uploads-tmp')
                        ->storeFileNamesIn('file_name')
                        ->multiple()
                        ->maxSize(UploadLimits::documentMaxKb())
                        ->required()
                        ->columnSpan(FieldGrid::MODAL_LONG)
                        ->columnStart(1),
                    Hidden::make('file_name'),
                ]))
            ->action(function (array $data) use ($service): void {
                /** @var Proposal $proposal */
                $proposal = $this->getOwnerRecord();
                $role = ProposalDocumentRole::tryFrom((string) ($data['role'] ?? ''));
                $files = self::files($data['file'] ?? null, $data['file_name'] ?? null);

                if ($role === null || $files === []) {
                    return;
                }

                try {
                    $service->uploadProposalDocuments($proposal, $role, $files);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('proposal_document.messages.uploaded_in_place'));

                // Sayfadaki kapsam ve surum kartlari da yenilensin.
                $this->redirect(ProposalResource::getUrl('view', ['record' => $proposal]));
            });
    }

    /**
     * Cop kutusu (D-186): belge guncel surumden cikar (proposal_documents
     * baglantisi); Document kaydi ve dosyasi Dokumanlar'da kalir. Onayla sorar.
     */
    private function detachAction(): Action
    {
        return Action::make('detach_document')
            ->label(__('proposal_document.actions.detach'))
            ->icon(Heroicon::OutlinedTrash)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('proposal_document.actions.detach_heading'))
            ->modalDescription(__('proposal_document.help.detach'))
            ->modalSubmitActionLabel(__('proposal_document.actions.detach'))
            ->visible(fn (mixed $record): bool => $record instanceof ProposalDocument
                && $this->targetVersionId() !== null
                && (int) $record->proposal_version_id === $this->targetVersionId()
                && Gate::allows('update', $this->getOwnerRecord()))
            ->action(function (mixed $record): void {
                if (! $record instanceof ProposalDocument) {
                    return;
                }

                /** @var Proposal $proposal */
                $proposal = $this->getOwnerRecord();

                try {
                    app(AcquisitionIntakeService::class)->detachProposalDocument($proposal, $record);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('proposal_document.messages.detached'));
                $this->redirect(ProposalResource::getUrl('view', ['record' => $proposal]));
            });
    }

    /** Satirin indirme adresi (D-186: Genel katalog satirinda indirme bicimi). */
    private static function downloadUrl(mixed $record): ?string
    {
        if (is_array($record)) {
            return ProposalDocumentRows::kind($record) === ProposalDocumentRows::CATALOG ? ($record['download_url'] ?? null) : null;
        }

        return self::info($record)['url'] ?? null;
    }

    /** Indirmeden goruntuleme adresi (D-186); sanal satirlarda yok (katalog satiri zaten acar). */
    private static function previewUrl(mixed $record): ?string
    {
        return $record instanceof ProposalDocument ? (self::info($record)['preview'] ?? null) : null;
    }

    /**
     * Formdaki yollar ve ozgun adlar: tek dosyada metin, coklu dosyada dizi
     * (storeFileNamesIn: yol => ad).
     *
     * @return list<array{path: string, name: string|null}>
     */
    private static function files(mixed $paths, mixed $names): array
    {
        $files = [];

        foreach (is_array($paths) ? $paths : [$paths] as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $name = is_array($names) ? ($names[$path] ?? (count($names) === 1 ? reset($names) : null)) : $names;
            $files[] = ['path' => $path, 'name' => is_string($name) && $name !== '' ? $name : null];
        }

        return $files;
    }

    private function proposal(): Proposal
    {
        /** @var Proposal $proposal */
        $proposal = $this->getOwnerRecord();

        return $proposal;
    }

    /**
     * Satirin dosya adi ve adresi. Sanal satirda (D-184) ProposalDocumentRows'un
     * verdigi dosya adi ve adres (katalog: satir ici; referans listesi: adres yok, eylemle iner).
     *
     * @return array{title?: string, revision?: string|null, file: string|null, url: string|null}|null
     */
    private static function info(mixed $record): ?array
    {
        if (is_array($record)) {
            return ['file' => $record['file'] ?? null, 'url' => $record['url'] ?? null];
        }

        if (! $record instanceof ProposalDocument) {
            return null;
        }

        // Satir basina bir kez (eylemlerin gorunurluk ve adres kapanislari tekrar sorar).
        $key = (int) $record->getKey().':'.(int) $record->document_revision_id;

        return self::$infos[$key] ??= DocumentLine::info($record->documentRevision?->document, $record->documentRevision);
    }

    /** @var array<string, array<string, mixed>|null> */
    private static array $infos = [];

    /** Satir tiklamasi: belgenin Dokuman sayfasi; Genel katalog satirinda PDF (D-184). */
    private static function rowUrl(mixed $record): ?string
    {
        if (is_array($record)) {
            return ProposalDocumentRows::kind($record) === ProposalDocumentRows::CATALOG ? ($record['url'] ?? null) : null;
        }

        return $record instanceof ProposalDocument && ($document = $record->documentRevision?->document) !== null && Gate::allows('view', $document)
            ? DocumentResource::getUrl('view', ['record' => $document])
            : null;
    }
}
