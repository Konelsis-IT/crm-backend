<?php

declare(strict_types=1);

namespace App\Http\Controllers\Files;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Http\Controllers\Controller;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Personnel\Personnel;
use App\Services\Document\DocumentBundleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Tum belgeleri indir" (D-176): kaydin belgeleri klasorlu tek ZIP.
 * Rotalar panelin kimlik dogrulamali grubunda, ozellik anahtariyla
 * (RequireFeature: documents.bundles). Kaydin gorme yetkisi burada, belge basina
 * yetki DocumentBundleService'te denetlenir. Hata olursa (belge yok, ZIP
 * yazilamadi) kisi geldigi sayfaya bildirimle doner.
 */
final class DocumentBundleController extends Controller
{
    public function proposal(Request $request, Proposal $proposal, DocumentBundleService $bundles): Response
    {
        Gate::authorize('view', $proposal);

        return $this->respond($request, fn (Personnel $viewer): array => $bundles->forProposal($proposal, $viewer));
    }

    public function businessCase(Request $request, BusinessCase $businessCase, DocumentBundleService $bundles): Response
    {
        Gate::authorize('view', $businessCase);

        return $this->respond($request, fn (Personnel $viewer): array => $bundles->forBusinessCase($businessCase, $viewer));
    }

    /**
     * @param  callable(Personnel): array{path: string, name: string, count: int}  $build
     */
    private function respond(Request $request, callable $build): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof Personnel, 403);

        // Buyuk belgelerde (D-127: 1 GB'a kadar) ZIP yazimi uzun surebilir.
        @set_time_limit(0);

        try {
            $bundle = $build($viewer);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return redirect()->back();
        }

        return response()
            ->download($bundle['path'], $bundle['name'], ['Content-Type' => 'application/zip', 'X-Content-Type-Options' => 'nosniff'])
            ->deleteFileAfterSend();
    }
}
