<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialContentType;
use App\Enums\SocialMedia\SocialImageFormat;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialReactionType;
use App\Enums\SocialMedia\SocialReminderStage;
use App\Enums\SocialMedia\SocialResolutionPreset;
use App\Enums\SocialMedia\SocialWatchKind;
use App\Exceptions\AbstractException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialCategory;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentPlatform;
use App\Policies\SocialContentPolicy;
use App\Query\SocialMedia\SocialAnalyticsQueries;
use App\Query\SocialMedia\SocialContentQueries;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialContentPresenter;
use App\Services\SocialMedia\SocialContentService;
use App\Services\SocialMedia\SocialSettingsPresenter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sosyal medya icerik JSON uclari (B31, D-106). Panelin kimlik dogrulamali
 * rota grubundadir; tarayicidaki React arayuzu bu uclarla konusur. Yazma
 * isleri SocialContentService'te, okuma sorgulari SocialContentQueries'te,
 * bicimleme SocialContentPresenter'da; burasi yalniz dogrulama, yetki ve
 * bicimleme cagrisidir.
 *
 * Her uc once me() kapisindan gecer, sonra politikaya sorar. Her degisiklik
 * `{ content: ayrinti }` doner (taze row_version ile). Is hatalari 422,
 * surum cakismasi 409 olarak `{ message, code }` bicimindedir.
 */
final class SocialContentController extends Controller
{
    public const PER_PAGE = 24;

    public function __construct(
        private readonly SocialContentQueries $queries,
        private readonly SocialContentPresenter $presenter,
    ) {}

    /** Arayuzun acilis verisi: kisi, hesaplar, secenekler, sinirlar, sayaclar, depolama. */
    public function bootstrap(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $profiles = $this->queries->profiles();
        $settings = app(SocialSettingsPresenter::class);
        $counts = [];

        foreach ($profiles as $profile) {
            $counts[(string) $profile->getKey()] = $this->queries->countsFor((int) $profile->getKey(), $me);
        }

        $creators = [];

        foreach ($this->queries->creators() as $creator) {
            $creators[] = $this->presenter->person($creator);
        }

        return response()->json([
            'me' => [
                ...(array) $this->presenter->person($me->loadMissing('orgUnit')),
                'abilities' => $this->myAbilities($me),
                'is_responsible' => app(SocialResponsibilityQueries::class)->isResponsible((int) $me->getKey()),
            ],
            'profiles' => $profiles->map(fn ($profile): array => $settings->profile($profile))->values()->all(),
            'categories' => $settings->categories($this->queries->categories()),
            'options' => $this->options(),
            'limits' => $this->limits(),
            'creators' => $creators,
            // Bos oldugunda da JSON nesnesi kalsin ({}), dizi degil.
            'counts' => (object) $counts,
            'storage' => $this->storage(),
            'reminders' => ['last_run_at' => $this->lastReminderRun()],
        ]);
    }

    /** Bir hesabin sayaclari (60 sn'lik yenileme bunu cagirir). */
    public function counts(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(['counts' => $this->queries->countsFor((int) $data['profile'], $me)]);
    }

