<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Enums\SocialMedia\SocialPlatform;
use App\Exceptions\AbstractException;
use App\Exceptions\SocialMedia\UnsupportedMediaException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialMetricEntry;
use App\Query\SocialMedia\SocialAnalyticsQueries;
use App\Services\Document\FileDeliveryService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialMetricEntryService;
use App\Services\SocialMedia\SocialSettingsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Platform istatistigi JSON uclari (D-106): donemlik olcumler elle girilir
 * ve / veya rapor dosyasi yuklenir (`report`, FormData).
 *
 * Yetki (AMENDMENTS D): liste ve rapor dosyasi `viewAny`, giris ve duzeltme
 * `manageData`. Dosya gecici olarak local diskteki modul klasorune alinir ve
 * servise ANAHTAR olarak verilir (E9); tur / uzanti / boyut kurali servistedir
 * (E15). Is hatasinda gecici dosya silinir (F8). Rapor dosyasi yalniz PDF ve
 * gorselde satir ici acilir; tablo dosyalari her zaman indirilir.
 */
final class SocialMetricController extends Controller
{
    public function __construct(
        private readonly SocialAnalyticsQueries $queries,
        private readonly SocialSettingsPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $data = $this->validated($request, [
            'profile' => ['nullable', 'integer', Rule::exists('social_profiles', 'id')],
            'platform' => ['nullable', 'string', Rule::in(SocialPlatform::values())],
        ]);

        $entries = $this->queries->metricEntries(
            filled($data['profile'] ?? null) ? (int) $data['profile'] : null,
            filled($data['platform'] ?? null) ? SocialPlatform::from((string) $data['platform']) : null,
        );

        return response()->json(['entries' => $this->presenter->metricEntries($entries)]);
    }

    public function store(Request $request, SocialMetricEntryService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, $this->rules());

        return $this->save($request, $data, fn (array $payload): SocialMetricEntry => $service->create($payload));
    }

    public function update(Request $request, SocialMetricEntry $entry, SocialMetricEntryService $service): JsonResponse
    {
        $this->me($request);
        Gate::authorize('manageData', SocialContent::class);

        $data = $this->validated($request, $this->rules() + ['remove_report' => ['nullable', 'boolean']]);

        return $this->save($request, $data, fn (array $payload): SocialMetricEntry => $service->update($entry, $payload));
    }

    /** Yuklenen istatistik raporu: `disposition = inline|download`. */
    public function file(Request $request, SocialMetricEntry $entry, FileDeliveryService $delivery): Response
    {
        $this->me($request);
        Gate::authorize('viewAny', SocialContent::class);

        $file = $entry->reportFile;
        abort_if($file === null, 404);

        // csv / xls / xlsx ve diger turler tarayicida acilmaz, indirilir (E15).
        $inlineAllowed = $file->isInlinePreviewable() && ($file->isImage() || (string) $file->mime_type === 'application/pdf');
        $disposition = $request->query('disposition') === 'download' || ! $inlineAllowed ? 'download' : 'inline';

        return $delivery->respond($file, 'original', $disposition);
    }

    /**
     * Rapor dosyasini gecici klasore alir, servisi cagirir; is hatasinda gecici
     * dosyayi siler.
     *
     * @param  array<string, mixed>  $data
     * @param  callable(array<string, mixed>): SocialMetricEntry  $write
     */
    private function save(Request $request, array $data, callable $write): JsonResponse
    {
        unset($data['report']);
        $tempKey = null;

        if ($request->hasFile('report')) {
            $upload = $request->file('report');

            if ($upload === null || is_array($upload) || ! $upload->isValid()) {
                return $this->failure(UnsupportedMediaException::make());
            }

            $stored = $upload->store($this->tmpDirectory(), 'local');

            if (! is_string($stored) || $stored === '') {
                return $this->failure(UnsupportedMediaException::make());
            }

            $tempKey = $stored;
            $data['report_temp_path'] = $tempKey;
            $data['report_original_name'] = $upload->getClientOriginalName();
        }

        try {
            $entry = $write($data);
        } catch (AbstractException $exception) {
            $this->discard($tempKey);

            return $this->failure($exception);
        } catch (Throwable $exception) {
            $this->discard($tempKey);

            throw $exception;
        }

        return response()->json(['entry' => $this->presenter->metricEntry($entry)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'profile_id' => ['required', 'integer', Rule::exists('social_profiles', 'id')],
            'platform' => ['required', 'string', Rule::in(SocialPlatform::values())],
            'period_start_on' => ['required', 'date_format:Y-m-d'],
            'period_end_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start_on'],
            'note' => ['nullable', 'string', 'max:'.max(1, (int) config('konelsis.social_media.note_max', 4000))],
            'report' => ['nullable', 'file', 'max:'.max(1, (int) config('konelsis.social_media.max_report_kb', 20480))],
        ];

        foreach (SocialMetricEntry::METRICS as $metric) {
            $rules[$metric] = ['nullable', 'integer', 'min:0', 'max:999999999999999'];
        }

        return $rules;
    }

    private function tmpDirectory(): string
    {
        $directory = trim((string) config('konelsis.social_media.tmp_directory', 'social/tmp'), '/');

        return $directory === '' ? 'social/tmp' : $directory;
    }

    /** Gecici yukleme dosyasini siler (yalniz modulun gecici klasorundeki anahtar). */
    private function discard(?string $tempKey): void
    {
        if ($tempKey === null || ! str_starts_with($tempKey, $this->tmpDirectory().'/')) {
            return;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($tempKey)) {
            $disk->delete($tempKey);
        }
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

    /** Is hatasi -> { message, code } (F8); surum catismasi 409, digerleri 422. */
    private function failure(AbstractException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->userMessage(),
            'code' => Str::snake(Str::beforeLast(class_basename($exception), 'Exception')),
        ], $exception instanceof StaleRecordException ? 409 : 422);
    }
}
