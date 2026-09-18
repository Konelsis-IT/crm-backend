<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialWatchKind;
use App\Exceptions\AbstractException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialCategory;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialProfile;
use App\Models\SocialMedia\SocialSpecialDay;
use App\Models\SocialMedia\SocialWatchAccount;
use App\Query\SocialMedia\SocialAnalyticsQueries;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialCategoryService;
use App\Services\SocialMedia\SocialProfileService;
use App\Services\SocialMedia\SocialResponsiblePositionService;
use App\Services\SocialMedia\SocialSettingsPresenter;
use App\Services\SocialMedia\SocialSpecialDayService;
use App\Services\SocialMedia\SocialWatchAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sosyal Medya ayar ve "Ilham ve Rakipler" JSON uclari (D-106): izlenen
 * hesaplar, kategoriler, ozel gunler, hesap baglantilari ve sorumlu gorevler.
 *
 * Yetki (AMENDMENTS D): listeler `viewAny`; modul verisi (kategori, ozel gun,
 * izlenen hesap, hesap baglantilari) `manageData`; sorumlu gorevler
 * `manageSettings`. Yazma isleri servislerde, okuma sorgulari Query
 * siniflarinda, bicim SocialSettingsPresenter'dadir; burasi yalniz dogrulama,
 * yetki ve bicimleme. Hicbir uc kayit silmez (pasife alma vardir).
 */
final class SocialSettingsController extends Controller
{
    public function __construct(
        private readonly SocialAnalyticsQueries $queries,
        private readonly SocialSettingsPresenter $presenter,
    ) {}

    // ------------------------------------------------------------------
    // Izlenen hesaplar
    // ------------------------------------------------------------------

    /** Secili hesabin turune gore rakip / kurum hesaplari (SPEC 3.11). */
    public function watch(Request $request): JsonResponse
    {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['required', 'integer', Rule::exists('social_profiles', 'id')],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $profile = $this->queries->findProfile((int) $data['profile']);
        abort_if($profile === null, 404);

        // Pasif hesaplar yalniz modul verisini yonetebilene gosterilir (F10).
        $includeInactive = (bool) ($data['include_inactive'] ?? false) && Gate::allows('manageData', SocialContent::class);

        return response()->json([
            'groups' => $this->presenter->watchGroups($profile, $this->queries->watchAccounts($profile->kind, $includeInactive)),
        ]);
    }

    public function watchStore(Request $request, SocialWatchAccountService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, $this->watchRules());

        try {
            /** @var SocialWatchAccount $account */
            $account = $service->create($data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['account' => $this->presenter->watchAccount($account)]);
    }

    public function watchUpdate(Request $request, SocialWatchAccount $account, SocialWatchAccountService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, $this->watchRules());

        try {
            /** @var SocialWatchAccount $account */
            $account = $service->update($account, $data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['account' => $this->presenter->watchAccount($account)]);
    }

    // ------------------------------------------------------------------
    // Kategoriler
    // ------------------------------------------------------------------

    public function categoryStore(Request $request, SocialCategoryService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, $this->categoryRules());

        try {
            /** @var SocialCategory $category */
            $category = $service->create($data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->categoryResponse($category);
    }

    public function categoryUpdate(Request $request, SocialCategory $category, SocialCategoryService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, $this->categoryRules());

        try {
            /** @var SocialCategory $category */
            $category = $service->update($category, $data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->categoryResponse($category);
    }

    // ------------------------------------------------------------------
    // Ozel gunler
    // ------------------------------------------------------------------

    public function days(Request $request): JsonResponse
    {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['nullable', 'integer', Rule::exists('social_profiles', 'id')],
        ]);

        $profileId = filled($data['profile'] ?? null) ? (int) $data['profile'] : null;

        return response()->json(['days' => $this->presenter->days($this->queries->specialDays($profileId))]);
    }