    /** Akis: suzgecli, sayfali kartlar. */
    public function index(Request $request): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $filters = $this->validated($request, [
            'profile' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(SocialContentStatus::values())],
            'type' => ['nullable', 'string', Rule::in(SocialContentType::values())],
            'platform' => ['nullable', 'string', Rule::in(SocialPlatform::values())],
            'category' => ['nullable', 'integer', 'min:1'],
            'creator' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', 'string', Rule::in(SocialContentQueries::SCOPES)],
            'published' => ['nullable', 'string', Rule::in(SocialContentQueries::PUBLISHED_FILTERS)],
            'stage' => ['nullable', 'string', Rule::in(SocialReminderStage::values())],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = (int) ($filters['page'] ?? 1);
        $result = $this->queries->feed($filters, $me, $page, self::PER_PAGE);
        $cards = [];

        foreach ($result->items() as $content) {
            $cards[] = $this->presenter->card($content, $me);
        }

        return response()->json([
            'data' => $cards,
            'meta' => [
                'page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'has_more' => $result->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('create', SocialContent::class);

        $data = $this->validated($request, [
            'profile_id' => ['required', 'integer', Rule::exists('social_profiles', 'id')],
            'content_type' => ['required', 'string', Rule::in(SocialContentType::values())],
            'title' => ['required', 'string', 'max:200'],
            'platforms' => ['required', 'array', 'min:1', 'max:7'],
            ...$this->contentRules(),
        ]);

        try {
            $content = $service->create($data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    public function show(Request $request, SocialContent $content): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('view', $content);

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    public function update(Request $request, SocialContent $content, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('update', $content);

        // Tur degismez. Istemci ayni turu geri gonderirse yok sayilir; farkli
        // bir tur "prohibited" kuraliyla reddedilir.
        $sentType = $request->input('content_type');
        $typeChanged = $sentType !== null && $sentType !== '' && $sentType !== $content->content_type->value;

        $data = $this->validated($request, [
            'row_version' => ['required', 'integer', 'min:1'],
            'content_type' => $typeChanged ? ['prohibited'] : ['nullable', 'string'],
            'profile_id' => ['sometimes', 'required', 'integer', Rule::exists('social_profiles', 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'platforms' => ['sometimes', 'required', 'array', 'min:1', 'max:7'],
            ...$this->contentRules(),
        ]);

        try {
            $service->update($content, $data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    /** Durum degisikligi; gereken yetenek hedefe gore politikadan sorulur. */
    public function status(Request $request, SocialContent $content, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);

        $data = $this->validated($request, [
            'status' => ['required', 'string', Rule::in([
                SocialContentStatus::Approved->value,
                SocialContentStatus::Rejected->value,
                SocialContentStatus::RevisionRequested->value,
                SocialContentStatus::Archived->value,
                SocialContentStatus::Pending->value,
                'unarchive',
            ])],
            'note' => ['nullable', 'string', 'max:'.$this->limit('note_max', 4000)],
        ]);

        Gate::authorize(SocialContentPolicy::transitionAbility((string) $data['status']), $content);

        try {
            $service->changeStatus($content, (string) $data['status'], $data['note'] ?? null);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    /** Paylasildi olarak isaretleme; baglantilar yalniz icerigin platformlari icin kabul edilir. */
    public function publish(Request $request, SocialContent $content, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('publish', $content);

        $data = $this->validated($request, [
            'urls' => ['nullable', 'array', 'max:7'],
            'urls.*' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'note' => ['nullable', 'string', 'max:'.$this->limit('note_max', 4000)],
        ]);

        $urls = (array) ($data['urls'] ?? []);
        $own = $content->platforms()->get()
            ->map(fn (SocialContentPlatform $row): string => $row->platform->value)
            ->all();

        if (array_diff(array_map('strval', array_keys($urls)), $own) !== []) {
            throw $this->invalid(['urls' => [$this->validationText('urls_platform')]]);
        }

        try {
            $service->publish($content, $urls, $data['note'] ?? null);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    public function unpublish(Request $request, SocialContent $content, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('unpublish', $content);

        try {
            $service->unpublish($content);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    /** Acil onay istegi; bildirim giden kisi sayisi da doner. */
    public function urgent(Request $request, SocialContent $content, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('requestUrgent', $content);

        try {
            $notified = $service->requestUrgentApproval($content);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me, ['notified' => $notified]);
    }

    public function reaction(Request $request, SocialContent $content, SocialContentService $service): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('react', $content);

        $data = $this->validated($request, [
            'reaction' => ['required', 'string', Rule::in(SocialReactionType::values())],
        ]);

        try {
            $service->react($content, SocialReactionType::from((string) $data['reaction']));
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->contentResponse((int) $content->getKey(), $me);
    }

    /**
     * Olusturma ve duzenlemenin ortak alan kurallari (F9).
     *
     * @return array<string, list<mixed>>
     */
    private function contentRules(): array
    {
        return [
            'platforms.*' => ['required', 'string', 'distinct', Rule::in(SocialPlatform::values())],
            'caption' => ['nullable', 'string', 'max:'.$this->limit('caption_max', 5000)],
            'body_text' => ['nullable', 'string', 'max:'.$this->limit('short_text_hard_limit', 25000)],
            'body_html' => ['nullable', 'string', 'max:'.$this->limit('body_html_max_bytes', 1048576)],
            'category_id' => ['nullable', 'integer', Rule::exists('social_categories', 'id')],
            'image_format' => ['nullable', 'string', Rule::in(SocialImageFormat::values())],
            'video_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'planned_on' => ['nullable', 'date_format:Y-m-d'],
            'planned_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * `{ content: ayrinti }` yaniti; icerik her zaman taze okunur.
     *
     * @param  array<string, mixed>  $extra
     */
    private function contentResponse(int $contentId, Personnel $me, array $extra = []): JsonResponse
    {
        try {
            $content = $this->queries->findForDetail($contentId);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['content' => $this->presenter->detail($content, $me), ...$extra]);
    }

    /**
     * Kisinin modul duzeyi yetenekleri.
     *
     * @return array{create: bool, manage_data: bool, manage_settings: bool, approve_any: bool, publish_any: bool}
     */
    private function myAbilities(Personnel $me): array
    {
        $gate = Gate::forUser($me);
        $probe = $this->queries->probe();

        return [
            'create' => $gate->allows('create', SocialContent::class),
            'manage_data' => $gate->allows('manageData', SocialContent::class),
            'manage_settings' => $gate->allows('manageSettings', SocialContent::class),
            // Kayittan bagimsiz soru: olusturani olmayan ornek icerik uzerinde sorulur.
            'approve_any' => $gate->allows('approve', $probe),
            'publish_any' => $gate->allows('publish', $probe),
        ];
    }

    /**
     * Secim listeleri: deger + etiket (+ renk / olcu).
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'platforms' => array_map(fn (SocialPlatform $platform): array => [
                'value' => $platform->value,
                'label' => $platform->getLabel(),
                'color' => $platform->brandColor(),
            ], SocialPlatform::cases()),
            'types' => array_map(fn (SocialContentType $type): array => [
                'value' => $type->value,
                'label' => $type->getLabel(),
            ], SocialContentType::cases()),
            'statuses' => array_map(fn (SocialContentStatus $status): array => [
                'value' => $status->value,
                'label' => $status->getLabel(),
                'color' => $status->uiColor(),
            ], SocialContentStatus::cases()),
            'formats' => array_map(fn (SocialImageFormat $format): array => [
                'value' => $format->value,
                'label' => $format->getLabel(),
                'width' => $format->width(),
                'height' => $format->height(),
            ], SocialImageFormat::cases()),
            'presets' => array_map(fn (SocialResolutionPreset $preset): array => [
                'value' => $preset->value,
                'label' => $preset->getLabel(),
            ], SocialResolutionPreset::cases()),
            'watch_kinds' => array_map(fn (SocialWatchKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->getLabel(),
            ], SocialWatchKind::cases()),
            'stages' => array_map(fn (SocialReminderStage $stage): array => [
                'value' => $stage->value,
                'label' => $stage->getLabel(),
            ], SocialReminderStage::cases()),
            'category_colors' => SocialCategory::COLORS,
        ];
    }

    /**
     * Istemcinin bilmesi gereken sinirlar (config konelsis.social_media).
     *
     * @return array<string, mixed>
     */
    private function limits(): array
    {
        return [
            'max_image_kb' => $this->limit('max_image_kb', 25600),
            'max_video_mb' => $this->limit('max_video_mb', 1024),
            'chunk_kb' => $this->limit('chunk_kb', 5120),
            'max_report_kb' => $this->limit('max_report_kb', 20480),
            'image_mimes' => array_values((array) config('konelsis.social_media.image_mimes', [])),
            'video_mimes' => array_values((array) config('konelsis.social_media.video_mimes', [])),
            // Istatistik raporu: istemci on denetimi sunucuyla ayni uzanti listesini kullansin.
            'report_extensions' => array_values((array) config('konelsis.social_media.report_extensions', [])),
            'short_text_soft' => $this->limit('short_text_soft_limit', 280),
            'short_text_hard' => $this->limit('short_text_hard_limit', 25000),
            'caption_max' => $this->limit('caption_max', 5000),
            'comment_max' => $this->limit('comment_max', 4000),
            'note_max' => $this->limit('note_max', 4000),
            'body_html_max_bytes' => $this->limit('body_html_max_bytes', 1048576),
            'prefer_link_over_mb' => $this->limit('prefer_link_over_mb', 200),
            'max_versions_per_media' => $this->limit('max_versions_per_media', 12),
            'approaching_days' => $this->limit('approaching_days', 3),
            'urgent_window_days' => $this->limit('urgent_window_days', 3),
            'urgent_cooldown_hours' => $this->limit('urgent_cooldown_hours', 12),
        ];
    }

    private function limit(string $key, int $default): int
    {
        return max(0, (int) config('konelsis.social_media.'.$key, $default));
    }

    /**
     * Depolama kutusu; olcum basarisiz olursa acilis verisi bozulmasin diye
     * sifirlanmis bicim doner.
     *
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        try {
            return app(SocialAnalyticsQueries::class)->storage();
        } catch (Throwable) {
            return [
                'video_bytes' => 0, 'image_bytes' => 0, 'other_bytes' => 0, 'total_bytes' => 0, 'removed_bytes' => 0,
                'free_bytes' => null, 'disk_total_bytes' => null, 'low' => false,
                'video_human' => '0 B', 'image_human' => '0 B', 'total_human' => '0 B', 'free_human' => null,
            ];
        }
    }

    /** Hatirlatma komutunun son calisma ani (ISO-8601) ya da null. */
    private function lastReminderRun(): ?string
    {
        try {
            $value = Cache::get('social.remind.last_run');
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Dogrulama; mesajlar ve alan adlari lang `social_content.validation`
     * altindan gelir (cerceve varsayilani Ingilizcedir).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $messages = __('social_content.validation.messages');
        $attributes = __('social_content.validation.attributes');

        try {
            return $request->validate(
                $rules,
                is_array($messages) ? $messages : [],
                is_array($attributes) ? $attributes : [],
            );
        } catch (ValidationException $exception) {
            throw $this->invalid($exception->errors());
        }
    }

    /**
     * Dogrulama hatasi yaniti: `message` yalniz ilk hatanin Turkce metnidir
     * (cercevenin Ingilizce "and N more errors" eki olmadan).
     *
     * @param  array<string, array<int, string>>  $errors
     */
    private function invalid(array $errors): HttpResponseException
    {
        $first = null;

        foreach ($errors as $messages) {
            foreach ((array) $messages as $message) {
                $first ??= (string) $message;
            }
        }

        return new HttpResponseException(response()->json([
            'message' => $first ?? (string) __('exceptions.generic'),
            'errors' => $errors,
            'code' => 'invalid_input',
        ], 422));
    }

    private function validationText(string $key): string
    {
        $full = 'social_content.validation.'.$key;
        $text = __($full);

        return is_string($text) && $text !== $full ? $text : (string) __('exceptions.generic');
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B31') && FeatureFlags::enabled('social_media.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }

    /** Is hatasi: `{ message, code }`; surum cakismasi 409, digerleri 422. */
    private function failure(AbstractException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->userMessage(),
            'code' => Str::snake(Str::beforeLast(class_basename($exception), 'Exception')),
        ], $exception instanceof StaleRecordException ? 409 : 422);
    }
}
