<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Exports\DocumentExporter;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DocumentWorkspace;
use App\Filament\Support\ExportActions;
use App\Filament\Support\FileLinks;
use App\Models\Document\Document;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Dokuman detay arayuzu (D-75): form degil, calisma alani. Dokuman karti,
 * guncel icerik karti (dosya / sistemde yazilan govde) ve ust sekmeler.
 * Yeni surum, paylas ve onaya gonder eylemleri sayfa ustundedir.
 *
 * D-181: kartin altinda "Bagli kayitlar" (teklif, potansiyel is, sozlesme,
 * ihale, firma); baslik dugmeleri kisa (Duzenle) ya da yalniz simge.
 */
class ViewDocumentRecord extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    public function getTitle(): string
    {
        /** @var Document $document */
        $document = $this->getRecord();

        return $document->document_no.' · '.$document->title;
    }

    public function content(Schema $schema): Schema
    {
        /** @var Document $document */
        $document = $this->getRecord();
        $workspace = app(DocumentWorkspace::class);

        return $schema->columns(1)->components(array_values(array_filter([
            $workspace->headerCard($document, [
                Action::make('edit_details')
                    ->label(__('document.actions.edit_details'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color(ActionColors::EDIT)
                    ->link()
                    ->url(DocumentResource::getUrl('edit', ['record' => $document])),
            ]),
            // D-181: belgenin bagli oldugu teklif, potansiyel is ... (bag yoksa kart yok).
            $workspace->linkedRecordsCard($document),
            $workspace->contentCard($document),
            $workspace->tabs($document, static::class),
        ])));
    }

    protected function getHeaderActions(): array
    {
        /** @var Document $document */
        $document = $this->getRecord();
        $workspace = app(DocumentWorkspace::class);
        $revision = $document->displayRevision();
        $previewUrl = $revision === null ? null : FileLinks::revisionPreview($revision);
        $downloadUrl = $revision === null ? null : FileLinks::revisionOriginal($revision, 'download');

        // D-181 (9 Ekim 2026 kullanici: basliktaki dugmeler cok uzun): Yeni surum,
        // Onaya gonder ve Duzenle kisa etiketli; onizle, indir ve paylas yalniz
        // simge, adlari ipucunda (renkleri ActionColors'a gore ayni).
        return [
            $workspace->newRevisionAction($document),
            $workspace->approvalAction($document),
            EditAction::make()->label(__('document.actions.edit_details')),
            Action::make('preview_current')
                ->label(__('document.actions.preview_current'))
                ->tooltip(__('document.actions.preview_current'))
                ->icon(Heroicon::OutlinedEye)
                ->color(ActionColors::NEUTRAL)
                ->iconButton()
                ->visible($previewUrl !== null)
                ->url($previewUrl)
                ->openUrlInNewTab(),
            Action::make('download_current')
                ->label(__('document.actions.download_current'))
                ->tooltip(__('document.actions.download_current'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color(ActionColors::NEUTRAL)
                ->iconButton()
                ->visible($downloadUrl !== null)
                ->url($downloadUrl),
            $workspace->shareAction($document)
                ->tooltip(__('document.actions.share'))
                ->iconButton(),
            ExportActions::record(DocumentExporter::class),
        ];
    }
}
