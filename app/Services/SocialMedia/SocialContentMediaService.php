<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Document\FileObjectStatus;
use App\Enums\SocialMedia\SocialImageFormat;
use App\Enums\SocialMedia\SocialMediaKind;
use App\Enums\SocialMedia\SocialMediaUsage;
use App\Enums\SocialMedia\SocialMediaVariant;
use App\Enums\SocialMedia\SocialResolutionPreset;
use App\Exceptions\AbstractException;
use App\Exceptions\RecordNotFoundException;
use App\Exceptions\SocialMedia\FileTooLargeException;
use App\Exceptions\SocialMedia\ImageTooLargeException;
use App\Exceptions\SocialMedia\MediaNotImageException;
use App\Exceptions\SocialMedia\UnsupportedMediaException;
use App\Infrastructure\Media\ChunkedUploadStore;
use App\Infrastructure\Media\GdImageProcessor;
use App\Models\Document\FileObject;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\FileObjectService;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use finfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Icerik medyasi servisi (B31, D-106): gorsel/video ekleme, bicim ve cozunurluk
 * surumleri, surum secimi, siralama, aciklama, galeriden cikarma / geri alma,
 * video kapagi ve tembel onizleme.
 *
 * Kurallar:
 * - "Medya grubu" = kok satir + surumleri. Grupta tek satir secilidir; aciklama
 *   ve sira KOK satirda tutulur. Surumler her zaman kok OZGUN dosyadan uretilir
 *   (ust uste yeniden kodlama olmaz); `crop_*` koke gore normalize kirpmadir.
 * - Her degisiklik tek transaction'da, ust icerik satiri kilitlenerek calisir
 *   (E2). Arsivdeki ya da paylasilmis icerikte medya degismez
 *   (SocialContentService::assertEditable). Onayli icerigin galerisi degisirse
 *   icerik yeniden onaya doner (requireReapproval); metin ici gorsel bunu
 *   tetiklemez (govde kaydi tetikler).
 * - Silme yoktur: cikarma `removed_at` yazar, dosya yerinde kalir.
 * - Gecici dosyalar `local` diskte ANAHTAR olarak dolasir (E9); her hata
 *   yolunda silinir. Dosya turu istemci beyanina degil finfo'ya gore belirlenir.
 * - Hareket kayitlarinda yalniz okunur etiketler yer alir (kimlik yazilmaz).
 */
final class SocialContentMediaService extends AbstractService
{
    /** Tembel onizlemenin uzun kenari (px) ve JPEG kalitesi. */
    public const PREVIEW_LONG_EDGE = 1280;

    public const PREVIEW_QUALITY = 85;

    public const CAPTION_MAX = 300;

    private const LABEL_MAX = 80;

    /** Istemcinin bildirdigi video olcusu/suresi icin ust sinirlar. */
    private const MAX_REPORTED_EDGE = 16384;

    private const MAX_REPORTED_SECONDS = 86400;

    protected string $model = SocialContentMedia::class;

