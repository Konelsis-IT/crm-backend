<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Filament\Exports\ProposalExporter;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\ExportActions;
use App\Filament\Support\ProposalDetail;
use App\Models\Acquisition\Proposal;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Teklif detayi (22 Eylul 2026 kullanici karari, D-112, D-113): is dosyasi
 * detay sayfasiyla ayni yapi. Ustte teklif karti ve guncel surum karti yan
 * yana, is dosyasinin asama uyarisi, "Is dosyasi -> Teklif -> Proje" adimlari
 * (2. adim bos; is dosyasi ve proje adimlari ayrintili) ve altta surumler,
 * dokumanlar ve raporlar.
 */
class ViewProposal extends ViewRecord
{
    protected static string $resource = ProposalResource::class;

    #[Url]
    public ?string $step = null;

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
        $case = $proposal->businessCase;
        $wizard = app(BusinessCaseWizard::class);
        $detail = app(ProposalDetail::class);

        $steps = [$detail->caseStep($proposal), $detail->proposalStep($proposal)];

        if ($case !== null) {
            $steps[] = $wizard->projectStep($case, $proposal);
        }

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->extraAttributes(['class' => 'kc-grid-top'])
                ->components([
                    $detail->headerCard($proposal),
                    $detail->versionCard($proposal),
                ]),
            ...($case !== null ? [$wizard->stageCallout($case)] : []),
            Section::make(__('business_case.sections.chain'))
                ->icon(Heroicon::OutlinedArrowLongRight)
                ->description(__('business_case.help.chain_intro'))
                ->components([
                    Wizard::make($steps)
                        ->skippable()
                        ->startOnStep($detail->startStep($proposal, $this->step))
                        ->contained(false),
                ]),
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
