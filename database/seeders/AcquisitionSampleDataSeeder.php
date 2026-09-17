<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\CompletionState;
use App\Enums\Acquisition\ContractVersionStatus;
use App\Enums\Acquisition\EstimateVersionStatus;
use App\Enums\Acquisition\OpportunityStage;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\ReviewDecision;
use App\Enums\Acquisition\SubmissionChannel;
use App\Enums\Project\FocusDirection;
use App\Enums\Project\StageInstanceStatus;
use App\Enums\Project\StageReviewDecision;
use App\Enums\Project\WorkstreamStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\TenderSource;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\Transmittal;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\UnitOfMeasure;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Acquisition\BusinessDevelopmentActivityService;
use App\Services\Acquisition\BusinessDevelopmentActivityParticipantService;
use App\Services\Acquisition\ComplianceItemService;
use App\Services\Acquisition\ContractDocumentService;
use App\Services\Acquisition\ContractMilestoneService;
use App\Services\Acquisition\ContractObligationService;
use App\Services\Acquisition\ContractPartyService;
use App\Services\Acquisition\ContractService;
use App\Services\Acquisition\ContractVersionService;
use App\Services\Acquisition\EstimateLineService;
use App\Services\Acquisition\EstimateVersionService;
use App\Services\Acquisition\HandoffItemService;
use App\Services\Acquisition\HandoffReviewService;
use App\Services\Acquisition\OperationHandoffService;
use App\Services\Acquisition\OperationHandoffVersionService;
use App\Services\Acquisition\OpportunityService;
use App\Services\Acquisition\PricingScenarioService;
use App\Services\Acquisition\ProposalDocumentService;
use App\Services\Acquisition\ProposalService;
use App\Services\Acquisition\ProposalVersionService;
use App\Services\Acquisition\ResponsibilityMatrixItemService;
use App\Services\Acquisition\TenderDeadlineService;
use App\Services\Acquisition\TenderNoticeService;
use App\Services\Acquisition\TenderRequirementService;
use App\Services\Audit\ActorContext;
use App\Services\Party\AddressService;
use App\Services\Party\CommunicationPointService;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Project\CbsNodeService;
use App\Services\Project\CommercialClarificationService;
use App\Services\Project\CommercialExposureService;
use App\Services\Project\DelayEventService;
use App\Services\Project\MilestoneService;
use App\Services\Project\ProgressSnapshotService;
use App\Services\Project\ProjectChangeService;
use App\Services\Project\ProjectDecisionService;
use App\Services\Project\ProjectIssueService;
use App\Services\Project\ProjectRiskService;
use App\Services\Project\ProjectStageInstanceService;
use App\Services\Project\ProjectWorkstreamService;
use App\Services\Project\RecoveryActionService;
use App\Services\Project\ScheduleBaselineService;
use App\Services\Project\StageEvidenceService;
use App\Services\Project\StageReviewService;
use App\Services\Project\WbsCbsMappingService;
use App\Services\Project\WbsNodeService;
use App\Services\Project\ProjectPhotoService;
use App\Services\Project\ProjectService;
use App\Services\Project\ProjectSupplyItemService;
use App\Services\Project\ProjectTeamMemberService;
use App\Services\Project\WorkPackageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Is Alim -> Operasyon zincirinin ucdan uca ornek verisi (kurgusal
 * "Karapinar 500 MW GES" senaryosu): party ve kisi, business case + TKLF
 * kodu, firsat asamalari, ihale ilani, teklif surumu (onayli/gonderilmis),
 * tahmin ve fiyat senaryosu, sozlesme surumu (yururlukte), Operasyona devir
 * (kabul -> PRJ kodu + proje), workstream/gate/WBS/CBS/milestone/issue/risk
 * kayitlari. Her adim gercek servislerden gecer; DMS ornek dokumanlari
 * projeye ve teklife baglanir.
 *
 * DocumentSampleDataSeeder ve ProjectCatalogSeeder'dan sonra calisir;
 * bootstrap yonetici personeline (omer@gmail.com) baglidir.
 */
class AcquisitionSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('parties') || ! Schema::hasTable('projects')) {
            $this->command?->warn('B16/B17 tablolari yok; Is Alim ornek verisi atlandi.');