    /** @var list<string> */
    protected array $with = ['fileObject'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly FileObjectService $fileObjects,
        private readonly GdImageProcessor $images,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Galeriye gorsel ya da video ekler. $tempKey `local` diskteki gecici
     * dosyadir; basarida kalici yere tasinir, hatada silinir.
     *
     * @param  array{caption?: string|null, width?: int|string|null, height?: int|string|null, duration_seconds?: int|string|null}  $meta
     */
    public function attach(SocialContent $c, string $tempKey, ?string $name, array $meta = []): SocialContentMedia
    {
        return $this->store($c, $tempKey, $name, $meta, SocialMediaUsage::Gallery);
    }

    /**
     * Metin editorune gomulen gorseli ekler (uzun metin / blog). Galeriye
     * girmez, sayaclari ve onay durumunu etkilemez.
     *
     * @param  array{caption?: string|null}  $meta
     */
    public function attachInline(SocialContent $c, string $tempKey, ?string $name, array $meta = []): SocialContentMedia
    {
        return $this->store($c, $tempKey, $name, $meta, SocialMediaUsage::Inline);
    }

    /**
     * Icerik bu turde galeri medyasi kabul ediyor mu? Buyuk bir yukleme
     * baslamadan once denetleyicinin cagirmasi icindir (arsiv/paylasim kilidi
     * dahil).
     */
    public function assertCanAttach(SocialContent $c, SocialMediaKind $kind): void
    {
        $this->contents()->assertEditable($c);
        $this->assertAccepts($c, $kind, SocialMediaUsage::Gallery);
    }

    /**
     * Gorselin yeni bir surumunu uretir ve secer.
     *
     * Hat (E12): (1) bicim = verilen, yoksa grubun secili satirinin bicimi;
     * `original` verilirse bicim ve devralinan kirpma birakilir. (2) kirpma =
     * verilen, yoksa (bicim degismiyorsa) secili satirin kirpmasi, yoksa
     * ortalanmis. (3) bicim varsa hedef olcuye kapla-kirp. (4) cozunurluk:
     * SD/HD/Full HD uzun kenari 720 / 1280 / 1920 yapar (buyutme serbest), 2x iki
     * katina cikarir (4096 sinirli). Ayni surum zaten varsa yenisi eklenmez, o
     * satir secilir.
     *
     * @param  array{x?: mixed, y?: mixed, w?: mixed, h?: mixed}|null  $crop  Kok ozgun gorsele gore 0..1
     */
    public function createVariant(SocialContentMedia $m, ?SocialImageFormat $format, ?SocialResolutionPreset $preset, ?array $crop): SocialContentMedia
    {
        if (! $m->isImage()) {
            throw MediaNotImageException::make();
        }

        $variant = $this->mutate($m, function (SocialContent $content, SocialContentMedia $row) use ($format, $preset, $crop): SocialContentMedia {
            if (! $row->isImage()) {
                throw MediaNotImageException::make();
            }

            $group = $this->group($row);
            $root = $this->rootIn($group, $row);
            $selected = $group->first(fn (SocialContentMedia $item): bool => (bool) $item->is_selected) ?? $root;

            $selectedFormat = $selected->variant?->imageFormat();
            $effectiveFormat = $format ?? $selectedFormat;

            if ($effectiveFormat === SocialImageFormat::Original) {
                $effectiveFormat = null;
            }

            // Secili satirin kadraji yalniz bicim degismiyorsa devralinir (cozunurluk
            // dugmeleri kadraji korur); baska bir bicime gecis ortalanmis baslar.
            $inheritsCrop = $format === null || $format === $selectedFormat;
            $effectiveCrop = $crop ?? ($inheritsCrop ? $selected->cropBox() : null);

            $before = (int) $selected->getKey();
            $result = $this->buildVariant($content, $root, $group, $effectiveFormat, $preset, $effectiveCrop);

            if ((int) $result->getKey() !== $before) {
                $this->contents()->requireReapproval($content, 'media_variant');
            }

            return $result;
        });

        return $this->reload($variant);
    }

    /** Grubun kullanilacak surumunu secer. */
    public function select(SocialContentMedia $media): SocialContentMedia
    {
        $selected = $this->mutate($media, function (SocialContent $content, SocialContentMedia $row): SocialContentMedia {
            if ($row->is_selected) {
                return $row;
            }

            $group = $this->group($row);
            $root = $this->rootIn($group, $row);

            $this->markSelected($group, $row);
            $this->recordActivity($root, 'selected', $this->summary($content, $root, [
                'surum' => $this->labelOf($row),
            ]));
            $this->contents()->requireReapproval($content, 'media_selected');

            return $row;
        });

        return $this->reload($selected);
    }

    /**
     * Galeri sirasini degistirir. $rootIds, icerigin galeride duran (cikarilmamis)
     * kok satirlarinin TAMAMI olmalidir; eksik, fazla ya da yabanci kimlik
     * reddedilir.
     *
     * @param  array<int, mixed>  $rootIds
     */
    public function reorder(SocialContent $c, array $rootIds): void
    {
        $ordered = [];

        foreach ($rootIds as $id) {
            if (! is_numeric($id) || in_array((int) $id, $ordered, true)) {
                throw RecordNotFoundException::make();
            }

            $ordered[] = (int) $id;
        }

        $this->transactions->run(function () use ($c, $ordered): void {
            $content = $this->lockContent((int) $c->getKey());
            $this->contents()->assertEditable($content);

            $roots = $this->galleryRoots($content);
            $current = $roots->map(fn (SocialContentMedia $root): int => (int) $root->getKey())->all();

            $expected = $current;
            $given = $ordered;
            sort($expected);
            sort($given);

            if ($expected !== $given) {
                throw RecordNotFoundException::make();
            }

            if ($current === $ordered) {
                return;
            }

            $byId = $roots->keyBy(fn (SocialContentMedia $root): int => (int) $root->getKey());
            $names = [];

            foreach ($ordered as $position => $id) {
                /** @var SocialContentMedia $root */
                $root = $byId->get($id);
                $names[] = $this->fileName($root);

                if ((int) $root->sort_order !== $position) {
                    $root->fill(['sort_order' => $position])->save();
                }
            }

            /** @var SocialContentMedia $first */
            $first = $byId->get($ordered[0]);
            $shown = array_slice($names, 0, 10);

            if (count($names) > count($shown)) {
                $shown[] = '…';
            }

            $this->recordActivity($first, 'reordered', [
                'icerik_no' => (string) $content->content_no,
                'icerik' => (string) $content->title,
                'sira' => implode(' → ', $shown),
            ]);
            $this->contents()->requireReapproval($content, 'media_reordered');
        });
    }

    /** Medyanin aciklamasini (grup icin tek, kok satirda) gunceller. */
    public function updateCaption(SocialContentMedia $media, ?string $caption): SocialContentMedia
    {
        $caption = $this->cleanCaption($caption);

        $updated = $this->mutate($media, function (SocialContent $content, SocialContentMedia $row) use ($caption): SocialContentMedia {
            $root = $this->rootIn($this->group($row), $row);
            $previous = $root->caption;

            if ($previous === $caption) {
                return $row;
            }

            $root->fill(['caption' => $caption])->save();

            $this->recordActivity($root, 'caption_updated', $this->summary($content, $root, [
                'aciklama' => ['onceki' => $previous, 'yeni' => $caption],
            ]));
            $this->contents()->requireReapproval($content, 'media_caption');

            return $row;
        });

        return $this->reload($updated);
    }

    /** Medya grubunu galeriden cikarir (dosya silinmez; geri alinabilir). */
    public function remove(SocialContentMedia $media): SocialContentMedia
    {
        $removed = $this->mutate($media, function (SocialContent $content, SocialContentMedia $row): SocialContentMedia {
            $group = $this->group($row);
            $root = $this->rootIn($group, $row);
            $now = Carbon::now('UTC');
            $actorId = $this->actor->personnelId();

            foreach ($group as $item) {
                $item->fill(['removed_at' => $now, 'removed_by_personnel_id' => $actorId])->save();
            }

            $this->recordActivity($root, 'removed', $this->summary($content, $root));
            $this->contents()->requireReapproval($content, 'media_removed');
            $this->contents()->recomputeCounters($content);

            return $row;
        });

        return $this->reload($removed);
    }

    /** Galeriden cikarilmis grubu geri alir; grup galerinin sonuna eklenir. */
    public function restore(SocialContentMedia $media): SocialContentMedia
    {
        $restored = $this->mutate($media, function (SocialContent $content, SocialContentMedia $row): SocialContentMedia {
            if (! $row->isRemoved()) {
                return $row;
            }

            $group = $this->group($row);
            $root = $this->rootIn($group, $row);
            $position = $this->nextSortOrder($content);

            foreach ($group as $item) {
                $values = ['removed_at' => null, 'removed_by_personnel_id' => null];

                if ($item->isRoot()) {
                    $values['sort_order'] = $position;
                }

                $item->fill($values)->save();
            }

            $this->recordActivity($root, 'restored', $this->summary($content, $root));
            $this->contents()->requireReapproval($content, 'media_restored');
            $this->contents()->recomputeCounters($content);

            return $row;
        }, allowRemoved: true);

        return $this->reload($restored);
    }

    /**
     * Videonun kapak gorselini belirler (tarayicida yakalanan kare). $tempKey
     * `local` diskteki gecici gorseldir.
     */
    public function setPoster(SocialContentMedia $media, string $tempKey, ?string $name): SocialContentMedia
    {
        $currentKey = $tempKey;

        try {
            if (! $media->isVideo()) {
                throw UnsupportedMediaException::make();
            }

            $upload = $this->inspectUpload($currentKey, imageOnly: true);
            $name = $this->cleanName($name, 'kapak.jpg');

            $updated = $this->mutate($media, function (SocialContent $content, SocialContentMedia $row) use ($upload, $name): SocialContentMedia {
                if (! $row->isVideo()) {
                    throw UnsupportedMediaException::make();
                }

                $poster = $this->fileObjects->createFromUpload($upload['key'], $name);
                $this->assertUsable($poster);

                $root = $this->rootIn($this->group($row), $row);
                $root->fill(['poster_file_object_id' => (int) $poster->getKey()])->save();

                $this->recordActivity($root, 'poster_set', $this->summary($content, $root));
                $this->contents()->requireReapproval($content, 'media_poster');

                return $root;
            });

            return $this->reload($updated);
        } catch (Throwable $exception) {
            $this->deleteTemp($tempKey);
            $this->deleteTemp($currentKey);

            throw $exception;
        }
    }

    /**
     * Gorselin 1280 px uzun kenarli onizlemesini (yoksa) uretir ve dondurur.
     * Gorsel zaten kucukse, video ise ya da uretim basarisizsa null doner; asla
     * istisna firlatmaz (cagiran ozgun dosyaya duser). Goruntuleme sirasinda
     * calistigi icin icerigin kilidine, onay durumuna ve surumune dokunmaz;
     * kolon model olayi tetiklemeden yazilir.
     */
    public function ensurePreview(SocialContentMedia $media): ?FileObject
    {
        if (! $media->isImage()) {
            return null;
        }

        $existing = $media->preview_file_object_id !== null ? $media->previewFile : null;

        if ($existing !== null && $existing->status === FileObjectStatus::Active) {
            return $existing;
        }

        $source = $media->fileObject;

        if ($source === null || $source->status !== FileObjectStatus::Active) {
            return null;
        }

        $width = (int) ($media->width ?? $source->image_width ?? 0);
        $height = (int) ($media->height ?? $source->image_height ?? 0);

        if ($width > 0 && $height > 0 && max($width, $height) <= self::PREVIEW_LONG_EDGE) {
            return null;
        }

        $tempKey = null;

        try {
            return $this->transactions->run(function () use ($media, $source, &$tempKey): ?FileObject {
                /** @var SocialContentMedia|null $row */
                $row = SocialContentMedia::query()->lockForUpdate()->whereKey($media->getKey())->first();

                if ($row === null) {
                    return null;
                }

                if ($row->preview_file_object_id !== null) {
                    return $row->previewFile;
                }

                $absPath = $this->absolutePath($source);
                $dimensions = $this->images->dimensions($absPath);

                if ($dimensions === null || max($dimensions[0], $dimensions[1]) <= self::PREVIEW_LONG_EDGE) {
                    return null;
                }

                $tempKey = $this->images->resizeLongEdge($absPath, self::PREVIEW_LONG_EDGE, self::PREVIEW_QUALITY);
                $extension = strtolower((string) pathinfo($tempKey, PATHINFO_EXTENSION)) ?: 'jpg';
                $name = pathinfo((string) $source->original_name, PATHINFO_FILENAME).'-onizleme.'.$extension;

                $preview = $this->fileObjects->createFromUpload($tempKey, $name);

                SocialContentMedia::query()
                    ->whereKey($row->getKey())
                    ->update(['preview_file_object_id' => (int) $preview->getKey()]);

                return $preview;
            });
        } catch (Throwable) {
            $this->deleteTemp($tempKey);

            return null;
        }
    }

    /**
     * Yuklemeyi dogrular, dosya nesnesini olusturur ve medya satirini yazar.
     *
     * @param  array<string, mixed>  $meta
     */
    private function store(SocialContent $c, string $tempKey, ?string $name, array $meta, SocialMediaUsage $usage): SocialContentMedia
    {
        $currentKey = $tempKey;

        try {
            // Agir isten once ucuz denetim; kilit altinda yeniden yapilir.
            $this->contents()->assertEditable($c);
            $this->assertAccepts($c, null, $usage);

            $upload = $this->inspectUpload($currentKey, imageOnly: $usage === SocialMediaUsage::Inline);
            $name = $this->cleanName($name, $upload['kind'] === SocialMediaKind::Video ? 'video' : 'gorsel');
            $caption = $this->cleanCaption(isset($meta['caption']) ? (string) $meta['caption'] : null);

            $row = $this->transactions->run(function () use ($c, $upload, $name, $caption, $meta, $usage): SocialContentMedia {
                $content = $this->lockContent((int) $c->getKey());
                $this->contents()->assertEditable($content);
                $this->assertAccepts($content, $upload['kind'], $usage);

                $isImage = $upload['kind'] === SocialMediaKind::Image;

                $file = $isImage
                    ? $this->fileObjects->createFromUpload($upload['key'], $name)
                    : $this->fileObjects->createFromLocalFile($upload['key'], $name, self::videoDirectory());

                $this->assertUsable($file);

                $row = new SocialContentMedia([
                    'content_id' => (int) $content->getKey(),
                    'file_object_id' => (int) $file->getKey(),
                    'kind' => $upload['kind']->value,
                    'usage' => $usage->value,
                    'parent_media_id' => null,
                    'variant' => SocialMediaVariant::Original->value,
                    'variant_label' => null,
                    'is_selected' => true,
                    'sort_order' => $usage === SocialMediaUsage::Gallery ? $this->nextSortOrder($content) : 0,
                    'caption' => $caption,
                    'width' => $isImage ? $upload['width'] : $this->reported($meta, 'width', self::MAX_REPORTED_EDGE),
                    'height' => $isImage ? $upload['height'] : $this->reported($meta, 'height', self::MAX_REPORTED_EDGE),
                    'byte_size' => (int) $file->byte_size,
                    'duration_seconds' => $isImage ? null : $this->reported($meta, 'duration_seconds', self::MAX_REPORTED_SECONDS),
                ]);
                $row->save();
                $row->setRelation('fileObject', $file);

                $this->recordActivity($row, 'attached', $this->summary($content, $row, [
                    'tur' => $usage === SocialMediaUsage::Inline ? $usage->getLabel() : $upload['kind']->getLabel(),
                    'olcu' => $isImage ? $upload['width'].'×'.$upload['height'] : null,
                ]));

                if ($usage === SocialMediaUsage::Inline) {
                    return $row;
                }

                // Icerigin bicimi seciliyse ortalanmis kirpma surumu hemen uretilir ve secilir.
                $format = $content->image_format;

                if ($isImage && $format instanceof SocialImageFormat && $format->requiresCrop()) {
                    try {
                        $this->buildVariant($content, $row, new Collection([$row]), $format, null, null);
                    } catch (AbstractException) {
                        // Kirpma uretilemezse yukleme bozulmaz; ozgun gorsel secili kalir.
                    }
                }

                $this->contents()->requireReapproval($content, 'media_added');
                $this->contents()->recomputeCounters($content);

                return $row;
            });

            return $this->reload($row);
        } catch (Throwable $exception) {
            $this->deleteTemp($tempKey);
            $this->deleteTemp($currentKey);

            throw $exception;
        }
    }

    /**
     * Kok ozgun gorselden istenen surumu uretir, satirini yazar ve secer.
     * Ayni surum (ayni tur, olcu ve kirpma ya da ayni dosya) grupta varsa yeni
     * satir eklenmez; o satir secilir. Hicbir donusum gerekmiyorsa kok secilir.
     *
     * @param  Collection<int, SocialContentMedia>  $group
     * @param  array{x?: mixed, y?: mixed, w?: mixed, h?: mixed}|null  $crop
     */
    private function buildVariant(SocialContent $content, SocialContentMedia $root, Collection $group, ?SocialImageFormat $format, ?SocialResolutionPreset $preset, ?array $crop): SocialContentMedia
    {
        $source = $root->fileObject;

        if ($source === null) {
            throw RecordNotFoundException::make();
        }

        $this->assertUsable($source);

        $absPath = $this->absolutePath($source);
        $dimensions = $this->images->dimensions($absPath);

        if ($dimensions === null) {
            throw UnsupportedMediaException::make();
        }

        [$sourceWidth, $sourceHeight] = $dimensions;
        $hasFormat = $format !== null && $format->requiresCrop();

        if ($hasFormat) {
            [$baseWidth, $baseHeight] = (array) $format->targetSize();
            $region = GdImageProcessor::coverRegion($sourceWidth, $sourceHeight, (int) $baseWidth, (int) $baseHeight, $crop);
        } else {
            $region = GdImageProcessor::cropBox($sourceWidth, $sourceHeight, $crop);
            [$baseWidth, $baseHeight] = [$region['w'], $region['h']];
        }

        [$width, $height] = $this->presetSize((int) $baseWidth, (int) $baseHeight, $preset);

        $isWhole = $region['x'] === 0 && $region['y'] === 0 && $region['w'] === $sourceWidth && $region['h'] === $sourceHeight;
        $cropValues = $isWhole ? null : GdImageProcessor::normalizeRegion($region, $sourceWidth, $sourceHeight);

        // Donusum gerekmiyor: kok (ozgun) satir secilir.
        if (! $hasFormat && $isWhole && $width === $sourceWidth && $height === $sourceHeight) {
            $this->selectWithActivity($content, $root, $group, $root);

            return $root;
        }

        $variant = SocialMediaVariant::forFormat($hasFormat ? $format : null);

        $same = $group->first(fn (SocialContentMedia $item): bool => ! $item->isRoot()
            && $item->variant === $variant
            && (int) $item->width === $width
            && (int) $item->height === $height
            && $this->sameCrop($item->cropBox(), $cropValues));

        if ($same instanceof SocialContentMedia) {
            $this->selectWithActivity($content, $root, $group, $same);

            return $same;
        }

        if ($group->count() >= self::maxVersions()) {
            throw UnsupportedMediaException::make();
        }

        $tempKey = $this->images->fit($absPath, $width, $height, $cropValues);

        try {
            $extension = strtolower((string) pathinfo($tempKey, PATHINFO_EXTENSION)) ?: 'jpg';
            $fileName = pathinfo((string) $source->original_name, PATHINFO_FILENAME).'-'.$variant->value.'-'.$width.'x'.$height.'.'.$extension;
            $file = $this->fileObjects->createFromUpload($tempKey, $fileName);
            $this->assertUsable($file);
        } catch (Throwable $exception) {
            $this->deleteTemp($tempKey);

            throw $exception;
        }

        // Tekillestirme ayni dosyayi dondurduyse (ayni baytlar) yeni satir acilmaz.
        $shared = $group->first(fn (SocialContentMedia $item): bool => (int) $item->file_object_id === (int) $file->getKey());

        if ($shared instanceof SocialContentMedia) {
            $this->selectWithActivity($content, $root, $group, $shared);

            return $shared;
        }

        $actualWidth = (int) ($file->image_width ?? $width);
        $actualHeight = (int) ($file->image_height ?? $height);
        $labelBase = $hasFormat
            ? $format->getLabel()
            : ($preset?->getLabel() ?? $variant->getLabel());

        $row = new SocialContentMedia([
            'content_id' => (int) $content->getKey(),
            'file_object_id' => (int) $file->getKey(),
            'kind' => SocialMediaKind::Image->value,
            'usage' => SocialMediaUsage::Gallery->value,
            'parent_media_id' => (int) $root->getKey(),
            'variant' => $variant->value,
            'variant_label' => mb_substr($labelBase.' '.$actualWidth.'×'.$actualHeight, 0, self::LABEL_MAX),
            'is_selected' => false,
            'sort_order' => (int) $root->sort_order,
            'caption' => null,
            'width' => $actualWidth,
            'height' => $actualHeight,
            'byte_size' => (int) $file->byte_size,
            'crop_x' => $cropValues['x'] ?? null,
            'crop_y' => $cropValues['y'] ?? null,
            'crop_w' => $cropValues['w'] ?? null,
            'crop_h' => $cropValues['h'] ?? null,
        ]);
        $row->save();
        $row->setRelation('fileObject', $file);

        $group->push($row);
        $this->markSelected($group, $row);

        $this->recordActivity($root, 'variant_created', $this->summary($content, $root, [
            'surum' => (string) $row->variant_label,
        ]));

        return $row;
    }

    /**
     * Medya uzerindeki degisikliklerin ortak cercevesi: transaction, ust icerik
     * kilidi, arsiv/paylasim kilidi, satirin kilit altinda yeniden okunmasi.
     * Metin ici gorseller ve (izin verilmedikce) cikarilmis gruplar degistirilemez.
     *
     * @template T
     *
     * @param  callable(SocialContent, SocialContentMedia): T  $callback
     * @return T
     */
    private function mutate(SocialContentMedia $media, callable $callback, bool $allowRemoved = false): mixed
    {
        return $this->transactions->run(function () use ($media, $callback, $allowRemoved): mixed {
            $content = $this->lockContent((int) $media->content_id);
            $this->contents()->assertEditable($content);

            /** @var SocialContentMedia $row */
            $row = $this->lockForUpdate($media);

            if ((int) $row->content_id !== (int) $content->getKey() || $row->usage !== SocialMediaUsage::Gallery) {
                throw RecordNotFoundException::make();
            }

            if (! $allowRemoved && $row->isRemoved()) {
                throw RecordNotFoundException::make();
            }

            return $callback($content, $row);
        });
    }

    /**
     * Gecici yuklemeyi dogrular: bos mu, gercek turu (finfo) izinli mi, boyut
     * sinirinda mi; gorselse olcusu okunabiliyor ve piksel siniri icinde mi.
     * JPEG yon etiketi tasiyorsa dik hale getirilir ve anahtar degisir
     * ($tempKey referansla guncellenir; eski dosya silinir).
     *
     * @return array{key: string, kind: SocialMediaKind, mime: string, width: int|null, height: int|null}
     */
    private function inspectUpload(string &$tempKey, bool $imageOnly): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($tempKey)) {
            throw RecordNotFoundException::make();
        }

