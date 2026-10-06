<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Models\Acquisition\Proposal;
use App\Services\Acquisition\AcquisitionIntakeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif olustur (22 Eylul 2026 kullanici karari): is dosyasi olusturma
 * sihirbazinin 2. adimi tek sayfada. Zorunlu is dosyasi secimi, secilen is
 * dosyasinin ozet karti, teklif bolumu (surum, teklif durumu, belgeler) ve
 * istenirse hemen projeye donusum. Yazma AcquisitionIntakeService::addProposal.
 *
 * B43 (D-155, 5 Ekim 2026 kullanici talimati: "Teklif olustur'a bastigimizda
 * ... adimli olan arayuz acilmalidir"): potansiyel is sihirbaziyla ayni dort
 * adim (ihale, potansiyel is secimi, teklif, proje). ?business_case_id= ile
 * acilirsa teklif adiminda baslar. Teklif adiminda "Kaydet" ve "Taslak olarak
 * kaydet" vardir; son adimdaki "Olustur" istenirse projeye de donusturur.
 */
class CreateProposal extends CreateRecord
{
    use HasColoredFormActions;
    use HasSaveableWizard {
        form as protected wizardForm;
        hasFormWrapper as protected wizardHasFormWrapper;
        getFormContentComponent as protected wizardFormContentComponent;
    }

    protected static string $resource = ProposalResource::class;

    /** Bu kaydetme taslak olarak yazilir (B43). */
    public bool $saveAsDraft = false;

    /** Teklif adimindaki "Kaydet": projeye donusum yapilmaz. */
    public bool $stopAtProposal = false;

    /** Teklif bu istekte projeye donusturulduyse proje calisma alanina gecilir. */
    private bool $converted = false;

    public function form(Schema $schema): Schema
    {
        if (BusinessCaseWizard::b43()) {
            return $this->wizardForm($schema);
        }

        return $schema->columns(1)->components(app(BusinessCaseWizard::class)->proposalCreateComponents());
    }

    public function hasFormWrapper(): bool
    {
        return BusinessCaseWizard::b43() ? $this->wizardHasFormWrapper() : parent::hasFormWrapper();
    }

    public function getFormContentComponent(): Component
    {
        return BusinessCaseWizard::b43() ? $this->wizardFormContentComponent() : parent::getFormContentComponent();
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return app(BusinessCaseWizard::class)->proposalCreateSteps();
    }

    /** Potansiyel is secili geldiyse teklif adimi, degilse potansiyel is secimi. */
    public function getStartStep(): int
    {
        $step = request()->integer('business_case_id') > 0 ? BusinessCaseWizard::STEP_PROPOSAL : BusinessCaseWizard::STEP_CASE;

        return (int) (BusinessCaseWizard::stepNumber($step) ?? 1);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getStepSaveMethods(): array
    {
        return [BusinessCaseWizard::STEP_PROPOSAL => 'saveProposalOnly'];
    }

    protected function getStepDraftMethods(): array
    {
        return DraftSupport::enabled() ? [BusinessCaseWizard::STEP_PROPOSAL => 'saveProposalDraft'] : [];
    }

    protected function getSubmitFormLivewireMethodName(): string
    {
        return BusinessCaseWizard::b43() ? 'createAll' : 'create';
    }

    public function createAll(): void
    {
        $this->stopAtProposal = false;
        $this->saveAsDraft = false;
        $this->create();
    }

    public function saveProposalOnly(): void
    {
        $this->stopAtProposal = true;
        $this->saveAsDraft = false;
        $this->create();
    }

    public function saveProposalDraft(): void
    {
        $this->stopAtProposal = true;
        $this->saveAsDraft = true;
        $this->create();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->stopAtProposal) {
            $data['convert_now'] = false;
        }

        return [...$data, ...DraftSupport::attributes($this->saveAsDraft, BusinessCaseWizard::STEP_PROPOSAL)];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $proposal = app(AcquisitionIntakeService::class)->addProposal((int) $data['business_case_id'], $data);
            $project = $proposal->businessCase?->project;
            $this->converted = (bool) ($data['convert_now'] ?? false) && $project !== null;

            DomainNotifications::success($this->converted
                ? __('project.messages.converted', ['code' => $project->businessCode?->formatted_code ?? '-'])
                : __($this->saveAsDraft ? 'proposal.messages.draft_saved' : 'proposal.messages.created', ['no' => $proposal->proposal_no ?? '-']));

            return $proposal;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();
        $project = $proposal->businessCase?->project;

        if ($this->converted && $project !== null) {
            return ProjectResource::getUrl('view', ['record' => $project]);
        }

        // Taslak teklif adimindan devam eder.
        return $this->saveAsDraft
            ? static::getResource()::getUrl('edit', ['record' => $proposal, 'step' => BusinessCaseWizard::STEP_PROPOSAL])
            : static::getResource()::getUrl('view', ['record' => $proposal]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
