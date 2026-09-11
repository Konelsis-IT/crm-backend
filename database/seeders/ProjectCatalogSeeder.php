<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\TenderAccessMode;
use App\Enums\Acquisition\TenderSourceType;
use App\Enums\Project\EvidenceType;
use App\Enums\Project\StageTemplateProjectType;
use App\Enums\Shared\ActiveStatus;
use App\Models\Acquisition\TenderSource;
use App\Models\Personnel\Personnel;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\FocusExpectation;
use App\Models\Project\OperationGroupDefinition;
use App\Models\Project\StageTemplate;
use App\Services\Audit\ActorContext;
use App\Services\Project\StageDependencyService;
use App\Services\Project\StageNodeService;
use App\Services\Project\StageRequirementDefinitionService;
use App\Services\Project\StageTemplateService;
use App\Services\Project\StageTemplateVersionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * B17/B16 kataloglari (docs/planning/16 B24; D-20, D-22): operasyon
 * gruplari (alti workstream, varsayilan sira), proje bilesenleri, ihale
 * kaynaklari ve 'generic' G0-G7 stage-gate sablonunun yayimli ilk surumu.
 * GES/HES/RES varyantlari sirket verisiyle sonra eklenir (D-20 acik).
 */
class ProjectCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('operation_group_definitions') || ! Schema::hasTable('stage_templates')) {
            $this->command?->warn('B16/B17 tablolari yok; proje katalogu atlandi.');

