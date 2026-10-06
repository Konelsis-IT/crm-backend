<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DealTrack;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\TenderSchema;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\TenderNotice;
use App\Services\Platform\FeatureFlags;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Ihale detayi. B43 (D-155, 5 Ekim 2026 kullanici talimati: "Ihale detayi da
 * yine ayni sekilde kart yapisiyla potansiyel is detayi teklif detayi gibi
 * olacak"): ustte ihale karti ve ayrinti karti yan yana, altinda "Bu is nerede?"
 * hatti (ihale -> potansiyel is -> teklifler -> proje) ve surumler. Grup
 * uygulanmadiysa Filament'in varsayilan bilgi sayfasi.
 */
class ViewTenderNotice extends ViewRecord
{
    protected static string $resource = TenderNoticeResource::class;

    public function getTitle(): string
    {
        /** @var TenderNotice $notice */
        $notice = $this->getRecord();

        return BusinessCaseWizard::b43() ? (string) $notice->title : parent::getTitle();
    }

    public function content(Schema $schema): Schema
    {
        if (! BusinessCaseWizard::b43()) {
            return parent::content($schema);
        }

        /** @var TenderNotice $notice */
        $notice = $this->getRecord();
        $notice->loadMissing(['source', 'issuerParty', 'currentVersion.sourceDocumentRevision.document', 'currentVersion.sourceDocumentRevision.files.fileObject', 'businessCase.codes']);
        $tender = app(TenderSchema::class);

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    $tender->headerCard($notice),
                    $tender->detailsCard($notice),
                ]),
            // "Bu iş nerede?" kendi ozellik anahtariyla kapanabilir (D-147).
            ...(FeatureFlags::enabled(Feature::DealTrack) ? [app(DealTrack::class)->forTender($notice)] : []),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var TenderNotice $notice */
        $notice = $this->getRecord();

        return [
            EditAction::make()
                ->url(fn (): string => TenderNoticeResource::getUrl('edit', array_filter([
                    'record' => $notice,
                    // Taslak kaldigi adimdan acilir.
                    'step' => DraftSupport::enabled() && (bool) $notice->getAttribute('is_draft') ? $notice->getAttribute('draft_step') : null,
                ]))),
            Action::make('create_case_from_tender')
                ->label(__('tender_notice.actions.create_case'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->color(ActionColors::CREATE)
                ->visible(fn (): bool => BusinessCaseWizard::b43() && $notice->business_case_id === null && Gate::allows('create', BusinessCase::class))
                ->url(fn (): string => BusinessCaseResource::getUrl('create', [BusinessCaseWizard::QUERY_TENDER => $notice->getKey()])),
        ];
    }
}
