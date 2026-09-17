<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\ProposalDocumentRole;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Document\Document;
use App\Query\Document\FixedDocumentQueries;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectConversionService;
use App\Services\Support\TransactionRunner;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Is alim girisi (D-72): "Is dosyasi -> Teklif -> Proje" sihirbazinin tek
 * transaction'daki karsiligi.
 *
 * Adim 1 is dosyasini acar (TKLF kodu, firsat kaydi: BusinessCaseService;
 * B29 ile teklif tipi ve proje kapsamlari da ayni cagrida gider).
 * Adim 2 istenirse ilk teklifi ve ilk surumunu acar (ProposalService,
 * ProposalVersionService) ve is dosyasini "Teklif hazirlaniyor" asamasina
 * tasir; B29 ile teklif durumu yazilir ve teklif belgeleri (firmanin
 * beklentileri, teklif mektubu, sabit Referanslar belgesi / Genel katalog)
 * ilk surume baglanir. Adim 3 istenirse (kazanilmis / dogrudan yapilacak
 * is) teklifi ProjectConversionService ile projeye donusturur; kanonik
 * zincir (teklif onayi -> kazanildi -> Operasyona devir -> kabul -> PRJ)
 * atlanmaz.
 */
final class AcquisitionIntakeService
{
    /** @var list<string> Is dosyasina yazilan alanlar. */
    private const CASE_KEYS = [
        'primary_party_id', 'title', 'short_description', 'country_code', 'currency_code', 'project_type_code',
        'source_kind', 'criticality', 'offer_type', 'owner_employee_id', 'proposal_owner_employee_id', 'estimated_value',
        'classification_id', 'legal_entity_id', 'scope_types', 'scopes',
    ];

    /** @var list<string> Ilk teklif surumune yazilan alanlar. */
    private const VERSION_KEYS = ['total_price', 'margin_pct', 'validity_until', 'is_critical_route', 'summary'];

    /** @var list<string> Donusumde projeye aktarilan alanlar. */
    private const PROJECT_KEYS = [
        'project_manager_employee_id', 'planned_start_on', 'planned_finish_on',
        'site_address_line1', 'site_address_line2', 'site_district', 'site_city', 'site_postal_code', 'site_country_code',
    ];

