<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Document\FileObjectStatus;
use App\Models\Document\FileObject;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dosya teslimi (D-71): yetki kontrolu cagiran denetleyicide (sahip is nesnesi
 * uzerinden) yapilir; bu servis yalniz dogru diskten, dogru baslikla akitir.
 *
 * - `inline` yalniz guvenli turlerde (gorsel, PDF, duz metin) satir ici;
 *   digerleri her zaman indirme (HTML/SVG/JS tarayicida calistirilmaz).
 * - `thumbnail` varyanti gorsellerde kucuk gorsel turevini verir; yoksa
 *   uretmeyi dener, uretilemezse orijinali dondurur.
 */
final class FileDeliveryService
{
    public function __construct(private readonly FileObjectService $fileObjects) {}

    public function respond(FileObject $file, string $variant = 'original', string $disposition = 'inline', ?string $downloadName = null): Response
    {
        $target = $file;

        if ($variant === 'thumbnail') {
            $target = $this->fileObjects->ensureThumbnail($file) ?? $file;
        }

        if ($target->status !== FileObjectStatus::Active) {
            abort(404);
        }

        $disk = Storage::disk($target->storage_disk ?: 'local');

        if (! $disk->exists($target->storage_key)) {
            abort(404);
        }

        $name = filled($downloadName) ? $downloadName : (string) $target->original_name;
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $target->is_derived ? 'private, max-age=86400' : 'private, max-age=0, no-cache',
        ];

        if ($disposition === 'inline' && $target->isInlinePreviewable()) {
            return $disk->response($target->storage_key, $name, ['Content-Type' => $target->mime_type, ...$headers], 'inline');
        }

        return $disk->download($target->storage_key, $name, $headers);
    }
}
