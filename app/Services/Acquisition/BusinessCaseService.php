<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\BusinessCodeStatus;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\LifecycleSegment;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\OfferType;
use App\Enums\Acquisition\OpportunityStage;
use App\Enums\Platform\Feature;
use App\Enums\Reference\ClassificationCode;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCode;
use App\Models\Acquisition\Opportunity;
use App\Models\Acquisition\Proposal;
use App\Models\Reference\LegalEntity;
use App\Models\Reference\SecurityClassification;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Numbering\AllocateBusinessNumber;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Acquisition\ChecklistTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Business case (tek ticari is koku) servisi (10 SS2, 14 SS2.21 SM-BC).
 *
 * create override edilmistir: global sira numarasi ayrilir
 * (AllocateBusinessNumber, ic kimlik), ayni transaction'da potansiyel is
 * kodu (business_codes; B40 ile POTIS-YYYY-NNNN, oncesinde TKLF-n) ve 1:1
 * firsat kaydi (opportunities) acilir. Teklif numarasi (TKLF-YYYY-NNNN)
 * teklifin kendisindedir (ProposalService, D-132).
 *
 * changeStage bes temel islemin disinda, SM-BC gecislerini uygular;
 * handover_accepted yalniz OperationHandoffService::accept() icinde
 * yazilir.
 *
 * B29 (D-101): sihirbazdan gelen `scope_types` / `scopes` anahtarlari is
 * dosyasi verisinden ayrilir ve ayni transaction'da BusinessCaseScopeService
 * ile kapsam satirlarina islenir; grup uygulanmadiysa hic dokunulmaz.
 * `offer_type` duz fillable kolondur.
 *
 * B43 (D-155): kapsam tutarlari teklife tasindi; potansiyel iste yalniz proje
 * tipi secimi kalir (`scopes` artik gelmez, tip satirlari tutarsiz yazilir).
 * Teklif oncesi kontrol listesi (`checklist`: cevaplar + ana madde belgeleri)
 * ve genel belgeler (`case_document_files`) ayni transaction'da islenir;
 * ardindan teklif sicakligi (heat_score) yeniden hesaplanir ve GES 1.3
 * "Gecerlilik suresi devam ediyor mu?" Hayir ise teklif tipi Butcesel olur.
 * `license_status`, `is_draft`, `draft_step` duz kolondur; grup uygulanmadiysa
 * hicbiri yazilmaz.
 */
