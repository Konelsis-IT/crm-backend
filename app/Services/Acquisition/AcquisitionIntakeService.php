<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\ProposalDocumentRole;
use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\TenderNotice;
use App\Query\Document\FixedDocumentQueries;
use App\Services\Acquisition\Concerns\ProposalAmendment;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectConversionService;
use App\Services\Support\TransactionRunner;
use App\Support\Acquisition\ScopeTypes;
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
 *
 * D-186 (9 Ekim 2026 kullanici karari: "Belgedeki revizyon mantigini en azindan
 * teklif icin kaldiriyoruz ... surumleme isi artik personeldedir"):
 * - Duzenle (updateProposal): guncel surum yerinde degisir (alanlar, kapsam,
 *   belgeler); surum numarasi hicbir degisiklikte artmaz, surumun durumu ne
 *   olursa olsun (ProposalAmendment).
 * - Yeni teklif surumu (newProposalVersion): personel dugmeye basinca N+1 acilir;
 *   onceki surumun alanlari formdaki duzeltmelerle, kapsam ve belgeler carpi
 *   ile cikarilmadiysa tasinir, yeni dosyalar eklenir.
 * - Teklif belgelerinde revizyon yok: yuklenen her dosya yeni belgedir; carpi
 *   (cipin "x"i, formda `{anahtar}_removed`) ya da Dokumanlar'daki cop kutusu belgeyi surumden
 *   ayirir, Document kaydi Dokumanlar'da kalir (silinmez).
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

    /** D-186: formda kaldirilmak uzere isaretlenen belgelerin anahtar eki (`{anahtar}_removed`: revizyon kimlikleri). */
    public const REMOVED_SUFFIX = '_removed';

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

            // D-177 "Proje tipi eklemek istiyorum": yeni tipler kayitli tiplere eklenir;
            // kayitli tip bu yoldan kaldirilmaz (tip secimi gizliyken gelmez).
            $added = ScopeTypes::values((array) ($data['added_scope_types'] ?? []));
            unset($data['added_scope_types'], $data['add_scope_types']);

            if ($added !== [] && ! array_key_exists('scope_types', $data)) {
                $data['scope_types'] = [...ScopeTypes::values($case->scopes()->pluck('scope_type')->all()), ...$added];
            }

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

            // D-183: Teklif olustur ekranindaki "Teklif sorumlusu" (formda dolu gelir).
            // Potansiyel is sihirbazindaki owner_employee_id isin sahibidir; o yol
            // (create) bu parametreyi vermez.
            $owner = is_numeric($data['owner_employee_id'] ?? null) && (int) $data['owner_employee_id'] > 0 ? (int) $data['owner_employee_id'] : null;

            // D-186: Teklif olustur ekraninda da "Yeni proje tipi eklemek istiyorum"
            // (Proje tipi bolumu); eklenen tip once potansiyel ise eklenir.
            $data = $this->addScopeTypes($case, $data);

            return $this->openProposal($case, $data, (bool) ($data['convert_now'] ?? false), $owner);
        });
    }

    /**
     * Teklif "Duzenle" (D-186, 9 Ekim 2026 kullanici karari: "Eger kisi dogrudan
     * teklif detayinda duzenleye basarsa o zaman istedigi herhangi bir
     * degisiklikte teklif surumu yukselmeyecektir. Beraberinde yine belgeyi
     * duzenleme adiminda silebilir, ek dosyalar yukleyebilir, ozgurdur").
     *
     * Koke yazilanlar (baslik, sorumlu, taslak) ve guncel surumun alanlari,
     * kapsamlari ve belgeleri ayni surumde, surumun durumundan bagimsiz
     * guncellenir (ProposalAmendment); yeni surum hicbir zaman acilmaz. Surumu
     * olmayan eski teklifte ilk surum acilir. Teklif durumu (offer_status)
     * buradan degismez (D-182, sayfa formu gondermez).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProposal(Proposal $proposal, array $data): Proposal
    {
        return $this->transactions->run(function () use ($proposal, $data): Proposal {
            /** @var Proposal $proposal */
            $proposal = $this->proposals->show($proposal->getKey());
            /** @var BusinessCase $case */
            $case = $proposal->businessCase;
            /** @var ProposalVersion|null $current */
            $current = $proposal->currentVersion;

            $this->updateProposalRoot($proposal, $data);
            $data = $this->addScopeTypes($case, $data);

            $types = $this->scopeTypes($case, $data);
            $rows = is_array($data['scopes'] ?? null) ? $data['scopes'] : [];
            $versionData = $this->versionData($case, $data);

            if ($current === null) {
                /** @var ProposalVersion $version */
                $version = $this->proposalVersions->create([...$versionData, 'proposal_id' => $proposal->getKey()]);

                if (SchemaReadiness::hasBatch('B43')) {
                    $this->scopes->sync($version, $case, $types, $rows);
                }

                $this->syncVersionDocuments($case, $version, null, $data);

                return $proposal->refresh();
            }

            ProposalAmendment::run((int) $current->getKey(), function () use ($case, $current, $types, $rows, $versionData, $data): void {
                $this->proposalVersions->update($current, $versionData);

                if (SchemaReadiness::hasBatch('B43')) {
                    $this->scopes->sync($current, $case, $types, $rows);
                }

                $this->syncVersionDocuments($case, $current, null, $data);
                $this->proposalVersions->refreshHash($current);
            });

            return $proposal->refresh();
        });
    }

    /**
     * "Yeni teklif surumu" (D-186, 9 Ekim 2026 kullanici karari: "Personel 'Yeni
     * teklif surumu' action butonuna tiklarsa onceki bilgilerin tamami klasik bir
     * duzenleme ekrani gibi gelecek ve var olan belgeleri de isterse carpi butonu
     * ile kaldirip yeni surume yeni belgeleri yukleyebilecektir. Ancak bu hamle
     * teklif surumunu 2 yapacaktir").
     *
     * Surum N+1 acilir ve guncel olur (ProposalVersionService::revise; onceki
     * surum "Yerini aldi"). Alanlar formdan; kapsam tipleri, degerleri, kapsam
     * ve maliyet listeleri ile belgeler onceki surumden tasinir, carpi ile
     * cikarilanlar tasinmaz, yeni dosyalar yeni belge olarak eklenir. Teklif
     * durumu (offer_status) degismez.
     *
     * @param  array<string, mixed>  $data
     */
    public function newProposalVersion(Proposal $proposal, array $data): ProposalVersion
    {
        return $this->transactions->run(function () use ($proposal, $data): ProposalVersion {
            /** @var Proposal $proposal */
            $proposal = $this->proposals->show($proposal->getKey());
            /** @var BusinessCase $case */
            $case = $proposal->businessCase;
            /** @var ProposalVersion $previous */
            $previous = $proposal->currentVersion ?? throw RecordNotFoundException::make();

            $this->updateProposalRoot($proposal, $data);
            $data = $this->addScopeTypes($case, $data);

            $types = $this->scopeTypes($case, $data);
            $rows = is_array($data['scopes'] ?? null) ? $data['scopes'] : [];

            $version = $this->proposalVersions->revise($proposal, $this->versionData($case, $data));

            if (SchemaReadiness::hasBatch('B43')) {
                $this->scopes->sync($version, $case, $types, $rows, $previous);
            }

            $this->syncVersionDocuments($case, $version, $previous, $data);

            return $version->refresh();
        });
    }

    /**
     * Teklif kokune yazilanlar: baslik, sorumlu, taslak isaretleri.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateProposalRoot(Proposal $proposal, array $data): void
    {
        $this->proposals->update($proposal, [
            'title' => filled($data['proposal_title'] ?? null) ? (string) $data['proposal_title'] : $proposal->title,
            ...(array_key_exists('owner_employee_id', $data) && filled($data['owner_employee_id']) ? ['owner_employee_id' => (int) $data['owner_employee_id']] : []),
            ...$this->proposalExtras($data),
        ]);
    }

    /**
     * D-177 "Yeni proje tipi eklemek istiyorum": secilen yeni tipler once
     * potansiyel ise eklenir (yalniz ekler); kapsamlari bu kaydetmede teklif
     * surumune yazilir. Form anahtarlari veriden cikarilir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function addScopeTypes(BusinessCase $case, array $data): array
    {
        $added = ScopeTypes::values((array) ($data['added_scope_types'] ?? []));
        unset($data['added_scope_types'], $data['add_scope_types']);

        if ($added !== [] && SchemaReadiness::hasBatch('B43')) {
            $this->businessCases->addScopeTypes($case, $added);
        }

        return $data;
    }

    /**
     * Dokumanlar sekmesinden bir ya da birden fazla dosya (D-176, 8 Ekim 2026
     * kullanici talimati: "bir belge tipine orn. sartnamede 2 belge
     * yuklenecekti ... birden fazla yuklenebilir").
     *
     * D-186: her dosya bu turde yeni belgedir ve guncel surume eklenir; teklif
     * surumu degismez (surumleme personelde, "Yeni teklif surumu").
     *
     * @param  list<array{path: string, name: string|null}>  $files
     */
    public function uploadProposalDocuments(Proposal $proposal, ProposalDocumentRole $role, array $files): Proposal
    {
        $key = $this->uploadKeyFor($role) ?? throw RecordNotFoundException::make();
        $paths = [];
        $names = [];

        foreach ($files as $file) {
            $path = trim((string) ($file['path'] ?? ''));

            if ($path !== '') {
                $paths[] = $path;
                $names[$path] = $file['name'] ?? null;
            }
        }

        if ($paths === []) {
            throw RecordNotFoundException::make();
        }

        $data = [$key.'_file' => $paths, $key.'_file_name' => $names];

        return $this->transactions->run(function () use ($proposal, $data): Proposal {
            /** @var Proposal $proposal */
            $proposal = $this->proposals->show($proposal->getKey());
            /** @var BusinessCase $case */
            $case = $proposal->businessCase;
            /** @var ProposalVersion $current */
            $current = $proposal->currentVersion ?? throw RecordNotFoundException::make();

            ProposalAmendment::run((int) $current->getKey(), function () use ($case, $current, $data): void {
                $this->syncVersionDocuments($case, $current, null, $data);
                $this->proposalVersions->refreshHash($current);
            });

            return $proposal->refresh();
        });
    }

    /**
     * Belgeyi tekliften kaldirma (D-186, 9 Ekim 2026 kullanici talimati:
     * "Dokumanlar relation kisminda ... Indir ve Cop kutusu butonu olsun"):
     * guncel surumun belge satiri (proposal_documents baglantisi) kaldirilir;
     * Document kaydi ve dosyalari Dokumanlar'da kalir (arsiv kurali, silme yok).
     * Teklif surumu degismez.
     */
    public function detachProposalDocument(Proposal $proposal, ProposalDocument $row): Proposal
    {
        return $this->transactions->run(function () use ($proposal, $row): Proposal {
            /** @var Proposal $proposal */
            $proposal = $this->proposals->show($proposal->getKey());
            $currentId = $proposal->current_version_id;

            if ($currentId === null || (int) $row->proposal_version_id !== (int) $currentId) {
                throw RecordNotFoundException::make();
            }

            /** @var ProposalVersion $current */
            $current = $proposal->currentVersion;

            ProposalAmendment::run((int) $currentId, function () use ($row, $current): void {
                $this->proposalDocuments->delete($row);
                $this->proposalVersions->refreshHash($current);
            });

            return $proposal->refresh();
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
    private function openProposal(BusinessCase $case, array $data, bool $convertNow, ?int $ownerId = null): Proposal
    {
        /** @var Proposal $proposal */
        $proposal = $this->proposals->create([
            'business_case_id' => $case->getKey(),
            'title' => filled($data['proposal_title'] ?? null) ? (string) $data['proposal_title'] : null,
            ...($ownerId !== null ? ['owner_employee_id' => $ownerId] : []),
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
     * Surumun belgeleri (D-186: teklifte belge revizyonu yok).
     *
     * - $previous verilirse (Yeni teklif surumu): onceki surumun belgeleri ayni
     *   revizyonla yeni surume baglanir; formda carpi ile isaretlenenler
     *   (`{anahtar}_removed`) baglanmaz. Referanslar / Genel
     *   katalog formdaki secime gore tasinir ya da birakilir.
     * - $previous yoksa (Duzenle, belge yukleme, ilk surum): satirlar zaten bu
     *   surumdedir; carpi ile cikarilan satir ve secimi kaldirilan sabit belge
     *   satiri surumden ayrilir (Document kaydi Dokumanlar'da kalir).
     * - Yuklenen her dosya kendi turunde yeni belge + ilk revizyondur (D-176:
     *   bir turde birden fazla belge; "ayni ad = yeni revizyon" kurali kalkti).
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
        $removed = $this->removedRevisions($data);

        /** @var list<ProposalDocument> $sourceRows */
        $sourceRows = ($previous ?? $version)->documents()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
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

            // D-186: cipin "x"i ile isaretlenmeyen belge tutulur.
            $keep = ! in_array((int) $row->document_revision_id, $removed[$roleValue] ?? [], true);

            if ($inPlace && ! $keep) {
                $this->proposalDocuments->delete($row);
            } elseif (! $inPlace && $keep) {
                $this->linkRevision($version, (int) $row->document_revision_id, $role, $sortOrder);
            }
        }

        foreach ($uploads as $roleValue => $list) {
            foreach ($list as $upload) {
                $this->linkRevision($version, $this->newDocumentRevision($case, $typeIds[$roleValue], $upload), ProposalDocumentRole::from($roleValue), $sortOrder);
            }
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
     * Formda dosyasi olan roller: rol degeri => dosyalar [anahtar, gecici yol,
     * ozgun ad, tur kodu, baslik eki]. D-176: kutu coklu dosya alir; her dosya
     * ayri yuklemedir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<array{key: string, path: string, name: string|null, type: string, suffix: string}>>
     */
    private function uploads(array $data): array
    {
        $uploads = [];

        foreach ($this->uploadedDocuments() as $key => [$typeCode, $suffix, $role]) {
            foreach ($this->strings($data[$key.'_file'] ?? null) as $path) {
                $uploads[$role->value][] = [
                    'key' => $key,
                    'path' => $path,
                    'name' => $this->originalName($data[$key.'_file_name'] ?? null, $path),
                    'type' => $typeCode,
                    'suffix' => $suffix,
                ];
            }
        }

        return $uploads;
    }

    /**
     * Formda kaldirilmak uzere isaretlenen belgeler (D-186, cipin "x"i): rol
     * degeri => revizyon kimlikleri.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<int>>
     */
    private function removedRevisions(array $data): array
    {
        $removed = [];

        foreach ($this->uploadedDocuments() as $key => [, , $role]) {
            foreach ((array) ($data[$key.self::REMOVED_SUFFIX] ?? []) as $value) {
                if (is_numeric($value) && (int) $value > 0) {
                    $removed[$role->value][] = (int) $value;
                }
            }
        }

        return $removed;
    }

    /**
     * Belge turleri dosya tasinmadan ONCE dogrulanir: disk tasima islemi geri
     * alinamaz, eksik tur ikinci belgede cikarsa ilk dosya yetim kalirdi.
     *
     * @param  array<string, list<array{key: string, path: string, name: string|null, type: string, suffix: string}>>  $uploads
     * @return array<string, int>
     */
    private function documentTypeIds(array $uploads): array
    {
        $ids = [];

        foreach ($uploads as $roleValue => $list) {
            $ids[$roleValue] = $this->fixedDocuments->documentTypeId($list[0]['type']) ?? throw RecordNotFoundException::make();
        }

        return $ids;
    }

    /** @return array<string, array{0: string, 1: string, 2: ProposalDocumentRole}> */
    private function uploadedDocuments(): array
    {
        return SchemaReadiness::hasBatch('B43') ? [...self::UPLOADED_DOCUMENTS, ...self::B43_DOCUMENTS] : self::UPLOADED_DOCUMENTS;
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

    /**
     * Doldurulmus butun yollar (FileUpload tek dosyada metin, coklu dosyada dizi).
     *
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        $paths = [];

        foreach (is_array($value) ? $value : [$value] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $paths[] = trim($candidate);
            }
        }

        return array_values(array_unique($paths));
    }

    /** storeFileNamesIn tek dosyada metin, coklu dosyada yol => ad dizisi verir. */
    private function originalName(mixed $value, string $tempPath): ?string
    {
        if (is_array($value)) {
            // Coklu yuklemede ad yola gore okunur; tek elemanli dizi eski bicimdir.
            $value = $value[$tempPath] ?? (count($value) === 1 ? reset($value) : null);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