    public function dayStore(Request $request, SocialSpecialDayService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validatedDay($request);

        try {
            /** @var SocialSpecialDay $day */
            $day = $service->create($data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['day' => $this->presenter->day($day->load('profile'))]);
    }

    public function dayUpdate(Request $request, SocialSpecialDay $day, SocialSpecialDayService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validatedDay($request);

        try {
            /** @var SocialSpecialDay $day */
            $day = $service->update($day, $data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['day' => $this->presenter->day($day->load('profile'))]);
    }

    // ------------------------------------------------------------------
    // Hesap (profil)
    // ------------------------------------------------------------------

    /** Tanitim yazisi ve platform baglantilari; kod, ad, tur ve sahip degismez. */
    public function profileUpdate(Request $request, SocialProfile $profile, SocialProfileService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, [
            'bio' => ['nullable', 'string', 'max:2000'],
            'links' => ['nullable', 'array', 'max:'.count(SocialPlatform::cases())],
            'links.*.platform' => ['required', 'string', 'distinct', Rule::in(SocialPlatform::values())],
            'links.*.url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'links.*.handle' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            /** @var SocialProfile $profile */
            $profile = $service->update($profile, $data);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['profile' => $this->presenter->profile($profile)]);
    }

    // ------------------------------------------------------------------
    // Sorumlu gorevler
    // ------------------------------------------------------------------

    public function responsibles(Request $request, SocialResponsibilityQueries $responsibility): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageSettings', SocialContent::class);

        return response()->json($this->presenter->responsibles(
            $responsibility->positionOptions(),
            $responsibility->responsiblePeople(),
        ));
    }

    public function responsiblesSync(
        Request $request,
        SocialResponsiblePositionService $service,
        SocialResponsibilityQueries $responsibility,
    ): JsonResponse {
        $this->me($request);
        Gate::authorize('manageSettings', SocialContent::class);

        $data = $this->validated($request, [
            'position_ids' => ['present', 'array', 'max:200'],
            'position_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        try {
            $service->sync((array) $data['position_ids']);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        // Servis istek icindeki sorumluluk bellegini bosaltir; liste tazedir.
        return response()->json($this->presenter->responsibles(
            $responsibility->positionOptions(),
            $responsibility->responsiblePeople(),
        ));
    }

    // ------------------------------------------------------------------
    // Kurallar ve yardimcilar
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function watchRules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(SocialWatchKind::values())],
            'name' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:'.$this->noteMax()],
            'status' => ['nullable', 'string', Rule::in(ActiveStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'links' => ['nullable', 'array', 'max:'.count(SocialPlatform::cases())],
            'links.*.platform' => ['required', 'string', 'distinct', Rule::in(SocialPlatform::values())],
            'links.*.url' => ['nullable', 'string', 'max:500', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', Rule::in(SocialCategory::COLORS)],
            'status' => ['nullable', 'string', Rule::in(ActiveStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * Ozel gun dogrulamasi; takvimde olmayan tarih (31 Subat gibi) alan hatasi olur.
     *
     * @return array<string, mixed>
     */
    private function validatedDay(Request $request): array
    {
        $data = $this->validated($request, [
            'name' => ['required', 'string', 'max:160'],
            'month' => ['required', 'integer', 'between:1,12'],
            'day' => ['required', 'integer', 'between:1,31'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'profile_id' => ['nullable', 'integer', Rule::exists('social_profiles', 'id')],
            'note' => ['nullable', 'string', 'max:300'],
            'status' => ['nullable', 'string', Rule::in(ActiveStatus::values())],
        ]);

        $year = filled($data['year'] ?? null) ? (int) $data['year'] : null;

        if (! SocialSpecialDayService::isValidDate((int) $data['month'], (int) $data['day'], $year)) {
            throw ValidationException::withMessages(['day' => $this->validationText('invalid_special_day')]);
        }

        return $data;
    }

    private function categoryResponse(SocialCategory $category): JsonResponse
    {
        return response()->json([
            'category' => $this->presenter->category($category),
            'categories' => $this->presenter->categories($this->queries->categories()),
        ]);
    }

    private function noteMax(): int
    {
        return max(1, (int) config('konelsis.social_media.note_max', 4000));
    }

    /**
     * Dogrulama; ileti ve alan adlari `social_content.validation` altindan gelir.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $validation = $this->validationLang();

        return $request->validate(
            $rules,
            is_array($validation['messages'] ?? null) ? $validation['messages'] : [],
            is_array($validation['attributes'] ?? null) ? $validation['attributes'] : [],
        );
    }

    /** `social_content.validation.messages.<anahtar>`; yoksa genel hata metni. */
    private function validationText(string $key): string
    {
        $text = $this->validationLang()['messages'][$key] ?? null;

        return is_string($text) && $text !== '' ? $text : (string) __('exceptions.generic');
    }

    /**
     * @return array<string, mixed>
     */
    private function validationLang(): array
    {
        $validation = __('social_content.validation');

        return is_array($validation) ? $validation : [];
    }

    private function me(Request $request): Personnel
    {
        abort_unless(SchemaReadiness::hasBatch('B31') && FeatureFlags::enabled('social_media.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        return $user;
    }

    /** Is hatasi -> { message, code } (F8); surum catismasi 409, digerleri 422. */
    private function failure(AbstractException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->userMessage(),
            'code' => Str::snake(Str::beforeLast(class_basename($exception), 'Exception')),
        ], $exception instanceof StaleRecordException ? 409 : 422);
    }
}