        $absPath = $disk->path($tempKey);
        clearstatcache(true, $absPath);
        $size = (int) @filesize($absPath);

        if ($size < 1) {
            throw UnsupportedMediaException::make();
        }

        $mime = $this->detectMime($absPath);
        $isImage = in_array($mime, self::mimes('image_mimes'), true);
        $isVideo = in_array($mime, self::mimes('video_mimes'), true);

        if ($imageOnly && $isVideo) {
            throw MediaNotImageException::make();
        }

        if (! $isImage && ! $isVideo) {
            throw UnsupportedMediaException::make();
        }

        if ($isVideo) {
            if ($size > ChunkedUploadStore::maxBytes()) {
                throw FileTooLargeException::make(['max' => ChunkedUploadStore::readableSize(ChunkedUploadStore::maxBytes())]);
            }

            return ['key' => $tempKey, 'kind' => SocialMediaKind::Video, 'mime' => $mime, 'width' => null, 'height' => null];
        }

        $maxImageBytes = max(1, (int) config('konelsis.social_media.max_image_kb', 25600)) * 1024;

        if ($size > $maxImageBytes) {
            throw FileTooLargeException::make(['max' => ChunkedUploadStore::readableSize($maxImageBytes)]);
        }

        $dimensions = $this->images->dimensions($absPath);

