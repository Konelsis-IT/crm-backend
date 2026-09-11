<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Services\Project\ProjectConversionService;
use App\Services\Support\TransactionRunner;

/**
 * Is alim girisi (D-72): "Is dosyasi -> Teklif -> Proje" sihirbazinin tek
 * transaction'daki karsiligi.
 *
 * Adim 1 is dosyasini acar (TKLF kodu, firsat kaydi: BusinessCaseService).
 * Adim 2 istenirse ilk teklifi ve ilk surumunu acar (ProposalService,
 * ProposalVersionService) ve is dosyasini "Teklif hazirlaniyor" asamasina
 * tasir. Adim 3 istenirse (kazanilmis / dogrudan yapilacak is) teklifi
 * ProjectConversionService ile projeye donusturur; kanonik zincir (teklif
 * onayi -> kazanildi -> Operasyona devir -> kabul -> PRJ) atlanmaz.
 */
final class AcquisitionIntakeService
{
    /** @var list<string> Is dosyasina yazilan alanlar. */
    private const CASE_KEYS = [
        'primary_party_id', 'title', 'short_description', 'country_code', 'currency_code', 'project_type_code',
        'source_kind', 'criticality', 'owner_employee_id', 'proposal_owner_employee_id', 'estimated_value',
        'classification_id', 'legal_entity_id',
    ];

    /** @var list<string> Ilk teklif surumune yazilan alanlar. */
    private const VERSION_KEYS = ['total_price', 'margin_pct', 'validity_until', 'is_critical_route', 'summary'];

    /** @var list<string> Donusumde projeye aktarilan alanlar. */
    private const PROJECT_KEYS = [
        'project_manager_employee_id', 'planned_start_on', 'planned_finish_on',
        'site_address_line1', 'site_address_line2', 'site_district', 'site_city', 'site_postal_code', 'site_country_code',
    ];

    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly BusinessCaseService $businessCases,
        private readonly ProposalService $proposals,
        private readonly ProposalVersionService $proposalVersions,
        private readonly ProjectConversionService $conversion,
    ) {}

    /**
     * @param  array<string, mixed>  $data  is dosyasi alanlari + create_proposal, proposal_title, surum alanlari + convert_now, project_name, proje alanlari
     */
    public function start(array $data): BusinessCase
    {
        return $this->transactions->run(function () use ($data): BusinessCase {
            /** @var BusinessCase $case */
            $case = $this->businessCases->create(array_intersect_key($data, array_flip(self::CASE_KEYS)));

            $createProposal = (bool) ($data['create_proposal'] ?? false);
            $convertNow = (bool) ($data['convert_now'] ?? false);

            if ($convertNow && ! $createProposal) {
                throw GuardNotSatisfiedException::make(['reason' => 'projeye donusum icin once teklif olusturulmali']);
            }

            if (! $createProposal) {
                return $case->refresh();
            }

            /** @var Proposal $proposal */
            $proposal = $this->proposals->create([
                'business_case_id' => $case->getKey(),
                'title' => filled($data['proposal_title'] ?? null) ? (string) $data['proposal_title'] : null,
            ]);

            $version = $this->proposalVersions->create([
                ...array_intersect_key($data, array_flip(self::VERSION_KEYS)),
                'proposal_id' => $proposal->getKey(),
                'is_critical_route' => (bool) ($data['is_critical_route'] ?? false),
            ]);

            $case->refresh();

            if ($case->acquisition_stage->canTransitionTo(AcquisitionStage::OfferPreparation)) {
                $this->businessCases->changeStage($case, AcquisitionStage::OfferPreparation);
            }

            if ($convertNow) {
                $this->conversion->convertProposal($proposal, [
                    ...array_intersect_key($data, array_flip(self::PROJECT_KEYS)),
                    'proposal_version_id' => $version->getKey(),
                    'approve_draft' => true,
                    'name' => filled($data['project_name'] ?? null) ? (string) $data['project_name'] : (string) $case->title,
                    'description' => $data['short_description'] ?? null,
                ]);
            }

            return $case->refresh();
        });
    }
}
