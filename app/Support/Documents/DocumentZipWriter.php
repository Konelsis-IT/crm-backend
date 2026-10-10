<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\Document\FileObjectStatus;
use App\Exceptions\Document\BundleNotCreatedException;
use App\Models\Document\FileObject;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Klasorlu ZIP yazici (D-176, "Tum belgeleri indir"). Dosyalar diskten
 * dogrudan ZIP'e eklenir; hicbiri bellege okunmaz (D-127: belgeler 1 GB'a
 * kadar). Yerel disk dosyasi yerinde okunur; baska diskteki dosya once gecici
 * dosyaya akitilir.
 *
 * Adlar:
 * - Klasor ve dosya adlarinda Windows / macOS'ta yasak karakterler
 *   (\ / : * ? " < > |, kontrol karakterleri) bosluga doner; Turkce harfler
 *   korunur ve ZIP'e UTF-8 bayragiyla yazilir.
 * - Ayni klasorde ayni ad ikinci kez gelirse "ad (2).uzanti" olur (buyuk /
 *   kucuk harf farki ayni ad sayilir).
 * - Zaten sikistirilmis turler (PDF, Office, gorsel, arsiv) sikistirilmadan
 *   eklenir; ZIP hizli olusur.
 */
final class DocumentZipWriter
{
    /** Klasor ve dosya adinin en fazla uzunlugu (karakter). */
    private const MAX_SEGMENT = 120;

    /** @var list<string> Yeniden sikistirilmayan uzantilar. */
    private const STORED_EXTENSIONS = [
        'pdf', 'zip', 'rar', '7z', 'gz', 'kmz', 'docx', 'xlsx', 'pptx', 'docm', 'xlsm', 'odt', 'ods',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'mp4', 'mov', 'avi', 'mp3',
    ];

    /** @var list<string> */
    private array $temporary = [];

    /**
     * ZIP'i $target yoluna yazar; eklenen dosya sayisini doner.
     *
     * D-184: belge disinda uretilmis bir dosya (or. teklifin referans listesi
     * Excel'i) `path` (yerel gecici dosya) ve `name` ile verilebilir; silinmesi
     * cagiranin isidir.
     *
     * @param  list<array{folders: list<string>, file?: FileObject, path?: string, name?: string}>  $entries
     */
    public function write(array $entries, string $target): int
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw BundleNotCreatedException::make();
        }

        $used = [];
        $count = 0;

        try {
            foreach ($entries as $entry) {
                $generated = isset($entry['path']);
                $source = $generated
                    ? (is_file((string) $entry['path']) ? (string) $entry['path'] : null)
                    : (isset($entry['file']) ? $this->localPath($entry['file']) : null);

                if ($source === null) {
                    continue;
                }

                $folder = implode('/', array_map(self::segment(...), $entry['folders']));
                $fileName = $generated ? self::segment((string) ($entry['name'] ?? basename($source))) : self::fileName($entry['file']);
                $name = self::uniqueName($used, $folder, $fileName);
                $path = ($folder !== '' ? $folder.'/' : '').$name;

                if (! $zip->addFile($source, $path, 0, ZipArchive::LENGTH_TO_END, ZipArchive::FL_OVERWRITE | ZipArchive::FL_ENC_UTF_8)) {
                    continue;
                }

                if (in_array(mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION)), self::STORED_EXTENSIONS, true)) {
                    $zip->setCompressionName($path, ZipArchive::CM_STORE);
                }

                $count++;
            }

            // Hic dosya eklenmediyse bos ZIP yazilmaz (ZipArchive kapatirken dosya olusturmaz).
            $zip->close();
        } finally {
            foreach ($this->temporary as $temporary) {
                @unlink($temporary);
            }

            $this->temporary = [];
        }

        return $count;
    }

    /** Klasor / dosya adi parcasi: yasak karakterler temizlenir, Turkce korunur. */
    public static function segment(string $value): string
    {
        $clean = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean), " .\t");

        if (mb_strlen($clean) > self::MAX_SEGMENT) {
            $clean = rtrim(mb_substr($clean, 0, self::MAX_SEGMENT), ' .');
        }

        return $clean !== '' ? $clean : '_';
    }

    private static function fileName(FileObject $file): string
    {
        $name = self::segment((string) $file->original_name);
        $extension = (string) $file->extension;

        if ($name === '_' && $extension !== '') {
            return 'belge.'.$extension;
        }

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            return $name.'.'.$extension;
        }

        return $name;
    }

    /**
     * @param  array<string, true>  $used
     */
    private static function uniqueName(array &$used, string $folder, string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $index = 2;

        while (isset($used[mb_strtolower($folder.'/'.$candidate)])) {
            $candidate = $base.' ('.$index.')'.($extension !== '' ? '.'.$extension : '');
            $index++;
        }

        $used[mb_strtolower($folder.'/'.$candidate)] = true;

        return $candidate;
    }

    /** Dosyanin yerel yolu; dosya yoksa ya da etkin degilse null. */
    private function localPath(FileObject $file): ?string
    {
        if ($file->status !== FileObjectStatus::Active) {
            return null;
        }

        $diskName = $file->storage_disk ?: 'local';
        $disk = Storage::disk($diskName);

        if (! $disk->exists($file->storage_key)) {
            return null;
        }

        if (config('filesystems.disks.'.$diskName.'.driver') === 'local') {
            return $disk->path($file->storage_key);
        }

        $stream = $disk->readStream($file->storage_key);
        $temporary = tempnam(sys_get_temp_dir(), 'kbz');

        if ($stream === null || $temporary === false) {
            return null;
        }

        $target = fopen($temporary, 'wb');

        if ($target === false) {
            return null;
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);
        $this->temporary[] = $temporary;

        return $temporary;
    }
}