        if ($dimensions === null) {
            throw UnsupportedMediaException::make();
        }

        if ($dimensions[0] * $dimensions[1] > GdImageProcessor::maxMegapixels() * 1_000_000) {
            throw ImageTooLargeException::make(['max' => GdImageProcessor::maxMegapixels()]);
        }

        if ($mime === 'image/jpeg') {
            $uprightKey = $this->images->normalizeOrientation($tempKey);

            if ($uprightKey !== null) {
                $disk->delete($tempKey);
                $tempKey = $uprightKey;
                $dimensions = $this->images->dimensions($disk->path($tempKey)) ?? $dimensions;
            }
        }

        return ['key' => $tempKey, 'kind' => SocialMediaKind::Image, 'mime' => $mime, 'width' => $dimensions[0], 'height' => $dimensions[1]];
    }

    /** Dosyanin icerigine gore turu (uzantiya ya da istemci beyanina bakilmaz). */
    private function detectMime(string $absPath): string
    {
        if (! class_exists(finfo::class)) {
            throw UnsupportedMediaException::make();
        }

        $mime = @(new finfo(FILEINFO_MIME_TYPE))->file($absPath);

        return is_string($mime) ? strtolower(trim($mime)) : '';
    }

    /** Icerik turu bu kullanimdaki medyayi kabul ediyor mu? */
    private function assertAccepts(SocialContent $content, ?SocialMediaKind $kind, SocialMediaUsage $usage): void
    {
        $type = $content->content_type;

        if ($usage === SocialMediaUsage::Inline) {
            if ($type === null || ! $type->hasRichBody()) {
                throw UnsupportedMediaException::make();
            }

            if ($kind === SocialMediaKind::Video) {
                throw MediaNotImageException::make();
            }

            return;
        }

        if ($type === null || ! $type->hasGallery()) {
            throw UnsupportedMediaException::make();
        }
    }

    /** Dosya nesnesi kullanilabilir mi (imha edilmis bir kayda baglanilmaz)? */
    private function assertUsable(FileObject $file): void
    {
        if ($file->status !== FileObjectStatus::Active) {
            throw UnsupportedMediaException::make();
        }
    }

    /** Kalici dosyanin mutlak yolu; GD yalniz yerel surucudeki dosyayi isleyebilir. */
    private function absolutePath(FileObject $file): string
    {
        $diskName = (string) ($file->storage_disk ?: 'local');

        if (config('filesystems.disks.'.$diskName.'.driver') !== 'local') {
            throw UnsupportedMediaException::make();
        }

        $disk = $this->fileObjects->diskFor($file);

        if (! $disk->exists((string) $file->storage_key)) {
            throw RecordNotFoundException::make();
        }

        return $disk->path((string) $file->storage_key);
    }

    /**
     * Cozunurluk dugmesinin olcuye etkisi.
     *
     * @return array{0: int, 1: int}
     */
    private function presetSize(int $width, int $height, ?SocialResolutionPreset $preset): array
    {
        if ($preset === null) {
            return [max(1, $width), max(1, $height)];
        }

        $longEdge = $preset->longEdge();

        if ($longEdge !== null) {
            return GdImageProcessor::sizeForLongEdge($width, $height, $longEdge);
        }

        return GdImageProcessor::sizeForFactor($width, $height, $preset->factor() ?? 2.0, SocialResolutionPreset::MAX_EDGE);
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}|null  $a
     * @param  array{x: float, y: float, w: float, h: float}|null  $b
     */
    private function sameCrop(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        foreach (['x', 'y', 'w', 'h'] as $edge) {
            if (abs($a[$edge] - $b[$edge]) > 0.000002) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hedef satiri secer; secim degistiyse hareket kaydi yazar.
     *
     * @param  Collection<int, SocialContentMedia>  $group
     */
    private function selectWithActivity(SocialContent $content, SocialContentMedia $root, Collection $group, SocialContentMedia $target): void
    {
        if ($target->is_selected) {
            return;
        }

        $this->markSelected($group, $target);
        $this->recordActivity($root, 'selected', $this->summary($content, $root, [
            'surum' => $this->labelOf($target),
        ]));
    }

    /**
     * Grupta yalniz hedef satir secili kalir.
     *
     * @param  Collection<int, SocialContentMedia>  $group
     */
    private function markSelected(Collection $group, SocialContentMedia $target): void
    {
        foreach ($group as $item) {
            $shouldBeSelected = (int) $item->getKey() === (int) $target->getKey();

            if ((bool) $item->is_selected !== $shouldBeSelected) {
                $item->fill(['is_selected' => $shouldBeSelected])->save();
            }
        }

        $target->setAttribute('is_selected', true);
        $target->syncOriginalAttribute('is_selected');
    }

    /**
     * Satirin grubu: kok + surumler (kimlik sirasiyla, dosyalariyla).
     *
     * @return Collection<int, SocialContentMedia>
     */
    private function group(SocialContentMedia $row): Collection
    {
        $rootId = $row->rootId();

        return SocialContentMedia::query()
            ->with('fileObject')
            ->where(fn ($query) => $query->whereKey($rootId)->orWhere('parent_media_id', $rootId))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, SocialContentMedia>  $group
     */
    private function rootIn(Collection $group, SocialContentMedia $row): SocialContentMedia
    {
        $root = $group->first(fn (SocialContentMedia $item): bool => (int) $item->getKey() === $row->rootId());

        if (! $root instanceof SocialContentMedia) {
            throw RecordNotFoundException::make();
        }

        return $root;
    }

    /**
     * Galeride duran (cikarilmamis) kok satirlar, sirayla.
     *
     * @return Collection<int, SocialContentMedia>
     */
    private function galleryRoots(SocialContent $content): Collection
    {
        return SocialContentMedia::query()
            ->with('fileObject')
            ->where('content_id', (int) $content->getKey())
            ->where('usage', SocialMediaUsage::Gallery->value)
            ->whereNull('parent_media_id')
            ->whereNull('removed_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Galerinin sonundaki sira numarasi (cikarilmis gruplar da sayilir; cakisma olmaz). */
    private function nextSortOrder(SocialContent $content): int
    {
        $max = SocialContentMedia::query()
            ->where('content_id', (int) $content->getKey())
            ->where('usage', SocialMediaUsage::Gallery->value)
            ->whereNull('parent_media_id')
            ->max('sort_order');

        return $max === null ? 0 : min(65535, (int) $max + 1);
    }

    private function lockContent(int $contentId): SocialContent
    {
        /** @var SocialContent|null $content */
        $content = SocialContent::query()->lockForUpdate()->whereKey($contentId)->first();

        if ($content === null) {
            throw RecordNotFoundException::make();
        }

        return $content;
    }

    /** Donen satir: dosyasi, kapagi ve surumleriyle taze okunur. */
    private function reload(SocialContentMedia $row): SocialContentMedia
    {
        /** @var SocialContentMedia $fresh */
        $fresh = $this->query()
            ->with(['posterFile', 'parent.fileObject', 'versions.fileObject'])
            ->whereKey($row->getKey())
            ->first() ?? $row;

        return $fresh;
    }

    /**
     * Hareket ozeti: yalniz okunur etiketler (kimlik yazilmaz).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function summary(SocialContent $content, SocialContentMedia $row, array $extra = []): array
    {
        return array_filter([
            'icerik_no' => (string) $content->content_no,
            'icerik' => (string) $content->title,
            'dosya' => $this->fileName($row),
            ...$extra,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function fileName(SocialContentMedia $row): string
    {
        return (string) ($row->fileObject?->original_name ?? $row->kind?->getLabel() ?? '');
    }

    /** Surumun okunur adi: kayitli etiket, yoksa tur + olcu. */
    private function labelOf(SocialContentMedia $row): string
    {
        if (filled($row->variant_label)) {
            return (string) $row->variant_label;
        }

        $label = (string) ($row->variant?->getLabel() ?? '');

        return $row->width !== null && $row->height !== null
            ? trim($label.' '.$row->width.'×'.$row->height)
            : $label;
    }

    private function cleanCaption(?string $caption): ?string
    {
        $caption = trim((string) $caption);

        return $caption === '' ? null : mb_substr($caption, 0, self::CAPTION_MAX);
    }

    /** Istemcinin dosya adi: yol ve denetim karakterleri atilir, 255 ile sinirlanir. */
    private function cleanName(?string $name, string $fallback): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        return $name !== '' ? mb_substr($name, 0, 255) : $fallback;
    }

    /**
     * Istemcinin bildirdigi sayisal bilgi (video olcusu/suresi); gecersizse null.
     *
     * @param  array<string, mixed>  $meta
     */
    private function reported(array $meta, string $key, int $max): ?int
    {
        $value = $meta[$key] ?? null;

        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) round((float) $value);

        return $value > 0 ? min($max, $value) : null;
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
            // Gecici dosya temizligi asil hatayi golgelemez; artan dosyayi purgeStale siler.
        }
    }

    /**
     * Icerik servisi tembel cozulur: iki servis birbirini kurucuda isterse
     * dongusel bagimlilik olusur.
     */
    private function contents(): SocialContentService
    {
        return app(SocialContentService::class);
    }

    /**
     * @return list<string>
     */
    private static function mimes(string $key): array
    {
        return array_values(array_map(
            static fn (mixed $mime): string => strtolower((string) $mime),
            (array) config('konelsis.social_media.'.$key, []),
        ));
    }

    private static function videoDirectory(): string
    {
        $directory = trim((string) config('konelsis.social_media.video_directory', 'social/videos'), '/');

        return $directory !== '' ? $directory : 'social/videos';
    }

    private static function maxVersions(): int
    {
        return max(2, (int) config('konelsis.social_media.max_versions_per_media', 12));
    }
}
