<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Exceptions\AbstractException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\WorkItemPresenter;
use App\Services\Report\WorkItemService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Is panosu JSON uclari (B36, D-115): pano verisi, kart yazma, durum
 * (surukle-birak), sira, oneriler, bagli kayit aramasi, gunu / haftayi kapat
 * ve Yonetim panosu dondurmasi. Yazma isleri WorkItemService'te, okumalar
 * WorkItemQueries'te, bicimleme WorkItemPresenter'da; burasi dogrulama, yetki
 * ve yanit bicimidir. Is hatalari 422, surum cakismasi 409: `{ message, code }`.
 */
final class WorkBoardController extends Controller
{
    public function __construct(
        private readonly WorkItemQueries $queries,
        private readonly WorkItemPresenter $presenter,
        private readonly WorkItemService $service,
    ) {}

    /** Pano: tip, tarih araligi, (proje panosunda) proje, (ekipte) departman. */
    public function board(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('viewAny', WorkItem::class);

        $scope = in_array($request->query('scope'), WorkItemQueries::SCOPES, true) ? (string) $request->query('scope') : 'mine';

        if ($scope === 'all') {
            Gate::forUser($me)->authorize('viewAll', WorkItem::class);
        } elseif ($scope === 'team') {
            Gate::forUser($me)->authorize('viewTeam', WorkItem::class);
        }

        [$from, $to, $preset] = $this->queries->range(
            is_string($request->query('range')) ? $request->query('range') : null,
            is_string($request->query('from')) ? $request->query('from') : null,
            is_string($request->query('to')) ? $request->query('to') : null,
        );

        $ids = static fn (mixed $value): array => array_values(array_filter(array_map(
            'intval',
            is_array($value) ? $value : array_filter(explode(',', (string) $value), 'strlen'),
        )));

        $cards = $this->queries->board($me, $scope, [
            'projects' => $ids($request->query('projects')),
            'units' => $ids($request->query('units')),
            'from' => $from,
            'to' => $to,
        ]);

        $suggestions = [];

        if ($scope === 'mine') {
            $activities = $this->queries->suggestionActivities($me);
            $subjects = $this->queries->suggestionSubjects($activities);

            foreach ($activities as $activity) {
                $row = $this->presenter->suggestion($activity, $subjects, $me->orgUnit?->code);

                if ($row !== null) {
                    $suggestions[] = $row;
                }
            }
        }

        return response()->json([
            'scope' => $scope,
            'range' => ['preset' => $preset, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'today' => WorkItemQueries::today()->format('Y-m-d'),
            'cards' => $cards->map(fn (WorkItem $item): array => $this->presenter->card($item, $me))->values()->all(),
            'suggestions' => $suggestions,
            'limited' => $cards->count() >= WorkItemQueries::BOARD_LIMIT,
        ]);
    }

    /** Yoksayilan oneriler (Kaynak suzgeci > Yoksayilanlar). */
    public function dismissed(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $activities = $this->queries->suggestionActivities($me, dismissed: true);
        $subjects = $this->queries->suggestionSubjects($activities);
        $rows = [];

        foreach ($activities as $activity) {
            $row = $this->presenter->suggestion($activity, $subjects, $me->orgUnit?->code, dismissed: true);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return response()->json(['suggestions' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('create', WorkItem::class);
        $data = $this->validated($request, $this->itemRules());

        return $this->respond(fn (): array => ['card' => $this->cardOf($this->service->create($data), $me)]);
    }

    public function update(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('update', $item);
        $data = $this->validated($request, [...$this->itemRules(), 'row_version' => ['nullable', 'integer']]);

        return $this->respond(fn (): array => ['card' => $this->cardOf($this->service->update($item, $data), $me)]);
    }

    /** Durum: surukle-birak ya da kart menusu. Yanit onceki durumu da dondurur (Geri al). */
    public function status(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('update', $item);
        $data = $this->validated($request, [
            'status' => ['required', Rule::enum(WorkItemStatus::class)],
            ...$this->waitingRules(),
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $previous = [
            'status' => $item->status?->value,
            'waiting_kind' => $item->waiting_kind?->value,
            'waiting_personnel_id' => $item->waiting_personnel_id,
            'waiting_party_id' => $item->waiting_party_id,
            'waiting_text' => $item->waiting_text,
        ];

        return $this->respond(fn (): array => [
            'card' => $this->cardOf($this->service->changeStatus($item, (string) $data['status'], $data), $me),
            'previous' => $previous,
        ]);
    }

    public function critical(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('update', $item);
        $data = $this->validated($request, ['critical' => ['required', 'boolean']]);

        return $this->respond(fn (): array => ['card' => $this->cardOf($this->service->setCritical($item, (bool) $data['critical']), $me)]);
    }

    public function destroy(Request $request, WorkItem $item): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('delete', $item);

        return $this->respond(function () use ($item): array {
            $this->service->delete($item);

            return ['ok' => true];
        });
    }

    /** Sutun ici sira. */
    public function reorder(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('viewAny', WorkItem::class);
        $data = $this->validated($request, [
            'status' => ['required', Rule::enum(WorkItemStatus::class)],
            'ids' => ['required', 'array', 'max:600'],
            'ids.*' => ['integer'],
            'moved' => ['nullable', 'integer'],
        ]);

        return $this->respond(function () use ($data): array {
            $this->service->reorder((string) $data['status'], array_map('intval', $data['ids']), isset($data['moved']) ? (int) $data['moved'] : null);

            return ['ok' => true];
        });
    }

    /** "Kart yap". */
    public function fromSuggestion(Request $request, int $activity): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('create', WorkItem::class);
        $data = $this->validated($request, [
            'title' => ['nullable', 'string', 'max:200'],
            'project_id' => ['nullable', 'integer'],
            'category_code' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in([WorkItemStatus::Planned->value, WorkItemStatus::InProgress->value, WorkItemStatus::Done->value])],
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);

        return $this->respond(fn (): array => ['card' => $this->cardOf($this->service->fromSuggestion($activity, $data), $me)]);
    }

    public function dismiss(Request $request, int $activity): JsonResponse
    {
        $this->me($request);

        return $this->respond(function () use ($activity): array {
            $this->service->dismissSuggestion($activity);

            return ['ok' => true];
        });
    }

    public function restore(Request $request, int $activity): JsonResponse
    {
        $this->me($request);

        return $this->respond(function () use ($activity): array {
            $this->service->restoreSuggestion($activity);

            return ['ok' => true];
        });
    }

    /** Bagli kayit aramasi. */
    public function links(Request $request): JsonResponse
    {
        $this->me($request);
        $kind = WorkItemLinkKind::tryFrom((string) $request->query('kind')) ?? WorkItemLinkKind::None;

        return response()->json(['items' => $this->queries->searchLinks($kind, (string) $request->query('q', ''))]);
    }

    /** Taraf aramasi (kimden bekleniyor). */
    public function parties(Request $request): JsonResponse
    {
        $this->me($request);

        return response()->json(['items' => $this->queries->searchParties((string) $request->query('q', ''))]);
    }

    /** "Gunu kapat" penceresinin verisi. */
    public function day(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json($this->presenter->dayPreview($me, WorkItemQueries::day((string) $request->query('date', ''))));
    }

    public function closeDay(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('create', Report::class);
        $data = $this->validated($request, [
            'date' => ['required', 'date_format:Y-m-d'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'array', 'max:100'],
            'plan.*' => ['nullable', 'string', 'max:200'],
            'extra_plan' => ['nullable', 'array', 'max:50'],
            'extra_plan.*' => ['nullable', 'string', 'max:200'],
            'submit' => ['required', 'boolean'],
        ]);

        return $this->respond(fn (): array => ['report' => $this->presenter->reportInfo(
            $this->service->closeDay((string) $data['date'], $data, (bool) $data['submit'])->load('reviewer'),
        )]);
    }

    /** "Haftayi kapat" penceresinin verisi. */
    public function week(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json($this->presenter->weekPreview($me, WorkItemQueries::day((string) $request->query('date', ''))));
    }

    public function closeWeek(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('create', Report::class);
        $data = $this->validated($request, [
            'date' => ['required', 'date_format:Y-m-d'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'array', 'max:100'],
            'plan.*' => ['nullable', 'string', 'max:200'],
            'extra_plan' => ['nullable', 'array', 'max:50'],
            'extra_plan.*' => ['nullable', 'string', 'max:200'],
            'submit' => ['required', 'boolean'],
        ]);

        return $this->respond(fn (): array => ['report' => $this->presenter->reportInfo(
            $this->service->closeWeek((string) $data['date'], $data, (bool) $data['submit'])->load('reviewer'),
        )]);
    }

    /** Yonetim panosunu dondur. */
    public function freeze(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::forUser($me)->authorize('viewAll', WorkItem::class);
        $data = $this->validated($request, [
            'ids' => ['required', 'array', 'max:600'],
            'ids.*' => ['integer'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->respond(fn (): array => ['report' => $this->presenter->reportInfo(
            $this->service->freezeBoard(array_map('intval', $data['ids']), $data['summary'] ?? null),
        )]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'personnel_id' => ['nullable', 'integer'],
            'org_unit_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'category_code' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::enum(WorkItemStatus::class)],
            'is_critical' => ['nullable', 'boolean'],
            'work_on' => ['nullable', 'date_format:Y-m-d'],
            'work_time' => ['nullable', 'date_format:H:i'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            ...$this->waitingRules(),
            'requester_kind' => ['nullable', Rule::enum(WorkWaitingKind::class)],
            'requester_personnel_id' => ['nullable', 'integer'],
            'requester_party_id' => ['nullable', 'integer'],
            'requester_text' => ['nullable', 'string', 'max:200'],
            'link_kind' => ['nullable', Rule::enum(WorkItemLinkKind::class)],
            'link_id' => ['nullable', 'integer'],
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waitingRules(): array
    {
        return [
            'waiting_kind' => ['nullable', Rule::enum(WorkWaitingKind::class)],
            'waiting_personnel_id' => ['nullable', 'integer'],
            'waiting_party_id' => ['nullable', 'integer'],
            'waiting_text' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardOf(WorkItem $item, Personnel $me): array
    {
        $fresh = $this->queries->newQuery()->withCount('children')->whereKey($item->getKey())->first();

        return $this->presenter->card($fresh ?? $item, $me);
    }

    /**
     * Is hatasini JSON'a cevirir.
     *
     * @param  callable(): array<string, mixed>  $callback
     */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (StaleRecordException $exception) {
            return response()->json(['message' => $exception->userMessage(), 'code' => 'stale_record'], 409);
        } catch (AbstractException $exception) {
            return response()->json(['message' => $exception->userMessage(), 'code' => 'business_rule'], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $attributes = __('work_item.validation.attributes');

        try {
            return $request->validate($rules, [], is_array($attributes) ? $attributes : []);
        } catch (ValidationException $exception) {
            $first = null;

            foreach ($exception->errors() as $messages) {
                foreach ((array) $messages as $message) {
                    $first ??= (string) $message;
                }
            }

            throw new HttpResponseException(response()->json([
                'message' => $first ?? (string) __('exceptions.generic'),
                'errors' => $exception->errors(),
                'code' => 'invalid_input',
            ], 422));
        }
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B36'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user->loadMissing('orgUnit');
    }
}
