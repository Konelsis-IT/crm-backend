<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\ControlMatrixQueries;
use App\Query\Report\WorkAnalysisQueries;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Is raporlari JSON uclari (B36, D-115): analiz panosu ve personel
 * kartindaki Dikkat karti.
 */
final class WorkInsightController extends Controller
{
    /** Analiz panosu: donem (ay), departman, proje, onceki donemle karsilastirma. */
    public function analysis(Request $request, WorkAnalysisQueries $queries): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('viewAnalytics', WorkItem::class);

        return response()->json($queries->dashboard(
            $me,
            (string) $request->query('month', ''),
            filled($request->query('unit')) ? (int) $request->query('unit') : null,
            filled($request->query('project')) ? (int) $request->query('project') : null,
            filter_var($request->query('compare', '1'), FILTER_VALIDATE_BOOLEAN),
        ));
    }

    /** Dikkat karti: ozel yetki; kisi kendi kartini gormez. */
    public function attention(Request $request, Personnel $personnel, ControlMatrixQueries $queries): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('viewAttentionCard', [WorkItem::class, $personnel]);

        return response()->json($queries->attentionCard($personnel));
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B36'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }
}
