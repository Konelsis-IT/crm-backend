<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\BusinessCodeStatus;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\LifecycleSegment;
use App\Enums\Acquisition\OpportunityStage;
use App\Enums\Reference\ClassificationCode;
use App\Exceptions\InvalidTransitionException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCode;
use App\Models\Acquisition\Opportunity;
use App\Models\Reference\LegalEntity;
use App\Models\Reference\SecurityClassification;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Numbering\AllocateBusinessNumber;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Business case (tek ticari is koku) servisi (10 SS2, 14 SS2.21 SM-BC).
 *
 * create override edilmistir: global sira numarasi ayrilir
 * (AllocateBusinessNumber), ayni transaction'da TKLF-n kodu
 * (business_codes) ve 1:1 firsat kaydi (opportunities) acilir.
 *
 * changeStage bes temel islemin disinda, SM-BC gecislerini uygular;
 * handover_accepted yalniz OperationHandoffService::accept() icinde
 * yazilir.
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
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
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

            $code = BusinessCode::query()->create([
                'business_case_id' => $case->getKey(),
                'sequence_no' => $sequenceNo,
                'code_kind' => BusinessCodeKind::Offer,
                'issued_at' => Carbon::now('UTC'),
                'issued_by_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
                'status' => BusinessCodeStatus::Active,
            ]);

            Opportunity::query()->create([
                'business_case_id' => $case->getKey(),
                'stage' => OpportunityStage::Identified,
                'probability_pct' => 0,
                'expected_value' => $data['estimated_value'] ?? null,
            ]);

            $this->recordActivity($case, 'code_issued', ['kod' => 'TKLF-'.$sequenceNo, 'business_case_id' => $case->getKey(), 'business_code_id' => $code->getKey()]);

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

            /** @var BusinessCode $code */
            $code = BusinessCode::query()->create([
                'business_case_id' => $case->getKey(),
                'sequence_no' => $sequenceNo,
                'code_kind' => BusinessCodeKind::Project,
                'issued_at' => $now,
                'issued_by_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
                'status' => BusinessCodeStatus::Active,
            ]);
            $code->refresh();

            $this->recordActivity($case, 'code_issued', ['kod' => 'PRJ-'.$sequenceNo, 'business_case_id' => $case->getKey(), 'business_code_id' => $code->getKey(), 'kaynak' => 'dogrudan proje']);

            return [$case, $code];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['sequence_no'], $data['lifecycle_segment'], $data['acquisition_stage'], $data['outcome'], $data['outcome_at']);

        return parent::update($record, $data);
    }

    /**
     * SM-BC gecisi. Kazanma/kaybetme/iptalde outcome da guncellenir.
     */
    public function changeStage(Model|int|string $record, AcquisitionStage $target, ?string $reasonCode = null): BusinessCase
    {
        return $this->transactions->run(function () use ($record, $target, $reasonCode): BusinessCase {
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
                $attributes['outcome_reason_code'] = $reasonCode;
            }

            if ($target === AcquisitionStage::HandoverAccepted) {
                $attributes['lifecycle_segment'] = LifecycleSegment::Operation;
            }

            $case->forceFill($attributes)->save();

            $this->recordActivity($case, 'stage_changed', [
                'asama' => ['onceki' => $from->value, 'yeni' => $target->value],
                'gerekce' => $reasonCode,
            ]);

            return $case;
        });
    }

    /** Aktif TKLF kodu. */
    public function offerCode(BusinessCase $case): ?BusinessCode
    {
        return $case->codes()->where('code_kind', BusinessCodeKind::Offer->value)->first();
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
