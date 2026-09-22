<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Support\BusinessCaseWizard;
use App\Filament\Support\DomainNotifications;
use App\Models\Acquisition\Proposal;
use App\Services\Acquisition\AcquisitionIntakeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Teklif olustur (22 Eylul 2026 kullanici karari): is dosyasi olusturma
 * sihirbazinin 2. adimi tek sayfada. Zorunlu is dosyasi secimi, secilen is
 * dosyasinin ozet karti, teklif bolumu (surum, teklif durumu, belgeler) ve
 * istenirse hemen projeye donusum. Yazma AcquisitionIntakeService::addProposal.
 */
class CreateProposal extends CreateRecord
{
    protected static string $resource = ProposalResource::class;

    /** Teklif bu istekte projeye donusturulduyse proje calisma alanina gecilir. */
    private bool $converted = false;

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(app(BusinessCaseWizard::class)->proposalCreateComponents());
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $proposal = app(AcquisitionIntakeService::class)->addProposal((int) $data['business_case_id'], $data);
            $project = $proposal->businessCase?->project;
            $this->converted = (bool) ($data['convert_now'] ?? false) && $project !== null;

            DomainNotifications::success($this->converted
                ? __('project.messages.converted', ['code' => $project->businessCode?->formatted_code ?? '-'])
                : __('proposal.messages.created', ['no' => $proposal->proposal_no ?? '-']));

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

        return $this->converted && $project !== null
            ? ProjectResource::getUrl('view', ['record' => $project])
            : static::getResource()::getUrl('view', ['record' => $proposal]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
