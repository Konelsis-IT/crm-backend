<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Exports\ProposalExporter;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DealTrack;
use App\Filament\Support\DocumentBundleAction;
use App\Services\Platform\FeatureFlags;
use App\Filament\Support\ExportActions;
use App\Filament\Support\ProposalDetail;
use App\Filament\Support\ProposalScopeSchema;
use App\Filament\Support\StatusButton;
use App\Models\Acquisition\Proposal;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

/**
 * Teklif detayi (22 Eylul 2026 kullanici karari, D-112, D-113): ustte teklif
 * karti ve guncel surum karti yan yana. D-143 (29 Eylul 2026): eski "Is akisi"
 * sihirbazi (sayfa Potansiyel is adimiyla aciliyordu) ve ayri durum notu yerine
 * "Bu is nerede?" dikey hatti (DealTrack): potansiyel is ozet etiketlerle,
 * "Buradasiniz" bu teklif (durum cumlesi, isin diger teklifleri), proje ya da
 * acilis kosullari. Altta surumler, gorusme notlari, dokumanlar ve raporlar.
 * D-155: kartlarin altinda guncel surumun proje kapsami ve belgeleri.
 * D-158: ayri surum sayfasi yok; "Surumler" dugmesi eski surumu pencerede
 * gosterir, sayfa hep guncel surumdur. Belgeler Dokumanlar sekmesinde (yukleme orada).
 */
class ViewProposal extends ViewRecord
{
    protected static string $resource = ProposalResource::class;

    public function getTitle(): string
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();

        return trim($proposal->proposal_no.' · '.($proposal->title ?? ''), ' ·');
    }

    public function content(Schema $schema): Schema
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();
        $proposal->loadMissing(['businessCase.primaryParty', 'businessCase.project', 'owner', 'currentVersion.preparer']);
        $detail = app(ProposalDetail::class);
        // "Bu iş nerede?" kendi ozellik anahtariyla kapanabilir (D-147).
        $track = FeatureFlags::enabled(Feature::DealTrack) ? app(DealTrack::class)->forProposal($proposal) : null;

        // B43 (D-155): guncel surumun proje kapsami; belgeler yalniz Dokumanlar
        // sekmesinde (ayni belgeler iki yerde gorunmesin), eski surumler
        // "Surumler" penceresinde. D-186 ("duzenle'deki tasarim ile goruntule
        // tasarimi birebir ayni olmalidir"): duzenleme ekranindaki tip bolumlerinin
        // aynisi (GES kapsami ...: ayni baslik, simge, Referanslar + indir, izgara,
        // kutular), degerler etiketli; kapsam toplam satisi ve marj surum kartinda
        // (duzenlemede Teklif bilgileri'nde oldugu gibi).
        $scopeGrid = null;

        if (SchemaReadiness::hasBatch('B43')) {
            $proposal->loadMissing([
                'currentVersion.scopes.scopeDocument.revisions.files.fileObject',
                'currentVersion.scopes.scopeDocumentRevision.files.fileObject',
            ]);
            $scopeGrid = app(ProposalScopeSchema::class)->recordGrid($proposal->currentVersion, references: true);
        }

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    $detail->headerCard($proposal),
                    $detail->versionCard($proposal),
                ]),
            ...($scopeGrid !== null ? [$scopeGrid] : []),
            ...($track !== null ? [$track] : []),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();
        $case = $proposal->businessCase;
        // D-158: surumler ayri sayfada degil, bu sayfanin "Surumler" penceresinde.
        $versions = SchemaReadiness::hasBatch('B43') ? app(ProposalDetail::class)->versionsAction($proposal) : null;

        return [
            // D-182: Teklif durumu acilir dugmesi (duzenlemedekiyle ayni, yeni
            // durum hemen kaydedilir); ozellik kapaliyken D-161'deki sabit dugme.
            ...StatusButton::proposalHeader($proposal),
            EditAction::make(),
            // D-186: surumleme personelde; Duzenle surum artirmaz, bu dugme N+1 acar.
            ...array_filter([app(ProposalDetail::class)->newVersionAction($proposal)]),
            ...($versions !== null ? [$versions] : []),
            ...($case !== null ? [app(BusinessCaseWizard::class)->convertAction($case, proposal: $proposal)] : []),
            // D-184: "Tum belgeleri indir" baslikta (yalniz simge, ipucunda adi);
            // Dokumanlar sekmesindeki dugme kalir.
            DocumentBundleAction::proposalHeader($proposal),
            ExportActions::record(ProposalExporter::class),
        ];
    }
}
