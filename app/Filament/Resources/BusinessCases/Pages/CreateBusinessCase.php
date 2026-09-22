<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasSaveableWizard;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Models\Acquisition\BusinessCase;
use App\Services\Acquisition\AcquisitionIntakeService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * "Is dosyasi -> Teklif -> Proje" olusturma sihirbazi (D-72). Uc adim
 * AcquisitionIntakeService ile tek transaction'da yazilir; proje de
 * olusturulduysa dogrudan proje calisma alanina gecilir.
 */
class CreateBusinessCase extends CreateRecord
{
    use HasSaveableWizard;

    protected static string $resource = BusinessCaseResource::class;

    public function getTitle(): string
    {
        return __('business_case.actions.create');
    }

    public function getSubheading(): ?string
    {
        return __('business_case.help.chain_intro');
    }

    /**
     * "Kaydet" ile kesilen adim (22 Eylul 2026 kullanici karari): 'case' yalniz
     * is dosyasi, 'proposal' is dosyasi + teklif; null ise son adimdaki
     * "Olustur" ile tum zincir.
     */
    private ?string $stopAt = null;

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return app(BusinessCaseWizard::class)->createSteps();
    }

    /** Alt satirdaki "Kaydet": 1. adimda yalniz is dosyasi, 2. adimda is dosyasi + teklif. */
    protected function getStepSaveMethods(): array
    {
        return [
            BusinessCaseWizard::STEP_CASE => 'saveCaseOnly',
            BusinessCaseWizard::STEP_PROPOSAL => 'saveWithProposal',
        ];
    }

    /** 1. adimdaki "Kaydet": yalniz is dosyasi acilir. */
    public function saveCaseOnly(): void
    {
        $this->stopAt = BusinessCaseWizard::STEP_CASE;
        $this->create();
    }

    /** 2. adimdaki "Kaydet": is dosyasi ve (istendiyse) teklif; projeye donusum yok. */
    public function saveWithProposal(): void
    {
        $this->stopAt = BusinessCaseWizard::STEP_PROPOSAL;
        $this->create();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->stopAt === BusinessCaseWizard::STEP_CASE) {
            $data['create_proposal'] = false;
        }

        if ($this->stopAt !== null) {
            $data['convert_now'] = false;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $case = app(AcquisitionIntakeService::class)->start($data);
            $project = $case->project;

            DomainNotifications::success($project === null
                ? __('business_case.messages.created', ['code' => $case->offerCode()?->formatted_code ?? '-'])
                : __('project.messages.converted', ['code' => $project->businessCode?->formatted_code ?? '-']));

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

        return $project === null
            ? static::getResource()::getUrl('view', ['record' => $case])
            : ProjectResource::getUrl('view', ['record' => $project]);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return null;
    }
}
