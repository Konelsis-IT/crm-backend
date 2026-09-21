<?php

declare(strict_types=1);

namespace App\Query\Export;

use Filament\Actions\Exports\Models\Export;

/** Disa aktarma kayitlari (B35, D-110). */
final class ExportQueries
{
    /**
     * Personelin bu disa aktariciyla en son tamamlanan disa aktarimi. Kuyruksuz
     * akista bu, dugmeye basinca az once hazirlanan dosyadir.
     */
    public function latestCompleted(int $userId, string $exporter): ?Export
    {
        return Export::query()
            ->where('user_id', $userId)
            ->where('exporter', $exporter)
            ->whereNotNull('completed_at')
            ->latest('id')
            ->first();
    }
}
