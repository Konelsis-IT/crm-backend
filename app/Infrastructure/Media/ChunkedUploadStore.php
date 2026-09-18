<?php

declare(strict_types=1);

namespace App\Infrastructure\Media;

use App\Exceptions\SocialMedia\FileTooLargeException;
use App\Exceptions\SocialMedia\UnsupportedMediaException;
use App\Exceptions\SocialMedia\UploadSessionInvalidException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Parcali video yuklemesinin gecici deposu (B31, D-106).
 *
 * Her oturum `local` diskte `<tmp>/<token>/` klasorudur: `manifest.json`
 * (sahip, icerik, beyan edilen boyut, parca boyu, alinan parcalar, istemcinin
 * bildirdigi olcu/sure) ve `000000.part`, `000001.part`... dosyalari. Token 40
 * karakterlik rastgele harf-rakamdir; her giriste desen yeniden denetlenir
 * (yol gecisi olmaz). Oturum yalniz sahibine aciktir.
 *
 * - begin: boyut ve tur on denetimi, kisi basina acik oturum siniri, disk
 *   bos alan denetimi.
 * - append: TEKRARLANABILIR. Siradaki parca eklenir; son alinan parcanin ayni
 *   sira numarasiyla yeniden gelmesi (yaniti kaybolmus istek) ustune yazar.
 *   Baska her sira numarasi reddedilir. Son parca disindakiler tam parca
 *   boyunda olmalidir; toplam, beyan edilen boyutu asamaz.
 * - assemble: parcalari akisla (stream_copy_to_stream) tek dosyada birlestirir,
 *   boyutu dogrular, oturum klasorunu siler ve gecici dosyanin ANAHTARINI doner.
 *   Gercek tur denetimi (finfo) bundan sonra medya servisinde yapilir.
 * - purgeStale: suresi dolmus oturumlari ve sahipsiz gecici dosyalari siler;
 *   yalniz gecici klasorun altinda calisir, kalici dosyalara dokunmaz.
 *
 * Veritabanina yazmaz. `local` diskte `throw => false` oldugu icin yazma/tasima
 * sonuclari tek tek denetlenir.
 */
final class ChunkedUploadStore
{
    public const TOKEN_LENGTH = 40;

    private const TOKEN_PATTERN = '/^[A-Za-z0-9]{40}$/';

    private const MANIFEST = 'manifest.json';

    private const LOCK_FILE = '.lock';

    /** Yukleme sirasinda diskte birakilacak asgari bos alan (bayt). */
    private const FREE_SPACE_RESERVE = 536870912;

    /**
     * Yeni yukleme oturumu acar.
     *
     * @param  array<string, mixed>  $meta  Istemcinin okudugu olcu/sure (width, height, duration_seconds)
     * @return string Oturum tokeni
     */
    public function begin(int $personnelId, string $name, int $size, string $mime, ?int $contentId = null, array $meta = []): string
    {
        if ($size < 1) {
            throw UnsupportedMediaException::make();
        }

        $maxBytes = self::maxBytes();

        if ($size > $maxBytes) {
            throw FileTooLargeException::make(['max' => self::readableSize($maxBytes)]);
        }

        if (! in_array(strtolower(trim($mime)), self::videoMimes(), true)) {
            throw UnsupportedMediaException::make();
        }

        if ($this->openSessionCount($personnelId) >= self::maxOpenUploads()) {
            throw UploadSessionInvalidException::make();
        }

        $this->guardFreeSpace($size);

        $disk = $this->disk();
        $token = Str::random(self::TOKEN_LENGTH);
        $directory = $this->directory($token);

        if (! $disk->makeDirectory($directory)) {
            throw UploadSessionInvalidException::make();
        }

        $now = Carbon::now('UTC')->toIso8601String();

        $this->writeManifest($token, [
            'token' => $token,
            'owner' => $personnelId,
            'content_id' => $contentId,
            'name' => self::cleanName($name),
            'size' => $size,
            'mime' => strtolower(trim($mime)),
            'chunk_bytes' => self::chunkBytes(),
            'parts' => [],
            'created_at' => $now,
            'updated_at' => $now,
            'meta' => self::cleanMeta($meta),
        ]);

        return $token;
    }

