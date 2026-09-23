<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Exceptions\AbstractException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\ControlMatrixQueries;
use App\Query\Report\WorkItemQueries;
use App\Reports\Work\ControlSectionCatalog;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Kontrol matrisi JSON uclari (B36, D-115; gunluk doldurma D-116): bolum
 * sekmeleri, gunun isaretleri, haftanin toplami ve Kaydet (kisi + bolum +
 * gun basina bir kontrol raporu).
 *
 * Ekrani acmak: `ControlMatrix:WorkItem` izni (IK) ya da ust yonetim.
 * Doldurmak: yalniz IK (ust yonetim salt okunur).
 */
final class ControlMatrixController extends Controller
{
    public function __construct(
        private readonly ControlMatrixQueries $queries,
        private readonly ControlSectionCatalog $sections,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $canFill = Gate::forUser($me)->allows('fillControl', WorkItem::class);
        $sections = $this->queries->sectionList();
        $codes = array_column($sections, 'code');
        $section = in_array($request->query('section'), $codes, true) ? (string) $request->query('section') : ($codes[0] ?? null);
        // Gorunum secilmediyse: dolduran IK gunluk, salt okuyan ust yonetim haftalik baslar.
        $requested = (string) $request->query('mode', '');
        $mode = in_array($requested, ['day', 'week'], true) ? $requested : ($canFill ? 'day' : 'week');
        $day = WorkItemQueries::day((string) $request->query('day', ''));
        $week = WorkItemQueries::weekStart($mode === 'week'
            ? WorkItemQueries::day((string) $request->query('week', ''))
            : $day);

        return response()->json([
            'sections' => $sections,
            'section' => $section,
            'mode' => $mode,
            'sheet' => $section === null ? null : ($mode === 'week'
                ? $this->queries->weekSheet($section, $week)
                : $this->queries->daySheet($section, $day)),
            'day' => $day->format('Y-m-d'),
            'week' => $week->format('Y-m-d'),
            'today' => WorkItemQueries::today()->format('Y-m-d'),
            'today_week' => WorkItemQueries::weekStart(WorkItemQueries::today())->format('Y-m-d'),
            'can' => ['fill' => $canFill],
        ]);
    }

    public function save(Request $request, ReportService $reports): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('fillControl', WorkItem::class);

        $data = $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'section' => ['required', 'string', 'max:64'],
            'rows' => ['required', 'array', 'max:300'],
            'rows.*.personnel_id' => ['required', 'integer'],
            'rows.*.marks' => ['nullable', 'array'],
            'rows.*.marks.*' => ['nullable', 'in:ok,bad,none'],
            'rows.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->sections->has((string) $data['section'])) {
            abort(422);
        }

        // Yalniz bolumun uyeleri kaydedilir.
        $members = $this->queries->members((string) $data['section'])->map(fn (Personnel $person): int => (int) $person->getKey())->all();
        $rows = array_values(array_filter(
            array_map(static fn (array $row): array => [
                'personnel_id' => (int) $row['personnel_id'],
                'marks' => is_array($row['marks'] ?? null) ? $row['marks'] : [],
                'note' => $row['note'] ?? null,
            ], $data['rows']),
            static fn (array $row): bool => in_array($row['personnel_id'], $members, true),
        ));

        try {
            $result = $reports->saveControlSheet((string) $data['section'], (string) $data['day'], $rows);
        } catch (AbstractException $exception) {
            return response()->json(['message' => $exception->userMessage(), 'code' => 'business_rule'], 422);
        }

        return response()->json([
            ...$result,
            'sheet' => $this->queries->daySheet((string) $data['section'], WorkItemQueries::day((string) $data['day'])),
        ]);
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B36'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);
        Gate::forUser($user)->authorize('controlMatrix', WorkItem::class);

        return $user;
    }
}