    /**
     * Teklif adiminda yuklenen belgeler (B29): form anahtari => dokuman turu
     * kodu, belge basligi eki, teklif belgesi rolu. Dosya `{anahtar}_file`,
     * ozgun adi `{anahtar}_file_name` ile gelir.
     *
     * @var array<string, array{0: string, 1: string, 2: ProposalDocumentRole}>
     */
    private const UPLOADED_DOCUMENTS = [
        'customer_expectations' => ['BEK', 'Firmanın beklentileri', ProposalDocumentRole::CustomerExpectations],
        'proposal_letter' => ['TKM', 'Teklif mektubu', ProposalDocumentRole::ProposalLetter],
    ];

    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly BusinessCaseService $businessCases,
        private readonly ProposalService $proposals,
        private readonly ProposalVersionService $proposalVersions,
        private readonly ProjectConversionService $conversion,
        private readonly ProposalDocumentService $proposalDocuments,
        private readonly DocumentService $documents,
        private readonly FixedDocumentQueries $fixedDocuments,
        private readonly ActorContext $actor,
    ) {}

    /**
     * @param  array<string, mixed>  $data  is dosyasi alanlari + create_proposal, proposal_title, surum alanlari, teklif belgeleri + convert_now, project_name, proje alanlari
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
                ...$this->proposalExtras($data),
            ]);

            /** @var ProposalVersion $version */
            $version = $this->proposalVersions->create([
                ...array_intersect_key($data, array_flip(self::VERSION_KEYS)),
                'proposal_id' => $proposal->getKey(),
                'is_critical_route' => (bool) ($data['is_critical_route'] ?? false),
            ]);

            $this->attachProposalDocuments($case, $version, $data);

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

    /**
     * Teklif kokune yazilan B29 alanlari (teklif durumu). Grup uygulanmadiysa
     * ya da anahtar gelmediyse hicbir sey eklenmez; enum degeri metne indirgenir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function proposalExtras(array $data): array
    {
        if (! SchemaReadiness::hasBatch('B29') || ! array_key_exists('offer_status', $data)) {
            return [];
        }

        $status = $data['offer_status'];
        $status = $status instanceof BackedEnum ? $status->value : $status;

        return ['offer_status' => filled($status) ? (string) $status : null];
    }

    /**
     * Teklif adiminin belgeleri (B29, D-101): firmanin beklentileri (BEK) ve
     * teklif mektubu (TKM) yeni belge + ilk revizyon olarak acilir; Referanslar
     * belgesi (REF) ve Genel katalog (KAT) Dokumanlar'daki sabit belgenin
     * guncel revizyonuyla baglanir. Hepsi ilk teklif surumune
     * proposal_documents satiri olur. Grup uygulanmadiysa hicbiri islenmez.
     *
     * @param  array<string, mixed>  $data
     */
    private function attachProposalDocuments(BusinessCase $case, ProposalVersion $version, array $data): void
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            return;
        }

        $sortOrder = 0;

        // Belge turleri dosya tasinmadan ONCE dogrulanir: disk tasima islemi
        // geri alinamaz, eksik tur ikinci belgede cikarsa ilk dosya yetim kalirdi.
        $typeIds = [];

        foreach (self::UPLOADED_DOCUMENTS as $key => [$typeCode]) {
            if ($this->firstString($data[$key.'_file'] ?? null) === null) {
                continue;
            }

            $typeIds[$key] = $this->fixedDocuments->documentTypeId($typeCode) ?? throw RecordNotFoundException::make();
        }

        foreach (self::UPLOADED_DOCUMENTS as $key => [$typeCode, $titleSuffix, $role]) {
            $tempPath = $this->firstString($data[$key.'_file'] ?? null);

            if ($tempPath === null) {
                continue;
            }

            $document = $this->documents->createWithInitialRevision([
                'document_type_id' => $typeIds[$key],
                'title' => self::documentTitle($case, $titleSuffix),
                'owner_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
                'default_language' => 'tr',
                'file_temp_path' => $tempPath,
                'file_original_name' => $this->originalName($data[$key.'_file_name'] ?? null, $tempPath),
            ]);

            $this->linkDocument($version, $document->refresh(), $role, $sortOrder);
        }

        if ((bool) ($data['attach_references'] ?? false)) {
            $this->linkDocument($version, $this->fixedDocuments->referenceDocument(), ProposalDocumentRole::References, $sortOrder);
        }

        if ((bool) ($data['attach_catalog'] ?? false)) {
            $this->linkDocument($version, $this->fixedDocuments->catalogDocument(), ProposalDocumentRole::Catalog, $sortOrder);
        }
    }

    /** "{is dosyasi} – {ek}" basligi; documents.title 255 karakterle sinirlidir. */
    private static function documentTitle(BusinessCase $case, string $suffix): string
    {
        return Str::limit((string) $case->title, 255 - mb_strlen(' – '.$suffix), '').' – '.$suffix;
    }

    /**
     * Belgenin guncel (yoksa en son) revizyonunu teklif surumune baglar;
     * belge ya da revizyon yoksa sessizce atlar.
     */
    private function linkDocument(ProposalVersion $version, ?Document $document, ProposalDocumentRole $role, int &$sortOrder): void
    {
        $revisionId = $document?->displayRevision()?->getKey();

        if ($revisionId === null) {
            return;
        }

        $this->proposalDocuments->create([
            'proposal_version_id' => $version->getKey(),
            'document_revision_id' => (int) $revisionId,
            'document_role' => $role->value,
            'sort_order' => $sortOrder++,
        ]);
    }

    /** Filament FileUpload tek dosyada metin, coklu dosyada dizi verir; ilk dolu yolu alir. */
    private function firstString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /** storeFileNamesIn tek dosyada metin, coklu dosyada yol => ad dizisi verir. */
    private function originalName(mixed $value, string $tempPath): ?string
    {
        if (is_array($value)) {
            $value = $value[$tempPath] ?? reset($value);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
