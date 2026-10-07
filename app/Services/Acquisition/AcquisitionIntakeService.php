<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\ProposalDocumentRole;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\TenderNotice;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Query\Document\FixedDocumentQueries;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\DocumentService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectConversionService;
use App\Services\Support\TransactionRunner;
use BackedEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

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
 *
 * B43 (D-155, 5 Ekim 2026 kullanici talimati): zincir "Ihale -> Potansiyel is
 * -> Teklif -> Proje".
 * - Ihale adimi: potansiyel is mevcut bir ihaleye baglanir ya da yeni ihale
 *   acilir (`tender_mode`: none / existing / new); ihale ekrani potansiyel is
 *   secmeden ihale acar (startTender).
 * - Kontrol listesi, belgeler ve taslak potansiyel is verisiyle gider
 *   (BusinessCaseService).
 * - Proje kapsami teklif surumundedir (ProposalVersionScopeService); marj
 *   kapsamdan hesaplanir, toplam fiyat bossa kapsamin toplam satisi yazilir.
 * - Teklif belgelerine sartname uygunlugu, deviasyon listesi, marka listesi ve
 *   sorumluluk matrisi eklendi (Excel ya da herhangi bir dosya; madde madde
 *   giris yok).
 * - Teklif duzenleme (reviseProposal): alanlarda, kapsamda ya da belgelerde
 *   gercek degisiklik yeni surumdur. Onceki surum ve belgeleri saklanir; yeni
 *   yuklenen belge ayni belgenin yeni revizyonu olur, yuklenmeyen belge yeni
 *   surume aynen tasinir. Taslak teklif (is_draft) bitirilene kadar ayni
 *   surum uzerinde calisir.
 */
final class AcquisitionIntakeService
{
    /** @var list<string> Is dosyasina yazilan alanlar. */
    private const CASE_KEYS = [
        'primary_party_id', 'title', 'short_description', 'country_code', 'currency_code', 'project_type_code',
        'source_kind', 'criticality', 'offer_type', 'owner_employee_id', 'proposal_owner_employee_id', 'estimated_value',
        'classification_id', 'legal_entity_id', 'scope_types', 'scopes',
        // B43: proje durumu, kontrol listesi, belgeler, taslak.
        'license_status', 'checklist', 'case_document_files', 'case_document_files_name', 'is_draft', 'draft_step',
        // B47 (D-170): elle secilen is gelistirme turu; grup yokken servis cikarir.
        'development_kind',
    ];

    /** @var list<string> Teklif surumune yazilan alanlar (B43: marj kapsamdan hesaplanir). */
    private const VERSION_KEYS = ['total_price', 'margin_pct', 'validity_until', 'is_critical_route', 'summary'];

    /** @var list<string> Duzenlemede "degisti mi" diye bakilan, kullanicinin girdigi surum alanlari. */
    private const VERSION_INPUT_KEYS = ['total_price', 'validity_until', 'is_critical_route', 'summary'];

    /** @var list<string> Donusumde projeye aktarilan alanlar. */
    private const PROJECT_KEYS = [
        'project_manager_employee_id', 'planned_start_on', 'planned_finish_on',
        'site_address_line1', 'site_address_line2', 'site_district', 'site_city', 'site_postal_code', 'site_country_code',
    ];

