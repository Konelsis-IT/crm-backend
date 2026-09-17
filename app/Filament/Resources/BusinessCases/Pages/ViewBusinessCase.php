<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Models\Acquisition\BusinessCase;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Is dosyasi goruntuleme (D-72): kart + asama uyarisi + "Is dosyasi -> Teklif
 * -> Proje" adimlari (olusturma/duzenleme sihirbaziyla ayni gorunum) ve
 * altta diger alt listeler (firsat, aktiviteler, ihale ilanlari, sozlesmeler,
 * Operasyona devirler).
 */
class ViewBusinessCase extends ViewRecord
{
    protected static string $resource = BusinessCaseResource::class;

    #[Url]
    public ?string $step = null;

    public function getTitle(): string
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return ($case->offerCode()?->formatted_code ?? '').' · '.$case->title;
    }

    public function content(Schema $schema): Schema
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();
        $wizard = app(BusinessCaseWizard::class);

        // B29: kart rozetleri ve ayrinti adimi kapsamlari tek yuklemeyle okur.
        if (SchemaReadiness::hasBatch('B29')) {
            $case->loadMissing('scopes.scopeDocument.revisions.files.fileObject');
        }

        return $schema->columns(1)->components([
            $wizard->headerCard($case),
            $wizard->stageCallout($case),
            Section::make(__('business_case.sections.chain'))
                ->icon(Heroicon::OutlinedArrowLongRight)
                ->description(__('business_case.help.chain_intro'))
                ->components([
                    Wizard::make([
                        $wizard->detailsStep($case),
                        $wizard->proposalTableStep($case, static::class),
                        $wizard->projectStep($case),
                    ])
                        ->skippable()
                        ->startOnStep($wizard->startStep($case, $this->step))
                        ->contained(false),
                ]),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();
        $wizard = app(BusinessCaseWizard::class);

        return [
            EditAction::make()
                ->url(fn (): string => BusinessCaseResource::getUrl('edit', [
                    'record' => $this->getRecord(),
                    'step' => BusinessCaseWizard::STEP_IDS[$wizard->startStep($this->getRecord(), null) - 1],
                ])),
            Action::make('open_project')
                ->label(__('project.actions.open_workspace'))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->project !== null)
                ->url(fn (): string => ProjectResource::getUrl('view', ['record' => $this->getRecord()->project])),
            $wizard->convertAction($case),
        ];
    }
}
