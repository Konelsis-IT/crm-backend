<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Enums\SocialMedia\SocialMediaKind;
use App\Exceptions\AbstractException;
use App\Exceptions\RecordNotFoundException;
use App\Exceptions\SocialMedia\UploadSessionInvalidException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Infrastructure\Media\ChunkedUploadStore;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Query\SocialMedia\SocialContentQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialContentMediaService;
use App\Services\SocialMedia\SocialContentPresenter;
use App\Services\SocialMedia\SocialMediaPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Parcali video yukleme uclari (D-106): baslat, parca gonder, durum sor,
 * tamamla, iptal et. Buyuk dosya tek istekte gonderilmez; parcalar
 * ChunkedUploadStore'da birikir, tamamlaninca tek dosyada birlesir ve medya
 * servisi uzerinden icerige baglanir.
 *
 * Yetki (AMENDMENTS D, F2): baslatma ve tamamlama `update($content)` ister;
 * parca, durum ve iptal uclarinda oturumun sahibi olmak yeterlidir (depo
 * denetler). Tamamlamada icerik govdeden degil oturum kaydindan alinir ve
 * yetki yeniden sorulur. Burasi yalniz dogrulama, yetki ve bicimleme yapar.
 */
final class SocialUploadController extends Controller
{
    public function __construct(
        private readonly ChunkedUploadStore $uploads,
        private readonly SocialContentMediaService $media,
        private readonly SocialContentQueries $queries,
    ) {}

    /** Yukleme oturumu acar -> { token, chunk_bytes }. */
    public function begin(Request $request): JsonResponse
    {
        $me = $this->me($request);

        $data = $this->validated($request, [
            'content_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['required', 'string', 'max:100'],
            'width' => ['nullable', 'integer', 'min:1', 'max:16384'],
            'height' => ['nullable', 'integer', 'min:1', 'max:16384'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);

        $content = $this->contentOrFail((int) $data['content_id']);
        Gate::authorize('update', $content);

        try {
            $this->media->assertCanAttach($content, SocialMediaKind::Video);

            $token = $this->uploads->begin(
                (int) $me->getKey(),
                (string) $data['name'],
                (int) $data['size'],
                (string) $data['mime'],
                (int) $content->getKey(),
                $this->reportedMeta($data),
            );
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json([
            'token' => $token,
            'chunk_bytes' => ChunkedUploadStore::chunkBytes(),
        ]);
    }

    /** Bir parcayi kaydeder (tekrarlanabilir) -> { received, next_index }. */
    public function chunk(Request $request, string $token): JsonResponse
    {
        $me = $this->me($request);

        $data = $this->validated($request, [
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.max(1, (int) config('konelsis.social_media.chunk_kb', 5120))],
        ]);

        $chunk = $request->file('chunk');

        try {
            if (! $chunk instanceof UploadedFile) {
                throw UploadSessionInvalidException::make();
            }

            $progress = $this->uploads->append($token, (int) $me->getKey(), (int) $data['index'], $chunk);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json([
            'received' => $progress['received'],
            'next_index' => $progress['next_index'],
        ]);
    }

    /** Kesilen yuklemeyi surdurmek icin ilerleme -> { received, next_index, size }. */
    public function status(Request $request, string $token): JsonResponse
    {
        $me = $this->me($request);

        try {
            $progress = $this->uploads->status($token, (int) $me->getKey());
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json([
            'received' => $progress['received'],
            'next_index' => $progress['next_index'],
            'size' => $progress['size'],
        ]);
    }

    /** Parcalari birlestirir ve videoyu icerige ekler -> { media, content }. */
    public function complete(Request $request, string $token): JsonResponse
    {
        $me = $this->me($request);

        $data = $this->validated($request, [
            'content_id' => ['required', 'integer', 'min:1'],
            'caption' => ['nullable', 'string', 'max:'.SocialContentMediaService::CAPTION_MAX],
            'width' => ['nullable', 'integer', 'min:1', 'max:16384'],
            'height' => ['nullable', 'integer', 'min:1', 'max:16384'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ]);

        try {
            $manifest = $this->uploads->manifest($token, (int) $me->getKey());

            // Icerik oturum kaydindan alinir; govdedeki kimlik yalniz tutarlilik icindir.
            if ($manifest['content_id'] === null || (int) $manifest['content_id'] !== (int) $data['content_id']) {
                throw UploadSessionInvalidException::make();
            }
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        $content = $this->contentOrFail((int) $manifest['content_id']);
        Gate::authorize('update', $content);

        // Buyuk dosyada birlestirme ve ozet alma zaman alir.
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        $tempKey = null;

        try {
            $this->media->assertCanAttach($content, SocialMediaKind::Video);

            $assembled = $this->uploads->assemble($token, (int) $me->getKey());
            $tempKey = $assembled['path'];

            $row = $this->media->attach($content, $tempKey, $assembled['name'], [
                ...$assembled['meta'],
                ...$this->reportedMeta($data),
                'caption' => $data['caption'] ?? null,
            ]);
        } catch (AbstractException $exception) {
            $this->deleteTemp($tempKey);

            return $this->failure($exception);
        } catch (Throwable $exception) {
            $this->deleteTemp($tempKey);

            throw $exception;
        }

        try {
            $fresh = $this->queries->findForDetail((int) $content->getKey());
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json([
            'media' => $this->attachedItem($fresh, $row->rootId()) ?? SocialMediaPresenter::item($row),
            'content' => app(SocialContentPresenter::class)->detail($fresh, $me),
        ]);
    }

    /** Yuklemeyi iptal eder ve parcalari siler -> { ok: true }. */
    public function abort(Request $request, string $token): JsonResponse
    {
        $me = $this->me($request);

        try {
            $this->uploads->abort($token, (int) $me->getKey());
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return response()->json(['ok' => true]);
    }

    private function contentOrFail(int $contentId): SocialContent
    {
        try {
            return $this->queries->findForDetail($contentId);
        } catch (RecordNotFoundException) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attachedItem(SocialContent $content, int $rootId): ?array
    {
        foreach (SocialMediaPresenter::gallery($content) as $group) {
            if ((int) ($group['root_id'] ?? 0) === $rootId) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Istemcinin <video> ogesinden okudugu olcu ve sure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    private function reportedMeta(array $data): array
    {
        $meta = [];

        foreach (['width', 'height', 'duration_seconds'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key]) && (float) $data[$key] > 0) {
                $meta[$key] = max(1, (int) round((float) $data[$key]));
            }
        }

        return $meta;
    }

    private function deleteTemp(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        try {
            $disk = Storage::disk('local');

            if ($disk->exists($key)) {
                $disk->delete($key);
            }
        } catch (Throwable) {
            // Artan gecici dosyayi gunluk temizlik (purgeStale) siler.
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
