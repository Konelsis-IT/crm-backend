<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\TenderNotice;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Acquisition\TenderNoticeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Ihale olustur. B43 (D-155, 5 Ekim 2026 kullanici talimati: "Potansiyel isten
 * bir adim oncesi de ihale olustur ekrani olacak ... potansiyel is secimine
 * gerek yok ... olustur - duzenle ekranlari diger potansiyel isler ve teklif
 * adimindaki ekran gibi olacak"): ayni dort adimli sihirbaz, ihale adiminda
 * acilir. Ihale potansiyel is secmeden kaydedilir; son adimdan "Olustur" ve
 * "kaydettikten sonra potansiyel is olustur" seciliyse potansiyel is sihirbazi
 * bu ihale secili olarak acilir. Grup uygulanmadiysa eski tek bolumlu form
 * (potansiyel is zorunlu).
 */
class CreateTenderNotice extends CreateRecord
{
    use HasColoredFormActions;
    use HasSaveableWizard {
        form as protected wizardForm;
        hasFormWrapper as protected wizardHasFormWrapper;
        getFormContentComponent as protected wizardFormContentComponent;
    }

    protected static string $resource = TenderNoticeResource::class;

    /** Bu kaydetme taslak olarak yazilir (B43). */
    public bool $saveAsDraft = false;

    /** Ihale adimindaki "Kaydet": potansiyel is sihirbazina gecilmez. */
    public bool $stopAtTender = false;

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
        return app(BusinessCaseWizard::class)->tenderSteps(null);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getStepSaveMethods(): array
    {
        return [BusinessCaseWizard::STEP_TENDER => 'saveTenderOnly'];
    }

    protected function getStepDraftMethods(): array
    {
        return DraftSupport::enabled() ? [BusinessCaseWizard::STEP_TENDER => 'saveTenderDraft'] : [];
    }

    protected function getSubmitFormLivewireMethodName(): string
    {
        return BusinessCaseWizard::b43() ? 'createAll' : 'create';
    }

    public function createAll(): void
    {
        $this->stopAtTender = false;
        $this->saveAsDraft = false;
        $this->create();
    }

    public function saveTenderOnly(): void
    {
        $this->stopAtTender = true;
        $this->saveAsDraft = false;
        $this->create();
    }

    public function saveTenderDraft(): void
    {
        $this->stopAtTender = true;
        $this->saveAsDraft = true;
        $this->create();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, ...DraftSupport::attributes($this->saveAsDraft, BusinessCaseWizard::STEP_TENDER)];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            if (! BusinessCaseWizard::b43()) {
                return app(TenderNoticeService::class)->create($data);
            }

            $notice = app(AcquisitionIntakeService::class)->startTender($data);
            DomainNotifications::success(__($this->saveAsDraft ? 'tender_notice.messages.draft_saved' : 'tender_notice.messages.created'));

            return $notice;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var TenderNotice $notice */
        $notice = $this->getRecord();

        if (! BusinessCaseWizard::b43()) {
            return parent::getRedirectUrl();
        }

        if ($this->saveAsDraft) {
            return static::getResource()::getUrl('edit', ['record' => $notice]);
        }

        // Son adimdaki "Olustur" + secim: zincir potansiyel isle devam eder.
        if (! $this->stopAtTender && (bool) ($this->data['continue_to_case'] ?? false) && Gate::allows('create', BusinessCase::class)) {
            return BusinessCaseResource::getUrl('create', [BusinessCaseWizard::QUERY_TENDER => $notice->getKey()]);
        }

        return static::getResource()::getUrl('view', ['record' => $notice]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return BusinessCaseWizard::b43() ? null : parent::getCreatedNotification();
    }
}
