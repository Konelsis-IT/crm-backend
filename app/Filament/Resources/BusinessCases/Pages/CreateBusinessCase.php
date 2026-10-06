<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\ConfirmsChecklist;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\DraftSupport;
use App\Filament\Support\TenderSchema;
use App\Models\Acquisition\BusinessCase;
use App\Query\Acquisition\TenderQueries;
use App\Services\Acquisition\AcquisitionIntakeService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * "Is dosyasi -> Teklif -> Proje" olusturma sihirbazi (D-72). Uc adim
 * AcquisitionIntakeService ile tek transaction'da yazilir; proje de
 * olusturulduysa dogrudan proje calisma alanina gecilir.
 *
 * B43 (D-155): zincir "Ihale -> Potansiyel is -> Teklif -> Proje"; sayfa
 * potansiyel is adiminda acilir, ihale adimi geride ve istege baglidir
 * (?ihale=ID ile ihaleden acilirsa ihale secili ve sabit). Potansiyel is
 * adiminin Kaydet'inde ve Ileri'sinde kontrol listesi ozeti acilir
 * (ConfirmsChecklist, D-157); adimlarda "Taslak olarak kaydet" vardir (DraftSupport).
 */
class CreateBusinessCase extends CreateRecord
{
    use ConfirmsChecklist;
    use HasColoredFormActions;
    use HasSaveableWizard;

    protected static string $resource = BusinessCaseResource::class;

    /** Taraf kartindan acilinca musteri on secili (D-129): ?taraf=ID. */
    public const QUERY_PARTY = 'taraf';

    /** Ihaleden acildiysa ihale kimligi (B43). */
    #[Locked]
    public ?int $fromTender = null;

    /**
     * "Kaydet" ile kesilen adim (22 Eylul 2026 kullanici karari): 'case' yalniz
     * is dosyasi, 'proposal' is dosyasi + teklif; null ise son adimdaki
     * "Olustur" ile tum zincir. Kontrol listesi penceresinden sonra da
     * gecerli kalsin diye Livewire durumundadir.
     */
    public ?string $stopAt = null;

    public function getTitle(): string
    {
        return __('business_case.actions.create');
    }

    public function mount(): void
    {
        $tender = request()->query(BusinessCaseWizard::QUERY_TENDER);

        if (BusinessCaseWizard::b43() && is_numeric($tender) && app(TenderQueries::class)->forSummary((int) $tender) !== null) {
            $this->fromTender = (int) $tender;
        }

        parent::mount();
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return app(BusinessCaseWizard::class)->createSteps(tenderLocked: $this->fromTender !== null);
    }

    /** B43: sayfa potansiyel is adiminda acilir; ihale adimi geride. */
    public function getStartStep(): int
    {
        return (int) (BusinessCaseWizard::stepNumber(BusinessCaseWizard::STEP_CASE) ?? 1);
    }

    protected function hasSkippableSteps(): bool
    {
        return BusinessCaseWizard::b43();
    }

    /** Alt satirdaki "Kaydet": potansiyel is adiminda yalniz is, teklif adiminda is + teklif. */
    protected function getStepSaveMethods(): array
    {
        return [
            BusinessCaseWizard::STEP_CASE => 'saveCaseOnly',
            BusinessCaseWizard::STEP_PROPOSAL => 'saveWithProposal',
        ];
    }

    /** "Taslak olarak kaydet" (B43, taslak ozelligi aciksa). */
    protected function getStepDraftMethods(): array
    {
        return DraftSupport::enabled() ? [
            BusinessCaseWizard::STEP_CASE => 'saveCaseDraft',
            BusinessCaseWizard::STEP_PROPOSAL => 'saveProposalDraft',
        ] : [];
    }

    /** Potansiyel is adimindan "Ileri": once kontrol listesi ozeti (D-157). */
    protected function getStepNextGuards(): array
    {
        return [BusinessCaseWizard::STEP_CASE => 'confirmCaseStepNext'];
    }

    /** Ozet yalniz potansiyel is adiminin "Kaydet"inde; teklif adiminda ve son adimda Ileri'de goruldu. */
    protected function checklistSummaryOnSave(): bool
    {
        return $this->stopAt === BusinessCaseWizard::STEP_CASE;
    }