final class BusinessCaseService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['primaryParty', 'owner', 'opportunity', 'codes', 'legalEntity'];

    protected string $orderBy = 'sequence_no';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly AllocateBusinessNumber $allocator,
        private readonly YearlyCodeAllocator $codes,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            [$data, $scopeTypes, $scopeRows, $syncScopes] = $this->extractScopes($data);
            [$data, $checklist] = $this->extractChecklist($data);
            $sequenceNo = $this->allocator->handle();

            /** @var BusinessCase $case */
            $case = parent::create([
                ...$data,
                'sequence_no' => $sequenceNo,
                'legal_entity_id' => $data['legal_entity_id'] ?? $this->defaultLegalEntityId(),
                'classification_id' => $data['classification_id'] ?? $this->defaultClassificationId(),
                'lifecycle_segment' => LifecycleSegment::Acquisition,
                'acquisition_stage' => AcquisitionStage::BusinessDevelopment,
                'outcome' => BusinessOutcome::Open,
                'owner_employee_id' => $data['owner_employee_id'] ?? $this->actor->personnelId(),
            ]);

            // Potansiyel isin kodu: POTIS-YYYY-NNNN (B40, D-132); oncesinde TKLF-n.
            $code = $this->issueCode($case, BusinessCodeKind::Potential);

            Opportunity::query()->create([
                'business_case_id' => $case->getKey(),
                'stage' => OpportunityStage::Identified,
                'probability_pct' => 0,
                'expected_value' => $data['estimated_value'] ?? null,
            ]);

            $this->recordActivity($case, 'code_issued', ['kod' => $code->formatted_code, 'business_case_id' => $case->getKey(), 'business_code_id' => $code->getKey()]);

            if ($syncScopes) {
                app(BusinessCaseScopeService::class)->sync($case, $scopeTypes, $scopeRows);
            }

            $this->applyChecklist($case, $checklist, $syncScopes);

            return $case;
        });
    }

    /**
     * Dogrudan proje olusturma icin (D-68): teklif sureci yasanmamis,
     * gecmiste yapilmis veya halen suren bir is. Kanonik zincir korunur —
     * business case yine acilir ama TKLF kodu ve firsat kaydi uretilmez;
     * ayni global siradan yalniz PRJ-n kodu ayrilir ve is dogrudan
     * `operation` segmentinde `handover_accepted` asamasinda baslar.
     *
     * @param  array<string, mixed>  $data  title, primary_party_id, country_code, currency_code, project_type_code, short_description, criticality, owner_employee_id, classification_id, legal_entity_id
     * @return array{0: BusinessCase, 1: BusinessCode}
     */
    public function createForProject(array $data): array
    {
        return $this->transactions->run(function () use ($data): array {
            $sequenceNo = $this->allocator->handle();
            $now = Carbon::now('UTC');

            /** @var BusinessCase $case */
            $case = parent::create([
                ...$data,
                'sequence_no' => $sequenceNo,
                'legal_entity_id' => $data['legal_entity_id'] ?? $this->defaultLegalEntityId(),
                'classification_id' => $data['classification_id'] ?? $this->defaultClassificationId(),
                'source_kind' => $data['source_kind'] ?? 'manual',
                'lifecycle_segment' => LifecycleSegment::Operation,
                'acquisition_stage' => AcquisitionStage::HandoverAccepted,
                'outcome' => BusinessOutcome::Won,
                'outcome_at' => $now,
                'outcome_reason_code' => 'direct_project',
                'owner_employee_id' => $data['owner_employee_id'] ?? $this->actor->personnelId(),
            ]);

            // Proje kodu: PRJ-YYYY-NNNN (B40, D-132); oncesinde PRJ-n.
            $code = $this->issueCode($case, BusinessCodeKind::Project);

            $this->recordActivity($case, 'code_issued', ['kod' => $code->formatted_code, 'business_case_id' => $case->getKey(), 'business_code_id' => $code->getKey(), 'kaynak' => 'dogrudan proje']);

            return [$case, $code];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['sequence_no'], $data['lifecycle_segment'], $data['acquisition_stage'], $data['outcome'], $data['outcome_at']);

        return $this->transactions->run(function () use ($record, $data): Model {
            [$data, $scopeTypes, $scopeRows, $syncScopes] = $this->extractScopes($data);
            [$data, $checklist] = $this->extractChecklist($data);

            /** @var BusinessCase $case */
            $case = parent::update($record, $data);

            if ($syncScopes) {
                app(BusinessCaseScopeService::class)->sync($case, $scopeTypes, $scopeRows);
            }

            $this->applyChecklist($case, $checklist, $syncScopes);

            return $case;
        });
    }

    /**
     * Kontrol listesinden teklif sicakligi ve GES 1.3 kurali (B43, D-155):
     * potansiyel isin proje tiplerinin baktigi listelerin kayitli cevaplariyla
     * heat_score yeniden hesaplanir; 1.3 "Hayir" ise teklif tipi Butcesel olur
     * (hareket kaydiyla). Teklif tipi degistiyse true doner.
     */
    public function refreshChecklistState(BusinessCase $case): bool
    {
        if (! SchemaReadiness::hasBatch('B43')) {
            return false;
        }

        return $this->transactions->run(function () use ($case): bool {
            /** @var BusinessCase $locked */
            $locked = $this->lockForUpdate($case);
            $templates = ChecklistTemplates::forScopeTypes($locked->scopes()->pluck('scope_type')->all());
            $answers = app(BusinessCaseChecklistAnswerService::class)->answersFor($locked);
            // Lisansli projede Cagri mektubu opsiyonel, payi diger maddelere dagilir (D-157);
            // maddenin belgesi de sorulari gibi bir paydir (D-159).
            $license = $locked->license_status;
            $documents = app(BusinessCaseDocumentService::class)->itemPresence($locked);
            $attributes = ['heat_score' => ChecklistTemplates::heat($templates, $answers, $license, $documents)];
            $budgetary = in_array(ChecklistTemplates::GES, $templates, true)
                && ChecklistTemplates::forcesBudgetary($answers, $license)
                && $locked->offer_type !== OfferType::Budgetary;

            if ($budgetary) {
                $attributes['offer_type'] = OfferType::Budgetary;
            }

            $locked->forceFill($attributes);

            if ($locked->isDirty()) {
                $locked->save();
            }

            if ($budgetary) {
                $this->recordActivity($locked, 'offer_type_budgetary', ['kural' => 'GES 1.3', 'teklif_tipi' => OfferType::Budgetary->value]);
            }

            $case->setRawAttributes($locked->getAttributes(), true);

            return $budgetary;
        });
    }

    /**
     * SM-BC gecisi. Kazanma/kaybetme/iptalde outcome da guncellenir.
     *
     * $reasonCode kod bicimindeyse (or. direct_project) outcome_reason_code
     * kolonuna (32) yazilir; "Durum degistir" penceresindeki serbest gerekce
     * metni yalniz etkinlik gecmisine yazilir (28 Eylul 2026: uzun gerekce
     * kolonu tasiriyordu).
     */
    public function changeStage(Model|int|string $record, AcquisitionStage $target, ?string $reasonCode = null): BusinessCase
    {
        return $this->transactions->run(function () use ($record, $target, $reasonCode): BusinessCase {
            $code = $reasonCode !== null && preg_match('/^[a-z0-9_.-]{1,32}$/', $reasonCode) === 1 ? $reasonCode : null;
            /** @var BusinessCase $case */
            $case = $this->lockForUpdate($record);
            $from = $case->acquisition_stage;

            if (! $from->canTransitionTo($target)) {
                throw InvalidTransitionException::make(['from' => $from->getLabel(), 'to' => $target->getLabel()]);
            }

            $attributes = ['acquisition_stage' => $target];

            $outcome = match ($target) {
                AcquisitionStage::Won, AcquisitionStage::HandoverPreparing, AcquisitionStage::HandoverReview, AcquisitionStage::HandoverAccepted => BusinessOutcome::Won,
                AcquisitionStage::Lost => BusinessOutcome::Lost,
                AcquisitionStage::Cancelled => BusinessOutcome::Cancelled,
                default => null,
            };

            if ($outcome !== null && $case->outcome !== $outcome) {
                $attributes['outcome'] = $outcome;
                $attributes['outcome_at'] = Carbon::now('UTC');
                $attributes['outcome_reason_code'] = $code;
            }

            if ($target === AcquisitionStage::HandoverAccepted) {
                $attributes['lifecycle_segment'] = LifecycleSegment::Operation;
            }

            $case->forceFill($attributes)->save();

            $this->recordActivity($case, 'stage_changed', [
                'asama' => ['onceki' => $from->value, 'yeni' => $target->value],
                'gerekce' => $reasonCode,
            ]);

            // D-161: kaybedilen potansiyel isin teklifleri "Kacan firsat" olur.
            if ($target === AcquisitionStage::Lost) {
                app(ProposalService::class)->syncOfferStatus(Proposal::query()->where('business_case_id', $case->getKey())->get(), OfferStatus::Lost);
            }

            return $case;
        });
    }

    /** Potansiyel isin kodu (POTIS; B40 oncesi kayitlarda eski TKLF-n). */
    public function caseCode(BusinessCase $case): ?BusinessCode
    {
        return $case->loadMissing('codes')->caseCode();
    }

    /**
     * Is kodu verir (D-132). B40 uygulandiysa yillik kod (POTIS / PRJ -YYYY-NNNN,
     * YearlyCodeAllocator); uygulanmadiysa eski uretilmis kolon: potansiyel is
     * kodu "offer" turunde TKLF-n, proje PRJ-n. Ayni transaction icinde cagrilir.
     */
    public function issueCode(BusinessCase $case, BusinessCodeKind $kind, ?int $predecessorId = null): BusinessCode
    {
        $attributes = [
            'business_case_id' => $case->getKey(),
            'sequence_no' => $case->sequence_no,
            'code_kind' => $kind,
            'issued_at' => Carbon::now('UTC'),
            'issued_by_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
            'predecessor_code_id' => $predecessorId,
            'status' => BusinessCodeStatus::Active,
        ];

        if ($this->codes->enabled()) {
            $attributes = [...$attributes, ...$this->codes->next($kind->prefix())];
        } elseif ($kind === BusinessCodeKind::Potential) {
            $attributes['code_kind'] = BusinessCodeKind::Offer;
        }

        /** @var BusinessCode $code */
        $code = BusinessCode::query()->create($attributes);
        $code->refresh();

        return $code;
    }

    /**
     * Sihirbazin kapsam anahtarlarini (`scope_types`, `scopes`) is dosyasi
     * verisinden ayirir. Kapsamlar yalniz B29 uygulanmis ve `scope_types`
     * anahtari gelmisse islenir; grup yokken `offer_type` da yazilmaz ki
     * eski sema uzerinde ekranlar calismaya devam etsin.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<mixed>, 2: array<string, mixed>, 3: bool}
     */
    private function extractScopes(array $data): array
    {
        $present = array_key_exists('scope_types', $data);
        $types = is_array($data['scope_types'] ?? null) ? array_values($data['scope_types']) : [];
        $rows = is_array($data['scopes'] ?? null) ? $data['scopes'] : [];
        unset($data['scope_types'], $data['scopes']);

        $ready = SchemaReadiness::hasBatch('B29');

        if (! $ready) {
            unset($data['offer_type']);
        }

        // B43: tutarlar teklif kapsamidir; potansiyel iste yalniz tip satiri kalir.
        if (SchemaReadiness::hasBatch('B43')) {
            $rows = [];
        }

        return [$data, $types, $rows, $present && $ready];
    }

    /**
     * Kontrol listesi ve belge anahtarlarini ayirir (B43). Grup uygulanmadiysa
     * B43 kolonlari da cikarilir; heat_score hicbir zaman formdan yazilmaz.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array{checklist: array<string, mixed>|null, files: mixed, names: mixed}|null}
     */
    private function extractChecklist(array $data): array
    {
        $payload = [
            'checklist' => is_array($data['checklist'] ?? null) ? $data['checklist'] : null,
            'files' => $data['case_document_files'] ?? null,
            'names' => $data['case_document_files_name'] ?? null,
        ];
        unset($data['checklist'], $data['case_document_files'], $data['case_document_files_name'], $data['heat_score']);

        if (! SchemaReadiness::hasBatch('B43')) {
            unset($data['license_status'], $data['is_draft'], $data['draft_step']);

            return [$data, null];
        }

        if ($payload['checklist'] === null && blank($payload['files'])) {
            return [$data, null];
        }

        return [$data, $payload];
    }

    /**
     * Cevaplar ve belgeler; ardindan sicaklik. Proje tipi degistiyse (liste
     * degisebilir) kontrol listesi acikken cevap gelmese de sicaklik tazelenir.
     *
     * @param  array{checklist: array<string, mixed>|null, files: mixed, names: mixed}|null  $payload
     */
    private function applyChecklist(BusinessCase $case, ?array $payload, bool $scopesChanged): void
    {
        if ($payload !== null) {
            if ($payload['checklist'] !== null) {
                app(BusinessCaseChecklistAnswerService::class)->sync($case, $payload['checklist']);
            }

            app(BusinessCaseDocumentService::class)->syncFromForm($case, $payload['checklist'] ?? [], $payload['files'], $payload['names']);
        }

        if (($payload['checklist'] ?? null) !== null || ($scopesChanged && FeatureFlags::enabled(Feature::BusinessCaseChecklist))) {
            $this->refreshChecklistState($case);
        }
    }

    private function defaultLegalEntityId(): int
    {
        return (int) (LegalEntity::query()
            ->where('code', (string) config('konelsis.legal_entity.code', 'KONELSIS_MAIN'))
            ->value('id') ?? LegalEntity::query()->value('id'));
    }

    private function defaultClassificationId(): int
    {
        return (int) SecurityClassification::query()->where('code', ClassificationCode::Internal)->value('id');
    }
}
