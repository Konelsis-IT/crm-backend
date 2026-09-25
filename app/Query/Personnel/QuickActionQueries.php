<?php

declare(strict_types=1);

namespace App\Query\Personnel;

use App\Models\Personnel\PersonnelQuickAction;
use App\Services\Platform\SchemaReadiness;

/**
 * Kisisel hizli islemler (B38, D-122): kisinin sectigi islem kodlari.
 * B38 uygulanmamissa bos doner; ekranda yalniz sabit islemler gorunur.
 */
final class QuickActionQueries
{
    /**
     * Kisinin sectigi islem kodlari, sirasiyla.
     *
     * @return list<string>
     */
    public function codesFor(int $personnelId): array
    {
        if (! SchemaReadiness::hasBatch('B38')) {
            return [];
        }

        return PersonnelQuickAction::query()
            ->where('personnel_id', $personnelId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('action_code')
            ->map(fn ($code): string => (string) $code)
            ->all();
    }
}
