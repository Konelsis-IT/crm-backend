<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kapali ozelligin uclari yok sayilir (D-128): `RequireFeature::class.':work.board'`.
 * Ekran gizlense de JSON / dosya ucu dogrudan cagrilamasin diye rota grubuna eklenir.
 */
final class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(FeatureFlags::enabled(Feature::from($feature)), 404);

        return $next($request);
    }

    /** Rota tanimi icin: RequireFeature::for(Feature::WorkBoard). */
    public static function for(Feature $feature): string
    {
        return self::class.':'.$feature->value;
    }
}
