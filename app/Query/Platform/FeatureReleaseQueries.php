<?php

declare(strict_types=1);

namespace App\Query\Platform;

use App\Models\Platform\FeatureRelease;
use App\Services\Platform\SchemaReadiness;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Yayin kayitlari (B42, D-151). Gecerli yayin surumu en son eklenen satirdir;
 * satir yoksa (ya da B42 uygulanmamissa) yayin surumu yoktur ve surum suzgeci
 * uygulanmaz.
 */
final class FeatureReleaseQueries
{
    public function current(): ?FeatureRelease
    {
        if (! SchemaReadiness::hasBatch('B42')) {
            return null;
        }

        return FeatureRelease::query()->latest('id')->first();
    }

    public function currentVersion(): ?string
    {
        $version = $this->current()?->version;

        return filled($version) ? (string) $version : null;
    }

    /**
     * Her surumun ilk yayin ani (D-153: surum notlarinda gosterilen tarih).
     *
     * @return array<string, CarbonInterface>
     */
    public function publishedDates(): array
    {
        if (! SchemaReadiness::hasBatch('B42')) {
            return [];
        }

        $dates = [];

        foreach (FeatureRelease::query()->orderBy('id')->get(['version', 'published_at']) as $release) {
            if ($release->published_at !== null) {
                $dates[(string) $release->version] ??= $release->published_at;
            }
        }

        return $dates;
    }

    /**
     * Son yayinlar, en yenisi ustte.
     *
     * @return Collection<int, FeatureRelease>
     */
    public function history(int $limit = 10): Collection
    {
        if (! SchemaReadiness::hasBatch('B42')) {
            return new Collection;
        }

        return FeatureRelease::query()->latest('id')->limit(max(1, $limit))->get();
    }
}