    /**
     * Bir parcayi kaydeder (tekrarlanabilir).
     *
     * @return array{received: int, next_index: int, received_bytes: int, size: int}
     */
    public function append(string $token, int $personnelId, int $index, UploadedFile $chunk): array
    {
        return $this->withLock($token, function () use ($token, $personnelId, $index, $chunk): array {
            $manifest = $this->readManifest($token, $personnelId);

            if (! $chunk->isValid()) {
                throw UploadSessionInvalidException::make();
            }

            /** @var list<int> $parts */
            $parts = $manifest['parts'];
            $received = count($parts);
            $size = (int) $manifest['size'];
            $chunkBytes = (int) $manifest['chunk_bytes'];
            $lastIndex = (int) ceil($size / $chunkBytes) - 1;

            // Yalniz siradaki parca ya da son alinan parcanin tekrari kabul edilir.
            if ($index < 0 || $index > $lastIndex || ($index !== $received && $index !== $received - 1)) {
                throw UploadSessionInvalidException::make();
            }

            $bytes = (int) $chunk->getSize();
            $expected = $index < $lastIndex ? $chunkBytes : $size - $chunkBytes * $lastIndex;

            if ($bytes < 1 || $bytes !== $expected) {
                throw UploadSessionInvalidException::make();
            }

            $parts[$index] = $bytes;
            $parts = array_values($parts);

            if (array_sum($parts) > $size) {
                throw UploadSessionInvalidException::make();
            }

            $stored = $chunk->storeAs($this->directory($token), self::partName($index), 'local');

            if ($stored === false) {
                throw UploadSessionInvalidException::make();
            }

            $manifest['parts'] = $parts;
            $manifest['updated_at'] = Carbon::now('UTC')->toIso8601String();
            $this->writeManifest($token, $manifest);

            return self::progress($manifest);
        });
    }

    /**
     * Oturumun ilerlemesi (kesilen yuklemeyi surdurmek icin).
     *
     * @return array{received: int, next_index: int, received_bytes: int, size: int}
     */
    public function status(string $token, int $personnelId): array
    {
        return self::progress($this->readManifest($token, $personnelId));
    }

    /**
     * Oturum bilgisi (sahip denetimli): tamamlamadan once icerigin yeniden
     * yetkilendirilmesi icin denetleyici okur.
     *
     * @return array{token: string, owner: int, content_id: int|null, name: string, size: int, mime: string, chunk_bytes: int, parts: list<int>, created_at: string, updated_at: string, meta: array<string, int>}
     */
    public function manifest(string $token, int $personnelId): array
    {
        return $this->readManifest($token, $personnelId);
    }

    /**
     * Parcalari tek dosyada birlestirir ve oturum klasorunu siler.
     *
     * @return array{path: string, name: string, size: int, mime: string, content_id: int|null, meta: array<string, int>}
     */
    public function assemble(string $token, int $personnelId): array
    {
        $result = $this->withLock($token, function () use ($token, $personnelId): array {
            $manifest = $this->readManifest($token, $personnelId);
            $disk = $this->disk();

            /** @var list<int> $parts */
            $parts = $manifest['parts'];
            $size = (int) $manifest['size'];
            $expectedParts = (int) ceil($size / (int) $manifest['chunk_bytes']);

            if (count($parts) !== $expectedParts || array_sum($parts) !== $size) {
                throw UploadSessionInvalidException::make();
            }

            $key = self::tmpDirectory().'/'.Str::random(self::TOKEN_LENGTH).'.'.self::extensionOf((string) $manifest['name']);
            $target = $disk->path($key);
            $output = @fopen($target, 'wb');

            if ($output === false) {
                throw UploadSessionInvalidException::make();
            }

            $written = 0;
            $complete = false;

            try {
                foreach (array_keys($parts) as $index) {
                    $partPath = $disk->path($this->directory($token).'/'.self::partName((int) $index));
                    $input = @fopen($partPath, 'rb');

                    if ($input === false) {
                        throw UploadSessionInvalidException::make();
                    }

                    try {
                        $copied = stream_copy_to_stream($input, $output);
                    } finally {
                        fclose($input);
                    }

                    if ($copied === false || $copied !== (int) $parts[$index]) {
                        throw UploadSessionInvalidException::make();
                    }

                    $written += $copied;
                }

                $complete = fflush($output) && $written === $size;
            } finally {
                fclose($output);

                if (! $complete && is_file($target)) {
                    @unlink($target);
                }
            }

            clearstatcache(true, $target);

            if (! $complete || ! is_file($target) || (int) filesize($target) !== $size) {
                if (is_file($target)) {
                    @unlink($target);
                }

                throw UploadSessionInvalidException::make();
            }

            return [
                'path' => $key,
                'name' => (string) $manifest['name'],
                'size' => $size,
                'mime' => (string) $manifest['mime'],
                'content_id' => $manifest['content_id'],
                'meta' => $manifest['meta'],
            ];
        });

        // Kilit dosyasi kapandiktan sonra silinir (Windows acik dosyayi silemez).
        $this->disk()->deleteDirectory($this->directory($token));

        return $result;
    }

