<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Document\RevisionContentKind;
use App\Filament\Clusters\UiGallery;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\LegalHolds\LegalHoldResource;
use App\Filament\Resources\Transmittals\TransmittalResource;
use App\Models\Document\Document;
use App\Models\Personnel\Personnel;
use App\Query\Ui\CardGalleryQueries;
use App\Services\Authorization\RoleResolver;
use BackedEnum;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Harvirsidhu\FilamentCards\CardGroup;
use Harvirsidhu\FilamentCards\CardItem;
use Harvirsidhu\FilamentCards\Filament\Pages\CardsPage;

/**
 * UI Deneme › Eklenti: Filament Cards (10 Eylul 2026, kullanici istegiyle
 * kuruldu: harvirsidhu/filament-cards, MIT, v4/v5).
 *
 * Eklentinin asil amaci sayfa/kaynak navigasyonu icin kart "hub"idir; burada
 * ayni kart belge kayitlariyla da doldurulur ki kullanici sema kartlariyla
 * yan yana karsilastirabilsin. Bu sayfa katalogun parcasidir, silinmez.
 */
class UiPluginCards extends CardsPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $cluster = UiGallery::class;

    protected static ?int $navigationSort = 2;

    protected static int | string | array $columns = ['default' => 1, 'md' => 2, 'xl' => 3];

    protected static Alignment $itemsAlignment = Alignment::Start;

    protected static IconSize $iconSize = IconSize::Large;

    protected static bool $searchable = true;

    public static function getNavigationLabel(): string
    {
        return __('ui_gallery.pages.plugin');
    }

    public function getTitle(): string
    {
        return __('ui_gallery.plugin.title');
    }

    public function getSubheading(): ?string
    {
        return __('ui_gallery.plugin.subheading');
    }

    public static function getCardsSearchPlaceholder(): string
    {
        return __('ui_gallery.plugin.search');
    }

    /**
     * Calisma arayuzu (D-91): yalniz gelistirme ortaminda menude gorunur,
     * production'da hic kaydedilmez. Ayrica tam yetkili rol ister.
     */
    public static function canAccess(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof Personnel && app(RoleResolver::class)->hasFullAccess($user);
    }

    /**
     * @return array<int, CardGroup>
     */
    protected static function getCards(): array
    {
        $documents = app(CardGalleryQueries::class)->documents(12)
            ->map(fn (Document $document): CardItem => static::documentCard($document))
            ->all();

        return [
            CardGroup::make(__('ui_gallery.plugin.groups.documents'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->description(__('ui_gallery.plugin.groups.documents_help'))
                ->collapsible()
                ->schema($documents),
            CardGroup::make(__('ui_gallery.plugin.groups.hub'))
                ->icon(Heroicon::OutlinedSquares2x2)
                ->description(__('ui_gallery.plugin.groups.hub_help'))
                ->collapsible()
                ->schema([
                    CardItem::make(DocumentResource::class),
                    CardItem::make(TransmittalResource::class),
                    CardItem::make(LegalHoldResource::class),
                ]),
        ];
    }

    private static function documentCard(Document $document): CardItem
    {
        $revision = $document->displayRevision();
        $authored = $revision?->content_kind === RevisionContentKind::Authored;

        return CardItem::make(DocumentResource::getUrl('view', ['record' => $document]))
            ->label((string) $document->title)
            ->description($document->document_no.' · '.($document->documentType?->name ?? '-').' · '.($revision !== null ? 'Rev '.$revision->revision_code : __('ui_gallery.plugin.values.no_revision')))
            ->icon($authored ? Heroicon::OutlinedPencilSquare : Heroicon::OutlinedDocumentText)
            ->color($document->status->getColor())
            ->badge($document->status->getLabel())
            ->badgeColor($document->status->getColor())
            ->searchKeywords([(string) $document->document_no, (string) ($document->owner?->full_name ?? '')])
            ->checkAccess(false);
    }
}
