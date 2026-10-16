<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseQuotations\RelationManagers;

use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\PurchaseQuotations\Pages\ViewPurchaseQuotation;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DocumentLine;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationDocument;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseQuotationService;
use App\Support\UploadLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Satin alma teklifinin belgeleri (D-192): tedarikcinin PDF / Word / Excel /
 * fotograf teklifleri. "Belge yükle" coklu dosya alir; her dosya Dokumanlar'da
 * (tur STF) yeni belge olur ve teklife baglanir
 * (PurchaseQuotationService::attachDocuments). Satirda Onizle, Indir ve cop
 * kutusu (baglanti kalkar, dosya Dokumanlar'da kalir); Konelsis AI'nin belge
 * sonucu (teklif mi, kisa ozet) sutunlarda. Satira tiklamak belgenin Dokuman
 * sayfasini acar.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedPaperClip;

    /** Tedarikci teklif belgelerinin turleri: PDF, Word, Excel, fotograf. */
    private const ACCEPTED = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var array<int, array<string, mixed>|null> */
    private static array $infos = [];

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('purchase_quotation.documents.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewPurchaseQuotation::class
            && SchemaReadiness::hasBatch('B53')
            && FeatureFlags::enabled(Feature::PurchaseQuotations)
            && Gate::allows('viewAny', PurchaseQuotationDocument::class);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('purchase_quotation.documents.title'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['revision.document', 'revision.files.fileObject', 'createdBy']))
            ->columns([
                TextColumn::make('file')
                    ->label(__('purchase_quotation.documents.file'))
                    ->state(fn (PurchaseQuotationDocument $record): string => (string) (self::info($record)['file'] ?? self::info($record)['title'] ?? '-'))
                    ->description(fn (PurchaseQuotationDocument $record): ?string => self::info($record)['title'] ?? null)
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('purchase_quotation.documents.uploaded_at'))
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('createdBy.full_name')
                    ->label(__('purchase_quotation.documents.uploaded_by'))
                    ->placeholder(__('activity.system')),
                TextColumn::make('ai_status')
                    ->label(__('purchase_quotation.documents.ai_status'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('ai_summary')
                    ->label(__('purchase_quotation.documents.ai_summary'))
                    ->limit(160)
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->recordUrl(fn (PurchaseQuotationDocument $record): ?string => ($document = $record->revision?->document) !== null && Gate::allows('view', $document)
                ? DocumentResource::getUrl('view', ['record' => $document])
                : null)
            ->headerActions([
                $this->uploadAction(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('purchase_quotation.actions.preview'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color(ActionColors::NEUTRAL)
                    ->url(fn (PurchaseQuotationDocument $record): ?string => self::info($record)['preview'] ?? null)
                    ->openUrlInNewTab()
                    ->visible(fn (PurchaseQuotationDocument $record): bool => filled(self::info($record)['preview'] ?? null)),
                Action::make('download')
                    ->label(__('purchase_quotation.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color(ActionColors::NEUTRAL)
                    ->url(fn (PurchaseQuotationDocument $record): ?string => self::info($record)['url'] ?? null)
                    ->visible(fn (PurchaseQuotationDocument $record): bool => filled(self::info($record)['url'] ?? null)),
                $this->detachAction(),
            ])
            ->toolbarActions([])
            ->paginated(false)
            ->emptyStateHeading(__('purchase_quotation.documents.empty'))
            ->emptyStateIcon(Heroicon::OutlinedPaperClip);
    }

    /** "Belge yükle": coklu dosya; her dosya yeni belge olur ve teklife baglanir. */
    private function uploadAction(): Action
    {
        return Action::make('upload_quotation_documents')
            ->label(__('purchase_quotation.actions.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color(ActionColors::CREATE)
            ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
            ->modalHeading(__('purchase_quotation.actions.upload'))
            ->modalDescription(__('purchase_quotation.help.upload'))
            ->modalIcon(Heroicon::OutlinedArrowUpTray)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('purchase_quotation.actions.upload'))
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components([
                    FileUpload::make('files')
                        ->label(__('purchase_quotation.documents.files'))
                        ->disk('local')
                        ->directory('document-uploads-tmp')
                        ->storeFileNamesIn('file_names')
                        ->multiple()
                        ->acceptedFileTypes(self::ACCEPTED)
                        ->maxSize(UploadLimits::documentMaxKb())
                        ->required()
                        ->columnSpan(FieldGrid::MODAL_LONG),
                    Hidden::make('file_names'),
                ]))
            ->action(function (array $data): void {
                /** @var PurchaseQuotation $quotation */
                $quotation = $this->getOwnerRecord();
                $files = self::files($data['files'] ?? null, $data['file_names'] ?? null);

                if ($files === []) {
                    return;
                }

                try {
                    $attached = app(PurchaseQuotationService::class)->attachDocuments($quotation, $files);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('purchase_quotation.messages.uploaded', ['count' => count($attached)]));
            });
    }

    /** Cop kutusu: belge tekliften cikar; Document Dokumanlar'da kalir. */
    private function detachAction(): Action
    {
        return Action::make('detach_document')
            ->label(__('purchase_quotation.actions.detach'))
            ->icon(Heroicon::OutlinedTrash)
            ->color(ActionColors::DELETE)
            ->visible(fn (PurchaseQuotationDocument $record): bool => Gate::allows('delete', $record))
            ->requiresConfirmation()
            ->modalHeading(__('purchase_quotation.actions.detach'))
            ->modalDescription(__('purchase_quotation.help.detach'))
            ->modalSubmitActionLabel(__('purchase_quotation.actions.detach'))
            ->action(function (PurchaseQuotationDocument $record): void {
                try {
                    app(PurchaseQuotationService::class)->detachDocument($record);
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);

                    return;
                }

                DomainNotifications::success(__('purchase_quotation.messages.detached'));
            });
    }

    /**
     * Formdaki yollar ve ozgun adlar (storeFileNamesIn: yol => ad).
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

            $name = is_array($names) ? ($names[$path] ?? null) : $names;
            $files[] = ['path' => $path, 'name' => is_string($name) && $name !== '' ? $name : null];
        }

        return $files;
    }

    /**
     * Satirin dosya adi, basligi ve yetki kontrollu adresleri (satir basina bir kez).
     *
     * @return array<string, mixed>|null
     */
    private static function info(PurchaseQuotationDocument $record): ?array
    {
        $revision = $record->revision;

        return self::$infos[(int) $record->getKey()] ??= $revision !== null ? DocumentLine::info($revision->document, $revision) : null;
    }
}
