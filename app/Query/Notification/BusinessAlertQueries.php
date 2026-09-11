<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Enums\Notification\AlertState;
use App\Models\Notification\BusinessAlert;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class BusinessAlertQueries
{
    /** Panoda listelenecek acik uyarilar: sahibi ben ya da (yetkili ise) hepsi. */
    public function openForPanel(int $personnelId, bool $seesAll): Builder
    {
        $query = BusinessAlert::query()
            ->with(['owner', 'project'])
            ->whereIn('state', [AlertState::Open->value, AlertState::Acknowledged->value])
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'warning')")
            ->orderBy('due_at');

        if (! $seesAll) {
            $query->where('owner_personnel_id', $personnelId);
        }

        return $query;
    }

    /** Tarama icin: acik son tarih uyarilari. */
    public function openDeadlineAlerts(): Collection
    {
        return BusinessAlert::query()
            ->where('trigger_code', 'like', 'deadline.%')
            ->whereIn('state', [AlertState::Open->value, AlertState::Acknowledged->value])
            ->get();
    }

    public function openCountFor(int $personnelId): int
    {
        if (! SchemaReadiness::hasBatch('B11A')) {
            return 0;
        }

        return BusinessAlert::query()
            ->where('owner_personnel_id', $personnelId)
            ->where('state', AlertState::Open->value)
            ->count();
    }
}
