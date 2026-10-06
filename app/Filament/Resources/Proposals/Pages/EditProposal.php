<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\StatusButton;
use App\Models\Acquisition\Proposal;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Acquisition\ProposalService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Teklif duzenle. B43 (D-155, 5 Ekim 2026 kullanici talimati: "Teklif
 * duzenle ekrani bambaska bir sekilde aciliyor ... Duzenle - Olustur kisminda
 * ayni ekrani gormeliler"): teklif olusturla ayni dort adim, teklif adiminda
 * acilir. Kaydetmede alanlarda, kapsamda ya da belgelerde gercek degisiklik
 * varsa yeni surum acilir (AcquisitionIntakeService::reviseProposal); eski surum
 * ve belgeleri saklanir. Grup uygulanmadiysa eski tek bolumlu form.
 */
class EditProposal extends EditRecord
{
    use HasColoredFormActions;
    use HasSaveableWizard {
        form as protected wizardForm;
        hasFormWrapper as protected wizardHasFormWrapper;
        getFormContentComponent as protected wizardFormContentComponent;
    }

    protected static string $resource = ProposalResource::class;

    #[Url]
    public ?string $step = null;

    /** Bu kaydetme taslak olarak yazilir (B43). */
    public bool $saveAsDraft = false;

    public function form(Schema $schema): Schema
    {
        return BusinessCaseWizard::b43() ? $this->wizardForm($schema) : parent::form($schema);
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
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();

        return app(BusinessCaseWizard::class)->proposalEditSteps($proposal);
    }

    public function getStartStep(): int
    {
        return BusinessCaseWizard::stepNumber($this->step) ?? (int) BusinessCaseWizard::stepNumber(BusinessCaseWizard::STEP_PROPOSAL);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getStepSaveMethods(): array
    {
        return [BusinessCaseWizard::STEP_PROPOSAL => 'save'];
    }

    protected function getStepDraftMethods(): array
    {
        return DraftSupport::enabled() ? [BusinessCaseWizard::STEP_PROPOSAL => 'saveDraft'] : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Guncel surumun durumu; yeni surum acilmaz (D-161).
            StatusButton::proposal(editable: true),
            ...(BusinessCaseWizard::b43() ? [
                Action::make('save_now')
                    ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color(ActionColors::SAVE)
                    ->action('save'),
            ] : []),
            ViewAction::make(),
        ];
    }

    public function saveDraft(): void
    {
        $this->saveAsDraft = true;
        $this->save(shouldRedirect: true);
        $this->saveAsDraft = false;
    }

    /** B43: guncel surumun alanlari, kapsamlari ve belge secimleri forma yuklenir. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! BusinessCaseWizard::b43()) {
            return $data;
        }

        /** @var Proposal $proposal */
        $proposal = $this->getRecord();
        $proposal->loadMissing([
            'businessCase.scopes',
            'currentVersion.scopes.scopeDocument.revisions.files.fileObject',
            'currentVersion.scopes.scopeDocumentRevision.files.fileObject',
            'currentVersion.documents.documentRevision.document',
            'currentVersion.documents.documentRevision.files.fileObject',
        ]);

        return [...$data, ...app(BusinessCaseWizard::class)->proposalFormData($proposal)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, ...DraftSupport::attributes($this->saveAsDraft, BusinessCaseWizard::STEP_PROPOSAL)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            if (! BusinessCaseWizard::b43()) {
                return app(ProposalService::class)->update($record, $data);
            }

            /** @var Proposal $record */
            [$proposal, $version] = app(AcquisitionIntakeService::class)->reviseProposal($record, $data);

            DomainNotifications::success(match (true) {
                $this->saveAsDraft => __('proposal.messages.draft_saved', ['no' => $proposal->proposal_no ?? '-']),
                $version !== null => __('proposal.messages.new_version', ['no' => $version->version_no]),
                default => __('proposal.messages.saved_no_version'),
            });

            return $proposal;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    /** Yeni surum ve belgelerle form yenilenir (yuklenen dosya kutulari bosalir). */
    protected function afterSave(): void
    {
        if (! BusinessCaseWizard::b43()) {
            return;
        }

        $this->record = $this->getRecord()->fresh() ?? $this->getRecord();
        $this->fillForm();
    }

    protected function getSavedNotification(): ?Notification
    {
        return BusinessCaseWizard::b43() ? null : parent::getSavedNotification();
    }
}
