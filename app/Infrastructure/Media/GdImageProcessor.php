<?php

declare(strict_types=1);

namespace App\Infrastructure\Media;

use App\Exceptions\SocialMedia\ImageTooLargeException;
use App\Exceptions\SocialMedia\UnsupportedMediaException;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GD ile gorsel isleme (B31, D-106): bicime kirpma, cozunurluk degistirme ve
 * JPEG yon duzeltme. Sosyal Medya modulunun medya servisi tarafindan cagrilir;
 * veritabanina dokunmaz.
 *
 * Yol sozlesmesi (E9): kaynak MUTLAK yol olarak gelir (kalici dosyanin disk
 * yolu); sonuc `local` diskte gecici klasore yazilir ve diskteki ANAHTAR
 * doner. `normalizeOrientation()` gecici bir yuklemeyi isledigi icin anahtar
 * alir ve anahtar doner.
 *
 * Yeniden ornekleme interpolasyondur; yapay zeka ile detay uretmez. Buyutmeden
 * sonra hafif bir keskinlestirme uygulanir. Seffaflik tasiyan gorseller PNG,
 * digerleri JPEG (kalite 90) olarak yazilir.
 *
 * Bellek: GD piksel basina yaklasik 5 bayt ister. Islemden once kaynak + hedef
 * piksel sayisindan bir tahmin yapilir; tahmin memory_limit'in %60'ini asarsa
 * ImageTooLargeException atilir (bellek tasmasi yakalanamayan olumcul hatadir).
 * PHP 8'de GdImage nesnesi referansi birakilinca serbest kalir; bu yuzden
 * kaynak, kodlamadan once unset edilir (imagedestroy PHP 8.5'te kaldirilmak
 * uzere isaretlidir ve etkisizdir).
 */
final class GdImageProcessor
{
    /** Turetilen JPEG kalitesi. */
    public const JPEG_QUALITY = 90;

    /** Yonu duzeltilen ozgun JPEG'in kalitesi (ozgun yerine gectigi icin daha yuksek). */
    public const UPRIGHT_JPEG_QUALITY = 92;

    /** Ciktinin uzun kenar ust siniri (piksel). */
    public const MAX_EDGE = 4096;

    private const BYTES_PER_PIXEL = 5;

    private const MEMORY_SHARE = 0.6;

    /** Exif bolumu dosyanin basindadir; bu kadar bayt taranir. */
    private const EXIF_SCAN_BYTES = 131072;

    /** Seffaflik yoklamasinda kullanilan kucuk kopyanin kenari (piksel). */
    private const ALPHA_PROBE_EDGE = 96;

