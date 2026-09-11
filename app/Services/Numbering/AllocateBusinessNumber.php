<?php

declare(strict_types=1);

namespace App\Services\Numbering;

use App\Enums\Reference\AllocationPurpose;
use App\Enums\Reference\AllocationStatus;
use App\Models\Reference\BusinessNumberAllocation;
use App\Services\Audit\ActorContext;
use App\Services\Support\TransactionRunner;
use Illuminate\Support\Carbon;

/**
 * TKLF-n / PRJ-n kodlarinda kullanilan tek global sira numarasini ayirir.
 * Is kaydini olusturan transaction icinde cagrilir; iptal halinde olusan
 * bosluk kabul edilir, numara tekrar kullanilmaz.
 */
final class AllocateBusinessNumber
{
    public function __construct(
        private readonly ActorContext $actor,
        private readonly TransactionRunner $transactions,
    ) {}

    public function handle(AllocationPurpose $purpose = AllocationPurpose::BusinessCase): int
    {
        $this->transactions->assertInTransaction('AllocateBusinessNumber::handle');

        $allocation = BusinessNumberAllocation::query()->create([
            'purpose' => $purpose,
            'status' => AllocationStatus::Assigned,
            'allocated_at' => Carbon::now('UTC'),
            'allocated_by_personnel_id' => $this->actor->personnelId(),
        ]);

        return (int) $allocation->getKey();
    }
}
