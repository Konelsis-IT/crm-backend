<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Enums\Platform\Feature;
use App\Filament\Exports\ProposalExporter;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DealTrack;
use App\Services\Platform\FeatureFlags;
use App\Filament\Support\ExportActions;
use App\Filament\Support\ProposalDetail;
use App\Models\Acquisition\Proposal;
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

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    $detail->headerCard($proposal),
                    $detail->versionCard($proposal),
                ]),
            ...($track !== null ? [$track] : []),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();
        $case = $proposal->businessCase;

        return [
            EditAction::make(),
            ...($case !== null ? [app(BusinessCaseWizard::class)->convertAction($case, proposal: $proposal)] : []),
            ExportActions::record(ProposalExporter::class),
        ];
    }
}
