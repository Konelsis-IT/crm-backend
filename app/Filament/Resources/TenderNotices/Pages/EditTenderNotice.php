<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Models\Acquisition\TenderNotice;
use App\Services\Acquisition\TenderNoticeService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Ihale duzenle. B43 (D-155): ihale olusturla ayni dort adim; ihale adimi form
 * ("Kaydet", "Taslak olarak kaydet"), potansiyel is adiminda bagli is ya da
 * "Bu ihaleden potansiyel is olustur". Grup uygulanmadiysa eski tek bolumlu form.
 */
class EditTenderNotice extends EditRecord
{
    use HasColoredFormActions;
    use HasSaveableWizard {
        form as protected wizardForm;
        hasFormWrapper as protected wizardHasFormWrapper;
        getFormContentComponent as protected wizardFormContentComponent;
    }

    protected static string $resource = TenderNoticeResource::class;

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
        /** @var TenderNotice $notice */
        $notice = $this->getRecord();
        $notice->loadMissing(['businessCase.codes', 'businessCase.proposals.currentVersion', 'businessCase.project']);

        return app(BusinessCaseWizard::class)->tenderSteps($notice);
    }

    public function getStartStep(): int
    {
        return BusinessCaseWizard::stepNumber($this->step) ?? (int) BusinessCaseWizard::stepNumber(BusinessCaseWizard::STEP_TENDER);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getStepSaveMethods(): array
    {
        return [BusinessCaseWizard::STEP_TENDER => 'save'];
    }

    protected function getStepDraftMethods(): array
    {
        return DraftSupport::enabled() ? [BusinessCaseWizard::STEP_TENDER => 'saveDraft'] : [];
    }

    protected function getHeaderActions(): array
    {
        return [
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

    /** Taslak kaydi sihirbazda kalir; yalniz "Kaydet" detay sayfasina gider (D-178). */
    public function saveDraft(): void
    {
        $this->saveAsDraft = true;
        $this->save(shouldRedirect: false);
        $this->saveAsDraft = false;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, ...DraftSupport::attributes($this->saveAsDraft, BusinessCaseWizard::STEP_TENDER)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(TenderNoticeService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
