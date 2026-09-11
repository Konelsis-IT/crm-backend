<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\BusinessDevelopmentActivity;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Is gelistirme aktivitesi servisi (10 SS2.5): organizator ve zaman
 * varsayilani; party verilmezse business case'in birincil party'si yazilir.
 */
final class BusinessDevelopmentActivityService extends AbstractService
{
    protected string $model = BusinessDevelopmentActivity::class;

    /** @var list<string> */
    protected array $with = ['businessCase', 'party', 'organizer', 'participants'];

    protected string $orderBy = 'occurred_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        if (blank($data['party_id'] ?? null) && filled($data['business_case_id'] ?? null)) {
            $data['party_id'] = \App\Models\Acquisition\BusinessCase::query()->whereKey((int) $data['business_case_id'])->value('primary_party_id');
        }

        return parent::create([
            ...$data,
            'organizer_employee_id' => $data['organizer_employee_id'] ?? $this->actor->personnelId(),
            'occurred_at' => $data['occurred_at'] ?? Carbon::now('UTC'),
            'timezone' => $data['timezone'] ?? (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul'),
        ]);
    }
}
