<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Enums\Document\FileObjectStatus;
use App\Enums\SocialMedia\SocialImageFormat;
use App\Enums\SocialMedia\SocialMediaUsage;
use App\Enums\SocialMedia\SocialResolutionPreset;
use App\Exceptions\AbstractException;
use App\Exceptions\SocialMedia\UnsupportedMediaException;
use App\Exceptions\StaleRecordException;
use App\Http\Controllers\Controller;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
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
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

/**
 * Icerik medyasi JSON uclari (D-106): gorsel yukleme, galeri sirasi, aciklama,
 * bicim/cozunurluk surumu, surum secimi, galeriden cikarma / geri alma ve video
 * kapagi. Bu uctan yalniz GORSEL yuklenir; video parcali yukleme ucundan
 * (SocialUploadController) gelir.
 *
 * Yetki ust icerik uzerinden verilir: her islem `update($content)` ister
 * (AMENDMENTS D). Galeriden cikarilmis grup, geri alma disindaki butun uclarda
 * 404'tur. Yazma isleri SocialContentMediaService'te; burasi yalniz dogrulama,
 * yetki ve bicimleme. Her degisiklik guncel icerik ayrintisini dondurur.
 */
final class SocialMediaController extends Controller
{
    public function __construct(
        private readonly SocialContentMediaService $media,
        private readonly SocialContentQueries $queries,
    ) {}

    /** Gorsel yukler: galeriye ya da (`inline=1`) metin editorune. */
    public function store(Request $request, SocialContent $content): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('update', $content);

        $data = $this->validated($request, [
            'file' => ['required', 'file', 'max:'.$this->maxImageKb()],
            'caption' => ['nullable', 'string', 'max:'.SocialContentMediaService::CAPTION_MAX],
            'inline' => ['nullable', 'boolean'],
        ]);

        $upload = $request->file('file');

        if (! $upload instanceof UploadedFile || ! $this->isImage($upload)) {
            return $this->failure(UnsupportedMediaException::make());
        }

        $tempKey = $this->storeTemp($upload);

        if ($tempKey === null) {
            return $this->failure(UnsupportedMediaException::make());
        }

        $inline = (bool) ($data['inline'] ?? false);
        $meta = ['caption' => $data['caption'] ?? null];

