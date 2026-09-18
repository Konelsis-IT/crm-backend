<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Document\FileDerivationKind;
use App\Enums\Document\FileObjectStatus;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Document\FileObject;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dosya nesnesi servisi.
 *
 * createFromUpload bes temel islemin disinda, bu servise ozel bir istir
 * (kural S-2). Filament'in FileUpload bileseni dosyayi 'local' diskte bir
 * gecici klasore ('document-uploads-tmp') zaten yuklemis olur; bu metot
 * icerigi hash'ler, ayni icerik daha once yuklendiyse mevcut nesneye
 * baglar (tekillestirme), degilse dosyayi kalici, rastgele bir anahtarla
 * tasir ve metadata satirini olusturur.
 *
 * D-71: gorsellerde en/boy okunur ve GD ile kucuk gorsel (thumbnail) turevi
 * uretilir (`is_derived`, `derivation_kind = thumbnail`). Turev uretimi
 * yuklemeyi asla bozmaz; basarisiz olursa sessizce atlanir ve
 * ensureThumbnail() ile sonradan tekrar denenebilir (indirme ucu bunu yapar).
 */
final class FileObjectService extends AbstractService
{
    /** Kucuk gorselin uzun kenari (px). */
    public const THUMBNAIL_MAX_EDGE = 400;

    private const THUMBNAIL_DIRECTORY = 'documents/thumbnails';

    protected string $orderBy = 'uploaded_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    public function createFromUpload(string $tempPath, ?string $originalName): FileObject
    {
        return $this->transactions->run(function () use ($tempPath, $originalName): FileObject {
            $disk = Storage::disk('local');
            $bytes = $disk->get($tempPath);

            if ($bytes === null) {
                throw RecordNotFoundException::make();
            }

            $sha256 = hash('sha256', $bytes);

            /** @var FileObject|null $existing */
            $existing = FileObject::query()->where('sha256', $sha256)->first();

            if ($existing !== null) {
                $disk->delete($tempPath);
                $this->ensureThumbnail($existing);

                return $existing;
            }

            $originalName = filled($originalName) ? $originalName : basename($tempPath);
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'bin';
            $storageKey = 'documents/'.Str::random(40).'.'.$extension;

            $disk->move($tempPath, $storageKey);

            $mimeType = (string) ($disk->mimeType($storageKey) ?: 'application/octet-stream');
            $dimensions = str_starts_with($mimeType, 'image/') ? $this->imageDimensions($bytes) : null;

            /** @var FileObject $fileObject */
            $fileObject = parent::create([
                'storage_disk' => 'local',
                'storage_key' => $storageKey,
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'declared_mime_type' => $mimeType,
                'byte_size' => $disk->size($storageKey),
                'sha256' => $sha256,
                'scan_status' => 'skipped',
                'image_width' => $dimensions[0] ?? null,
                'image_height' => $dimensions[1] ?? null,
                'uploaded_by_personnel_id' => $this->actor->personnelId(),
                'uploaded_at' => Carbon::now('UTC'),
                'status' => 'active',
            ]);

            $this->ensureThumbnail($fileObject, $bytes);

            return $fileObject;
        });
    }

    /**
     * Buyuk dosyalar (video) icin bellek dostu kayit (B31, D-106).
     *
     * createFromUpload ile ayni sozlesme: `local` diskteki gecici ANAHTAR gelir;
     * ayni icerik (sha256) daha once kayitliysa gecici dosya silinir ve mevcut
     * nesne doner, degilse dosya `<directory>/<rastgele40>.<uzanti>` anahtarina
     * tasinir ve metadata satiri ayni alan listesiyle olusur. Farki: dosya
     * bellege OKUNMAZ (ozet hash_file ile, tur ve boyut diskten alinir), gorsel
     * olcusu okunmaz ve kucuk gorsel uretilmez. Yukleyen personel zorunludur.
     */
    public function createFromLocalFile(string $tempPath, ?string $originalName, string $directory = 'social/videos'): FileObject
    {
        $uploaderId = $this->actor->personnelId();

        if ($uploaderId === null) {
            throw ActorRequiredException::make();
        }

        return $this->transactions->run(function () use ($tempPath, $originalName, $directory, $uploaderId): FileObject {
            $disk = Storage::disk('local');

            if (! $disk->exists($tempPath)) {
                throw RecordNotFoundException::make();
            }

            $sha256 = hash_file('sha256', $disk->path($tempPath));

            if (! is_string($sha256) || $sha256 === '') {
                throw RecordNotFoundException::make();
            }

            /** @var FileObject|null $existing */
            $existing = FileObject::query()->where('sha256', $sha256)->first();

            if ($existing !== null) {
                $disk->delete($tempPath);

                return $existing;
            }

            $originalName = mb_substr(filled($originalName) ? (string) $originalName : basename($tempPath), 0, 255);
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $extension = preg_match('/^[a-z0-9]{1,16}$/', $extension) === 1 ? $extension : 'bin';

            $directory = trim($directory, '/');
            $storageKey = ($directory !== '' ? $directory : 'social/videos').'/'.Str::random(40).'.'.$extension;

            // 'local' diskte throw => false: tasima basarisizsa false doner.
            if (! $disk->move($tempPath, $storageKey)) {
                throw RecordNotFoundException::make();
            }

            $mimeType = (string) ($disk->mimeType($storageKey) ?: 'application/octet-stream');

            /** @var FileObject $fileObject */
            $fileObject = parent::create([
                'storage_disk' => 'local',
                'storage_key' => $storageKey,
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'declared_mime_type' => $mimeType,
                'byte_size' => $disk->size($storageKey),
                'sha256' => $sha256,
                'scan_status' => 'skipped',
                'image_width' => null,
                'image_height' => null,
                'uploaded_by_personnel_id' => $uploaderId,
                'uploaded_at' => Carbon::now('UTC'),
                'status' => 'active',
            ]);

            return $fileObject;
        });
    }

