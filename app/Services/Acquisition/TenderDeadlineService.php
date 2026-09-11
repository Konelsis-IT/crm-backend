<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\TenderDeadline;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ihale son tarihi servisi (10 SS2.11): yerel tarih + saat + saat dilimi
 * girilir, kritik tetikleyici kaynagi olan due_at_utc burada hesaplanir.
 */
final class TenderDeadlineService extends AbstractService
{
    protected string $model = TenderDeadline::class;

    protected string $orderBy = 'due_at_utc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return parent::create($this->withUtc($data, null));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var TenderDeadline $current */
        $current = $this->show($record);
        unset($data['tender_notice_version_id'], $data['alert_generated_at']);

        return parent::update($current, $this->withUtc($data, $current));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withUtc(array $data, ?TenderDeadline $current): array
    {
        $date = $data['local_due_date'] ?? $current?->local_due_date?->toDateString();
        $time = $data['local_due_time'] ?? $current?->local_due_time ?? '17:00:00';
        $timezone = $data['timezone'] ?? $current?->timezone ?? 'Europe/Istanbul';

        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        }

        if (filled($date)) {
            $data['timezone'] = $timezone;
            $data['local_due_time'] = $time;
            $data['due_at_utc'] = Carbon::parse("{$date} {$time}", $timezone)->utc();
        }

        return $data;
    }
}
