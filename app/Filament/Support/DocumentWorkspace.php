<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Document\RevisionContentKind;
use App\Enums\Document\RevisionPurpose;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\RelationManagers\AcknowledgementsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\ApprovalRequestsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\DistributionsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\LinksRelationManager;
use App\Filament\Resources\Documents\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\SharesRelationManager;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentShare;
use App\Query\Approval\ApprovalQueries;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\DocumentShareService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component as LivewireComponent;

/**
 * Dokuman detay arayuzu (D-75): form degil, calisma alani. Dokuman karti
 * (simgeli, baglantili alanlar), "guncel icerik" karti (dosya ya da sistemde
 * yazilan govde), ust sekmeler (revizyonlar, onaylar, paylasimlar, ...).
 * Yeni surum / paylas / onaya gonder eylemleri de burada kurulur; hepsi
 * Filament-native bilesenlerdir, yazma isleri servislere gider.
 */
final class DocumentWorkspace
{
    /** Kucuk gorsel ve dosya kutusu yerlesimi icin sabit yukseklik. */
    private const MEDIA_HEIGHT = '10rem';

    /**
     * @param  list<Action>  $headerActions
     */
    public function headerCard(Document $document, array $headerActions = []): Component
    {
        $revision = $document->displayRevision();
        $owner = $document->owner;
        $project = $document->project;
        $thumbnail = $revision !== null ? FileLinks::revisionThumbnail($revision) : null;

        $media = $thumbnail !== null
            ? Image::make($thumbnail, (string) $document->title)->imageHeight(self::MEDIA_HEIGHT)
            : Icon::make(($revision?->isAuthored() ?? false) ? Heroicon::OutlinedPencilSquare : Heroicon::OutlinedDocumentText)->color('gray');

        $badges = [
            Text::make((string) $document->document_no)->badge()->color('gray')->icon(Heroicon::OutlinedHashtag),
            Text::make($document->status->getLabel())->badge()->color($document->status->getColor()),
            Text::make($document->documentType?->name ?? '-')->badge()->color('info')->icon(Heroicon::OutlinedTag),
            Text::make($document->classification?->name_tr ?? '-')->badge()->color('warning')->icon(Heroicon::OutlinedLockClosed),
        ];

        if ($document->is_controlled) {
            $badges[] = Text::make(__('document.fields.is_controlled'))->badge()->color('success')->icon(Heroicon::OutlinedShieldCheck);
        }

        $entries = [
            TextEntry::make('owner')
                ->label(__('document.fields.owner'))
                ->state($owner?->full_name ?? '-')
                ->icon(Heroicon::OutlinedUserCircle)
                ->iconColor('primary')
                ->weight(FontWeight::SemiBold)
                ->color($owner !== null ? 'primary' : 'gray')
                ->url($owner !== null && Gate::allows('view', $owner) ? PersonnelResource::getUrl('view', ['record' => $owner]) : null),
            TextEntry::make('owner_org_unit')
                ->label(__('document.fields.owner_org_unit'))
                ->state($document->ownerOrgUnit?->name ?? '-')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->iconColor('gray'),
            TextEntry::make('project')
                ->label(__('document.fields.project'))
                ->state($project?->name ?? '-')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->iconColor($project !== null ? 'primary' : 'gray')
                ->color($project !== null ? 'primary' : 'gray')
                ->weight($project !== null ? FontWeight::SemiBold : FontWeight::Normal)
                ->url($project !== null && Gate::allows('view', $project) ? ProjectResource::getUrl('view', ['record' => $project]) : null)
                ->visible(SchemaReadiness::hasBatch('B17')),
            TextEntry::make('default_language')
                ->label(__('document.fields.default_language'))
                ->state(strtoupper((string) $document->default_language))
                ->badge()
                ->color('gray')
                ->icon(Heroicon::OutlinedLanguage),
            TextEntry::make('retention_policy')
                ->label(__('document.fields.retention_policy'))
                ->state($document->retentionPolicy?->name_tr ?? '-')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->iconColor('gray'),
            TextEntry::make('created')
                ->label(__('document.fields.created_at'))
                ->state(DisplayTime::format($document->created_at).' · '.($document->createdBy?->full_name ?? __('activity.system')))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->iconColor('gray'),
        ];

        return Section::make(__('document.sections.header'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->compact()
            ->headerActions($headerActions)
            ->components([
                Grid::make(['default' => 1, 'lg' => 4])->components([
                    Group::make([$media])->columnSpan(1),
                    Group::make([
                        Text::make((string) $document->title)->size(TextSize::Large)->weight(FontWeight::Bold),
                        Flex::make($badges),
                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries),
                        TextEntry::make('description')
                            ->label(__('document.fields.description'))
                            ->state((string) $document->description)
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->iconColor('gray')
                            ->hidden(blank($document->description)),
                    ])->columnSpan(['default' => 1, 'lg' => 3]),
                ]),
            ]);
    }

    /**
     * Guncel icerik karti: yayimlanmis (yoksa en son) revizyonun ozeti ve
     * icerigi — dosya kutusu (onizle/indir) ya da sistemde yazilan govde.
     */
    public function contentCard(Document $document): Component
    {
        $revision = $document->displayRevision();

        if ($revision === null) {
            return Callout::make(__('document.help.no_content'))
                ->warning()
                ->icon(Heroicon::OutlinedDocumentPlus);
        }

        $working = $document->workingRevision();
        $components = [];

        if ($working !== null && (int) $working->getKey() !== (int) $revision->getKey()) {
            $components[] = Callout::make(__('document.help.working_revision', [
                'code' => $working->revision_code,
                'status' => $working->status->getLabel(),
            ]))->info()->icon(Heroicon::OutlinedPencil);
        }

        $badges = [
            Text::make(__('document_revision.fields.revision_code').' '.$revision->revision_code)->badge()->color('gray')->icon(Heroicon::OutlinedDocumentDuplicate),
            Text::make($revision->status->getLabel())->badge()->color($revision->status->getColor()),
            Text::make($revision->purpose->getLabel())->badge()->color('info'),
            Text::make(strtoupper((string) $revision->language))->badge()->color('gray'),
        ];

        if (SchemaReadiness::hasBatch('B06A') && $revision->content_kind !== null) {
            $badges[] = Text::make($revision->content_kind->getLabel())->badge()->color($revision->content_kind->getColor())->icon($revision->content_kind->getIcon());
        }

        $components[] = Flex::make($badges);
        $components[] = Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->components([
            TextEntry::make('preparer')
                ->label(__('document_revision.fields.preparer'))
                ->state(($revision->preparer?->full_name ?? '-').' · '.DisplayTime::format($revision->prepared_at))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->iconColor('gray'),
            TextEntry::make('checker')
                ->label(__('document_revision.fields.checker'))
                ->state($revision->checker?->full_name ?? '-')
                ->icon(Heroicon::OutlinedEye)
                ->iconColor('gray'),
            TextEntry::make('approver')
                ->label(__('document_revision.fields.approver'))
                ->state(($revision->approver?->full_name ?? '-').($revision->approved_at !== null ? ' · '.DisplayTime::format($revision->approved_at) : ''))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->iconColor($revision->approved_at !== null ? 'success' : 'gray'),
            TextEntry::make('issued_at')
                ->label(__('document_revision.fields.issued_at'))
                ->state(DisplayTime::format($revision->issued_at))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->iconColor($revision->issued_at !== null ? 'success' : 'gray'),
        ]);

        if (filled($revision->change_summary)) {
            $components[] = TextEntry::make('change_summary')
                ->label(__('document_revision.fields.change_summary'))
                ->state((string) $revision->change_summary)
                ->icon(Heroicon::OutlinedChatBubbleLeft)
                ->iconColor('gray');
        }

        $components[] = $this->contentBody($revision);

        return Section::make(__('document.sections.content'))
            ->description(__('document.help.content'))
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->compact()
            ->components($components);
    }

    /** Dosya kutusu ya da sistemde yazilan govde. */
    public function contentBody(DocumentRevision $revision, bool $publicLinks = false, ?string $previewUrl = null, ?string $downloadUrl = null): Component
    {
        if ($revision->isAuthored()) {
            return Section::make(__('document.sections.body'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->collapsible()
                ->components([
                    TextEntry::make('body_html')
                        ->hiddenLabel()
                        ->state((string) $revision->body_html)
                        ->html(),
                ]);
        }

        $file = $revision->originalFile();

        if ($file === null) {
            return Callout::make(__('document.help.no_file'))->warning()->icon(Heroicon::OutlinedDocumentMinus);
        }

        if (! $publicLinks) {
            $previewUrl = FileLinks::revisionPreview($revision);
            $downloadUrl = FileLinks::revisionOriginal($revision, 'download');
        }

        $actions = [];

        if ($previewUrl !== null) {
            $actions[] = Action::make('preview_file')
                ->label(__('document_revision.actions.preview'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url($previewUrl)
                ->openUrlInNewTab();
        }

        if ($downloadUrl !== null) {
            $actions[] = Action::make('download_file')
                ->label(__('document_revision.actions.download'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->url($downloadUrl);
        }

        $thumbnail = $publicLinks ? null : FileLinks::revisionThumbnail($revision);

        return Section::make(__('document.sections.file'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->compact()
            ->components([
                Grid::make(['default' => 1, 'lg' => 4])->components([
                    Group::make([
                        $thumbnail !== null
                            ? Image::make($thumbnail, (string) $file->original_name)->imageHeight(self::MEDIA_HEIGHT)
                            : Icon::make(Heroicon::OutlinedDocument)->color('gray'),
                    ])->columnSpan(1),
                    Group::make([
                        Text::make((string) $file->original_name)->weight(FontWeight::SemiBold),
                        Flex::make([
                            Text::make($file->humanSize())->badge()->color('gray'),
                            Text::make((string) $file->mime_type)->badge()->color('info'),
                            Text::make($file->isInlinePreviewable() ? __('document.values.previewable') : __('document.values.download_only'))
                                ->badge()
                                ->color($file->isInlinePreviewable() ? 'success' : 'warning'),
                        ]),
                        Actions::make($actions)->hidden($actions === []),
                    ])->columnSpan(['default' => 1, 'lg' => 3]),
                ]),
            ]);
    }

    public function tabs(Document $document, string $pageClass): Component
    {
        $tabs = [
            Tab::make(__('document.tabs.revisions'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->badge((string) $document->revisions()->count())
                ->schema([$this->relationManager(RevisionsRelationManager::class, $document, $pageClass)]),
        ];

        if ($this->approvalsEnabled()) {
            $tabs[] = Tab::make(__('document.tabs.approvals'))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->badge((string) $document->approvalRequests()->count())
                ->schema([$this->relationManager(ApprovalRequestsRelationManager::class, $document, $pageClass)]);
        }

        if (SchemaReadiness::hasBatch('B06A')) {
            $tabs[] = Tab::make(__('document.tabs.shares'))
                ->icon(Heroicon::OutlinedShare)
                ->badge((string) $document->shares()->where('status', 'active')->count())
                ->schema([$this->relationManager(SharesRelationManager::class, $document, $pageClass)]);
        }

        $tabs[] = Tab::make(__('document.tabs.links'))
            ->icon(Heroicon::OutlinedLink)
            ->schema([$this->relationManager(LinksRelationManager::class, $document, $pageClass)]);
        $tabs[] = Tab::make(__('document.tabs.reviews'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->schema([$this->relationManager(ReviewsRelationManager::class, $document, $pageClass)]);
        $tabs[] = Tab::make(__('document.tabs.distributions'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->schema([$this->relationManager(DistributionsRelationManager::class, $document, $pageClass)]);
        $tabs[] = Tab::make(__('document.tabs.acknowledgements'))
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->schema([$this->relationManager(AcknowledgementsRelationManager::class, $document, $pageClass)]);

        return Tabs::make('document-workspace')
            ->id('document-workspace')
            ->persistTab()
            ->contained(false)
            ->tabs($tabs);
    }

    /**
     * @param  class-string<\Filament\Resources\RelationManagers\RelationManager>  $class
     */
    public function relationManager(string $class, Document $document, string $pageClass, string $suffix = ''): Livewire
    {
        $key = 'rm-'.class_basename($class).($suffix !== '' ? '-'.$suffix : '');

        return Livewire::make($class, [
            'ownerRecord' => $document,
            'pageClass' => $pageClass,
            ...$class::getDefaultProperties(),
        ])->key($key)->id('anchor-'.$key);
    }

    // ------------------------------------------------------------------ eylemler

    /** Yeni revizyon: dosya yukle ya da sistemde yaz; DocumentRevisionService::create. */
    public function newRevisionAction(Document $document, string $name = 'new_revision'): Action
    {
        return Action::make($name)
            ->label(__('document.actions.new_revision'))
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('primary')
            ->visible(fn (): bool => Gate::allows('update', $document))
            ->modalHeading(__('document.actions.new_revision'))
            ->modalDescription(__('document.help.new_revision'))
            ->modalWidth('4xl')
            ->schema([
                Section::make(__('document_revision.sections.main'))
                    ->columns(FieldGrid::COLUMNS)
                    ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('document_revision.fields.title'))
                            ->default((string) $document->title)
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('language')
                            ->label(__('document_revision.fields.language'))
                            ->options(['tr' => 'Türkçe', 'en' => 'English'])
                            ->default((string) ($document->default_language ?: 'tr'))
                            ->required()
                            ->native(false),
                        Select::make('purpose')
                            ->label(__('document_revision.fields.purpose'))
                            ->options(RevisionPurpose::class)
                            ->default(RevisionPurpose::ForReview->value)
                            ->required()
                            ->native(false),
                        Textarea::make('change_summary')
                            ->label(__('document_revision.fields.change_summary'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])),
                Section::make(__('document_revision.sections.content'))
                    ->components(self::revisionContentFields(true)),
            ])
            ->action(function (array $data, LivewireComponent $livewire) use ($document): void {
                $data['document_id'] = $document->getKey();
                $data['file_temp_path'] = $data['file'] ?? null;
                unset($data['file']);

                try {
                    app(DocumentRevisionService::class)->create($data);
                    DomainNotifications::success(__('document.messages.revision_created'));
                    $livewire->redirect(DocumentResource::getUrl('view', ['record' => $document]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Paylasim baglantisi olusturur ve adresi bildirimde gosterir. */
    public function shareAction(Document $document, string $name = 'share'): Action
    {
        return Action::make($name)
            ->label(__('document.actions.share'))
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->visible(fn (): bool => SchemaReadiness::hasBatch('B06A') && Gate::allows('update', $document) && $document->displayRevision() !== null)
            ->modalHeading(__('document.actions.share'))
            ->modalDescription(__('document.help.share_public'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                TextInput::make('label')
                    ->label(__('document_share.fields.label'))
                    ->helperText(__('document_share.help.label'))
                    ->maxLength(120),
                Toggle::make('allow_download')
                    ->label(__('document_share.fields.allow_download'))
                    ->default(true),
                DatePicker::make('expires_at')
                    ->label(__('document_share.fields.expires_at'))
                    ->displayFormat('d.m.Y')
                    ->helperText(__('document_share.help.expires_at')),
            ])))
            ->action(function (array $data, LivewireComponent $livewire) use ($document): void {
                $data['document_id'] = $document->getKey();

                try {
                    /** @var DocumentShare $share */
                    $share = app(DocumentShareService::class)->create($data);

                    Notification::make()
                        ->title(__('document.messages.share_created'))
                        ->body(self::shareUrl($share))
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('open_share')
                                ->label(__('document.actions.open_share'))
                                ->button()
                                ->url(self::shareUrl($share))
                                ->openUrlInNewTab(),
                        ])
                        ->send();

                    $livewire->redirect(DocumentResource::getUrl('view', ['record' => $document]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Uzerinde calisilan revizyonu onay motoruna gonderir (B07). */
    public function approvalAction(Document $document, string $name = 'send_to_approval'): Action
    {
        $queries = app(ApprovalQueries::class);

        return Action::make($name)
            ->label(__('document.actions.send_to_approval'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('warning')
            ->visible(function () use ($document, $queries): bool {
                if (! $this->approvalsEnabled() || ! Gate::allows('update', $document)) {
                    return false;
                }

                $working = $document->workingRevision();

                return $working !== null && $queries->openRequestFor(DocumentRevision::APPROVAL_SUBJECT_TYPE, (int) $working->getKey()) === null;
            })
            ->modalHeading(__('document.actions.send_to_approval'))
            ->modalDescription(__('document.help.approval'))
            ->schema(function () use ($document, $queries): array {
                $revisions = $document->revisions()
                    ->whereIn('status', ['draft', 'in_review'])
                    ->orderByDesc('revision_no')
                    ->get()
                    ->mapWithKeys(fn (DocumentRevision $revision): array => [(int) $revision->getKey() => 'Rev '.$revision->revision_code.' · '.$revision->title.' ('.$revision->status->getLabel().')'])
                    ->all();
                $policies = $queries->publishedPolicyOptions(DocumentRevision::APPROVAL_SUBJECT_TYPE);

                return [
                    Select::make('revision_id')
                        ->label(__('document_revision.label'))
                        ->options($revisions)
                        ->default(array_key_first($revisions))
                        ->required()
                        ->native(false),
                    Select::make('policy_id')
                        ->label(__('approval_policy.label'))
                        ->options($policies)
                        ->default(array_key_first($policies))
                        ->required()
                        ->native(false)
                        ->helperText($policies === [] ? __('document.help.no_policy') : null),
                    Textarea::make('note')
                        ->label(__('approval_request.fields.note'))
                        ->rows(2)
                        ->maxLength(500),
                ];
            })
            ->action(function (array $data, LivewireComponent $livewire) use ($document): void {
                try {
                    app(ApprovalRequestService::class)->request(
                        DocumentRevision::APPROVAL_SUBJECT_TYPE,
                        (int) $data['revision_id'],
                        null,
                        isset($data['policy_id']) ? (int) $data['policy_id'] : null,
                        $data['note'] ?? null,
                    );
                    DomainNotifications::success(__('document.messages.approval_requested'));
                    $livewire->redirect(DocumentResource::getUrl('view', ['record' => $document]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /**
     * Revizyon icerigi alanlari: dosya yukle ya da sistemde yaz. Dokuman
     * olusturma formu, yeni surum modali ve revizyon listesi bunu paylasir.
     *
     * @return list<Component>
     */
    public static function revisionContentFields(bool $forCreate, string $kindField = 'content_kind', string $fileField = 'file', string $bodyField = 'body_html'): array
    {
        $authoring = SchemaReadiness::hasBatch('B06A');
        $isUpload = fn (Get $get): bool => ! $authoring || ($get($kindField) ?? RevisionContentKind::Upload->value) === RevisionContentKind::Upload->value;
        $isAuthored = fn (Get $get): bool => $authoring && $get($kindField) === RevisionContentKind::Authored->value;

        return [
            ToggleButtons::make($kindField)
                ->label(__('document_revision.fields.content_kind'))
                ->helperText(__('document_revision.help.content_kind'))
                ->options(RevisionContentKind::class)
                ->default(RevisionContentKind::Upload->value)
                ->inline()
                ->live()
                ->visible($authoring && $forCreate)
                ->dehydrated(false)
                ->columnSpanFull(),
            FileUpload::make($fileField)
                ->label(__('document_revision.fields.file'))
                ->helperText(__('document_revision.help.file'))
                ->disk('local')
                ->directory('document-uploads-tmp')
                ->storeFileNamesIn('file_original_name')
                ->visible(fn (Get $get): bool => $forCreate && $isUpload($get))
                ->required(fn (Get $get): bool => $forCreate && $isUpload($get))
                ->columnSpanFull(),
            RichEditor::make($bodyField)
                ->label(__('document_revision.fields.body'))
                ->helperText(__('document_revision.help.body'))
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3'],
                    ['alignStart', 'alignCenter', 'alignEnd'],
                    ['blockquote', 'bulletList', 'orderedList'],
                    ['table'],
                    ['undo', 'redo'],
                ])
                ->visible(fn (Get $get, ?Model $record): bool => $forCreate ? $isAuthored($get) : ($authoring && $record instanceof DocumentRevision && $record->isAuthored()))
                ->required(fn (Get $get, ?Model $record): bool => $forCreate ? $isAuthored($get) : ($authoring && $record instanceof DocumentRevision && $record->isAuthored()))
                ->columnSpanFull(),
            Hidden::make('file_original_name'),
        ];
    }

    public static function shareUrl(DocumentShare $share): string
    {
        return route('filament.share.pages.shared-document', ['token' => $share->token]);
    }

    public static function shareFileUrl(DocumentShare $share, string $disposition = 'inline'): string
    {
        return route('filament.share.shared-file', ['token' => $share->token, 'disposition' => $disposition]);
    }

    public function approvalsEnabled(): bool
    {
        return FeatureFlags::enabled('approvals.admin_ui') && SchemaReadiness::hasBatch('B07');
    }
}