    /**
     * Gorsel dosya icin kucuk gorsel turevini uretir (yoksa). Gorsel degilse,
     * GD yoksa veya format desteklenmiyorsa null doner; asla istisna firlatmaz.
     */
    public function ensureThumbnail(FileObject $fileObject, ?string $bytes = null): ?FileObject
    {
        if (! $fileObject->isImage() || $fileObject->is_derived) {
            return null;
        }

        $existing = $fileObject->thumbnail();

        if ($existing !== null) {
            return $existing;
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $disk = Storage::disk($fileObject->storage_disk ?: 'local');
            $bytes ??= $disk->get($fileObject->storage_key);

            if ($bytes === null) {
                return null;
            }

            $thumb = $this->renderThumbnail($bytes);

            if ($thumb === null) {
                return null;
            }

            [$thumbBytes, $thumbExtension, $thumbMime, $width, $height] = $thumb;
            $sha256 = hash('sha256', $thumbBytes);

            /** @var FileObject|null $duplicate */
            $duplicate = FileObject::query()->where('sha256', $sha256)->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            $storageKey = self::THUMBNAIL_DIRECTORY.'/'.Str::random(40).'.'.$thumbExtension;
            $disk->put($storageKey, $thumbBytes);

            /** @var FileObject $thumbnail */
            $thumbnail = parent::create([
                'storage_disk' => $fileObject->storage_disk ?: 'local',
                'storage_key' => $storageKey,
                'original_name' => pathinfo((string) $fileObject->original_name, PATHINFO_FILENAME).'-thumb.'.$thumbExtension,
                'extension' => $thumbExtension,
                'mime_type' => $thumbMime,
                'declared_mime_type' => $thumbMime,
                'byte_size' => strlen($thumbBytes),
                'sha256' => $sha256,
                'scan_status' => 'skipped',
                'is_derived' => true,
                'derived_from_file_object_id' => $fileObject->getKey(),
                'derivation_kind' => FileDerivationKind::Thumbnail,
                'image_width' => $width,
                'image_height' => $height,
                'uploaded_by_personnel_id' => $fileObject->uploaded_by_personnel_id ?? $this->actor->personnelId(),
                'uploaded_at' => Carbon::now('UTC'),
                'retention_policy_id' => $fileObject->retention_policy_id,
                'status' => FileObjectStatus::Active,
            ]);

            return $thumbnail;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function imageDimensions(string $bytes): ?array
    {
        if (! function_exists('getimagesizefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! isset($info[0], $info[1])) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /**
     * GD ile uzun kenari THUMBNAIL_MAX_EDGE olan kucuk gorsel; PNG/GIF/WebP
     * seffafligi PNG olarak, digerleri JPEG olarak yazilir.
     *
     * @return array{0: string, 1: string, 2: string, 3: int, 4: int}|null  [bytes, extension, mime, width, height]
     */
    private function renderThumbnail(string $bytes): ?array
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1, self::THUMBNAIL_MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        $info = @getimagesizefromstring($bytes);
        $sourceMime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $keepAlpha = in_array($sourceMime, ['image/png', 'image/gif', 'image/webp'], true);

        if ($keepAlpha) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        if ($keepAlpha) {
            imagepng($target, null, 6);
            $extension = 'png';
            $mime = 'image/png';
        } else {
            imagejpeg($target, null, 82);
            $extension = 'jpg';
            $mime = 'image/jpeg';
        }
        $out = (string) ob_get_clean();
        imagedestroy($target);

        if ($out === '') {
            return null;
        }

        return [$out, $extension, $mime, $targetWidth, $targetHeight];
    }

    /** Dosyanin fiziksel olarak bulundugu disk. */
    public function diskFor(FileObject $fileObject): Filesystem
    {
        return Storage::disk($fileObject->storage_disk ?: 'local');
    }
}
