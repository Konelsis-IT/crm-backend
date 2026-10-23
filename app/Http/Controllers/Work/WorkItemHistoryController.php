<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Services\Report\WorkItemHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Isin Gecmis sekmesi (D-248): isi gorebilen, iste kimin ne zaman ne yaptigini
 * adim adim okur. Yalniz okuma; adimlar Personel Hareketleri'nden gelir.
 */
final class WorkItemHistoryController extends Controller
{
    public function __invoke(Request $request, WorkItem $item, WorkItemHistory $history): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);
        Gate::forUser($user)->authorize('view', $item);

        return response()->json(['entries' => $history->entries($item)]);
    }
}
