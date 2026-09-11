<?php

declare(strict_types=1);

namespace App\Filament\Share\Pages;

use App\Filament\Support\DocumentWorkspace;
use App\Models\Document\DocumentShare;
use App\Query\Document\DocumentShareQueries;
use App\Services\Document\DocumentShareService;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Paylasilan belge sayfasi (D-75): /share/documents/{token}. Kimlik
 * dogrulamasi yoktur; token acik, iptal edilmemis ve suresi dolmamis bir
 * paylasima ait olmali. Yalniz belge basligi, numarasi, tipi, guncel
 * revizyon ozeti ve icerik (dosya / govde) gosterilir; sahip, birim ve
 * kimlik bilgileri disari cikmaz.
 */
class SharedDocument extends Page
{
    protected static ?string $slug = 'documents/{token}';

    protected static bool $shouldRegisterNavigation = false;

    public ?DocumentShare $share = null;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'shared-document';
    }

    public function mount(string $token): void
    {
        $share = app(DocumentShareQueries::class)->openByToken($token);

        abort_if($share === null, 404);

        $this->share = app(DocumentShareService::class)->recordAccess($share);

        abort_unless($this->share->isOpen(), 404);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->share?->document?->title ?? __('document.share.title'));
    }

    public function getHeading(): string | Htmlable
    {
        return (string) ($this->share?->document?->title ?? __('document.share.title'));
    }

    public function getSubheading(): string | Htmlable | null
    {
        $document = $this->share?->document;

        if ($document === null) {
            return null;
        }

        return $document->document_no.' · '.($document->documentType?->name ?? '');
    }

    public function content(Schema $schema): Schema
    {
        $share = $this->share;
        $document = $share?->document;

        if ($share === null || $document === null) {
            return $schema->columns(1)->components([
                Callout::make(__('document.share.unavailable'))->warning(),
            ]);
        }

        $revision = $document->displayRevision();
        $workspace = app(DocumentWorkspace::class);

        $components = [
            Section::make(__('document.share.title'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->description(__('document.share.intro'))
                ->compact()
                ->components([
                    Text::make((string) $document->title)->size(TextSize::Large)->weight(FontWeight::Bold),
                    Flex::make(array_values(array_filter([
                        Text::make((string) $document->document_no)->badge()->color('gray')->icon(Heroicon::OutlinedHashtag),
                        Text::make($document->documentType?->name ?? '-')->badge()->color('info')->icon(Heroicon::OutlinedTag),
                        $revision !== null ? Text::make(__('document_revision.fields.revision_code').' '.$revision->revision_code)->badge()->color('gray') : null,
                        $revision !== null ? Text::make($revision->status->getLabel())->badge()->color($revision->status->getColor()) : null,
                        $revision !== null ? Text::make(strtoupper((string) $revision->language))->badge()->color('gray') : null,
                    ]))),
                    Grid::make(['default' => 1, 'md' => 3])->components([
                        TextEntry::make('purpose')
                            ->label(__('document_revision.fields.purpose'))
                            ->state($revision?->purpose->getLabel() ?? '-')
                            ->icon(Heroicon::OutlinedFlag)
                            ->iconColor('gray'),
                        TextEntry::make('issued_at')
                            ->label(__('document_revision.fields.issued_at'))
                            ->state($revision?->issued_at?->format('d.m.Y') ?? '-')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->iconColor('gray'),
                        TextEntry::make('prepared_at')
                            ->label(__('document_revision.fields.prepared_at'))
                            ->state($revision?->prepared_at?->format('d.m.Y') ?? '-')
                            ->icon(Heroicon::OutlinedClock)
                            ->iconColor('gray'),
                    ]),
                    TextEntry::make('description')
                        ->label(__('document.fields.description'))
                        ->state((string) $document->description)
                        ->hidden(blank($document->description)),
                ]),
        ];

        if ($revision === null) {
            $components[] = Callout::make(__('document.share.no_content'))->warning();
        } else {
            $file = $revision->originalFile();
            $previewUrl = $file !== null && $file->isInlinePreviewable() ? DocumentWorkspace::shareFileUrl($share, 'inline') : null;
            $downloadUrl = $file !== null && $share->allow_download ? DocumentWorkspace::shareFileUrl($share, 'download') : null;

            $components[] = $workspace->contentBody($revision, publicLinks: true, previewUrl: $previewUrl, downloadUrl: $downloadUrl);
        }

        $components[] = Text::make(__('document.share.footer', ['app' => __('app.name')]))->color('gray');

        return $schema->columns(1)->components($components);
    }
}