    /** @var list<string> Ihale adiminin alanlari (B43). */
    private const TENDER_KEYS = [
        'tender_source_id', 'title', 'external_notice_id', 'issuer_party_id', 'notice_url', 'status', 'summary',
        'published_on', 'source_document_revision_id', 'is_draft', 'draft_step',
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

    /**
     * B43 (D-155): teklif olustur / duzenle ekranindaki diger belgeler.
     *
     * @var array<string, array{0: string, 1: string, 2: ProposalDocumentRole}>
     */
    private const B43_DOCUMENTS = [
        'spec_compliance' => ['SUY', 'Şartname uygunluğu', ProposalDocumentRole::SpecCompliance],
        'deviation_list' => ['DEV', 'Deviasyon listesi', ProposalDocumentRole::DeviationList],
        'brand_list' => ['MRK', 'Marka listesi', ProposalDocumentRole::BrandList],
        'responsibility_matrix' => ['SRM', 'Sorumluluk matrisi', ProposalDocumentRole::ResponsibilityMatrix],
    ];

    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly BusinessCaseService $businessCases,
        private readonly ProposalService $proposals,
        private readonly ProposalVersionService $proposalVersions,
        private readonly ProjectConversionService $conversion,
        private readonly ProposalDocumentService $proposalDocuments,
        private readonly DocumentService $documents,
        private readonly DocumentRevisionService $revisions,
        private readonly FixedDocumentQueries $fixedDocuments,
        private readonly ActorContext $actor,
        private readonly TenderNoticeService $tenders,
        private readonly ProposalVersionScopeService $scopes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  is dosyasi alanlari + ihale adimi + create_proposal, proposal_title, surum alanlari, teklif belgeleri + convert_now, project_name, proje alanlari
     */
    public function start(array $data): BusinessCase
    {
        return $this->transactions->run(function () use ($data): BusinessCase {
            /** @var BusinessCase $case */
            $case = $this->businessCases->create(array_intersect_key($data, array_flip(self::CASE_KEYS)));

            $this->attachTender($case, $data);

            $createProposal = (bool) ($data['create_proposal'] ?? false);
            $convertNow = (bool) ($data['convert_now'] ?? false);

            if ($convertNow && ! $createProposal) {
                throw GuardNotSatisfiedException::make(['reason' => 'projeye donusum icin once teklif olusturulmali']);
            }

            if (! $createProposal) {
                return $case->refresh();
            }

            $this->openProposal($case, $data, $convertNow);

            return $case->refresh();
        });
    }

    /**
     * Potansiyel is duzenleme sihirbazi (B43): alanlar, kontrol listesi ve
     * ihale adimi tek transaction'da.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCase(BusinessCase $case, array $data): BusinessCase
    {
        return $this->transactions->run(function () use ($case, $data): BusinessCase {
            $tender = array_intersect_key($data, array_flip(['tender_mode', 'tender_notice_id', 'tender']));
            unset($data['tender_mode'], $data['tender_notice_id'], $data['tender']);

            /** @var BusinessCase $updated */
            $updated = $this->businessCases->update($case, $data);

            $this->attachTender($updated, $tender);

            return $updated;
        });
    }

    /**
     * Ihale ekranindan ihale (B43): potansiyel is secilmez, ilan ve ilk surumu
     * acilir. Potansiyel is daha sonra ihaleden acilir.
     *
     * @param  array<string, mixed>  $data
     */
    public function startTender(array $data): TenderNotice
    {
        /** @var TenderNotice $notice */
        $notice = $this->tenders->create(array_intersect_key($data, array_flip(self::TENDER_KEYS)));

        return $notice;
    }

    /**
     * Var olan is dosyasina teklif (Teklif olustur ekrani, 22 Eylul 2026
     * kullanici karari): sihirbazin 2. ve 3. adimiyla ayni yazma yolu. Ilk
     * surum, teklif belgeleri, "Teklif hazirlaniyor" asamasi ve istenirse
     * hemen projeye donusum.
     *
     * @param  array<string, mixed>  $data  proposal_title, surum alanlari, teklif belgeleri + convert_now, project_name, proje alanlari
     */
    public function addProposal(int $businessCaseId, array $data): Proposal
    {
        return $this->transactions->run(function () use ($businessCaseId, $data): Proposal {
            /** @var BusinessCase $case */
            $case = $this->businessCases->show($businessCaseId);

            return $this->openProposal($case, $data, (bool) ($data['convert_now'] ?? false));
        });
    }

    /**
     * Teklif duzenle (B43): koke yazilanlar (baslik, sorumlu, teklif durumu,
     * taslak) her zaman guncellenir; surum alanlari, kapsam ya da belgeler
     * gercekten degistiyse yeni surum acilir (ProposalVersionService::revise).
     * Taslak teklif ayni (taslak / incelemedeki) surum uzerinde guncellenir.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Proposal, 1: ProposalVersion|null} teklif ve (acildiysa) yeni surum
     */
    public function reviseProposal(Proposal $proposal, array $data): array
    {
        return $this->transactions->run(function () use ($proposal, $data): array {
            /** @var Proposal $proposal */
            $proposal = $this->proposals->show($proposal->getKey());
            /** @var BusinessCase $case */
            $case = $proposal->businessCase;
            /** @var ProposalVersion|null $current */
            $current = $proposal->currentVersion;
            $wasDraft = (bool) $proposal->getAttribute('is_draft');

            $this->proposals->update($proposal, [
                'title' => filled($data['proposal_title'] ?? null) ? (string) $data['proposal_title'] : $proposal->title,
                ...(array_key_exists('owner_employee_id', $data) && filled($data['owner_employee_id']) ? ['owner_employee_id' => (int) $data['owner_employee_id']] : []),
                ...$this->proposalExtras($data),
            ]);

            $types = $this->scopeTypes($case, $data);
            $rows = is_array($data['scopes'] ?? null) ? $data['scopes'] : [];
            $versionData = $this->versionData($case, $data);

            $changed = $current === null
                || $this->versionInputsDiffer($current, $data)
                || (SchemaReadiness::hasBatch('B43') && $this->scopes->differs($current, $types, $rows))
                || $this->documentsChanged($current, $data);

            if (! $changed) {
                return [$proposal->refresh(), null];
            }

            if ($current !== null && $wasDraft && in_array($current->status, [ProposalVersionStatus::Draft, ProposalVersionStatus::Review], true)) {
                $this->proposalVersions->update($current, $versionData);

                if (SchemaReadiness::hasBatch('B43')) {
                    $this->scopes->sync($current, $case, $types, $rows);
                }

                $this->syncVersionDocuments($case, $current, null, $data);

                return [$proposal->refresh(), null];
            }

            $version = $this->proposalVersions->revise($proposal, $versionData);

            if (SchemaReadiness::hasBatch('B43')) {
                $this->scopes->sync($version, $case, $types, $rows, $current);
            }

            $this->syncVersionDocuments($case, $version, $current, $data);

            return [$proposal->refresh(), $version];
        });
    }

    /**
     * Teklif sayfasinin Dokumanlar sekmesinden belge yukleme (D-158, 5 Ekim 2026
     * kullanici talimati: "Belgeyi yukleyebilmeliyim, her yeni yuklediğimde surum
     * guncellenmelidir ... belge revize olmadiysa dokuman kismini cogaltmanin
     * manasi yok").
     *
     * Rolde belge varsa ayni belgenin yeni revizyonu, yoksa yeni belge acilir.
     * Teklif duzenle ile ayni kural: yeni surum acilir (taslak teklifte ayni
     * surum guncellenir); surum alanlari, kapsam ve diger belgeler onceki
     * surumden aynen tasinir (belge kopyalanmaz, ayni revizyon yeni surume baglanir).
     *
     * @return array{0: Proposal, 1: ProposalVersion|null} teklif ve (acildiysa) yeni surum
     */
    public function uploadProposalDocument(Proposal $proposal, ProposalDocumentRole $role, string $tempPath, ?string $originalName): array
    {
        $key = $this->uploadKeyFor($role) ?? throw RecordNotFoundException::make();
        $data = [$key.'_file' => $tempPath, $key.'_file_name' => $originalName];

        return $this->transactions->run(function () use ($proposal, $data): array {
            /** @var Proposal $proposal */
            $proposal = $this->proposals->show($proposal->getKey());
            /** @var BusinessCase $case */
            $case = $proposal->businessCase;
            /** @var ProposalVersion|null $current */
            $current = $proposal->currentVersion;

            if ($current === null) {
                throw RecordNotFoundException::make();
            }

            if ((bool) $proposal->getAttribute('is_draft') && in_array($current->status, [ProposalVersionStatus::Draft, ProposalVersionStatus::Review], true)) {
                $this->syncVersionDocuments($case, $current, null, $data);

                return [$proposal->refresh(), null];
            }

            $version = $this->proposalVersions->revise($proposal, [
                'total_price' => $current->total_price,
                'margin_pct' => $current->margin_pct,
                'validity_until' => self::dateString($current->validity_until),
                'is_critical_route' => (bool) $current->is_critical_route,
                'summary' => $current->summary,
            ]);

            // Kapsam aynen tasinir: bos satirlarla senkron onceki surumun degerlerini ve kapsam listesini alir.
            if (SchemaReadiness::hasBatch('B43')) {
                $types = $current->scopes()->pluck('scope_type')->all();
                $this->scopes->sync($version, $case, $types, [], $current);
            }

            $this->syncVersionDocuments($case, $version, $current, $data);

            return [$proposal->refresh(), $version];
        });
    }

    /** Yuklenebilen teklif belgesinin form anahtari (or. customer_expectations); yuklenemiyorsa null. */
    public function uploadKeyFor(ProposalDocumentRole $role): ?string
    {
        foreach ($this->uploadedDocuments() as $key => [, , $uploadRole]) {
            if ($uploadRole === $role) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Dokumanlar sekmesinden yuklenebilen roller (sirali).
     *
     * @return list<ProposalDocumentRole>
     */
    public function uploadableRoles(): array
    {
        return array_values(array_map(static fn (array $definition): ProposalDocumentRole => $definition[2], $this->uploadedDocuments()));
    }

    /**
     * Teklif + ilk surum + belgeler; is dosyasi "Teklif hazirlaniyor"a gecer,
     * $convertNow ise teklif projeye donusturulur.
     *
     * @param  array<string, mixed>  $data
     */
    private function openProposal(BusinessCase $case, array $data, bool $convertNow): Proposal
    {
        /** @var Proposal $proposal */
        $proposal = $this->proposals->create([
            'business_case_id' => $case->getKey(),
            'title' => filled($data['proposal_title'] ?? null) ? (string) $data['proposal_title'] : null,
            ...$this->proposalExtras($data),
        ]);

        /** @var ProposalVersion $version */
        $version = $this->proposalVersions->create([
            ...$this->versionData($case, $data),
            'proposal_id' => $proposal->getKey(),
        ]);

        if (SchemaReadiness::hasBatch('B43')) {
            $this->scopes->sync($version, $case, $this->scopeTypes($case, $data), is_array($data['scopes'] ?? null) ? $data['scopes'] : []);
        }

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
                'description' => $data['short_description'] ?? $case->short_description,
            ]);
        }

        return $proposal->refresh();
    }

    /**
     * Ihale adimi (B43): mevcut ihaleyi bagla ya da yeni ihale ac; grup
     * uygulanmadiysa ya da adim bos gectiyse hicbir sey yapilmaz.
     *
     * @param  array<string, mixed>  $data
     */
    private function attachTender(BusinessCase $case, array $data): void
    {
        if (! SchemaReadiness::hasBatch('B43')) {
            return;
        }

        $mode = $data['tender_mode'] ?? 'none';
        $mode = $mode instanceof BackedEnum ? (string) $mode->value : (string) $mode;

        if ($mode === 'existing' && (int) ($data['tender_notice_id'] ?? 0) > 0) {
            $this->tenders->linkToCase((int) $data['tender_notice_id'], $case);

            return;
        }

        if ($mode === 'new' && is_array($data['tender'] ?? null)) {
            $tender = array_intersect_key($data['tender'], array_flip(self::TENDER_KEYS));
            unset($tender['is_draft'], $tender['draft_step']);

            $this->tenders->create([...$tender, 'business_case_id' => $case->getKey()]);
        }
    }

    /**
     * Teklif kokune yazilan B29 alanlari (teklif durumu) ve B43 taslak
     * isaretleri. Grup uygulanmadiysa ya da anahtar gelmediyse hicbir sey
     * eklenmez; enum degeri metne indirgenir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function proposalExtras(array $data): array
    {
        $extras = [];

        if (SchemaReadiness::hasBatch('B29') && array_key_exists('offer_status', $data)) {
            $status = $data['offer_status'];
            $status = $status instanceof BackedEnum ? $status->value : $status;
            $extras['offer_status'] = filled($status) ? (string) $status : null;
        }

        if (SchemaReadiness::hasBatch('B43')) {
            foreach (['is_draft', 'draft_step'] as $key) {
                if (array_key_exists($key, $data)) {
                    $extras[$key] = $data[$key];
                }
            }
        }

        return $extras;
    }

    /**
     * Surum alanlari. B43: marj kapsamin toplam maliyet / satisindan
     * hesaplanir; toplam fiyat bos birakildiysa kapsamin toplam satisi yazilir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function versionData(BusinessCase $case, array $data): array
    {
        $values = array_intersect_key($data, array_flip(self::VERSION_KEYS));
        $values['is_critical_route'] = (bool) ($data['is_critical_route'] ?? false);

        if (! SchemaReadiness::hasBatch('B43')) {
            return $values;
        }

        $rows = ProposalVersionScopeService::selectedRows($this->scopeTypes($case, $data), is_array($data['scopes'] ?? null) ? $data['scopes'] : []);
        $values['margin_pct'] = ProposalVersionScopeService::margin($rows);

        if (blank($values['total_price'] ?? null)) {
            $values['total_price'] = ProposalVersionScopeService::totalSales($rows);
        }

        return $values;
    }

    /**
     * Teklif kapsaminin tipleri: formdaki secim (sihirbazin potansiyel is adimi
     * ya da teklif ekraninin gizli alani), yoksa potansiyel isin proje tipleri.
     *
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function scopeTypes(BusinessCase $case, array $data): array
    {
        if (is_array($data['scope_types'] ?? null)) {
            return array_values($data['scope_types']);
        }

        return $case->scopes()->pluck('scope_type')->all();
    }

    /**
     * Kullanicinin girdigi surum alanlari kayitli surumden farkli mi
     * (hesaplanan marj karsilastirilmaz; kapsam degisikligi ayrica bakilir).
     *
     * @param  array<string, mixed>  $data
     */
    private function versionInputsDiffer(ProposalVersion $version, array $data): bool
    {
        foreach (self::VERSION_INPUT_KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $stored = $version->getAttribute($key);
            $incoming = $data[$key];

            $same = match ($key) {
                'total_price' => self::sameNumber($stored, $incoming),
                'validity_until' => self::dateString($stored) === self::dateString($incoming),
                'is_critical_route' => (bool) $stored === (bool) $incoming,
                default => trim((string) $stored) === trim((string) $incoming),
            };

            if (! $same) {
                return true;
            }
        }

        return false;
    }

    /**
     * Yeni dosya yuklendiyse ya da Referanslar / Genel katalog secimi
     * degistiyse belgeler degismistir.
     *
     * @param  array<string, mixed>  $data
     */
    private function documentsChanged(?ProposalVersion $version, array $data): bool
    {
        foreach (array_keys($this->uploadedDocuments()) as $key) {
            if ($this->firstString($data[$key.'_file'] ?? null) !== null) {
                return true;
            }
        }

        if (! SchemaReadiness::hasBatch('B29')) {
            return false;
        }

        $roles = $version === null ? [] : $version->documents()->pluck('document_role')
            ->map(static fn (mixed $role): string => $role instanceof BackedEnum ? (string) $role->value : (string) $role)
            ->all();

        foreach (['attach_references' => ProposalDocumentRole::References, 'attach_catalog' => ProposalDocumentRole::Catalog] as $key => $role) {
            if (array_key_exists($key, $data) && (bool) $data[$key] !== in_array($role->value, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Teklif adiminin belgeleri (B29, D-101): firmanin beklentileri (BEK) ve
     * teklif mektubu (TKM) yeni belge + ilk revizyon olarak acilir; Referanslar
     * belgesi (REF) ve Genel katalog (KAT) Dokumanlar'daki sabit belgenin
     * guncel revizyonuyla baglanir. Hepsi ilk teklif surumune
     * proposal_documents satiri olur. Grup uygulanmadiysa hicbiri islenmez.
     * B43 ile sartname uygunlugu, deviasyon listesi, marka listesi ve
     * sorumluluk matrisi de ayni yoldan gelir.
     *
     * @param  array<string, mixed>  $data
     */
    private function attachProposalDocuments(BusinessCase $case, ProposalVersion $version, array $data): void
    {
        $this->syncVersionDocuments($case, $version, null, $data);
    }

    /**
     * Surumun belgeleri.
     *
     * - $previous verilirse (yeni surum): onceki surumun belgeleri aynen tasinir;
     *   formda yeni dosyasi olan rol, onceki belgenin yeni revizyonu olur.
     *   Referanslar / Genel katalog formdaki secime gore tasinir ya da birakilir.
     * - $previous yoksa ve surumde zaten belge varsa (taslak teklifin kendi
     *   surumu): yeni dosya ayni belgeye yeni revizyon olarak eklenip satir yeni
     *   revizyona tasinir; secimi kaldirilan sabit belge satiri cikar.
     * - Surum bossa (ilk surum): yeni belgeler ve secilen sabit belgeler baglanir.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncVersionDocuments(BusinessCase $case, ProposalVersion $version, ?ProposalVersion $previous, array $data): void
    {
        if (! SchemaReadiness::hasBatch('B29')) {
            return;
        }

        $uploads = $this->uploads($data);
        $typeIds = $this->documentTypeIds($uploads);

        /** @var list<ProposalDocument> $sourceRows */
        $sourceRows = ($previous ?? $version)->documents()->with('documentRevision.document')->orderBy('sort_order')->orderBy('id')->get()->all();
        $inPlace = $previous === null;
        $sortOrder = $inPlace && $sourceRows !== []
            ? max(array_map(static fn (ProposalDocument $row): int => (int) $row->sort_order, $sourceRows)) + 1
            : 0;
        $handled = [];

        foreach ($sourceRows as $row) {
            $role = $row->document_role;
            $roleValue = $role instanceof BackedEnum ? (string) $role->value : (string) $role;

            if (in_array($roleValue, [ProposalDocumentRole::References->value, ProposalDocumentRole::Catalog->value], true)) {
                $key = $roleValue === ProposalDocumentRole::References->value ? 'attach_references' : 'attach_catalog';
                $keep = ! array_key_exists($key, $data) || (bool) $data[$key];
                $handled[$roleValue] = true;

                if ($inPlace && ! $keep) {
                    $this->proposalDocuments->delete($row);
                } elseif (! $inPlace && $keep) {
                    $this->linkRevision($version, (int) $row->document_revision_id, $role, $sortOrder);
                }

                continue;
            }

            $upload = $uploads[$roleValue] ?? null;

            if ($upload !== null && ! isset($handled[$roleValue])) {
                $handled[$roleValue] = true;
                $revisionId = $this->newRevisionFor($row->documentRevision?->document, $upload)
                    ?? $this->newDocumentRevision($case, $typeIds[$roleValue], $upload);

                if ($inPlace) {
                    $this->proposalDocuments->update($row, ['document_revision_id' => $revisionId]);
                } else {
                    $this->linkRevision($version, $revisionId, $role, $sortOrder);
                }

                continue;
            }

            if (! $inPlace) {
                $this->linkRevision($version, (int) $row->document_revision_id, $role, $sortOrder);
            }
        }

        foreach ($uploads as $roleValue => $upload) {
            if (isset($handled[$roleValue])) {
                continue;
            }

            $this->linkRevision($version, $this->newDocumentRevision($case, $typeIds[$roleValue], $upload), ProposalDocumentRole::from($roleValue), $sortOrder);
        }

        foreach (['attach_references' => ProposalDocumentRole::References, 'attach_catalog' => ProposalDocumentRole::Catalog] as $key => $role) {
            if (isset($handled[$role->value]) || ! (bool) ($data[$key] ?? false)) {
                continue;
            }

            $document = $role === ProposalDocumentRole::References ? $this->fixedDocuments->referenceDocument() : $this->fixedDocuments->catalogDocument();
            $revisionId = $document?->displayRevision()?->getKey();

            if ($revisionId !== null) {
                $this->linkRevision($version, (int) $revisionId, $role, $sortOrder);
            }
        }
    }

    /**
     * Formda dosyasi olan roller: rol degeri => [anahtar, gecici yol, ozgun ad, tur kodu, baslik eki].
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{key: string, path: string, name: string|null, type: string, suffix: string}>
     */
    private function uploads(array $data): array
    {
        $uploads = [];

        foreach ($this->uploadedDocuments() as $key => [$typeCode, $suffix, $role]) {
            $path = $this->firstString($data[$key.'_file'] ?? null);

            if ($path === null) {
                continue;
            }

            $uploads[$role->value] = [
                'key' => $key,
                'path' => $path,
                'name' => $this->originalName($data[$key.'_file_name'] ?? null, $path),
                'type' => $typeCode,
                'suffix' => $suffix,
            ];
        }

        return $uploads;
    }

    /**
     * Belge turleri dosya tasinmadan ONCE dogrulanir: disk tasima islemi geri
     * alinamaz, eksik tur ikinci belgede cikarsa ilk dosya yetim kalirdi.
     *
     * @param  array<string, array{key: string, path: string, name: string|null, type: string, suffix: string}>  $uploads
     * @return array<string, int>
     */
    private function documentTypeIds(array $uploads): array
    {
        $ids = [];

        foreach ($uploads as $roleValue => $upload) {
            $ids[$roleValue] = $this->fixedDocuments->documentTypeId($upload['type']) ?? throw RecordNotFoundException::make();
        }

        return $ids;
    }

    /** @return array<string, array{0: string, 1: string, 2: ProposalDocumentRole}> */
    private function uploadedDocuments(): array
    {
        return SchemaReadiness::hasBatch('B43') ? [...self::UPLOADED_DOCUMENTS, ...self::B43_DOCUMENTS] : self::UPLOADED_DOCUMENTS;
    }

    /**
     * Var olan belgeye yeni revizyon (eski dosya silinmez); belge yoksa null.
     *
     * @param  array{key: string, path: string, name: string|null, type: string, suffix: string}  $upload
     */
    private function newRevisionFor(?Document $document, array $upload): ?int
    {
        if ($document === null) {
            return null;
        }

        /** @var DocumentRevision $revision */
        $revision = $this->revisions->create([
            'document_id' => $document->getKey(),
            'title' => $document->title,
            'language' => 'tr',
            'purpose' => 'for_review',
            'file_temp_path' => $upload['path'],
            'file_original_name' => $upload['name'],
        ]);

        return (int) $revision->getKey();
    }

    /**
     * Yeni belge + ilk revizyon; revizyon kimligini doner.
     *
     * @param  array{key: string, path: string, name: string|null, type: string, suffix: string}  $upload
     */
    private function newDocumentRevision(BusinessCase $case, int $typeId, array $upload): int
    {
        $document = $this->documents->createWithInitialRevision([
            'document_type_id' => $typeId,
            'title' => self::documentTitle($case, $upload['suffix']),
            'owner_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
            'default_language' => 'tr',
            'file_temp_path' => $upload['path'],
            'file_original_name' => $upload['name'],
        ]);

        return (int) ($document->refresh()->displayRevision()?->getKey() ?? throw RecordNotFoundException::make());
    }

    /** "{is dosyasi} – {ek}" basligi; documents.title 255 karakterle sinirlidir. */
    private static function documentTitle(BusinessCase $case, string $suffix): string
    {
        return Str::limit((string) $case->title, 255 - mb_strlen(' – '.$suffix), '').' – '.$suffix;
    }

    private function linkRevision(ProposalVersion $version, int $revisionId, ProposalDocumentRole $role, int &$sortOrder): void
    {
        $this->proposalDocuments->create([
            'proposal_version_id' => $version->getKey(),
            'document_revision_id' => $revisionId,
            'document_role' => $role->value,
            'sort_order' => $sortOrder++,
        ]);
    }

    private static function sameNumber(mixed $left, mixed $right): bool
    {
        $a = is_numeric($left) ? (float) $left : null;
        $b = is_numeric($right) ? (float) $right : null;

        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs($a - $b) < 0.005;
    }

    private static function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value)->toDateString();
        } catch (Throwable) {
            return (string) $value;
        }
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
