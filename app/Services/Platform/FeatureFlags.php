<?php

declare(strict_types=1);

namespace App\Services\Platform;

final class FeatureFlags
{
    /**
     * Flag keys (e.g. "identity.admin_ui") are literal array keys in
     * config/features.php, not nested config paths. Fetching the whole
     * `flags` array first and indexing into it avoids Laravel's config()
     * dot-notation splitting the flag name itself.
     */
    public static function enabled(string $flag): bool
    {
        $flags = (array) config('features.flags', []);

        return (bool) ($flags[$flag] ?? false);
    }
}
