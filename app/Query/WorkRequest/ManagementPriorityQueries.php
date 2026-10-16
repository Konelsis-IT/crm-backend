<?php

declare(strict_types=1);

namespace App\Query\WorkRequest;

use App\Enums\Platform\Feature;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Platform\FeatureFlags;
use Illuminate\Database\Eloquent\Builder;

/**
 * Yonetim onceligi (D-216; kullanici 15 Ekim 2026: "yönetim tarafından
 * oluşturulan talepler daha önceliklidir. Personelden önce onun
 * önceliklendirilmesi gerekir ... iş ve talepleri buna bağlı kurgula").
 *
 * Yonetim = ust yonetim (ExecutiveDirectory: Yonetim kurulu baskani, Idari
 * mudur; pozisyona gore). Talebi ust yonetim actiysa ya da isi ust yonetim
 * talep ettiyse (is kartindaki "Talep eden") kayit yonetim kaydidir; acik
 * yonetim kayitlari listelerde personelinkilerden once gelir. Kapali kayitlarin
 * sirasi degismez.
 */
final class ManagementPriorityQueries
{
    public function __construct(private readonly ExecutiveDirectory $executives) {}

    public function enabled(): bool
    {
        return FeatureFlags::enabled(Feature::WorkRequestManagementPriority);
    }

    /** Kisi ust yonetimde mi (ozellik kapaliysa hic kimse). */
    public function isManagement(mixed $personnelId): bool
    {
        return $this->enabled()
            && is_numeric($personnelId)
            && in_array((int) $personnelId, $this->executives->executiveIds(), true);
    }

    /**
     * Acik yonetim kayitlarini one alan siralama (diger siralamalardan once eklenir).
     *
     * @param  list<string>  $openStatuses
     */
    public function openFirst(Builder $query, string $requesterColumn, string $statusColumn, array $openStatuses): Builder
    {
        $ids = $this->enabled() ? $this->executives->executiveIds() : [];

        if ($ids === [] || $openStatuses === []) {
            return $query;
        }

        $requester = $query->getModel()->qualifyColumn($requesterColumn);
        $status = $query->getModel()->qualifyColumn($statusColumn);
        $statusMarks = implode(', ', array_fill(0, count($openStatuses), '?'));

        return $query->orderByRaw(
            'case when '.$requester.' in ('.implode(', ', array_map('intval', $ids)).') and '.$status.' in ('.$statusMarks.') then 0 else 1 end',
            array_values($openStatuses),
        );
    }
}
