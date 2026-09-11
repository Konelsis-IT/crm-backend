<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Document\AcknowledgementKind;
use App\Enums\Document\DistributionKind;
use App\Enums\Document\DocumentDiscipline;
use App\Enums\Document\DocumentReviewDecision;
use App\Enums\Document\DocumentReviewType;
use App\Enums\Document\DocumentTemplateOutputKind;
use App\Enums\Document\LegalHoldStatus;
use App\Enums\Document\RevisionPurpose;
use App\Enums\Document\RevisionStatus;
use App\Enums\Document\TransmittalPurpose;
use App\Enums\Reference\ClassificationCode;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Reference\RetentionPolicy;
use App\Models\Reference\SecurityClassification;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentAcknowledgementService;
use App\Services\Document\DocumentDistributionService;
use App\Services\Document\DocumentReviewService;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\DocumentService;
use App\Services\Document\DocumentTemplateService;
use App\Services\Document\DocumentTemplateVersionService;
use App\Services\Document\DocumentTypeService;
use App\Services\Document\LegalHoldDocumentService;
use App\Services\Document\LegalHoldService;
use App\Services\Document\TransmittalItemService;
use App\Services\Document\TransmittalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Sektöre (yenilenebilir enerji EPC) uygun örnek DMS verisi.
 *
 * Kullanıcı talimatıyla eklendi: migrate:fresh --seed sonrasında sistem
 * bomboş kalmasın, DMS ekranları gerçekçi örnek verilerle gösterilebilsin.
 * Tüm kayıtlar bootstrap yönetici personeline (KONELSIS_BOOTSTRAP_ADMIN_EMAIL,
 * varsayılan omer@gmail.com) bağlıdır; bu yüzden PersonnelBootstrapSeeder'dan
 * sonra çalışmalıdır (bkz. DatabaseSeeder). Kurgusal proje/şirket adları
 * kullanılır, gerçek bir müşteri veya sözleşmeyi temsil etmez.
 */
class DocumentSampleDataSeeder extends Seeder
{
    private const FIXTURES = __DIR__.'/fixtures/documents';

    public function run(): void
    {
        $admin = Personnel::query()
            ->where('normalized_email', Personnel::normalizeEmail((string) config('konelsis.bootstrap_admin.email')))
            ->first();

        if ($admin === null) {
            $this->command?->warn('Bootstrap yonetici personel bulunamadi; ornek DMS verisi atlandi.');

            return;
        }

        if (Document::query()->exists()) {
            return;
        }

        $internalId = (int) SecurityClassification::query()->where('code', ClassificationCode::Internal)->value('id');
        $confidentialId = (int) SecurityClassification::query()->where('code', ClassificationCode::Confidential)->value('id');
        $retentionId = (int) RetentionPolicy::query()->where('code', 'RET-DOC-CONTROLLED')->value('id');

        if ($internalId === 0 || $confidentialId === 0 || $retentionId === 0) {
            $this->command?->warn('Guvenlik sinifi/saklama politikasi referans verisi bulunamadi; ornek DMS verisi atlandi.');

            return;
        }

        app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());

        $orgUnits = OrgUnit::query()->whereIn('code', ['PROJE', 'YAZILIM', 'SAHA', 'YONETIM', 'TEKLIF'])->pluck('id', 'code');

        $types = $this->seedDocumentTypes($internalId, $confidentialId, $retentionId);
        $documents = $this->seedDocuments($types, $orgUnits, (int) $admin->getKey());

