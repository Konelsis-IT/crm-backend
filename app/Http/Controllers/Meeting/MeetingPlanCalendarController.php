<?php

declare(strict_types=1);

namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Personnel;
use App\Query\Party\MeetingPlanQueries;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Gorusme plani takvim verisi (B34, D-109): GET ?month=YYYY-MM&personnel=ID.
 * Sosyal medya takviminin gun bicimiyle ({date, items}) doner. Liste izni
 * olmayan personel yalniz kendi (sorumlu / katilan) gorusmelerini gorur.
 */
final class MeetingPlanCalendarController extends Controller
{
    public function __invoke(Request $request, MeetingPlanQueries $queries): JsonResponse
    {
        abort_unless(SchemaReadiness::hasBatch('B34'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel, 403);

        $month = (string) $request->query('month', MeetingPlanQueries::today()->format('Y-m'));
        abort_unless(preg_match('/^(19[7-9]\d|[2-9]\d{3})-(0[1-9]|1[0-2])$/', $month) === 1, 422);

        $personnelId = filled($request->query('personnel')) ? (int) $request->query('personnel') : null;

        if (! Gate::forUser($user)->allows('viewAny', MeetingPlan::class)) {
            $personnelId = (int) $user->getKey();
        }

        return response()->json($queries->month($month, $personnelId));
    }
}
