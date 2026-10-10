<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Enums\Acquisition\OfferType;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\ConfirmsChecklist;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Support\ActionColors;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\ChecklistSchema;
use App\Filament\Support\DocumentBundleAction;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\StatusButton;
use App\Filament\Support\TenderSchema;
use App\Models\Acquisition\BusinessCase;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Is dosyasi duzenleme sihirbazi (D-72): olusturma sihirbaziyla ayni uc
 * adim — 1 alanlar ("Kaydet"), 2 teklifler tablosu, 3 proje / donusum.
 * Potansiyel is adimindan (ya da ?step= ile istenen adimdan) acilir; zincirde
 * en ileri adim acilmaz (D-160).
 *
 * B43 (D-155): dort adim — ihale (bagli ihaleler, yeni baglanti ya da yeni
 * ihale), potansiyel is (alanlar, kontrol listesi, belgeler), teklifler (teklif
 * olustur / duzenle ayni adimli ekrana gider), proje. Taslak kaldigi adimdan
 * acilir; potansiyel is adiminin Kaydet'inde ve Ileri'sinde kontrol listesi
 * ozeti acilir (ConfirmsChecklist, D-157).
 */
class EditBusinessCase extends EditRecord
{
    use ConfirmsChecklist;
    use HasColoredFormActions;
    use HasSaveableWizard;

    protected static string $resource = BusinessCaseResource::class;

    #[Url]
    public ?string $step = null;

    public function getSubheading(): ?string
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        // D-167 (6 Ekim 2026 kullanici talimati): teklife donusmus is duzenlenirken
        // isin su anda Teklif adiminda oldugu acikca yazar.
        if ($case->proposals()->exists()) {
            return __('business_case.help.edit_now_in_offer');
        }

        return BusinessCaseWizard::b43() ? __('business_case.help.edit_intro_chain') : __('business_case.help.edit_intro');
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        $wizard = app(BusinessCaseWizard::class);
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return [
            ...(BusinessCaseWizard::b43() ? [app(TenderSchema::class)->choiceStep($case)] : []),
            $wizard->caseStep(),
            $wizard->proposalTableStep($case),
            $wizard->projectStep($case),
        ];
    }

    public function getStartStep(): int
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return app(BusinessCaseWizard::class)->startStep($case, $this->step);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    /** Ihale ve potansiyel is adimi alt satirda "Kaydet" tasir; teklif ve proje adimlari kendi ekranlarina gider. */
    protected function getStepSaveMethods(): array
    {
        return [
            ...(BusinessCaseWizard::b43() ? [BusinessCaseWizard::STEP_TENDER => 'saveTenderStep'] : []),
            BusinessCaseWizard::STEP_CASE => 'save',
        ];
    }

    protected function getStepDraftMethods(): array
    {
        return DraftSupport::enabled() ? [BusinessCaseWizard::STEP_CASE => 'saveDraft'] : [];
    }

    /** Potansiyel is adimindan "Ileri": once kontrol listesi ozeti (D-157). */
    protected function getStepNextGuards(): array
    {
        return [BusinessCaseWizard::STEP_CASE => 'confirmCaseStepNext'];
    }

    /** Ihale adiminin "Kaydet"i ozet penceresi acmaz (ozet potansiyel is adimina aittir). */
    public function saveTenderStep(): void
    {
        $this->checklistSkipOnce = true;
        $this->save(shouldRedirect: true);
    }

    protected function getHeaderActions(): array
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return [
            Action::make('save_now')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(Heroicon::OutlinedCheck)
                ->color(ActionColors::SAVE)
                ->action('save'),
            // D-182: durum "Degisiklikleri kaydet"in hemen yaninda acilir dugme
            // (detaydaki menuyle ayni; burada ozellik anahtari yok, kodla gider).
            // Secim hemen kaydedilir; formun diger alanlari ve teklif surumleri
            // etkilenmez (D-161).
            ...StatusButton::businessCaseHeader($case, gated: false),
            // D-184: teklif duzenle ile ayni; "Tum belgeleri indir" yalniz simge.
            DocumentBundleAction::businessCaseHeader($case),
            ViewAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if ($this->checklistBlocks()) {
            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        $this->saveAsDraft = false;
    }

    /** Taslak kaydi sihirbazda kalir; yalniz "Kaydet" detay sayfasina gider (D-178). */
    public function saveDraft(): void
    {
        $this->saveAsDraft = true;
        $this->save(shouldRedirect: false);
    }

    protected function continueAfterChecklist(): void
    {
        // Ozet penceresindeki "Taslak olarak kaydet" de sihirbazda kalir (D-178).
        $this->save(shouldRedirect: ! $this->saveAsDraft);
    }

    protected function checklistRecord(): ?BusinessCase
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();

        return $case;
    }

    /**
     * B29: kayitli proje kapsamlari (secili tipler + tip basina sayisal
     * alanlar) forma yuklenir; dosya alanlari bos kalir. Kaydetme sirasinda
     * scope_types / scopes anahtarlarini BusinessCaseService isler.
     * B43: yalniz tipler, kontrol listesi cevaplari; ihale adimi bos baslar.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            return $data;
        }

        /** @var BusinessCase $case */
        $case = $this->getRecord();
        $case->loadMissing('scopes.scopeDocument.revisions.files.fileObject');

        if (BusinessCaseWizard::b43()) {
            $case->loadMissing(['checklistAnswers', 'caseDocuments.document.revisions.files.fileObject']);
        }

        // B29 oncesi acilmis kayitlarda teklif tipi bos; olusturma formundaki
        // varsayilan uygulanir (kullanici kaydedince yazilir).
        return [
            ...$data,
            'offer_type' => $data['offer_type'] ?? OfferType::Budgetary->value,
            ...app(BusinessCaseWizard::class)->scopeFormData($case),
            ...(BusinessCaseWizard::b43() ? [
                'checklist' => ChecklistSchema::formData($case),
                'tender_mode' => TenderSchema::MODE_NONE,
            ] : []),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rememberOfferType($data);

        // Taslak kaldigi adim: kaydedilen potansiyel is adimi.
        return [...$data, ...DraftSupport::attributes($this->saveAsDraft, BusinessCaseWizard::STEP_CASE)];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            /** @var BusinessCase $record */
            return BusinessCaseWizard::b43()
                ? app(AcquisitionIntakeService::class)->updateCase($record, $data)
                : app(BusinessCaseService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    /** Servis taze ornek uzerinden yazdigi icin kayit ve form yenilenir (row_version). */
    protected function afterSave(): void
    {
        $this->record = $this->getRecord()->fresh() ?? $this->getRecord();

        /** @var BusinessCase $case */
        $case = $this->record;
        $this->notifyBudgetarySwitch($case);

        if ($this->saveAsDraft) {
            DomainNotifications::success(__('business_case.messages.draft_saved', ['code' => $case->caseCode()?->formatted_code ?? '-']));
        }

        $this->fillForm();
    }
}