        try {
            $row = $inline
                ? $this->media->attachInline($content, $tempKey, $upload->getClientOriginalName(), $meta)
                : $this->media->attach($content, $tempKey, $upload->getClientOriginalName(), $meta);
        } catch (AbstractException $exception) {
            $this->deleteTemp($tempKey);

            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me, $row);
    }

    /** Galeri sirasi: `ids` galerideki kok medya kimliklerinin tamamidir. */
    public function order(Request $request, SocialContent $content): JsonResponse
    {
        $me = $this->me($request);
        Gate::authorize('update', $content);

        $data = $this->validated($request, [
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        try {
            $this->media->reorder($content, array_map(intval(...), array_values((array) $data['ids'])));
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /** Medya aciklamasi. */
    public function update(Request $request, SocialContentMedia $media): JsonResponse
    {
        $me = $this->me($request);
        $content = $this->contentOf($media);
        Gate::authorize('update', $content);

        $data = $this->validated($request, [
            'caption' => ['nullable', 'string', 'max:'.SocialContentMediaService::CAPTION_MAX],
        ]);

        try {
            $this->media->updateCaption($media, $data['caption'] ?? null);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /** Bicim (kirpma) ve/veya cozunurluk surumu uretir ve secer. */
    public function variant(Request $request, SocialContentMedia $media): JsonResponse
    {
        $me = $this->me($request);
        $content = $this->contentOf($media);
        Gate::authorize('update', $content);

        $data = $this->validated($request, [
            'format' => ['nullable', 'string', Rule::in(SocialImageFormat::values())],
            'preset' => ['nullable', 'string', Rule::in(SocialResolutionPreset::values())],
            'crop' => ['nullable', 'array'],
            'crop.x' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.y' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.w' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.h' => ['required_with:crop', 'numeric', 'between:0,1'],
        ]);

        $crop = is_array($data['crop'] ?? null)
            ? [
                'x' => (float) $data['crop']['x'],
                'y' => (float) $data['crop']['y'],
                'w' => (float) $data['crop']['w'],
                'h' => (float) $data['crop']['h'],
            ]
            : null;

        try {
            $this->media->createVariant(
                $media,
                filled($data['format'] ?? null) ? SocialImageFormat::from((string) $data['format']) : null,
                filled($data['preset'] ?? null) ? SocialResolutionPreset::from((string) $data['preset']) : null,
                $crop,
            );
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /** Grubun kullanilacak surumunu secer. */
    public function select(Request $request, SocialContentMedia $media): JsonResponse
    {
        $me = $this->me($request);
        $content = $this->contentOf($media);
        Gate::authorize('update', $content);

        try {
            $this->media->select($media);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /** Medya grubunu galeriden cikarir (dosya silinmez). */
    public function remove(Request $request, SocialContentMedia $media): JsonResponse
    {
        $me = $this->me($request);
        $content = $this->contentOf($media);
        Gate::authorize('update', $content);

        try {
            $this->media->remove($media);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /** Galeriden cikarilmis grubu geri alir. */
    public function restore(Request $request, SocialContentMedia $media): JsonResponse
    {
        $me = $this->me($request);
        $content = $this->contentOf($media, allowRemoved: true);
        Gate::authorize('update', $content);

        try {
            $this->media->restore($media);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /** Video kapagi (tarayicida yakalanan kare; yalniz gorsel kabul edilir). */
    public function poster(Request $request, SocialContentMedia $media): JsonResponse
    {
        $me = $this->me($request);
        $content = $this->contentOf($media);
        Gate::authorize('update', $content);

        $this->validated($request, [
            'file' => ['required', 'file', 'max:'.$this->maxImageKb()],
        ]);

        $upload = $request->file('file');

        if (! $upload instanceof UploadedFile || ! $media->isVideo() || ! $this->isImage($upload)) {
            return $this->failure(UnsupportedMediaException::make());
        }

        $tempKey = $this->storeTemp($upload);

        if ($tempKey === null) {
            return $this->failure(UnsupportedMediaException::make());
        }

        try {
            $this->media->setPoster($media, $tempKey, $upload->getClientOriginalName());
        } catch (AbstractException $exception) {
            $this->deleteTemp($tempKey);

            return $this->failure($exception);
        }

        return $this->respond((int) $content->getKey(), $me);
    }

    /**
     * Seri indirme: galerideki her grubun SECILI surumu tek zip dosyasinda
     * (D-106, kullanici talebi). Dosyalar `01-ad.uzanti` sirayla adlandirilir;
     * galeriden cikarilmis gruplar ve dosyasi eksik satirlar atlanir. Bos
     * galeri ya da metin turleri icin 404 (galeri yoktur).
     */
    public function zip(Request $request, SocialContent $content): Response
    {
        $this->me($request);
        Gate::authorize('view', $content);

        $content->loadMissing(['media.fileObject']);

        $roots = $content->media
            ->filter(fn (SocialContentMedia $row): bool => $row->usage === SocialMediaUsage::Gallery && $row->isRoot() && ! $row->isRemoved())
            ->sortBy(fn (SocialContentMedia $row): int => (int) $row->sort_order)
            ->values();

        abort_if($roots->isEmpty(), 404);

        $entries = [];

        foreach ($roots as $index => $root) {
            $selected = $content->media->first(
                fn (SocialContentMedia $row): bool => (int) ($row->parent_media_id ?? $row->getKey()) === (int) $root->getKey() && (bool) $row->is_selected,
            ) ?? $root;

            $file = $selected->fileObject;

            if ($file === null || $file->status !== FileObjectStatus::Active) {
                continue;
            }

            $disk = Storage::disk((string) ($file->storage_disk ?: 'local'));

            if (! $disk->exists((string) $file->storage_key)) {
                continue;
            }

            $entries[] = [
                'path' => $disk->path((string) $file->storage_key),
                'name' => sprintf('%02d-%s', $index + 1, $this->zipEntryName((string) $file->original_name, (string) $file->mime_type)),
            ];
        }

        abort_if($entries === [], 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'ks-series-');
        abort_if($zipPath === false, 500);

        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::OVERWRITE) === true, 500);

        foreach ($entries as $entry) {
            $zip->addFile($entry['path'], $entry['name']);
        }

        $zip->close();

        $downloadName = Str::slug((string) $content->content_no ?: 'seri').'.zip';

        return response()
            ->download($zipPath, $downloadName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /** Zip icindeki dosya adi: orijinal ad korunur, yoksa mime'dan uzanti uretilir. */
    private function zipEntryName(string $original, string $mime): string
    {
        $name = preg_replace('/[^\w.\-]+/u', '_', trim($original) !== '' ? $original : 'dosya') ?? 'dosya';

        if (! str_contains($name, '.')) {
            $name .= '.'.match (true) {
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'gif') => 'gif',
                str_contains($mime, 'mp4') => 'mp4',
                str_contains($mime, 'webm') => 'webm',
                str_contains($mime, 'quicktime') => 'mov',
                default => 'bin',
            };
        }

        return $name;
    }

    /**
     * Medyanin ust icerigi. Icerik yoksa, satir galeri medyasi degilse ya da
     * (izin verilmedikce) grup galeriden cikarilmissa 404.
     */
    private function contentOf(SocialContentMedia $media, bool $allowRemoved = false): SocialContent
    {
        $content = $media->content;

        abort_if($content === null, 404);
        abort_if($media->usage !== SocialMediaUsage::Gallery, 404);
        abort_if(! $allowRemoved && $media->isRemoved(), 404);

        return $content;
    }

    /**
     * Guncel icerik ayrintisi (+ yuklemede eklenen medya).
     */
    private function respond(int $contentId, Personnel $me, ?SocialContentMedia $attached = null): JsonResponse
    {
        try {
            $content = $this->queries->findForDetail($contentId);
        } catch (AbstractException $exception) {
            return $this->failure($exception);
        }

        $payload = [];

        if ($attached !== null) {
            $payload['media'] = $this->attachedItem($content, $attached);
        }

        $payload['content'] = app(SocialContentPresenter::class)->detail($content, $me);

        return response()->json($payload);
    }

    /**
     * Eklenen medyanin sunumu: galeri medyasinda grubu (surumleriyle), metin ici
     * gorselde tek satir.
     *
     * @return array<string, mixed>
     */
    private function attachedItem(SocialContent $content, SocialContentMedia $attached): array
    {
        if ($attached->usage === SocialMediaUsage::Gallery) {
            foreach (SocialMediaPresenter::gallery($content) as $group) {
                if ((int) ($group['root_id'] ?? 0) === $attached->rootId()) {
                    return $group;
                }
            }
        }

        return SocialMediaPresenter::item($attached);
    }

    /** Yuklenen dosya icerigine gore izinli bir gorsel mi (on denetim; asil denetim serviste)? */
    private function isImage(UploadedFile $upload): bool
    {
        if (! $upload->isValid()) {
            return false;
        }

        $allowed = array_map(
            static fn (mixed $mime): string => strtolower((string) $mime),
            (array) config('konelsis.social_media.image_mimes', []),
        );

        return in_array(strtolower((string) $upload->getMimeType()), $allowed, true);
    }

    /** Yuklemeyi `local` diskteki gecici klasore alir; anahtari doner. */
    private function storeTemp(UploadedFile $upload): ?string
    {
        $directory = trim((string) config('konelsis.social_media.tmp_directory', 'social/tmp'), '/') ?: 'social/tmp';
        $key = $upload->store($directory, 'local');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function deleteTemp(string $key): void
    {
        try {
            $disk = Storage::disk('local');

            if ($disk->exists($key)) {
                $disk->delete($key);
            }
        } catch (Throwable) {
            // Artan gecici dosyayi gunluk temizlik (purgeStale) siler.
        }
    }

    private function maxImageKb(): int
    {
        return max(1, (int) config('konelsis.social_media.max_image_kb', 25600));
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
