<?php

declare(strict_types=1);

namespace App\Services\Numbering;

use App\Models\Acquisition\BusinessCodeSequence;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;

/**
 * Yila gore kod ayirir (D-132, B40): "POTIS-2026-0001", "TKLF-2027-0012",
 * "PRJ-2026-0003". Yil kurum saatine gore bugunun yilidir (D-114); her on ek
 * icin sayac her yil 1'den baslar.
 *
 * Sayac satiri ayni transaction icinde kilitlenip artirilir: es zamanli iki
 * kayit ayni numarayi alamaz, islem geri alinirsa numara da geri alinir
 * (eski global siradaki gibi numara bosa gitmez).
 */
final class YearlyCodeAllocator
{
    public function __construct(private readonly TransactionRunner $transactions) {}

    /** B40 uygulanmis mi (yillik kodlar acik mi)? */
    public function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B40');
    }

    /**
     * @return array{formatted_code: string, code_year: int, year_sequence: int}
     */
    public function next(string $prefix): array
    {
        $this->transactions->assertInTransaction('YearlyCodeAllocator::next');

        $year = (int) Carbon::now(DisplayTime::zone())->format('Y');

        BusinessCodeSequence::query()->insertOrIgnore([
            'code_prefix' => $prefix,
            'code_year' => $year,
            'last_number' => 0,
        ]);

        /** @var BusinessCodeSequence $sequence */
        $sequence = BusinessCodeSequence::query()
            ->where('code_prefix', $prefix)
            ->where('code_year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $sequence->last_number + 1;
        $sequence->forceFill(['last_number' => $number])->save();

        return [
            'formatted_code' => sprintf('%s-%d-%04d', $prefix, $year, $number),
            'code_year' => $year,
            'year_sequence' => $number,
        ];
    }
}