    /** Son adimdaki "Olustur" zincirin tamamini yazar (onceki "Kaydet" secimi sifirlanir). */
    public function getSubmitFormLivewireMethodName(): string
    {
        return 'createAll';
    }

    /**
     * Taraf kartindaki "Is dosyasi olustur" (D-129) musteriyi on secer; gecersiz
     * kimlik secim alaninin kendi dogrulamasina takilir. Ihaleden acildiysa
     * (B43) ihale secili gelir.
     */
    protected function afterFill(): void
    {
        $partyId = request()->query(self::QUERY_PARTY);

        if (is_numeric($partyId) && (int) $partyId > 0) {
            $this->data['primary_party_id'] = (int) $partyId;
        }

        if ($this->fromTender !== null) {
            $this->data['tender_mode'] = TenderSchema::MODE_EXISTING;
            $this->data['tender_notice_id'] = $this->fromTender;
        }
    }

    public function createAll(): void
    {
        $this->stopAt = null;
        $this->saveAsDraft = false;
        $this->create();
    }

    /** Potansiyel is adimindaki "Kaydet": yalniz is dosyasi acilir. */
    public function saveCaseOnly(): void
    {
        $this->stopAt = BusinessCaseWizard::STEP_CASE;
        $this->saveAsDraft = false;
        $this->create();
    }

    public function saveCaseDraft(): void
    {
        $this->stopAt = BusinessCaseWizard::STEP_CASE;
        $this->saveAsDraft = true;
        $this->create();
    }

    /** Teklif adimindaki "Kaydet": is dosyasi ve (istendiyse) teklif; projeye donusum yok. */
    public function saveWithProposal(): void
    {
        $this->stopAt = BusinessCaseWizard::STEP_PROPOSAL;
        $this->saveAsDraft = false;
        $this->create();
    }

    public function saveProposalDraft(): void
    {
        $this->stopAt = BusinessCaseWizard::STEP_PROPOSAL;
        $this->saveAsDraft = true;
        $this->create();
    }

    public function create(bool $another = false): void
    {
        if ($this->checklistBlocks()) {
            return;
        }

        parent::create($another);
    }

    protected function continueAfterChecklist(): void
    {
        $this->create();
    }

    protected function checklistRecord(): ?BusinessCase
    {
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->stopAt === BusinessCaseWizard::STEP_CASE) {
            $data['create_proposal'] = false;
        }

        if ($this->stopAt !== null) {
            $data['convert_now'] = false;
        }

        // Ihaleden acildiysa secim sabittir (devre disi alan gonderilmese de).
        if ($this->fromTender !== null) {
            $data['tender_mode'] = TenderSchema::MODE_EXISTING;
            $data['tender_notice_id'] = $this->fromTender;
        }

        $this->rememberOfferType($data);

        return [
            ...$data,
            ...DraftSupport::attributes($this->saveAsDraft, $this->stopAt ?? BusinessCaseWizard::STEP_PROJECT),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $case = app(AcquisitionIntakeService::class)->start($data);
            $project = $case->project;

            DomainNotifications::success($project === null
                ? __($this->saveAsDraft ? 'business_case.messages.draft_saved' : 'business_case.messages.created', ['code' => $case->caseCode()?->formatted_code ?? '-'])
                : __('project.messages.converted', ['code' => $project->businessCode?->formatted_code ?? '-']));

            $this->notifyBudgetarySwitch($case);

            return $case;
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var BusinessCase $case */
        $case = $this->getRecord();
        $project = $case->project;

        if ($project !== null) {
            return ProjectResource::getUrl('view', ['record' => $project]);
        }

        // Taslak kaldigi adimdan devam eder (duzenleme sihirbazi).
        return $this->saveAsDraft
            ? static::getResource()::getUrl('edit', ['record' => $case, 'step' => $this->stopAt ?? BusinessCaseWizard::STEP_CASE])
            : static::getResource()::getUrl('view', ['record' => $case]);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return null;
    }
}