            return;
        }

        $admin = Personnel::query()
            ->whereKey(SystemAccountSeeder::actor()?->getKey())
            ->first();

        if ($admin === null || BusinessCase::query()->exists()) {
            return;
        }

        app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        $adminId = (int) $admin->getKey();

        // Tek transaction: bir adim basarisiz olursa yarim veri kalmaz ve
        // `db:seed --class=AcquisitionSampleDataSeeder` temiz sekilde tekrar calisir.
        DB::transaction(function () use ($adminId): void {
            [$customer, $contact, $konelsis] = $this->seedParties();
            $case = $this->seedBusinessCase($customer, $contact, $adminId);
            $this->seedTender($case, $customer);
            $this->seedProposalContent($this->seedProposal($case));
            $this->seedContract($case, $customer, $konelsis);
            $project = $this->seedHandoffAndProject($case, $adminId);
            $this->seedProjectControls($project, $adminId);
            $this->linkDocuments($project, $customer);

            if (Schema::hasTable('project_supply_items')) {
                $this->seedWorkspaceData($project, $customer, $adminId);
            }
        });
    }

    /**
     * @return array{0: Party, 1: Party, 2: Party}
     */
    private function seedParties(): array
    {
        $parties = app(PartyService::class);

        /** @var Party $customer */
        $customer = $parties->create([
            'party_kind' => 'organization',
            'display_name' => 'ABC Enerji Yatırım A.Ş.',
            'country_code' => 'TR',
            'default_locale' => 'tr',
            'status' => 'active',
            'organization_profile' => [
                'legal_name' => 'ABC Enerji Yatırım Anonim Şirketi',
                'trade_name' => 'ABC Enerji',
                'tax_office' => 'Büyük Mükellefler',
                'tax_number' => '1234567890',
                'sector_code' => 'ENERGY',
                'founded_year' => 2012,
                'website_url' => 'https://example.invalid/abc-enerji',
                'is_public_company' => false,
            ],
        ]);

        app(PartyRoleService::class)->create(['party_id' => $customer->getKey(), 'role_code' => 'customer', 'status' => 'active']);
        app(PartyRoleService::class)->create(['party_id' => $customer->getKey(), 'role_code' => 'employer', 'status' => 'active']);

        app(AddressService::class)->create([
            'party_id' => $customer->getKey(),
            'address_type' => 'registered',
            'line1' => 'Maslak Mah. Büyükdere Cad. No: 255',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
            'postal_code' => '34398',
            'country_code' => 'TR',
            'is_primary' => true,
            'status' => 'active',
        ]);

        app(CommunicationPointService::class)->create([
            'party_id' => $customer->getKey(),
            'channel_type' => 'email',
            'value' => 'info@abc-enerji.example',
            'purpose' => 'general',
            'is_primary' => true,
            'status' => 'active',
        ]);

        /** @var Party $contact */
        $contact = $parties->create([
            'party_kind' => 'person',
            'display_name' => 'Ayşe Yılmaz',
            'country_code' => 'TR',
            'default_locale' => 'tr',
            'status' => 'active',
            'person_profile' => ['given_name' => 'Ayşe', 'family_name' => 'Yılmaz', 'job_title' => 'Proje Direktörü', 'consent_status' => 'granted', 'consent_at' => Carbon::now('UTC')],
        ]);

        app(CommunicationPointService::class)->create([
            'party_id' => $contact->getKey(),
            'channel_type' => 'email',
            'value' => 'ayse.yilmaz@abc-enerji.example',
            'is_primary' => true,
            'status' => 'active',
        ]);

        app(ContactRelationshipService::class)->create([
            'organization_party_id' => $customer->getKey(),
            'contact_party_id' => $contact->getKey(),
            'relationship_role' => 'decision_maker',
            'department_note' => 'Yatırım / Proje Yönetimi',
            'is_primary' => true,
        ]);

        /** @var Party $konelsis */
        $konelsis = $parties->create([
            'party_kind' => 'organization',
            'display_name' => (string) config('konelsis.legal_entity.short_name', 'Konelsis'),
            'country_code' => 'TR',
            'default_locale' => 'tr',
            'status' => 'active',
            'organization_profile' => ['legal_name' => (string) config('konelsis.legal_entity.legal_name', 'Konelsis')],
        ]);

        return [$customer, $contact, $konelsis];
    }

    private function seedBusinessCase(Party $customer, Party $contact, int $adminId): BusinessCase
    {
        /** @var BusinessCase $case */
        $case = app(BusinessCaseService::class)->create([
            'primary_party_id' => $customer->getKey(),
            'title' => 'Karapınar 500 MW GES EPC İşi',
            'short_description' => '500 MWp güneş enerjisi santrali için anahtar teslim EPC: PV saha, OG/YG şalt, SCADA, devreye alma.',
            'country_code' => 'TR',
            'currency_code' => 'TRY',
            'project_type_code' => 'GES',
            'source_kind' => 'referral',
            'criticality' => 'critical',
            'estimated_value' => 4500000000,
            'proposal_owner_employee_id' => $adminId,
        ]);

        $activity = app(BusinessDevelopmentActivityService::class)->create([
            'business_case_id' => $case->getKey(),
            'party_id' => $customer->getKey(),
            'activity_type' => 'meeting',
            'subject' => 'Karapınar GES ön görüşme ve saha bilgilendirmesi',
            'location' => 'ABC Enerji Genel Müdürlük',
            'outcome_summary' => 'Müşteri anahtar teslim EPC teklifi istiyor; 14 ay teslim süresi kritik.',
            'next_action' => 'Teklif hazırlığına başla',
            'next_action_due_at' => Carbon::now('UTC')->addDays(7),
        ]);

        app(BusinessDevelopmentActivityParticipantService::class)->create(['activity_id' => $activity->getKey(), 'personnel_id' => $adminId, 'participation_role' => 'host']);
        app(BusinessDevelopmentActivityParticipantService::class)->create(['activity_id' => $activity->getKey(), 'contact_party_id' => $contact->getKey(), 'participation_role' => 'attendee']);

        $opportunities = app(OpportunityService::class);
        $opportunity = $case->opportunity;
        $opportunities->update($opportunity, ['probability_pct' => 60, 'expected_value' => 4500000000, 'expected_decision_on' => Carbon::now()->addMonths(2)->toDateString(), 'market_code' => 'TR-RENEWABLE']);

        foreach ([OpportunityStage::Qualified, OpportunityStage::BidDecisionPending, OpportunityStage::Bid, OpportunityStage::ConvertedToProposal] as $stage) {
            $opportunity = $opportunities->changeStage($opportunity, $stage, $stage === OpportunityStage::Bid ? 'Referans müşteri, stratejik proje' : null);
        }

        return $case->refresh();
    }

    private function seedTender(BusinessCase $case, Party $customer): void
    {
        $sourceId = TenderSource::query()->where('code', 'PRIVATE')->value('id');

        if ($sourceId === null) {
            return;
        }

        $notice = app(TenderNoticeService::class)->create([
            'business_case_id' => $case->getKey(),
            'tender_source_id' => $sourceId,
            'external_notice_id' => 'ABC-2026-GES-01',
            'title' => 'Karapınar 500 MW GES EPC Teklif Daveti',
            'issuer_party_id' => $customer->getKey(),
            'summary' => 'Anahtar teslim EPC; teknik ve ticari teklif ayrı zarf; 60 gün geçerlilik.',
            'published_on' => Carbon::now()->subDays(20)->toDateString(),
            'status' => 'pursuing',
        ]);

        $versionId = $notice->current_version_id;
        $requirements = app(TenderRequirementService::class);

        $requirements->create(['tender_notice_version_id' => $versionId, 'requirement_code' => 'EXP-GES-100MW', 'requirement_type' => 'experience', 'description' => 'Son 5 yılda en az 100 MW GES EPC referansı', 'is_mandatory' => true, 'compliance_state' => 'met', 'sort_order' => 0]);
        $requirements->create(['tender_notice_version_id' => $versionId, 'requirement_code' => 'FIN-TURNOVER', 'requirement_type' => 'financial', 'description' => 'Son 3 yıl ortalama ciro ≥ teklif bedelinin %50\'si', 'is_mandatory' => true, 'compliance_state' => 'partially_met', 'sort_order' => 1]);
        $requirements->create(['tender_notice_version_id' => $versionId, 'requirement_code' => 'DOC-ISO', 'requirement_type' => 'document', 'description' => 'ISO 9001 / 45001 belgeleri', 'is_mandatory' => true, 'compliance_state' => 'met', 'sort_order' => 2]);

        app(TenderDeadlineService::class)->create(['tender_notice_version_id' => $versionId, 'deadline_type' => 'submission', 'local_due_date' => Carbon::now()->addDays(25)->toDateString(), 'local_due_time' => '17:00:00', 'timezone' => 'Europe/Istanbul']);
        app(TenderDeadlineService::class)->create(['tender_notice_version_id' => $versionId, 'deadline_type' => 'site_visit', 'local_due_date' => Carbon::now()->addDays(10)->toDateString(), 'local_due_time' => '10:00:00', 'timezone' => 'Europe/Istanbul']);
    }

    /** Teklif kokunu ve ilk (taslak) surumu acar. */
    private function seedProposal(BusinessCase $case): ProposalVersion
    {
        $proposal = app(ProposalService::class)->create([
            'business_case_id' => $case->getKey(),
            'title' => 'Karapınar 500 MW GES EPC Teklifi',
        ]);

        return app(ProposalVersionService::class)->create([
            'proposal_id' => $proposal->getKey(),
            'locale' => 'tr',
            'currency_code' => 'TRY',
            'total_price' => 4480000000,
            'margin_pct' => 11.5,
            'validity_until' => Carbon::now()->addDays(60)->toDateString(),
            'is_critical_route' => true,
            'summary' => 'Sabit fiyat, anahtar teslim; 14 ay; P50 üretim taahhüdü %95.',
        ]);
    }

    /**
     * Taslak surumun icerigi (dokuman, uygunluk, matris, tahmin, senaryo) ve
     * onay/gonderim gecisleri.
     */
    private function seedProposalContent(ProposalVersion $version): ProposalVersion
    {
        $versions = app(ProposalVersionService::class);
        $documents = app(ProposalDocumentService::class);
        $revisionIds = $this->documentRevisionIds(['TEK-00001', 'ELE-00001', 'OTM-00001']);

        foreach ([['TEK-00001', 'commercial_offer'], ['ELE-00001', 'technical_offer'], ['OTM-00001', 'technical_offer']] as $order => [$documentNo, $role]) {
            if (isset($revisionIds[$documentNo])) {
                $documents->create(['proposal_version_id' => $version->getKey(), 'document_revision_id' => $revisionIds[$documentNo], 'document_role' => $role, 'sort_order' => $order]);
            }
        }

        $compliance = app(ComplianceItemService::class);
        $compliance->create(['proposal_version_id' => $version->getKey(), 'requirement_code' => 'EXP-GES-100MW', 'description' => 'GES EPC referansı', 'compliance_state' => 'comply', 'sort_order' => 0]);
        $compliance->create(['proposal_version_id' => $version->getKey(), 'requirement_code' => 'FIN-TURNOVER', 'description' => 'Ciro şartı', 'compliance_state' => 'partial', 'note' => 'Konsorsiyum ortağı ile karşılanacak', 'sort_order' => 1]);

        $matrix = app(ResponsibilityMatrixItemService::class);
        $matrix->create(['proposal_version_id' => $version->getKey(), 'scope_code' => 'GRID_CONN', 'scope_description' => '154 kV şebeke bağlantı izni', 'responsible_party_role' => 'customer', 'sort_order' => 0]);
        $matrix->create(['proposal_version_id' => $version->getKey(), 'scope_code' => 'PV_EPC', 'scope_description' => 'PV saha mühendislik, tedarik, montaj', 'responsible_party_role' => 'konelsis', 'sort_order' => 1]);
        $matrix->create(['proposal_version_id' => $version->getKey(), 'scope_code' => 'CIVIL', 'scope_description' => 'Saha düzenleme ve yol', 'responsible_party_role' => 'subcontractor', 'sort_order' => 2]);

        $estimate = app(EstimateVersionService::class)->create([
            'proposal_version_id' => $version->getKey(),
            'currency_code' => 'TRY',
            'target_margin_pct' => 11.5,
            'notes' => 'Kur snapshot: 07.09.2026 TCMB',
        ]);

        $uomIds = UnitOfMeasure::query()->pluck('id', 'code');
        $piece = (int) ($uomIds['PCS'] ?? $uomIds['EA'] ?? $uomIds->first());
        $lines = app(EstimateLineService::class);
        $lines->create(['estimate_version_id' => $estimate->getKey(), 'line_code' => 'MAT-PV', 'cost_type' => 'material', 'description' => 'PV panel (550 Wp)', 'quantity' => 909000, 'uom_id' => $piece, 'unit_cost' => 2100, 'unit_price' => 2400, 'wbs_hint' => '2.1', 'sort_order' => 0]);
        $lines->create(['estimate_version_id' => $estimate->getKey(), 'line_code' => 'MAT-INV', 'cost_type' => 'material', 'description' => 'String invertör 1,5 MW', 'quantity' => 42, 'uom_id' => $piece, 'unit_cost' => 4200000, 'unit_price' => 4700000, 'wbs_hint' => '2.2', 'sort_order' => 1]);
        $lines->create(['estimate_version_id' => $estimate->getKey(), 'line_code' => 'SUB-CIVIL', 'cost_type' => 'subcontract', 'description' => 'İnşaat ve saha düzenleme', 'quantity' => 1, 'uom_id' => $piece, 'unit_cost' => 380000000, 'unit_price' => 420000000, 'wbs_hint' => '3.0', 'sort_order' => 2]);
        $lines->create(['estimate_version_id' => $estimate->getKey(), 'line_code' => 'ENG', 'cost_type' => 'engineering', 'description' => 'Mühendislik ve proje yönetimi', 'quantity' => 1, 'uom_id' => $piece, 'unit_cost' => 95000000, 'unit_price' => 110000000, 'wbs_hint' => '1.0', 'sort_order' => 3]);

        $scenarios = app(PricingScenarioService::class);
        $scenarios->create(['estimate_version_id' => $estimate->getKey(), 'scenario_code' => 'BASE', 'name' => 'Baz senaryo (%11,5 marj)', 'target_margin_pct' => 11.5, 'total_price' => 4480000000, 'is_selected' => true]);
        $scenarios->create(['estimate_version_id' => $estimate->getKey(), 'scenario_code' => 'AGGR', 'name' => 'Agresif (%8 marj)', 'target_margin_pct' => 8, 'adjustment_pct' => -3.2, 'total_price' => 4340000000, 'is_selected' => false]);

        app(EstimateVersionService::class)->changeStatus($estimate, EstimateVersionStatus::Reviewed);
        app(EstimateVersionService::class)->changeStatus($estimate, EstimateVersionStatus::Approved);

        $version = $versions->changeStatus($version, ProposalVersionStatus::Review);
        $version = $versions->changeStatus($version, ProposalVersionStatus::Approved);

        return $versions->changeStatus($version, ProposalVersionStatus::Submitted, SubmissionChannel::Email, $revisionIds['TEK-00001'] ?? null);
    }

    private function seedContract(BusinessCase $case, Party $customer, Party $konelsis): void
    {
        $contract = app(ContractService::class)->create([
            'business_case_id' => $case->getKey(),
            'contract_type' => 'contract',
            'customer_party_id' => $customer->getKey(),
        ]);

        $versions = app(ContractVersionService::class);
        $version = $versions->create([
            'contract_id' => $contract->getKey(),
            'locale' => 'tr',
            'currency_code' => 'TRY',
            'contract_value' => 4480000000,
            'summary' => 'Anahtar teslim EPC sözleşmesi; 14 ay; gecikme cezası günlük binde 1 (üst sınır %10).',
            'effective_from' => Carbon::now()->toDateString(),
            'effective_until' => Carbon::now()->addMonths(38)->toDateString(),
        ]);

        $parties = app(ContractPartyService::class);
        $parties->create(['contract_version_id' => $version->getKey(), 'party_id' => $customer->getKey(), 'contract_role' => 'employer', 'signatory_name' => 'Ayşe Yılmaz']);
        $parties->create(['contract_version_id' => $version->getKey(), 'party_id' => $konelsis->getKey(), 'contract_role' => 'contractor', 'signatory_name' => 'Ömer (Genel Müdür)']);

        $revisionIds = $this->documentRevisionIds(['SOZ-00001']);
        if (isset($revisionIds['SOZ-00001'])) {
            app(ContractDocumentService::class)->create(['contract_version_id' => $version->getKey(), 'document_revision_id' => $revisionIds['SOZ-00001'], 'document_role' => 'signed_contract']);
        }

        $obligations = app(ContractObligationService::class);
        $obligations->create(['contract_version_id' => $version->getKey(), 'obligation_code' => 'PERF-BOND', 'obligation_type' => 'bond', 'description' => 'Kesin teminat mektubu (%10)', 'responsible_party_id' => $konelsis->getKey(), 'due_on' => Carbon::now()->addDays(15)->toDateString(), 'status' => 'open']);
        $obligations->create(['contract_version_id' => $version->getKey(), 'obligation_code' => 'ADV-PAY', 'obligation_type' => 'payment', 'description' => 'Avans ödemesi (%10)', 'responsible_party_id' => $customer->getKey(), 'due_on' => Carbon::now()->addDays(30)->toDateString(), 'status' => 'open']);
        $obligations->create(['contract_version_id' => $version->getKey(), 'obligation_code' => 'MONTHLY-RPT', 'obligation_type' => 'reporting', 'description' => 'Aylık ilerleme raporu', 'responsible_party_id' => $konelsis->getKey(), 'status' => 'open']);

        $milestones = app(ContractMilestoneService::class);
        $milestones->create(['contract_version_id' => $version->getKey(), 'milestone_code' => 'M1-NTP', 'name' => 'İşe başlama (NTP)', 'planned_on' => Carbon::now()->addDays(30)->toDateString(), 'payment_pct' => 10, 'payment_amount' => 448000000]);
        $milestones->create(['contract_version_id' => $version->getKey(), 'milestone_code' => 'M2-MC', 'name' => 'Mekanik tamamlanma', 'planned_on' => Carbon::now()->addMonths(10)->toDateString(), 'payment_pct' => 40, 'payment_amount' => 1792000000]);
        $milestones->create(['contract_version_id' => $version->getKey(), 'milestone_code' => 'M3-PAC', 'name' => 'Geçici kabul', 'planned_on' => Carbon::now()->addMonths(14)->toDateString(), 'payment_pct' => 45, 'payment_amount' => 2016000000]);

        $version = $versions->changeStatus($version, ContractVersionStatus::Review);
        $version = $versions->changeStatus($version, ContractVersionStatus::Approved);
        $versions->changeStatus($version, ContractVersionStatus::Executed);
    }

    private function seedHandoffAndProject(BusinessCase $case, int $adminId): Project
    {
        $handoff = app(OperationHandoffService::class)->create(['business_case_id' => $case->getKey()]);

        $version = app(OperationHandoffVersionService::class)->create([
            'operation_handoff_id' => $handoff->getKey(),
            'assumptions' => ['Şebeke bağlantı izni müşteri sorumluluğunda', 'Panel teslimatı 6. ayda başlar'],
            'schedule' => ['ntp' => Carbon::now()->addDays(30)->toDateString(), 'pac' => Carbon::now()->addMonths(14)->toDateString()],
        ]);

        $items = app(HandoffItemService::class);
        foreach ($version->items()->get() as $item) {
            if ($item->completion_state !== CompletionState::Complete) {
                $items->update($item, ['completion_state' => CompletionState::Complete->value]);
            }
        }

        app(OperationHandoffVersionService::class)->submit($version);

        app(HandoffReviewService::class)->create([
            'handoff_version_id' => $version->getKey(),
            'decision' => ReviewDecision::Accepted,
            'comment' => 'Referans takvim, sözleşme ve doküman manifesti tam; Proje Grubu kabul etti.',
            'project_overrides' => [
                'name' => 'Karapınar 500 MW GES Projesi',
                'project_manager_employee_id' => $adminId,
                'site_location' => 'Karapınar, Konya',
                'planned_start_on' => Carbon::now()->addDays(30)->toDateString(),
                'planned_finish_on' => Carbon::now()->addMonths(14)->toDateString(),
            ],
        ]);

        /** @var Project $project */
        $project = Project::query()->where('business_case_id', $case->getKey())->firstOrFail();

        return $project;
    }

    private function seedProjectControls(Project $project, int $adminId): void
    {
        $workstreams = $project->workstreams()->with('group')->get()->keyBy(fn ($ws) => $ws->group->code);
        $projectWs = $workstreams['PROJECT'];

        app(ProjectWorkstreamService::class)->changeStatus($projectWs, WorkstreamStatus::Active);

        $wbs = app(WbsNodeService::class);
        $eng = $wbs->create(['project_id' => $project->getKey(), 'wbs_code' => '1.0', 'name' => 'Mühendislik ve Proje Yönetimi', 'sort_order' => 1]);
        $sup = $wbs->create(['project_id' => $project->getKey(), 'wbs_code' => '2.0', 'name' => 'Tedarik', 'sort_order' => 2]);
        $wbs->create(['project_id' => $project->getKey(), 'parent_id' => $sup->getKey(), 'wbs_code' => '2.1', 'name' => 'PV Panel Tedariki', 'sort_order' => 1]);
        $wbs->create(['project_id' => $project->getKey(), 'parent_id' => $sup->getKey(), 'wbs_code' => '2.2', 'name' => 'İnvertör ve Trafo Tedariki', 'sort_order' => 2]);
        $site = $wbs->create(['project_id' => $project->getKey(), 'wbs_code' => '3.0', 'name' => 'Saha İşleri', 'sort_order' => 3]);
        $mount = $wbs->create(['project_id' => $project->getKey(), 'parent_id' => $site->getKey(), 'wbs_code' => '3.1', 'name' => 'Panel Montajı', 'sort_order' => 1]);

        $cbs = app(CbsNodeService::class);
        $mat = $cbs->create(['project_id' => $project->getKey(), 'cost_code' => 'MAT', 'name' => 'Malzeme', 'cost_category' => 'material']);
        $lab = $cbs->create(['project_id' => $project->getKey(), 'cost_code' => 'LAB', 'name' => 'İşçilik', 'cost_category' => 'labor']);
        $engc = $cbs->create(['project_id' => $project->getKey(), 'cost_code' => 'ENG', 'name' => 'Mühendislik', 'cost_category' => 'engineering']);

        $mappings = app(WbsCbsMappingService::class);
        $mappings->create(['wbs_node_id' => $eng->getKey(), 'cbs_node_id' => $engc->getKey(), 'allocation_pct' => 100]);
        $mappings->create(['wbs_node_id' => $mount->getKey(), 'cbs_node_id' => $mat->getKey(), 'allocation_pct' => 70]);
        $mappings->create(['wbs_node_id' => $mount->getKey(), 'cbs_node_id' => $lab->getKey(), 'allocation_pct' => 30]);

        $packages = app(WorkPackageService::class);
        $packages->create(['project_workstream_id' => $projectWs->getKey(), 'package_code' => 'WP-ENG-01', 'name' => 'Temel tasarım paketi', 'wbs_node_id' => $eng->getKey(), 'status' => 'active', 'planned_start_on' => Carbon::now()->toDateString(), 'planned_finish_on' => Carbon::now()->addMonths(2)->toDateString()]);
        $packages->create(['project_workstream_id' => $workstreams['FIELD']->getKey(), 'package_code' => 'WP-FLD-01', 'name' => 'A Bloğu panel montajı', 'wbs_node_id' => $mount->getKey(), 'status' => 'planned', 'planned_start_on' => Carbon::now()->addMonths(6)->toDateString(), 'planned_finish_on' => Carbon::now()->addMonths(9)->toDateString()]);

        $baseline = app(ScheduleBaselineService::class)->create([
            'project_id' => $project->getKey(),
            'name' => 'Sözleşme baseline takvimi',
            'source' => 'manual',
            'planned_start_on' => Carbon::now()->addDays(30)->toDateString(),
            'planned_finish_on' => Carbon::now()->addMonths(14)->toDateString(),
        ]);
        app(ScheduleBaselineService::class)->approve($baseline);

        $contractMilestones = $project->businessCase->contracts()->first()?->currentVersion?->milestones()->pluck('id', 'milestone_code') ?? collect();
        $milestones = app(MilestoneService::class);
        $milestones->create(['project_id' => $project->getKey(), 'milestone_code' => 'NTP', 'name' => 'İşe başlama', 'milestone_kind' => 'contractual', 'contract_milestone_id' => $contractMilestones['M1-NTP'] ?? null, 'planned_at' => Carbon::now('UTC')->addDays(30), 'status' => 'planned']);
        $milestones->create(['project_id' => $project->getKey(), 'milestone_code' => 'MC', 'name' => 'Mekanik tamamlanma', 'milestone_kind' => 'payment', 'wbs_node_id' => $site->getKey(), 'contract_milestone_id' => $contractMilestones['M2-MC'] ?? null, 'planned_at' => Carbon::now('UTC')->addMonths(10), 'status' => 'planned']);

        app(ProgressSnapshotService::class)->create(['project_id' => $project->getKey(), 'physical_progress_pct' => 2.5, 'planned_progress_pct' => 3, 'source' => 'manual']);

        app(ProjectIssueService::class)->create(['project_id' => $project->getKey(), 'workstream_id' => $projectWs->getKey(), 'title' => 'Şebeke bağlantı izni gecikiyor', 'description' => 'TEİAŞ bağlantı görüşü henüz alınmadı; müşteri sorumluluğunda.', 'severity' => 'high', 'due_at' => Carbon::now('UTC')->addDays(14)]);

        $risks = app(ProjectRiskService::class);
        $risks->create(['project_id' => $project->getKey(), 'title' => 'Panel teslimatında kur riski', 'description' => 'USD bazlı panel fiyatı; kur artışı marjı eritir.', 'category' => 'financial', 'probability' => 0.4, 'impact' => 4, 'response_strategy' => 'transfer', 'mitigation_plan' => 'Forward kur anlaşması', 'status' => 'assessed', 'review_due_on' => Carbon::now()->addMonth()->toDateString()]);
        $risks->create(['project_id' => $project->getKey(), 'workstream_id' => $workstreams['FIELD']->getKey(), 'title' => 'Kış aylarında saha erişimi', 'description' => 'Kar nedeniyle Aralık-Şubat saha verimi düşer.', 'category' => 'schedule', 'probability' => 0.6, 'impact' => 3, 'response_strategy' => 'mitigate', 'mitigation_plan' => 'Montajı Ekim öncesine planla', 'status' => 'identified']);

        $delay = app(DelayEventService::class)->create(['project_id' => $project->getKey(), 'workstream_id' => $projectWs->getKey(), 'delay_days' => 5, 'cause_category' => 'customer', 'description' => 'Saha teslimi 5 gün gecikti.', 'is_excusable' => true, 'status' => 'open']);
        app(RecoveryActionService::class)->create(['delay_event_id' => $delay->getKey(), 'owner_personnel_id' => $adminId, 'description' => 'Mühendislik paralel çalışmayla telafi', 'expected_recovery_days' => 5, 'due_at' => Carbon::now('UTC')->addDays(20), 'status' => 'planned']);

        app(ProjectChangeService::class)->create(['project_id' => $project->getKey(), 'change_type' => 'scope', 'title' => 'Ek 5 MW BESS talebi', 'description' => 'Müşteri 5 MW/10 MWh batarya depolama eklenmesini istiyor.', 'impact_cost' => 120000000, 'impact_days' => 45, 'affects_baseline' => true, 'status' => 'evaluating']);
        app(CommercialClarificationService::class)->create(['project_id' => $project->getKey(), 'clarification_type' => 'scope_interpretation', 'title' => 'Çit ve aydınlatma kapsamı', 'description' => 'Saha çevre çiti ve aydınlatma EPC kapsamında mı?', 'status' => 'open']);
        app(CommercialExposureService::class)->create(['project_id' => $project->getKey(), 'cbs_node_id' => $mat->getKey(), 'exposure_kind' => 'claim', 'description' => 'Saha teslim gecikmesi için süre uzatımı talebi', 'exposure_amount' => 2500000, 'probability' => 0.5, 'status' => 'identified']);
        app(ProjectDecisionService::class)->create(['project_id' => $project->getKey(), 'decision_scope' => 'technical', 'title' => 'String invertör tercihi', 'description' => 'Merkezi invertör yerine 1,5 MW string invertör ile devam edilecek.']);

        $this->seedFirstGate($project, $adminId);
    }

    private function seedFirstGate(Project $project, int $adminId): void
    {
        $instances = app(ProjectStageInstanceService::class);
        $g0 = $project->stageInstances()->whereHas('stageNode', fn ($q) => $q->where('stage_code', 'G0'))->first();

        if ($g0 === null) {
            return;
        }

        $instances->changeStatus($g0, StageInstanceStatus::Preparing);

        $revisionIds = $this->documentRevisionIds(['ELE-00001']);
        $evidenceService = app(StageEvidenceService::class);

        foreach ($g0->requirements()->get() as $requirement) {
            if ($requirement->requirement_code_snapshot === 'BASELINE_SCHEDULE' && isset($revisionIds['ELE-00001'])) {
                $evidence = $evidenceService->create(['project_stage_requirement_id' => $requirement->getKey(), 'document_revision_id' => $revisionIds['ELE-00001']]);
                $evidenceService->accept($evidence);
            }

            if ($requirement->requirement_code_snapshot === 'HANDOFF_ACCEPTED') {
                $requirement->forceFill(['status' => 'accepted', 'outcome_note' => 'Operasyona devir kabul kaydı mevcut.'])->save();
            }
        }

        $instances->changeStatus($g0, StageInstanceStatus::ReadyForReview);

        app(StageReviewService::class)->create([
            'project_stage_instance_id' => $g0->getKey(),
            'decision' => StageReviewDecision::Passed,
            'comment' => 'Devir kabulü ve baseline takvim doğrulandı.',
        ]);
    }

    private function linkDocuments(Project $project, Party $customer): void
    {
        Document::query()
            ->where('title', 'like', '%Karapınar%')
            ->update(['project_id' => $project->getKey()]);

        Transmittal::query()->update(['project_id' => $project->getKey(), 'recipient_party_id' => $customer->getKey()]);
    }

    /**
     * Calisma alani ornek verisi (D-68): tam saha adresi, tedarik kalemleri
     * (satin alma / lojistik / yazilim adimlari), saha ekibi ve temsili bir
     * saha fotografi (GD ile uretilir; kapak gorseli olur).
     */
    private function seedWorkspaceData(Project $project, Party $customer, int $adminId): void
    {
        app(ProjectService::class)->update($project, [
            'description' => '500 MWp güneş enerjisi santrali için anahtar teslim EPC: PV saha, OG/YG şalt, SCADA ve devreye alma. Sözleşme değeri sabit fiyat; 14 aylık takvim.',
            'site_address_line1' => 'Karapınar Enerji İhtisas Endüstri Bölgesi, 1. Cadde No: 12',
            'site_district' => 'Karapınar',
            'site_city' => 'Konya',
            'site_postal_code' => '42400',
            'site_country_code' => 'TR',
            'site_latitude' => 37.7145,
            'site_longitude' => 33.5502,
            'site_note' => 'Saha girişi güney kapıdan; ağır vasıta için 08:00-18:00 arası izin.',
        ]);

        $uomIds = UnitOfMeasure::query()->pluck('id', 'code');
        $piece = (int) ($uomIds['PCS'] ?? $uomIds['EA'] ?? $uomIds->first());
        $supply = app(ProjectSupplyItemService::class);
        $projectId = (int) $project->getKey();

        $supply->create(['project_id' => $projectId, 'item_kind' => 'product', 'item_code' => 'PV-550', 'name' => 'PV panel 550 Wp (bifacial)', 'specification' => 'Tier-1, 25 yıl ürün garantisi', 'quantity' => 909000, 'uom_id' => $piece, 'unit_cost' => 2100, 'supplier_party_id' => $customer->getKey(), 'needed_on' => Carbon::now()->addMonths(6)->toDateString(), 'expected_delivery_on' => Carbon::now()->addMonths(6)->toDateString(), 'status' => 'ordered']);
        $supply->create(['project_id' => $projectId, 'item_kind' => 'product', 'item_code' => 'INV-1500', 'name' => 'String invertör 1,5 MW', 'quantity' => 42, 'uom_id' => $piece, 'unit_cost' => 4200000, 'needed_on' => Carbon::now()->addMonths(7)->toDateString(), 'status' => 'requested']);
        $supply->create(['project_id' => $projectId, 'item_kind' => 'product', 'item_code' => 'TRF-154', 'name' => '154/33 kV güç trafosu', 'quantity' => 2, 'uom_id' => $piece, 'unit_cost' => 85000000, 'needed_on' => Carbon::now()->addMonths(5)->toDateString(), 'status' => 'delivered']);
        $supply->create(['project_id' => $projectId, 'item_kind' => 'service', 'item_code' => 'SRV-CIVIL', 'name' => 'Saha düzenleme ve inşaat hizmeti', 'quantity' => 1, 'uom_id' => $piece, 'unit_cost' => 380000000, 'needed_on' => Carbon::now()->addMonths(2)->toDateString(), 'status' => 'planned']);
        $supply->create(['project_id' => $projectId, 'item_kind' => 'software', 'item_code' => 'SCADA-LIC', 'name' => 'SCADA yazılım lisansı ve PLC programlama', 'specification' => 'IEC 61850 uyumlu; 5 yıl bakım', 'quantity' => 1, 'uom_id' => $piece, 'unit_cost' => 12000000, 'needed_on' => Carbon::now()->addMonths(11)->toDateString(), 'status' => 'planned']);

        app(ProjectTeamMemberService::class)->create([
            'project_id' => $projectId,
            'personnel_id' => $adminId,
            'team_role' => 'project_manager',
            'allocation_pct' => 50,
            'is_lead' => true,
            'note' => 'Proje yöneticisi; saha adımında şantiye şefi atanacak.',
        ]);

        $this->seedPhoto($project);
    }

    /** Temsili saha fotografi: GD yoksa atlanir. */
    private function seedPhoto(Project $project): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $width = 960;
        $height = 540;
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < 320; $y++) {
            $shade = (int) (150 + ($y / 320) * 80);
            imageline($image, 0, $y, $width, $y, imagecolorallocate($image, 90, $shade, 230));
        }

        imagefilledrectangle($image, 0, 320, $width, $height, imagecolorallocate($image, 176, 141, 87));

        $panel = imagecolorallocate($image, 20, 40, 90);
        $frame = imagecolorallocate($image, 220, 220, 220);

        for ($row = 0; $row < 4; $row++) {
            for ($col = 0; $col < 9; $col++) {
                $x = 40 + $col * 100;
                $y = 340 + $row * 48;
                imagefilledrectangle($image, $x, $y, $x + 88, $y + 36, $panel);
                imagerectangle($image, $x, $y, $x + 88, $y + 36, $frame);
            }
        }

        imagestring($image, 5, 30, 20, 'Karapinar 500 MW GES - saha genel gorunum (temsili)', imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $tempPath = 'document-uploads-tmp/karapinar-saha-genel.png';
        Storage::disk('local')->put($tempPath, $bytes);

        app(ProjectPhotoService::class)->create([
            'project_id' => $project->getKey(),
            'file_temp_path' => $tempPath,
            'file_original_name' => 'karapinar-saha-genel.png',
            'caption' => 'Saha genel görünüm (temsili görsel)',
            'taken_on' => Carbon::now()->toDateString(),
            'is_cover' => true,
        ]);
    }

    /**
     * @param  list<string>  $documentNos
     * @return array<string, int>
     */
    private function documentRevisionIds(array $documentNos): array
    {
        return DocumentRevision::query()
            ->join('documents', 'documents.id', '=', 'document_revisions.document_id')
            ->whereIn('documents.document_no', $documentNos)
            ->orderBy('document_revisions.revision_no')
            ->get(['documents.document_no', 'document_revisions.id'])
            ->pluck('id', 'document_no')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