    /**
     * Oturumu iptal eder ve parcalari siler. Oturum zaten yoksa sessizce doner;
     * baskasinin oturumu ise reddedilir.
     */
    public function abort(string $token, int $personnelId): void
    {
        $this->assertToken($token);

        $disk = $this->disk();
        $directory = $this->directory($token);

        if (! $disk->exists($directory.'/'.self::MANIFEST)) {
            if ($disk->exists($directory)) {
                $disk->deleteDirectory($directory);
            }

            return;
        }

        $manifest = $this->decodeManifest($disk->get($directory.'/'.self::MANIFEST));

        if ($manifest !== null && (int) $manifest['owner'] !== $personnelId) {
            throw UploadSessionInvalidException::make();
        }

        $disk->deleteDirectory($directory);
    }

    /**
     * Suresi dolmus oturum klasorlerini ve sahipsiz gecici dosyalari siler.
     * Yalniz gecici klasorun altinda calisir.
     *
     * @return int Silinen oge sayisi
     */
    public function purgeStale(int $hours): int
    {
        $disk = $this->disk();
        $root = self::tmpDirectory();

        if (! $disk->exists($root)) {
            return 0;
        }

        $threshold = Carbon::now('UTC')->subHours(max(1, $hours))->getTimestamp();
        $purged = 0;

        foreach ($disk->directories($root) as $directory) {
            try {
                $token = basename($directory);

                if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
                    continue;
                }

                if ($this->lastTouched($disk, $root.'/'.$token) < $threshold && $disk->deleteDirectory($root.'/'.$token)) {
                    $purged++;
                }
            } catch (Throwable) {
                continue;
            }
        }