    /** @var array<string, string> mime => GD yukleyici fonksiyonu */
    private const LOADERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif' => 'imagecreatefromgif',
    ];

    /**
     * Kapla ve kirp: kaynaktan hedef orana sahip en buyuk dikdortgeni alir ve
     * tam $w x $h olcusune getirir. $crop verilirse (kaynaga gore 0..1
     * normalize {x,y,w,h}) dikdortgen o kutunun icinden, verilmezse gorselin
     * ortasindan secilir.
     *
     * @param  array{x?: mixed, y?: mixed, w?: mixed, h?: mixed}|null  $crop
     * @return string `local` diskteki gecici dosyanin anahtari
     */
    public function fit(string $sourcePath, int $w, int $h, ?array $crop = null, int $quality = self::JPEG_QUALITY): string
    {
        [$w, $h] = self::clampSize($w, $h);
        [$sourceWidth, $sourceHeight, $mime] = $this->inspect($sourcePath);

        $region = self::coverRegion($sourceWidth, $sourceHeight, $w, $h, $crop);

        return $this->render($sourcePath, $mime, $sourceWidth, $sourceHeight, $region, $w, $h, $quality);
    }

    /**
     * Gorselin tamamini, uzun kenari $longEdge olacak sekilde yeniden boyutlar
     * (buyutme serbest, oran korunur).
     *
     * @return string `local` diskteki gecici dosyanin anahtari
     */
    public function resizeLongEdge(string $sourcePath, int $longEdge, int $quality = self::JPEG_QUALITY): string
    {
        [$sourceWidth, $sourceHeight, $mime] = $this->inspect($sourcePath);
        [$w, $h] = self::sizeForLongEdge($sourceWidth, $sourceHeight, $longEdge);

        $region = ['x' => 0, 'y' => 0, 'w' => $sourceWidth, 'h' => $sourceHeight];

        return $this->render($sourcePath, $mime, $sourceWidth, $sourceHeight, $region, $w, $h, $quality);
    }

    /**
     * Gorselin tamamini $factor katina getirir; uzun kenar $cap ile sinirlanir.
     * Buyutme isteginde (katsayi >= 1) sinir yuzunden kuculme olmaz.
     *
     * @return string `local` diskteki gecici dosyanin anahtari
     */
    public function scale(string $sourcePath, float $factor, int $cap = self::MAX_EDGE): string
    {
        [$sourceWidth, $sourceHeight, $mime] = $this->inspect($sourcePath);
        [$w, $h] = self::sizeForFactor($sourceWidth, $sourceHeight, $factor, $cap);

        $region = ['x' => 0, 'y' => 0, 'w' => $sourceWidth, 'h' => $sourceHeight];

        return $this->render($sourcePath, $mime, $sourceWidth, $sourceHeight, $region, $w, $h, self::JPEG_QUALITY);
    }

    /**
     * JPEG dosyasinin Exif yon etiketi (0x0112): 1..8; etiket yoksa, dosya JPEG
     * degilse ya da yapi okunamiyorsa 1. `exif` eklentisi gerekmez: dosyanin
     * ilk 128 KB'i taranir, APP1 "Exif" bolumundeki TIFF dizini okunur.
     */
    public function jpegOrientation(string $absPath): int
    {
        $handle = @fopen($absPath, 'rb');

        if ($handle === false) {
            return 1;
        }

        try {
            $data = (string) fread($handle, self::EXIF_SCAN_BYTES);
        } finally {
            fclose($handle);
        }

        $length = strlen($data);

        if ($length < 4 || $data[0] !== "\xFF" || $data[1] !== "\xD8") {
            return 1;
        }

        $offset = 2;

        while ($offset + 4 <= $length) {
            if ($data[$offset] !== "\xFF") {
                return 1;
            }

            $marker = ord($data[$offset + 1]);

            // Dolgu bayti: isaretciden once birden cok 0xFF olabilir.
            if ($marker === 0xFF) {
                $offset++;

                continue;
            }

            // Uzunluk tasimayan isaretciler.
            if ($marker === 0xD8 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $offset += 2;

                continue;
            }

            // Goruntu verisi basladi ya da dosya bitti: Exif bundan sonra olmaz.
            if ($marker === 0xDA || $marker === 0xD9) {
                return 1;
            }

            $segmentLength = (ord($data[$offset + 2]) << 8) | ord($data[$offset + 3]);

            if ($segmentLength < 2) {
                return 1;
            }

            if ($marker === 0xE1 && substr($data, $offset + 4, 6) === "Exif\0\0") {
                return $this->tiffOrientation(substr($data, $offset + 10, $segmentLength - 8));
            }

            $offset += 2 + $segmentLength;
        }

        return 1;
    }

    /**
     * Yon etiketi 1 olmayan bir JPEG yuklemesini dik hale getirir: piksel
     * verisi dondurulur/cevrilir ve etiketsiz yeni bir JPEG (kalite 92) yazilir.
     * Boylece ozgun dosya, kucuk gorseli, isaretler ve kirpmalar ayni yonu
     * paylasir. Duzeltme gerekmiyorsa ya da dosya cozulemiyorsa null doner;
     * eski gecici dosyayi silmek cagiranin isidir.
     *
     * @param  string  $key  `local` diskteki gecici yuklemenin anahtari
     * @return string|null Yeni gecici dosyanin anahtari
     */
    public function normalizeOrientation(string $key): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($key)) {
            return null;
        }

        $absPath = $disk->path($key);
        $orientation = $this->jpegOrientation($absPath);

        if ($orientation < 2 || $orientation > 8) {
            return null;
        }

        [$width, $height, $mime] = $this->inspect($absPath);

        if ($mime !== 'image/jpeg') {
            return null;
        }

        // Dondurme ikinci bir tam boy tuval acar.
        $this->guardMemory($width * $height * 2);

        $image = @imagecreatefromjpeg($absPath);

        if (! $image instanceof GdImage) {
            return null;
        }

        $upright = $this->upright($image, $orientation);
        unset($image);

        if ($upright === null) {
            return null;
        }

        return $this->encode($upright, false, self::UPRIGHT_JPEG_QUALITY);
    }

    /**
     * Gorselin olcusu ve gercek turu (yalniz baslik okunur, piksel cozulmez).
     *
     * @return array{0: int, 1: int, 2: string}|null [genislik, yukseklik, mime]
     */
    public function dimensions(string $absPath): ?array
    {
        if (! is_file($absPath)) {
            return null;
        }

        $info = @getimagesize($absPath);

        if (! is_array($info) || (int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
            return null;
        }

        return [(int) $info[0], (int) $info[1], (string) ($info['mime'] ?? '')];
    }

    /**
     * Normalize kirpma kutusunu (0..1) kaynak piksellerine cevirir; kutu yoksa
     * ya da gecersizse gorselin tamamini verir.
     *
     * @param  array{x?: mixed, y?: mixed, w?: mixed, h?: mixed}|null  $crop
     * @return array{x: int, y: int, w: int, h: int}
     */
    public static function cropBox(int $sourceWidth, int $sourceHeight, ?array $crop): array
    {
        $full = ['x' => 0, 'y' => 0, 'w' => max(1, $sourceWidth), 'h' => max(1, $sourceHeight)];

        if ($crop === null) {
            return $full;
        }

        foreach (['x', 'y', 'w', 'h'] as $edge) {
            if (! isset($crop[$edge]) || ! is_numeric($crop[$edge])) {
                return $full;
            }
        }

        $x = min(1.0, max(0.0, (float) $crop['x']));
        $y = min(1.0, max(0.0, (float) $crop['y']));
        $w = min(1.0 - $x, max(0.0, (float) $crop['w']));
        $h = min(1.0 - $y, max(0.0, (float) $crop['h']));

        if ($w <= 0.0 || $h <= 0.0) {
            return $full;
        }

        $left = min($full['w'] - 1, (int) round($x * $full['w']));
        $top = min($full['h'] - 1, (int) round($y * $full['h']));
        $width = max(1, min($full['w'] - $left, (int) round($w * $full['w'])));
        $height = max(1, min($full['h'] - $top, (int) round($h * $full['h'])));

        return ['x' => $left, 'y' => $top, 'w' => $width, 'h' => $height];
    }

    /**
     * Kirpma kutusunun (yoksa gorselin tamaminin) icinde, $w/$h oranina sahip
     * en buyuk ortalanmis dikdortgen (kaynak pikselleri).
     *
     * @param  array{x?: mixed, y?: mixed, w?: mixed, h?: mixed}|null  $crop
     * @return array{x: int, y: int, w: int, h: int}
     */
    public static function coverRegion(int $sourceWidth, int $sourceHeight, int $w, int $h, ?array $crop = null): array
    {
        $box = self::cropBox($sourceWidth, $sourceHeight, $crop);
        $aspect = max(1, $w) / max(1, $h);

        $regionWidth = $box['w'];
        $regionHeight = (int) round($regionWidth / $aspect);

        if ($regionHeight > $box['h']) {
            $regionHeight = $box['h'];
            $regionWidth = (int) round($regionHeight * $aspect);
        }

        $regionWidth = max(1, min($box['w'], $regionWidth));
        $regionHeight = max(1, min($box['h'], $regionHeight));

        return [
            'x' => $box['x'] + intdiv($box['w'] - $regionWidth, 2),
            'y' => $box['y'] + intdiv($box['h'] - $regionHeight, 2),
            'w' => $regionWidth,
            'h' => $regionHeight,
        ];
    }

    /**
     * Piksel dikdortgenini kaynaga gore 0..1 normalize kutuya cevirir
     * (6 ondalik; `crop_*` kolonlarina yazilan deger).
     *
     * @param  array{x: int, y: int, w: int, h: int}  $region
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function normalizeRegion(array $region, int $sourceWidth, int $sourceHeight): array
    {
        $sourceWidth = max(1, $sourceWidth);
        $sourceHeight = max(1, $sourceHeight);

        return [
            'x' => round($region['x'] / $sourceWidth, 6),
            'y' => round($region['y'] / $sourceHeight, 6),
            'w' => round($region['w'] / $sourceWidth, 6),
            'h' => round($region['h'] / $sourceHeight, 6),
        ];
    }

    /**
     * Uzun kenari $longEdge olan, orani korunan olcu.
     *
     * @return array{0: int, 1: int}
     */
    public static function sizeForLongEdge(int $width, int $height, int $longEdge): array
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $longEdge = max(1, min(self::MAX_EDGE, $longEdge));
        $ratio = $longEdge / max($width, $height);

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    /**
     * $factor katina getirilmis, uzun kenari $cap ile sinirli olcu. Buyutme
     * isteginde sinir yuzunden kuculme olmaz (olcu oldugu gibi kalir).
     *
     * @return array{0: int, 1: int}
     */
    public static function sizeForFactor(int $width, int $height, float $factor, int $cap = self::MAX_EDGE): array
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $cap = max(1, min(self::MAX_EDGE, $cap));
        $factor = $factor > 0 ? $factor : 1.0;

        $current = max($width, $height);
        $target = (int) round(min($current * $factor, $cap));

        if ($factor >= 1.0 && $target < $current) {
            $target = $current;
        }

        if ($target === $current) {
            return [$width, $height];
        }

        return self::sizeForLongEdge($width, $height, min($target, self::MAX_EDGE));
    }

    /**
     * Islenebilecek en buyuk kaynak (megapiksel); istisna metnindeki :max.
     */
    public static function maxMegapixels(): int
    {
        return max(1, (int) config('konelsis.social_media.max_source_megapixels', 24));
    }

    /**
     * Kaynagi dogrular: GD var mi, dosya desteklenen bir gorsel mi, piksel
     * sayisi sinirin altinda mi.
     *
     * @return array{0: int, 1: int, 2: string} [genislik, yukseklik, mime]
     */
    private function inspect(string $absPath): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw UnsupportedMediaException::make();
        }

        $dimensions = $this->dimensions($absPath);

        if ($dimensions === null) {
            throw UnsupportedMediaException::make();
        }

        [$width, $height, $mime] = $dimensions;
        $loader = self::LOADERS[$mime] ?? null;

        if ($loader === null || ! function_exists($loader)) {
            throw UnsupportedMediaException::make();
        }

        if ($width * $height > self::maxMegapixels() * 1_000_000) {
            throw ImageTooLargeException::make(['max' => self::maxMegapixels()]);
        }

        return [$width, $height, $mime];
    }

    /**
     * Kaynagin $region dikdortgenini $w x $h tuvale yeniden ornekler ve
     * gecici dosyaya yazar.
     *
     * @param  array{x: int, y: int, w: int, h: int}  $region
     */
    private function render(string $absPath, string $mime, int $sourceWidth, int $sourceHeight, array $region, int $w, int $h, int $quality): string
    {
        [$w, $h] = self::clampSize($w, $h);
        $upscaled = $w > $region['w'] || $h > $region['h'];

        // Keskinlestirme (imageconvolution) hedef boyutunda bir kopya daha acar.
        $this->guardMemory($sourceWidth * $sourceHeight + $w * $h * ($upscaled ? 2 : 1));

        $source = $this->load($absPath, $mime);
        $alpha = $this->hasAlpha($source, $mime);

        $target = imagecreatetruecolor($w, $h);

        if (! $target instanceof GdImage) {
            unset($source);

            throw UnsupportedMediaException::make();
        }

        if ($alpha) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            // Seffaf olmayan cikti JPEG'dir: olasi yari saydam pikseller beyaz zemine karisir.
            imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
            imagealphablending($target, true);
        }

        $copied = imagecopyresampled($target, $source, 0, 0, $region['x'], $region['y'], $w, $h, $region['w'], $region['h']);

        // Kaynak bellegi kodlamadan once birakilir.
        unset($source);

        if (! $copied) {
            unset($target);

            throw UnsupportedMediaException::make();
        }

        if ($upscaled) {
            $this->sharpen($target, $alpha);
        }

        return $this->encode($target, $alpha, $quality);
    }

    private function load(string $absPath, string $mime): GdImage
    {
        $loader = self::LOADERS[$mime] ?? null;

        if ($loader === null || ! function_exists($loader)) {
            throw UnsupportedMediaException::make();
        }

        $image = @$loader($absPath);

        if (! $image instanceof GdImage) {
            throw UnsupportedMediaException::make();
        }

        // Paletli gorseller (GIF, 8 bit PNG) dogru ornekleme icin gercek renge cevrilir.
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    /**
     * Gorsel gercekten seffaf piksel tasiyor mu? Yalniz seffaflik destekleyen
     * turlerde bakilir; kucuk bir kopya uzerinde taranir (tam tarama yavastir).
     * Tamami opak bir PNG boylece JPEG olarak yazilir ve gereksiz buyumez.
     */
    private function hasAlpha(GdImage $image, string $mime): bool
    {
        if (! in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $probeWidth = max(1, min($width, self::ALPHA_PROBE_EDGE));
        $probeHeight = max(1, min($height, self::ALPHA_PROBE_EDGE));

        $probe = imagecreatetruecolor($probeWidth, $probeHeight);

        if (! $probe instanceof GdImage) {
            return true;
        }

        imagealphablending($probe, false);
        imagesavealpha($probe, true);
        imagecopyresampled($probe, $image, 0, 0, 0, 0, $probeWidth, $probeHeight, $width, $height);

        for ($y = 0; $y < $probeHeight; $y++) {
            for ($x = 0; $x < $probeWidth; $x++) {
                if (((imagecolorat($probe, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Buyutmeden sonra hafif keskinlestirme (3x3 cekirdek, toplam agirlik 1). */
    private function sharpen(GdImage $image, bool $alpha): void
    {
        if (! function_exists('imageconvolution')) {
            return;
        }

        if ($alpha) {
            imagealphablending($image, false);
        }

        @imageconvolution($image, [
            [0.0, -1.0, 0.0],
            [-1.0, 16.0, -1.0],
            [0.0, -1.0, 0.0],
        ], 12.0, 0.0);
    }

    /**
     * Exif yonune gore gorseli dik hale getirir. imagerotate saat yonunun
     * tersine dondurur: 270 = saat yonunde 90 derece.
     */
    private function upright(GdImage $image, int $orientation): ?GdImage
    {
        $angle = match ($orientation) {
            3 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);

            if (! $rotated instanceof GdImage) {
                return null;
            }

            $image = $rotated;
        }

        $flip = match ($orientation) {
            2, 5, 7 => IMG_FLIP_HORIZONTAL,
            4 => IMG_FLIP_VERTICAL,
            default => null,
        };

        if ($flip !== null && ! imageflip($image, $flip)) {
            return null;
        }

        return $image;
    }

    /**
     * Tuvali gecici klasore yazar ve diskteki anahtari doner. Yazma basarisizsa
     * yarim dosya silinir.
     */
    private function encode(GdImage $image, bool $alpha, int $quality): string
    {
        $disk = Storage::disk('local');
        $directory = self::tmpDirectory();
        $disk->makeDirectory($directory);

        $key = $directory.'/'.Str::random(40).'.'.($alpha ? 'png' : 'jpg');
        $absPath = $disk->path($key);

        if ($alpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $written = @imagepng($image, $absPath, 6);
        } else {
            imageinterlace($image, true);
            $written = @imagejpeg($image, $absPath, max(1, min(100, $quality)));
        }

        unset($image);
        clearstatcache(true, $absPath);

        if (! $written || ! is_file($absPath) || (int) filesize($absPath) < 1) {
            if (is_file($absPath)) {
                @unlink($absPath);
            }

            throw UnsupportedMediaException::make();
        }

        return $key;
    }

    /** TIFF dizinindeki (IFD0) yon etiketini okur. */
    private function tiffOrientation(string $tiff): int
    {
        $length = strlen($tiff);

        if ($length < 14) {
            return 1;
        }

        $byteOrder = substr($tiff, 0, 2);

        if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
            return 1;
        }

        $little = $byteOrder === 'II';

        $u16 = static function (int $offset) use ($tiff, $length, $little): int {
            if ($offset < 0 || $offset + 2 > $length) {
                return 0;
            }

            return (int) (unpack($little ? 'v' : 'n', substr($tiff, $offset, 2))[1] ?? 0);
        };

        $u32 = static function (int $offset) use ($tiff, $length, $little): int {
            if ($offset < 0 || $offset + 4 > $length) {
                return 0;
            }

            return (int) (unpack($little ? 'V' : 'N', substr($tiff, $offset, 4))[1] ?? 0);
        };

        if ($u16(2) !== 42) {
            return 1;
        }

        $directory = $u32(4);

        if ($directory < 8 || $directory + 2 > $length) {
            return 1;
        }

        $entries = $u16($directory);

        for ($i = 0; $i < $entries; $i++) {
            $entry = $directory + 2 + $i * 12;

            if ($entry + 12 > $length) {
                break;
            }

            if ($u16($entry) !== 0x0112) {
                continue;
            }

            $value = match ($u16($entry + 2)) {
                3 => $u16($entry + 8),
                4 => $u32($entry + 8),
                1 => ord($tiff[$entry + 8]),
                default => 1,
            };

            return $value >= 1 && $value <= 8 ? $value : 1;
        }

        return 1;
    }

    /**
     * Tahmini bellek ihtiyaci sinirin uzerindeyse islemi reddeder.
     */
    private function guardMemory(int $pixels): void
    {
        $limit = self::memoryLimitBytes();

        if ($limit === null) {
            return;
        }

        $estimate = $pixels * self::BYTES_PER_PIXEL;
        $free = max(0, $limit - memory_get_usage(true));

        if ($estimate > $limit * self::MEMORY_SHARE || $estimate > $free * 0.9) {
            throw ImageTooLargeException::make(['max' => self::maxMegapixels()]);
        }
    }

    /** memory_limit bayt olarak; sinirsizsa (-1) null. */
    private static function memoryLimitBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $number = (float) $raw;

        if ($number <= 0) {
            return null;
        }

        $multiplier = match (strtolower(substr($raw, -1))) {
            'g' => 1073741824,
            'm' => 1048576,
            'k' => 1024,
            default => 1,
        };

        return (int) ($number * $multiplier);
    }

    /**
     * Olcuyu MAX_EDGE ile sinirlar (oran korunur).
     *
     * @return array{0: int, 1: int}
     */
    private static function clampSize(int $w, int $h): array
    {
        $w = max(1, $w);
        $h = max(1, $h);
        $long = max($w, $h);

        if ($long <= self::MAX_EDGE) {
            return [$w, $h];
        }

        $ratio = self::MAX_EDGE / $long;

        return [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
    }

    private static function tmpDirectory(): string
    {
        $directory = trim((string) config('konelsis.social_media.tmp_directory', 'social/tmp'), '/');

        return $directory !== '' ? $directory : 'social/tmp';
    }
}
