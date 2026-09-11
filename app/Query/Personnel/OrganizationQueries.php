<?php

declare(strict_types=1);

namespace App\Query\Personnel;

use App\Models\Personnel\OrgUnit;

/**
 * Organizasyon ekranlarinin okuma sorgulari.
 */
final class OrganizationQueries
{
    /**
     * Ust birim secim listesi; verilen id kendisiyle secilemesin diye disarida birakilir.
     *
     * @return array<int, string>
     */
    public function orgUnitOptions(?int $excludingId = null): array
    {
        return OrgUnit::query()
            ->when($excludingId !== null, fn ($query) => $query->whereKeyNot($excludingId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
