<?php

declare(strict_types=1);

namespace App\Http\Controllers\SocialMedia;

use App\Enums\Document\FileObjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Document\FileObject;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContentMedia;
use App\Services\Document\FileDeliveryService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialContentMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sosyal medya dosyasi goruntuleme / indirme (D-106). Yetki ust icerik
 * uzerinden verilir (`view`); dosya nesnesi dogrudan sunulmaz.
 *
 * Sorgu: variant = original | thumbnail | preview | poster | poster_thumbnail,
 * disposition = inline | download.
 *
 * - Gorsel: `thumbnail` kucuk gorsel, `preview` tembel uretilen 1280 px
 *   onizleme (uretilemezse ozgun), `original` dosyanin kendisi.
 * - Video: `original` Range destekli BinaryFileResponse ile akitilir (ileri
 *   sarma icin 206 gerekir); yalniz izinli video turleri, yerel surucu ve etkin
 *   dosya icin. `thumbnail` / `poster*` / `preview` yalniz kapak gorseli varsa
 *   yanit verir; video dosyasi ASLA kucuk gorsel yerine gonderilmez (404).
 * - Galeriden cikarilmis grup 404'tur. Tek istisna: icerigi duzenleyebilen kisi
 *   "geri al" listesinde neyi geri aldigini gorebilsin diye kucuk gorseli
 *   alabilir.
 */
final class SocialMediaFileController extends Controller
{
    private const VARIANTS = ['original', 'thumbnail', 'preview', 'poster', 'poster_thumbnail'];

    public function __invoke(Request $request, SocialContentMedia $media, FileDeliveryService $delivery, SocialContentMediaService $service): Response
    {
        abort_unless(SchemaReadiness::hasBatch('B31') && FeatureFlags::enabled('social_media.admin_ui'), 404);

        $user = $request->user();
        abort_unless($user instanceof Personnel && $user->isActive(), 403);

        $content = $media->content;
        $file = $media->fileObject;
        abort_if($content === null || $file === null, 404);

        Gate::authorize('view', $content);

        $variant = in_array($request->query('variant'), self::VARIANTS, true) ? (string) $request->query('variant') : 'original';
        $disposition = $request->query('disposition') === 'download' ? 'download' : 'inline';

        if ($media->isRemoved()) {
            $thumbnailOnly = in_array($variant, ['thumbnail', 'poster_thumbnail'], true);
            abort_unless($thumbnailOnly && Gate::allows('update', $content), 404);
        }

        return $media->isVideo()
            ? $this->video($request, $media, $file, $variant, $disposition, $delivery)
            : $this->image($media, $file, $variant, $disposition, $delivery, $service);
    }

    private function image(SocialContentMedia $media, FileObject $file, string $variant, string $disposition, FileDeliveryService $delivery, SocialContentMediaService $service): Response
    {
        if ($variant === 'thumbnail') {
            return $delivery->respond($file, 'thumbnail', 'inline');
        }

        if ($variant === 'preview') {
            $response = $delivery->respond($service->ensurePreview($media) ?? $file, 'original', 'inline');
            // Medya satirinin dosyasi degismez; onizleme uzun sure onbellekte kalabilir.
            $response->headers->set('Cache-Control', 'private, max-age=86400');

            return $response;
        }

        // Kapak yalniz videolarda vardir.
        abort_if($variant !== 'original', 404);

        return $delivery->respond($file, 'original', $disposition);
    }

    private function video(Request $request, SocialContentMedia $media, FileObject $file, string $variant, string $disposition, FileDeliveryService $delivery): Response
    {
        if ($variant !== 'original') {
            $poster = $media->posterFile;
            abort_if($poster === null, 404);

            $response = $delivery->respond($poster, in_array($variant, ['thumbnail', 'poster_thumbnail'], true) ? 'thumbnail' : 'original', 'inline');
            $response->headers->set('Cache-Control', 'private, max-age=3600');

            return $response;
        }

        $mime = strtolower((string) $file->mime_type);
        $allowed = array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            (array) config('konelsis.social_media.video_mimes', []),
        );

        // Izinli video turu degilse genel teslim kurallari gecerlidir (satir ici calistirilmaz).
        if (! in_array($mime, $allowed, true)) {
            return $delivery->respond($file, 'original', $disposition);
        }

        $diskName = (string) ($file->storage_disk ?: 'local');

        abort_if($file->status !== FileObjectStatus::Active, 404);
        abort_if(config('filesystems.disks.'.$diskName.'.driver') !== 'local', 404);

        $disk = Storage::disk($diskName);
        abort_unless($disk->exists((string) $file->storage_key), 404);

        $path = $disk->path((string) $file->storage_key);
        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        if ($disposition === 'download') {
            return response()->download($path, (string) $file->original_name, $headers);
        }

        $response = response()->file($path, [...$headers, 'Content-Disposition' => 'inline']);

        // Dosya icerigi degismez: sha256 kalici ETag'dir (Range / If-Range ve 304 icin).
        if (filled($file->sha256)) {
            $response->setEtag((string) $file->sha256);
            $response->isNotModified($request);
        }

        return $response;
    }
}