            return;
        }

        $this->seedOperationGroups();
        $this->seedComponents();
        $this->seedTenderSources();
        $this->seedGenericStageTemplate();

        if (Schema::hasTable('focus_expectations')) {
            $this->seedFocusExpectations();
        }
    }

    /**
     * Her adimin (operasyon grubunun) projeden bekledigi veriler (D-68).
     * Calisma alani kontrol listesi ve odak gecis guard'i buradan okur.
     */
    private function seedFocusExpectations(): void
    {
        $rows = [
            // grup, kod, tur, en az, zorunlu, ad TR, ad EN, yardim TR
            ['PROJECT', 'SITE_ADDRESS', 'site_address', 1, true, 'Saha adresi girildi', 'Site address entered', 'Tam adres (satır 1 ve il) proje kartında doldurulmalı.'],
            ['PROJECT', 'PLANNED_DATES', 'planned_dates', 1, true, 'Plan tarihleri belirlendi', 'Planned dates set', 'Planlanan başlangıç ve bitiş tarihleri girilmeli.'],
            ['PROJECT', 'COMPONENTS', 'components', 1, true, 'Proje bileşeni tanımlandı', 'Project component defined', 'En az bir bileşen (GES, SCADA, OG şalt...) kapsamda olmalı.'],
            ['PROJECT', 'DRAWINGS', 'drawings', 1, true, 'Çizim / plan dokümanı eklendi', 'Drawing or plan document added', 'Elektrik, otomasyon, mühendislik veya inşaat disiplininde en az bir doküman.'],
            ['PROJECT', 'WBS', 'wbs', 3, true, 'İş kırılımı hazır', 'WBS breakdown ready', 'En az üç iş kırılımı kalemi.'],
            ['PROJECT', 'MILESTONES', 'milestones', 1, true, 'Önemli tarih tanımlandı', 'Milestone defined', null],
            ['PROJECT', 'BASELINE', 'schedule_baseline', 1, true, 'Onaylı referans takvim', 'Approved schedule baseline', 'Onaylanmış bir referans takvim sürümü.'],
            ['PROJECT', 'PHOTOS', 'photos', 1, false, 'Saha fotoğrafı', 'Site photo', null],
            ['PROCUREMENT', 'SUPPLY_ITEMS', 'supply_items', 1, true, 'Tedarik kalemleri listelendi', 'Supply items listed', 'Ürün, hizmet ve yazılım kalemleri girilmeli.'],
            ['PROCUREMENT', 'SUPPLY_ORDERED', 'supply_ordered', 1, true, 'Sipariş verildi', 'Orders placed', 'En az bir kalem sipariş verilmiş durumda.'],
            ['ACCOUNTING', 'CBS', 'cbs', 1, true, 'Maliyet kırılımı', 'CBS cost breakdown', null],
            ['ACCOUNTING', 'EXPOSURES', 'exposures', 1, false, 'Ticari maruziyet kaydı', 'Commercial exposure record', null],
            ['LOGISTICS', 'SUPPLY_DELIVERED', 'supply_delivered', 1, true, 'Sahaya teslim edilen kalem', 'Item delivered to site', 'En az bir kalem teslim edilmiş veya montajı yapılmış.'],
            ['FIELD', 'TEAM', 'team_members', 1, true, 'Saha ekibi atandı', 'Site team assigned', null],
            ['FIELD', 'WORK_PACKAGES', 'work_packages', 1, true, 'İş paketleri tanımlandı', 'Work packages defined', null],
            ['FIELD', 'PROGRESS', 'progress', 1, true, 'İlerleme kaydı girildi', 'Progress recorded', null],
            ['FIELD', 'PHOTOS', 'photos', 1, false, 'Saha fotoğrafı', 'Site photo', null],
            ['SOFTWARE', 'SOFTWARE_ITEMS', 'software_items', 1, true, 'Yazılım / otomasyon kalemi', 'Software / automation item', 'SCADA, PLC, lisans vb. kalemler tedarik listesinde "yazılım" türüyle girilir.'],
            ['SOFTWARE', 'AUTOMATION_DOCS', 'automation_documents', 1, true, 'SCADA / PLC dokümanı', 'SCADA / PLC document', null],
        ];

        $groups = OperationGroupDefinition::query()->pluck('id', 'code');
        $order = [];

        foreach ($rows as [$groupCode, $code, $kind, $min, $mandatory, $nameTr, $nameEn, $helpTr]) {
            $groupId = $groups[$groupCode] ?? null;

            if ($groupId === null) {
                continue;
            }

            $order[$groupCode] = ($order[$groupCode] ?? 0) + 1;

            FocusExpectation::query()->firstOrCreate(
                ['group_definition_id' => $groupId, 'code' => $code],
                [
                    'name_tr' => $nameTr,
                    'name_en' => $nameEn,
                    'kind' => $kind,
                    'min_count' => $min,
                    'is_mandatory' => $mandatory,
                    'help_tr' => $helpTr,
                    'help_en' => null,
                    'sort_order' => $order[$groupCode] * 10,
                    'status' => ActiveStatus::Active,
                ],
            );
        }
    }

    private function seedOperationGroups(): void
    {
        $groups = [
            ['PROJECT', 'Proje', 'Project', 1],
            ['PROCUREMENT', 'Satın Alma', 'Procurement', 2],
            ['ACCOUNTING', 'Muhasebe', 'Accounting', 3],
            ['LOGISTICS', 'Lojistik', 'Logistics', 4],
            ['FIELD', 'Saha', 'Field', 5],
            ['SOFTWARE', 'Yazılım / Teknik', 'Software / Technical', 6],
        ];

        foreach ($groups as [$code, $tr, $en, $order]) {
            OperationGroupDefinition::query()->firstOrCreate(
                ['code' => $code],
                ['name_tr' => $tr, 'name_en' => $en, 'default_sort_order' => $order, 'status' => ActiveStatus::Active],
            );
        }
    }

    private function seedComponents(): void
    {
        $components = [
            ['GES', 'Güneş Enerjisi Santrali', 'Solar power plant', 'electrical'],
            ['HES', 'Hidroelektrik Santral', 'Hydroelectric plant', 'civil'],
            ['RES', 'Rüzgâr Enerjisi Santrali', 'Wind power plant', 'electrical'],
            ['BESS', 'Batarya Enerji Depolama', 'Battery energy storage', 'electrical'],
            ['EMS', 'Enerji Yönetim Sistemi', 'Energy management system', 'automation'],
            ['ENH', 'Enerji Nakil Hattı', 'Transmission line', 'electrical'],
            ['SUBSTATION', 'Şalt Sahası / Trafo Merkezi', 'Substation', 'electrical'],
            ['SCADA_DCS', 'SCADA / DCS', 'SCADA / DCS', 'automation'],
            ['PLC_RTU', 'PLC / RTU', 'PLC / RTU', 'automation'],
            ['CIVIL', 'İnşaat İşleri', 'Civil works', 'civil'],
            ['MECHANICAL', 'Mekanik İşler', 'Mechanical works', 'mechanical'],
            ['AUTOMATION', 'Otomasyon', 'Automation', 'automation'],
            ['FIBER_CCTV', 'Fiber / CCTV', 'Fiber / CCTV', 'automation'],
        ];

        foreach ($components as [$code, $tr, $en, $discipline]) {
            ComponentDefinition::query()->firstOrCreate(
                ['code' => $code],
                ['name_tr' => $tr, 'name_en' => $en, 'discipline' => $discipline, 'status' => ActiveStatus::Active],
            );
        }
    }

    private function seedTenderSources(): void
    {
        $sources = [
            ['EKAP', 'EKAP (Kamu İhale Kurumu)', 'EKAP (Public Procurement Authority)', TenderSourceType::PublicProcurement, TenderAccessMode::Manual],
            ['WORLD_BANK', 'Dünya Bankası', 'World Bank', TenderSourceType::InternationalFinance, TenderAccessMode::Email],
            ['ILLER_BANKASI', 'İller Bankası', 'Provincial Bank', TenderSourceType::ProvincialBank, TenderAccessMode::Manual],
            ['PRIVATE', 'Özel davet / doğrudan talep', 'Private invitation / direct request', TenderSourceType::PrivateInvitation, TenderAccessMode::Email],
        ];

        foreach ($sources as [$code, $tr, $en, $type, $mode]) {
            TenderSource::query()->firstOrCreate(
                ['code' => $code],
                ['name_tr' => $tr, 'name_en' => $en, 'source_type' => $type, 'access_mode' => $mode, 'scraping_allowed' => false, 'status' => ActiveStatus::Active],
            );
        }
    }

    private function seedGenericStageTemplate(): void
    {
        if (StageTemplate::query()->where('code', 'GENERIC-G')->exists()) {
            return;
        }

        $admin = Personnel::query()
            ->where('normalized_email', Personnel::normalizeEmail((string) config('konelsis.bootstrap_admin.email')))
            ->first();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $template = app(StageTemplateService::class)->create([
            'code' => 'GENERIC-G',
            'name_tr' => 'Genel EPC stage-gate şablonu (G0–G7)',
            'name_en' => 'Generic EPC stage-gate template (G0–G7)',
            'project_type' => StageTemplateProjectType::Generic->value,
        ]);

        $version = app(StageTemplateVersionService::class)->create([
            'stage_template_id' => $template->getKey(),
            'change_summary' => 'İlk yayım: devirden kesin kabule sekiz gate.',
        ]);

        $groups = OperationGroupDefinition::query()->pluck('id', 'code');

        $nodes = [
            ['G0', 'Devir Kabulü', 'Handoff acceptance', 'PROJECT', [
                ['HANDOFF_ACCEPTED', 'Operasyona devir kabul edildi', 'Operation handoff accepted', EvidenceType::Handoff, true],
                ['BASELINE_SCHEDULE', 'Referans takvim yüklendi', 'Baseline schedule uploaded', EvidenceType::Document, true],
            ]],
            ['G1', 'Mühendislik Tasarım Onayı', 'Engineering design approval', 'PROJECT', [
                ['DESIGN_PACKAGE', 'Tasarım paketi (tek hat, yerleşim) onaylı', 'Design package approved', EvidenceType::Document, true],
                ['TECH_REQ_REVIEW', 'Teknik gereksinim incelemesi', 'Technical requirement review', EvidenceType::Checklist, false],
            ]],
            ['G2', 'Tedarik Onayı', 'Procurement approval', 'PROCUREMENT', [
                ['PO_ISSUED', 'Kritik ekipman siparişleri verildi', 'Critical equipment POs issued', EvidenceType::Checklist, true],
            ]],
            ['G3', 'Saha Mobilizasyonu', 'Site mobilisation', 'FIELD', [
                ['HSE_PLAN', 'İSG planı ve saha izinleri', 'HSE plan and site permits', EvidenceType::Document, true],
            ]],
            ['G4', 'Mekanik Tamamlanma', 'Mechanical completion', 'FIELD', [
                ['MC_CERT', 'Mekanik tamamlanma sertifikası', 'Mechanical completion certificate', EvidenceType::Document, true],
            ]],
            ['G5', 'Devreye Alma', 'Commissioning', 'SOFTWARE', [
                ['COMMISSIONING_REPORT', 'Devreye alma raporu', 'Commissioning report', EvidenceType::Document, true],
            ]],
            ['G6', 'Geçici Kabul', 'Provisional acceptance', 'PROJECT', [
                ['PAC', 'Geçici kabul tutanağı', 'Provisional acceptance certificate', EvidenceType::Approval, true],
            ]],
            ['G7', 'Kesin Kabul', 'Final acceptance', 'PROJECT', [
                ['FAC', 'Kesin kabul tutanağı', 'Final acceptance certificate', EvidenceType::Approval, true],
            ]],
        ];

        $nodeService = app(StageNodeService::class);
        $requirementService = app(StageRequirementDefinitionService::class);
        $dependencyService = app(StageDependencyService::class);
        $previous = null;

        foreach ($nodes as $index => [$code, $tr, $en, $group, $requirements]) {
            $node = $nodeService->create([
                'stage_template_version_id' => $version->getKey(),
                'stage_code' => $code,
                'name_tr' => $tr,
                'name_en' => $en,
                'sequence_no' => $index,
                'is_hard_gate' => true,
                'owner_group_definition_id' => $groups[$group] ?? null,
            ]);

            foreach ($requirements as $order => [$reqCode, $reqTr, $reqEn, $evidence, $mandatory]) {
                $requirementService->create([
                    'stage_node_id' => $node->getKey(),
                    'requirement_code' => $reqCode,
                    'name_tr' => $reqTr,
                    'name_en' => $reqEn,
                    'evidence_type' => $evidence->value,
                    'is_mandatory' => $mandatory,
                    'sort_order' => $order,
                ]);
            }

            if ($previous !== null) {
                $dependencyService->create([
                    'predecessor_node_id' => $previous->getKey(),
                    'successor_node_id' => $node->getKey(),
                    'is_hard' => true,
                ]);
            }

            $previous = $node;
        }

        app(StageTemplateVersionService::class)->publish($version);
    }
}
