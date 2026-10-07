<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Concerns;

use App\Exceptions\AbstractException;
use App\Models\Report\Report;
use App\Reports\Formatting\ReportFormatter;
use App\Services\Report\ReportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Json;

/**
 * "Kopyala" (D-167): tarayicidaki konelsisReportCopy betigi bu yontemi
 * cagirir ($wire.reportCopyPayload(id)) ve donen zengin metni (HTML) ile duz
 * metni panoya yazar. Rapor gorme yetkisi yoksa bos doner.
 */
trait CopiesReportText
{
    /**
     * JSON yontemi: sayfa yeniden cizilmez, sonuc dogrudan betige doner.
     *
     * @return array{html: string, text: string}|null
     */
    #[Json]
    public function reportCopyPayload(int|string $reportId): ?array
    {
        try {
            $report = app(ReportService::class)->show((int) $reportId);
        } catch (AbstractException) {
            return null;
        }

        if (! $report instanceof Report || Gate::denies('view', $report)) {
            return null;
        }

        return app(ReportFormatter::class)->clipboard($report);
    }
}