        $this->seedReviews($documents, (int) $admin->getKey());
        $this->seedDistributionsAndAcknowledgements($documents, (int) $admin->getKey());
        $this->seedTransmittal($documents, (int) $admin->getKey());
        $this->seedTemplate((int) $admin->getKey());
        $this->seedLegalHold($documents, (int) $admin->getKey());
    }

    /**
     * @return array<string, \App\Models\Document\DocumentType>
     */
    private function seedDocumentTypes(int $internalId, int $confidentialId, int $retentionId): array
    {
        $service = app(DocumentTypeService::class);

        $rows = [
            ['ELE', 'Elektrik Tek Hat Şeması', DocumentDiscipline::Electrical, $internalId],
            ['OTM', 'SCADA/PLC Dokümantasyonu', DocumentDiscipline::Automation, $internalId],
            ['DVR', 'Devreye Alma Prosedürü', DocumentDiscipline::Engineering, $internalId],
            ['KAL', 'Kalite Kontrol Planı', DocumentDiscipline::Quality, $internalId],
            ['ISG', 'İş Sağlığı ve Güvenliği Talimatı', DocumentDiscipline::Hse, $internalId],
            ['TEK', 'Ticari Teklif Dosyası', DocumentDiscipline::Commercial, $confidentialId],
            ['SOZ', 'Sözleşme', DocumentDiscipline::Legal, $confidentialId],
        ];

        $types = [];

        foreach ($rows as [$code, $name, $discipline, $classificationId]) {
            $types[$code] = $service->create([
                'code' => $code,
                'name' => $name,
                'discipline' => $discipline->value,
                'numbering_prefix' => $code,
                'is_controlled' => true,
                'default_classification_id' => $classificationId,
                'default_retention_policy_id' => $retentionId,
                'status' => 'active',
            ]);
        }

        return $types;
    }

    /**
     * @param  array<string, \App\Models\Document\DocumentType>  $types
     * @param  \Illuminate\Support\Collection<string, int>  $orgUnits
     * @return array<string, array{document: Document, revision: DocumentRevision}>
     */
    private function seedDocuments(array $types, $orgUnits, int $adminId): array
    {
        $specs = [
            ['ELE', 'Karapınar GES Sahası Elektrik Tek Hat Şeması Açıklama Notu', 'PROJE', RevisionStatus::Issued, 'ele-tek-hat-semasi.txt'],
            ['OTM', 'Karapınar GES SCADA/PLC Entegrasyon Dokümantasyonu', 'YAZILIM', RevisionStatus::Issued, 'scada-plc-entegrasyon.txt'],
            ['DVR', 'A Bloğu İnvertör Devreye Alma Prosedürü', 'SAHA', RevisionStatus::Approved, 'devreye-alma-prosedur.txt'],
            ['KAL', 'Panel Montaj Kalite Kontrol Planı', 'SAHA', RevisionStatus::InReview, 'kalite-kontrol-plani.txt'],
            ['ISG', 'Yüksek Gerilim Sahalarında İş Sağlığı ve Güvenliği Talimatı', 'YONETIM', RevisionStatus::Issued, 'isg-talimati.txt'],
            ['TEK', '500 MW Karapınar GES Projesi Ticari Teklifi', 'TEKLIF', RevisionStatus::Issued, 'ticari-teklif.txt'],
            ['SOZ', 'Karapınar GES Projesi EPC Sözleşmesi', 'YONETIM', RevisionStatus::Issued, 'epc-sozlesme.txt'],
        ];

        $documentService = app(DocumentService::class);
        $revisionService = app(DocumentRevisionService::class);
        $results = [];

        foreach ($specs as [$typeCode, $title, $orgUnitCode, $targetStatus, $fixtureFile]) {
            /** @var Document $document */
            $document = $documentService->create([
                'title' => $title,
                'document_type_id' => (int) $types[$typeCode]->getKey(),
                'owner_personnel_id' => $adminId,
                'owner_org_unit_id' => $orgUnits[$orgUnitCode] ?? null,
                'default_language' => 'tr',
                'status' => 'draft',
            ]);

            $tempPath = 'document-uploads-tmp/'.$fixtureFile;
            Storage::disk('local')->put($tempPath, (string) file_get_contents(self::FIXTURES.'/'.$fixtureFile));

            /** @var DocumentRevision $revision */
            $revision = $revisionService->create([
                'document_id' => $document->getKey(),
                'language' => 'tr',
                'title' => $title,
                'purpose' => RevisionPurpose::ForApproval->value,
                'change_summary' => 'İlk yayım.',
                'file_temp_path' => $tempPath,
                'file_original_name' => $fixtureFile,
            ]);

            foreach ($this->transitionPath($targetStatus) as $step) {
                $revision = $revisionService->changeStatus($revision, $step);
            }

            $results[$typeCode] = ['document' => $document->fresh(), 'revision' => $revision];
        }

        return $results;
    }

    /**
     * @return list<RevisionStatus>
     */
    private function transitionPath(RevisionStatus $target): array
    {
        return match ($target) {
            RevisionStatus::InReview => [RevisionStatus::InReview],
            RevisionStatus::Approved => [RevisionStatus::InReview, RevisionStatus::Approved],
            RevisionStatus::Issued => [RevisionStatus::InReview, RevisionStatus::Approved, RevisionStatus::Issued],
            default => [],
        };
    }

    /**
     * @param  array<string, array{document: Document, revision: DocumentRevision}>  $documents
     */
    private function seedReviews(array $documents, int $adminId): void
    {
        $service = app(DocumentReviewService::class);
        $now = Carbon::now('UTC');

        $reviews = [
            ['ELE', DocumentReviewType::Approve, DocumentReviewDecision::Approved, 'Şema, saha yerleşim planıyla uyumlu; onaylandı.'],
            ['OTM', DocumentReviewType::Check, DocumentReviewDecision::Approved, 'Veri noktaları SCADA test ortamında doğrulandı.'],
            ['DVR', DocumentReviewType::Check, DocumentReviewDecision::ApprovedWithComments, 'Yük testi süresi 30 dakikadan 45 dakikaya çıkarılmalı; küçük not.'],
            ['KAL', DocumentReviewType::Check, DocumentReviewDecision::ApprovedWithComments, 'Saha kalite sorumlusunun nihai onayı bekleniyor.'],
            ['ISG', DocumentReviewType::Approve, DocumentReviewDecision::Approved, 'İSG talimatı yürürlüğe alınabilir.'],
            ['TEK', DocumentReviewType::Approve, DocumentReviewDecision::Approved, 'Ticari şartlar yönetim tarafından onaylandı.'],
            ['SOZ', DocumentReviewType::Approve, DocumentReviewDecision::Approved, 'Hukuk departmanı sözleşme metnini onayladı.'],
        ];

        foreach ($reviews as [$typeCode, $reviewType, $decision, $comment]) {
            $service->create([
                'document_revision_id' => $documents[$typeCode]['revision']->getKey(),
                'reviewer_personnel_id' => $adminId,
                'review_type' => $reviewType->value,
                'decision' => $decision->value,
                'comment' => $comment,
                'decided_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, array{document: Document, revision: DocumentRevision}>  $documents
     */
    private function seedDistributionsAndAcknowledgements(array $documents, int $adminId): void
    {
        $distributionService = app(DocumentDistributionService::class);
        $acknowledgementService = app(DocumentAcknowledgementService::class);
        $now = Carbon::now('UTC');

        // Yalnız "issued" durumuna ulaşmış revizyonlar dağıtılır/teyit edilir.
        foreach (['ELE', 'OTM', 'ISG', 'TEK', 'SOZ'] as $typeCode) {
            $revisionId = $documents[$typeCode]['revision']->getKey();

            $distributionService->create([
                'document_revision_id' => $revisionId,
                'recipient_personnel_id' => $adminId,
                'distribution_kind' => DistributionKind::ForInformation->value,
                'requires_acknowledgement' => true,
                'distributed_by_personnel_id' => $adminId,
                'distributed_at' => $now,
            ]);

            $acknowledgementService->create([
                'document_revision_id' => $revisionId,
                'personnel_id' => $adminId,
                'acknowledgement_kind' => AcknowledgementKind::Read->value,
                'acknowledged_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, array{document: Document, revision: DocumentRevision}>  $documents
     */
    private function seedTransmittal(array $documents, int $adminId): void
    {
        /** @var \App\Models\Document\Transmittal $transmittal */
        $transmittal = app(TransmittalService::class)->create([
            'recipient_description' => 'ABC Enerji Yatırım A.Ş. — Proje Sahibi',
            'purpose' => TransmittalPurpose::ForApproval->value,
            'status' => 'issued',
            'issued_by_personnel_id' => $adminId,
            'issued_at' => Carbon::now('UTC'),
            'cover_document_revision_id' => $documents['TEK']['revision']->getKey(),
            'external_reference' => 'ABC-2026-0917',
        ]);

        $itemService = app(TransmittalItemService::class);

        $itemService->create([
            'transmittal_id' => $transmittal->getKey(),
            'document_revision_id' => $documents['TEK']['revision']->getKey(),
            'sort_order' => 0,
            'copies' => 1,
        ]);

        $itemService->create([
            'transmittal_id' => $transmittal->getKey(),
            'document_revision_id' => $documents['ELE']['revision']->getKey(),
            'sort_order' => 1,
            'copies' => 1,
        ]);
    }

    private function seedTemplate(int $adminId): void
    {
        /** @var \App\Models\Document\DocumentTemplate $template */
        $template = app(DocumentTemplateService::class)->create([
            'code' => 'RPT-TEKHAT',
            'name' => 'Tek Hat Şeması Teknik Rapor Şablonu',
            'output_kind' => DocumentTemplateOutputKind::DocumentPdf->value,
            'status' => 'active',
        ]);

        app(DocumentTemplateVersionService::class)->create([
            'document_template_id' => $template->getKey(),
            'locale' => 'tr',
            'view_key' => 'documents.templates.tek-hat-raporu',
            'layout_config' => [
                'sections' => ['giris', 'sistem_topolojisi', 'koruma_koordinasyonu'],
            ],
            'required_field_keys' => ['proje_adi', 'tarih', 'hazirlayan'],
            'status' => 'published',
        ]);
    }

    /**
     * @param  array<string, array{document: Document, revision: DocumentRevision}>  $documents
     */
    private function seedLegalHold(array $documents, int $adminId): void
    {
        /** @var \App\Models\Document\LegalHold $legalHold */
        $legalHold = app(LegalHoldService::class)->create([
            'code' => 'HOLD-SOZ-001',
            'name' => 'Karapınar GES Sözleşmesi Hukuki Tutması',
            'reason' => 'Örnek senaryo: sözleşme maddeleri üzerinde hukuki inceleme sürdüğü için ilgili doküman değişikliğe/silmeye kapatıldı.',
            'personnel_id' => $adminId,
            'status' => LegalHoldStatus::Active->value,
            'starts_at' => Carbon::now('UTC'),
        ]);

        app(LegalHoldDocumentService::class)->create([
            'legal_hold_id' => $legalHold->getKey(),
            'document_id' => $documents['SOZ']['document']->getKey(),
            'added_by_personnel_id' => $adminId,
            'added_at' => Carbon::now('UTC'),
        ]);
    }
}
