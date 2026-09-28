<?php

declare(strict_types=1);

namespace App\Query\Platform;

use App\Models\Platform\PlatformFeature;
use Illuminate\Support\Collection;

/**
 * Ozellik anahtarlari (B39, D-128): tablonun tamami, koda gore.
 * Tablo kucuktur (katalog kadar satir); istek basina bir kez okunur.
 */
final class FeatureQueries
{
    /**
     * @return Collection<string, PlatformFeature>
     */
    public function all(): Collection
    {
        return PlatformFeature::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy(fn (PlatformFeature $row): string => (string) $row->code);
    }
}