        foreach ($disk->files($root) as $file) {
            try {
                $key = $root.'/'.basename($file);

                if ($disk->lastModified($key) < $threshold && $disk->delete($key)) {
                    $purged++;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $purged;
    }

    /** Parca boyu (bayt); istemciye `chunk_bytes` olarak bildirilir. */
    public static function chunkBytes(): int
    {
        return max(1, (int) config('konelsis.social_media.chunk_kb', 5120)) * 1024;
    }

    /** Izin verilen en buyuk video (bayt). */
    public static function maxBytes(): int
    {
        return max(1, (int) config('konelsis.social_media.max_video_mb', 1024)) * 1048576;
    }

    /** Okunur boyut (istisna metnindeki :max icin). */
    public static function readableSize(int $bytes): string
    {
        if ($bytes >= 1073741824 && $bytes % 1073741824 === 0) {
            return intdiv($bytes, 1073741824).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 0, ',', '.').' MB';
        }

        return number_format(max(1, $bytes / 1024), 0, ',', '.').' KB';
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(string $token, callable $callback): mixed
    {
        $this->assertToken($token);

        $disk = $this->disk();
        $directory = $this->directory($token);

        if (! $disk->exists($directory.'/'.self::MANIFEST)) {
            throw UploadSessionInvalidException::make();
        }

        $handle = @fopen($disk->path($directory.'/'.self::LOCK_FILE), 'c');

        if ($handle === false) {
            throw UploadSessionInvalidException::make();
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw UploadSessionInvalidException::make();
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $token, int $personnelId): array
    {
        $this->assertToken($token);

        $disk = $this->disk();
        $manifest = $this->decodeManifest($disk->get($this->directory($token).'/'.self::MANIFEST));

        if ($manifest === null || $manifest['token'] !== $token || (int) $manifest['owner'] !== $personnelId) {
            throw UploadSessionInvalidException::make();
        }

        if ($this->isExpired($manifest)) {
            $disk->deleteDirectory($this->directory($token));

            throw UploadSessionInvalidException::make();
        }

        return $manifest;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeManifest(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            return null;
        }

        foreach (['token', 'owner', 'name', 'size', 'mime', 'chunk_bytes', 'parts', 'created_at'] as $required) {
            if (! array_key_exists($required, $data)) {
                return null;
            }
        }

        if (! is_string($data['token']) || ! is_array($data['parts']) || (int) $data['size'] < 1 || (int) $data['chunk_bytes'] < 1) {
            return null;
        }

        $data['owner'] = (int) $data['owner'];
        $data['size'] = (int) $data['size'];
        $data['chunk_bytes'] = (int) $data['chunk_bytes'];
        $data['content_id'] = isset($data['content_id']) ? (int) $data['content_id'] : null;
        $data['parts'] = array_values(array_map(static fn (mixed $bytes): int => (int) $bytes, $data['parts']));
        $data['name'] = (string) $data['name'];
        $data['mime'] = (string) $data['mime'];
        $data['created_at'] = (string) $data['created_at'];
        $data['updated_at'] = (string) ($data['updated_at'] ?? $data['created_at']);
        $data['meta'] = self::cleanMeta(is_array($data['meta'] ?? null) ? $data['meta'] : []);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifest(string $token, array $manifest): void
    {
        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($json) || ! $this->disk()->put($this->directory($token).'/'.self::MANIFEST, $json)) {
            throw UploadSessionInvalidException::make();
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{received: int, next_index: int, received_bytes: int, size: int}
     */
    private static function progress(array $manifest): array
    {
        /** @var list<int> $parts */
        $parts = $manifest['parts'];

        return [
            'received' => count($parts),
            'next_index' => count($parts),
            'received_bytes' => (int) array_sum($parts),
            'size' => (int) $manifest['size'],
        ];
    }

    /** Kisinin suresi dolmamis acik oturum sayisi. */
    private function openSessionCount(int $personnelId): int
    {
        $disk = $this->disk();
        $root = self::tmpDirectory();

        if (! $disk->exists($root)) {
            return 0;
        }

        $count = 0;

        foreach ($disk->directories($root) as $directory) {
            $token = basename($directory);

            if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
                continue;
            }

            $manifest = $this->decodeManifest($disk->get($root.'/'.$token.'/'.self::MANIFEST));

            if ($manifest !== null && (int) $manifest['owner'] === $personnelId && ! $this->isExpired($manifest)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Birlestirme sirasinda parcalar ve birlesik dosya ayni anda diskte durur;
     * bu yuzden beyan edilen boyutun iki kati + pay kadar bos alan aranir.
     * Bos alan okunamiyorsa denetim atlanir.
     */
    private function guardFreeSpace(int $size): void
    {
        $free = @disk_free_space($this->disk()->path(''));

        if (! is_float($free) && ! is_int($free)) {
            return;
        }

        $usable = (int) floor(((float) $free - self::FREE_SPACE_RESERVE) / 2);

        if ($size > $usable) {
            throw FileTooLargeException::make(['max' => self::readableSize(max(0, $usable))]);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function isExpired(array $manifest): bool
    {
        $hours = max(1, (int) config('konelsis.social_media.tmp_ttl_hours', 24));

        try {
            $touched = Carbon::parse((string) ($manifest['updated_at'] ?? $manifest['created_at']), 'UTC');
        } catch (Throwable) {
            return true;
        }

        return $touched->lt(Carbon::now('UTC')->subHours($hours));
    }

    /** Oturum klasorune son dokunulan an (manifest yoksa klasordeki en yeni dosya). */
    private function lastTouched(Filesystem $disk, string $directory): int
    {
        $manifest = $this->decodeManifest($disk->get($directory.'/'.self::MANIFEST));

        if ($manifest !== null) {
            try {
                return Carbon::parse((string) $manifest['updated_at'], 'UTC')->getTimestamp();
            } catch (Throwable) {
                // Asagida dosya zamanina dusulur.
            }
        }

        $latest = 0;

        foreach ($disk->files($directory) as $file) {
            $latest = max($latest, (int) $disk->lastModified($directory.'/'.basename($file)));
        }

        return $latest;
    }

    private function assertToken(string $token): void
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw UploadSessionInvalidException::make();
        }
    }

    private function directory(string $token): string
    {
        return self::tmpDirectory().'/'.$token;
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    private static function partName(int $index): string
    {
        return sprintf('%06d.part', $index);
    }

    private static function tmpDirectory(): string
    {
        $directory = trim((string) config('konelsis.social_media.tmp_directory', 'social/tmp'), '/');

        return $directory !== '' ? $directory : 'social/tmp';
    }

    private static function maxOpenUploads(): int
    {
        return max(1, (int) config('konelsis.social_media.max_open_uploads', 3));
    }

    /**
     * @return list<string>
     */
    private static function videoMimes(): array
    {
        return array_values(array_map(
            static fn (mixed $mime): string => strtolower((string) $mime),
            (array) config('konelsis.social_media.video_mimes', []),
        ));
    }

    /** Istemcinin dosya adi: yol ayirici ve denetim karakterleri atilir, 255 ile sinirlanir. */
    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return $name !== '' ? mb_substr($name, 0, 255) : 'video';
    }

    private static function extensionOf(string $name): string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? $extension : 'bin';
    }

    /**
     * Istemcinin bildirdigi sayisal bilgiler; yalniz bilinen anahtarlar tutulur.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, int>
     */
    private static function cleanMeta(array $meta): array
    {
        $clean = [];

        foreach (['width', 'height', 'duration_seconds'] as $key) {
            if (isset($meta[$key]) && is_numeric($meta[$key]) && (int) $meta[$key] > 0) {
                $clean[$key] = (int) $meta[$key];
            }
        }

        return $clean;
    }
}
