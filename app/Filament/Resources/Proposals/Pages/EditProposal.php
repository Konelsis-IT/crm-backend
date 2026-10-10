<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Concerns\RemovesProposalFiles;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DocumentBundleAction;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\ProposalDetail;
use App\Filament\Support\ProposalFilesSchema;
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
 * acilir. Grup uygulanmadiysa eski tek bolumlu form.
 *
 * D-186 (9 Ekim 2026 kullanici karari: "surumleme isi artik personeldedir"):
 * kaydetme guncel surumu yerinde degistirir; hangi degisiklik olursa olsun
 * surum artmaz (AcquisitionIntakeService::updateProposal). Belgeler ciplerin
 * "x"i ile cikarilir, yeni dosyalar eklenir. Yeni surum yalniz "Yeni teklif
 * surumu" dugmesiyle (NewProposalVersion sayfasi, bu sinifin alt sinifi).
 */
class EditProposal extends EditRecord
{
    use HasColoredFormActions;
    use HasSaveableWizard {
        form as protected wizardForm;
        hasFormWrapper as protected wizardHasFormWrapper;
        getFormContentComponent as protected wizardFormContentComponent;
    }
    use RemovesProposalFiles;

    protected static string $resource = ProposalResource::class;

    #[Url]
    public ?string $step = null;

    /** Bu kaydetme taslak olarak yazilir (B43). */
    public bool $saveAsDraft = false;

    /** D-186: bu sayfa "Yeni teklif surumu" mu (alt sinif true doner)? */
    protected function isNewVersion(): bool
    {
        return false;
    }

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

        return app(BusinessCaseWizard::class)->proposalEditSteps($proposal, $this->isNewVersion());
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
        // D-186: yeni surum taslak olarak yarim birakilmaz (ikinci kayit N+2 acardi).
        return DraftSupport::enabled() && ! $this->isNewVersion() ? [BusinessCaseWizard::STEP_PROPOSAL => 'saveDraft'] : [];
    }

    protected function getHeaderActions(): array
    {
        /** @var Proposal $proposal */
        $proposal = $this->getRecord();

        return [
            ...(BusinessCaseWizard::b43() ? [
                Action::make('save_now')
                    ->label($this->isNewVersion()
                        ? __('proposal.new_version.save')
                        : __('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color(ActionColors::SAVE)
                    ->action('save'),
            ] : []),
            // D-182: Teklif durumu "Degisiklikleri kaydet"in hemen yaninda acilir
            // dugme; secim hemen kaydedilir, yeni surum acilmaz. Formda ayrica
            // Teklif durumu alani yok (tek yer burasi). Yeni surum ekraninda yok.
            ...($this->isNewVersion() ? [] : StatusButton::offerStatusHeader($proposal)),
            // D-186: "Yeni teklif surumu" (surum N+1, ayni form dolu gelir).
            ...($this->isNewVersion() ? [] : array_filter([app(ProposalDetail::class)->newVersionAction($proposal)])),
            // D-184: "Tum belgeleri indir" baslikta da (yalniz simge, ipucunda adi);
            // Dokumanlar sekmesindeki dugme kalir.
            DocumentBundleAction::proposalHeader($proposal),
            ViewAction::make(),
        ];
    }

    /** Taslak kaydi sihirbazda kalir; yalniz "Kaydet" detay sayfasina gider (D-178). */
    public function saveDraft(): void
    {
        $this->saveAsDraft = true;
        $this->save(shouldRedirect: false);
        $this->saveAsDraft = false;
    }

    /** B43: guncel surumun alanlari, kapsamlari ve belgeleri forma yuklenir. */
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
        // D-182: Teklif durumu yalniz baslik dugmesiyle degisir; kaydetme onu
        // hicbir zaman eski degerle ezmez.
        unset($data['offer_status']);

        return [...$data, ...DraftSupport::attributes($this->saveAsDraft, BusinessCaseWizard::STEP_PROPOSAL)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            if (! BusinessCaseWizard::b43()) {
                return app(ProposalService::class)->update($record, $data);
            }

            $service = app(AcquisitionIntakeService::class);

            /** @var Proposal $record */
            if ($this->isNewVersion()) {
                $version = $service->newProposalVersion($record, $data);
                DomainNotifications::success(__('proposal.messages.new_version', ['no' => $version->version_no]));

                return $record->refresh();
            }

            $proposal = $service->updateProposal($record, $data);

            DomainNotifications::success($this->saveAsDraft
                ? __('proposal.messages.draft_saved', ['no' => $proposal->proposal_no ?? '-'])
                : __('proposal.messages.saved_in_place', ['no' => $proposal->currentVersion?->version_no ?? '-']));

            return $proposal;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    /** Guncel surum ve belgelerle form yenilenir (taslak kaydinda sayfa acik kalir). */
    protected function afterSave(): void
    {
        if (! BusinessCaseWizard::b43()) {
            return;
        }

        ProposalFilesSchema::flushCache();
        $this->record = $this->getRecord()->fresh() ?? $this->getRecord();
        $this->fillForm();
    }

    protected function getSavedNotification(): ?Notification
    {
        return BusinessCaseWizard::b43() ? null : parent::getSavedNotification();
    }
}
