<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Query\SocialMedia\SocialAnalyticsQueries;
use App\Query\SocialMedia\SocialContentQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialClock;
use App\Services\SocialMedia\SocialContentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Sosyal Medya plan, analiz ve depolama JSON uclari (D-106): ay takvimi,
 * ajanda listeleri, donem analizi ve disk kullanimi. Panelin kimlik
 * dogrulamali rota grubundadir; React arayuzu bu uclarla konusur.
 *
 * Hepsi salt okumadir ve `viewAny` ile yetkilenir. Sorgular
 * SocialAnalyticsQueries / SocialContentQueries'te, kart bicimi
 * SocialContentPresenter'dadir (cardLite ajandanin ek alanlarini - plan
 * tarihi, platformlar, hazirlayan, acil onay - hazir tasir); burasi yalniz
 * dogrulama ve bicimleme.
 */
final class SocialPlanningController extends Controller
{
    /** Ajanda listelerinin sirasi ve adlari (F6). */
    private const AGENDA_LISTS = ['today', 'tomorrow', 'approaching', 'missed', 'awaiting_approval'];

    private const AGENDA_LIMIT = 50;

    /** Analiz doneminin en uzun hali (gun). */
    private const MAX_RANGE_DAYS = 731;

    public function __construct(
        private readonly SocialAnalyticsQueries $analytics,
        private readonly SocialContentPresenter $contents,
    ) {}

    /** Ay takvimi: gun gun planli icerikler ve ozel gunler. */
    public function calendar(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['required', 'integer', Rule::exists('social_profiles', 'id')],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $calendar = $this->analytics->calendar(
            (int) $data['profile'],
            (string) ($data['month'] ?? SocialClock::today()->format('Y-m')),
        );

        $calendar['days'] = array_map(function (array $day) use ($me): array {
            $day['contents'] = array_map(
                fn (SocialContent $content): array => $this->contents->cardLite($content, $me),
                $day['contents'],
            );

            return $day;
        }, $calendar['days']);

        return response()->json($calendar);
    }

    /** Ajanda: bugun, yarin, yaklasan, geciken ve onay bekleyen yaklasanlar. */
    public function agenda(Request $request, SocialContentQueries $queries): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['required', 'integer', Rule::exists('social_profiles', 'id')],
        ]);

        $lists = $queries->agendaFor((int) $data['profile'], $me);
        $agenda = [];

        foreach (self::AGENDA_LISTS as $name) {
            $agenda[$name] = collect($lists[$name] ?? [])
                ->take(self::AGENDA_LIMIT)
                ->map(fn (SocialContent $content): array => $this->contents->cardLite($content, $me))
                ->values()
                ->all();
        }

        return response()->json($agenda);
    }

    /** Donem analizi: gostergeler, dagilimlar, atlananlar, takipci egrileri. */
    public function analytics(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['required', 'integer', Rule::exists('social_profiles', 'id')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            // Karsilastirma yalniz baslangic verildiyse yapilir (bos alanla kiyas dogrulamayi dusurur).
            'to' => array_values(array_filter([
                'nullable',
                'date_format:Y-m-d',
                $request->filled('from') ? 'after_or_equal:from' : null,
            ])),
        ]);

        [$from, $to] = $this->range($data['from'] ?? null, $data['to'] ?? null);

        $analytics = $this->analytics->analytics((int) $data['profile'], $from, $to);

        $analytics['missed_planned_list'] = array_map(
            fn (SocialContent $content): array => $this->contents->cardLite($content, $me),
            $analytics['missed_planned_list'],
        );

        $analytics['top_creators'] = array_values(array_filter(array_map(
            fn (array $row): ?array => ($person = $this->contents->person($row['person'])) === null
                ? null
                : ['person' => $person, 'count' => (int) $row['count']],
            $analytics['top_creators'],
        )));

        return response()->json($analytics);
    }

    /** Modulun disk kullanimi ve sunucudaki bos alan. */
    public function storage(Request $request): JsonResponse
    {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        return response()->json(['storage' => $this->analytics->storage()]);
    }

    /**
     * Analiz donemi. Varsayilan: iki ay oncesinin ilk gununden bu ayin sonuna.
     * Donem en fazla MAX_RANGE_DAYS gun olabilir; uzunsa baslangic ileri cekilir.
     *
     * @return array{0: \Carbon\CarbonImmutable, 1: \Carbon\CarbonImmutable}
     */
    private function range(?string $from, ?string $to): array
    {
        $today = SocialClock::today();

        $end = filled($to) ? SocialClock::date((string) $to) : null;
        $start = filled($from) ? SocialClock::date((string) $from) : null;

        $end ??= $start !== null && $start->greaterThan($today->endOfMonth())
            ? $start->endOfMonth()->startOfDay()
            : $today->endOfMonth()->startOfDay();
        $start ??= $end->subMonthsNoOverflow(2)->startOfMonth();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $start = $end->subDays(self::MAX_RANGE_DAYS);
        }

        return [$start, $end];
    }

    /**
     * Dogrulama; ileti ve alan adlari `social_content.validation` altindan gelir.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $validation = __('social_content.validation');
        $validation = is_array($validation) ? $validation : [];

        return $request->validate(
            $rules,
            is_array($validation['messages'] ?? null) ? $validation['messages'] : [],
            is_array($validation['attributes'] ?? null) ? $validation['attributes'] : [],
        );
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B31') && FeatureFlags::enabled('social_media.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }
}
