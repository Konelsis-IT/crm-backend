<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialMediaUsage;
use App\Models\SocialMedia\SocialComment;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
use App\Query\SocialMedia\SocialContentQueries;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Medya satirlarini React arayuzunun bekledigi JSON bicimine cevirir (B31,
 * D-106; SPEC 8 + F4). Veritabanina yazmaz.
 *
 * - mediaItem: bir medya satiri (surum). Aciklama ve sira grubun KOK satirindan
 *   gelir; kok verilmezse satirin yuklenmis `parent` iliskisine, o da yoksa
 *   satirin kendisine bakilir.
 * - grup: secili surumun mediaItem'i + `versions` (kok once, sonra surumler).
 *   `open_mark_count` grupta butun surumlerin acik isaretlerinin toplamidir.
 * - Butun adresler KOK-GORELI'dir (`/admin/social/media/<id>/file...`); rota
 *   henuz kayitli degilse null doner. Videoda `thumbnail_url` yalniz kapak
 *   varsa doludur (video dosyasi asla kucuk gorsel yerine sunulmaz).
 *
 * Metotlar statiktir; hem `SocialMediaPresenter::item()` hem de enjekte edilmis
 * ornek uzerinden cagrilabilir.
 */
final class SocialMediaPresenter
{
    public const FILE_ROUTE = 'filament.admin.social.media.file';

    /**
     * Tek medya satiri.
     *
     * @param  int|null  $openMarks  Acik isaret sayisi; bilinmiyorsa null (0 yazilir)
     * @param  SocialContentMedia|null  $root  Grubun kok satiri (aciklama ve sira icin)
     * @return array<string, mixed>
     */
    public static function item(SocialContentMedia $row, ?int $openMarks = null, ?SocialContentMedia $root = null): array
    {
        $root ??= $row->isRoot() ? $row : ($row->relationLoaded('parent') ? $row->parent : null);
        $owner = $root ?? $row;

        $file = $row->fileObject;
        $url = self::url($row);
        $isImage = $row->isImage();

        $posterId = $owner->poster_file_object_id ?? $row->poster_file_object_id;
        $posterUrl = ! $isImage && $url !== null && $posterId !== null
            ? $url.'?variant=poster&v='.(int) $posterId
            : null;
        $posterThumbnailUrl = $posterUrl !== null
            ? $url.'?variant=poster_thumbnail&v='.(int) $posterId
            : null;

        $byteSize = (int) ($row->byte_size ?: ($file?->byte_size ?? 0));

        return [
            'id' => (int) $row->getKey(),
            'media_id' => (int) $row->getKey(),
            'root_id' => $row->rootId(),
            'kind' => $row->kind?->value,
            'kind_label' => $row->kind?->getLabel(),
            'usage' => $row->usage?->value,
            'variant' => $row->variant?->value,
            'variant_label' => self::label($row),
            'is_selected' => (bool) $row->is_selected,
            'name' => $file?->original_name,
            'caption' => $owner->caption,
            'width' => $row->width !== null ? (int) $row->width : null,
            'height' => $row->height !== null ? (int) $row->height : null,
            'byte_size' => $byteSize,
            'size_human' => self::humanSize($byteSize),
            'mime' => $file?->mime_type,
            'url' => $url,
            'thumbnail_url' => $url === null ? null : ($isImage ? $url.'?variant=thumbnail' : $posterThumbnailUrl),
            'preview_url' => $url === null ? null : ($isImage ? $url.'?variant=preview' : $posterUrl),
            'download_url' => $url !== null ? $url.'?disposition=download' : null,
            'poster_url' => $posterUrl,
            'duration_seconds' => $row->duration_seconds !== null ? (int) $row->duration_seconds : null,
            'crop' => $row->cropBox(),
            'sort_order' => (int) $owner->sort_order,
            'open_mark_count' => max(0, (int) ($openMarks ?? 0)),
            'removed_at' => $row->removed_at?->toIso8601String(),
        ];
    }

    /**
     * Medya grubu: secili surum + butun surumler (kok once).
     *
     * @param  Collection<int, SocialContentMedia>  $versions  Grubun satirlari; kok icinde olsa da olmasa da kabul edilir
     * @param  array<int, int>  $openMarksByMediaId  Medya satiri kimligi => acik isaret sayisi
     * @return array<string, mixed>
     */
    public static function group(SocialContentMedia $root, Collection $versions, array $openMarksByMediaId = []): array
    {
        $rootId = (int) $root->getKey();

        $rows = $versions
            ->filter(fn (SocialContentMedia $row): bool => (int) $row->getKey() !== $rootId)
            ->sortBy(fn (SocialContentMedia $row): int => (int) $row->getKey())
            ->prepend($root)
            ->values();

        $selected = $rows->first(fn (SocialContentMedia $row): bool => (bool) $row->is_selected) ?? $root;

        $total = 0;

        foreach ($rows as $row) {
            $total += (int) ($openMarksByMediaId[(int) $row->getKey()] ?? 0);
        }

        return [
            ...self::item($selected, $total, $root),
            'versions' => $rows
                ->map(fn (SocialContentMedia $row): array => self::item($row, (int) ($openMarksByMediaId[(int) $row->getKey()] ?? 0), $root))
                ->all(),
        ];
    }

    /**
     * Icerigin galerisi: cikarilmamis gruplar, kokun sirasina gore.
     *
     * @param  array<int, int>|null  $openMarksByMediaId  Verilmezse sorgudan alinir
     * @return list<array<string, mixed>>
     */
    public static function gallery(SocialContent $c, ?array $openMarksByMediaId = null): array
    {
        $marks = $openMarksByMediaId ?? self::openMarks($c, true);

        return self::groups($c, false)
            ->map(fn (array $group): array => self::group($group['root'], $group['rows'], $marks))
            ->all();
    }

    /**
     * Galeriden cikarilmis gruplar (geri alma listesi); en son cikarilan once.
     *
     * @return list<array<string, mixed>>
     */
    public static function removed(SocialContent $c): array
    {
        return self::groups($c, true)
            ->sortByDesc(fn (array $group): int => (int) ($group['root']->removed_at?->getTimestamp() ?? 0))
            ->values()
            ->map(fn (array $group): array => self::group($group['root'], $group['rows']))
            ->all();
    }

    /**
     * Kart kapagi: galerinin ilk grubunun secili surumu.
     *
     * @return array<string, mixed>|null
     */
    public static function cover(SocialContent $c): ?array
    {
        return self::thumbs($c, 1)[0] ?? null;
    }

    /**
     * Kart mozaigi: ilk $max grubun secili surumleri.
     *
     * @return list<array<string, mixed>>
     */
    public static function thumbs(SocialContent $c, int $max = 4): array
    {
        $marks = self::openMarks($c, false);

        return self::groups($c, false)
            ->take(max(0, $max))
            ->map(function (array $group) use ($marks): array {
                $item = self::group($group['root'], $group['rows'], $marks);
                unset($item['versions']);

                return $item;
            })
            ->all();
    }

    /** Galeride video var mi (kartin `has_video` alani)? */
    public static function hasVideo(SocialContent $c): bool
    {
        return self::groups($c, false)->contains(fn (array $group): bool => $group['root']->isVideo());
    }

    /** Surumun okunur adi: kayitli etiket, yoksa tur adi + olcu ("Özgün 4000×3000"). */
    public static function label(SocialContentMedia $row): string
    {
        if (filled($row->variant_label)) {
            return (string) $row->variant_label;
        }

        $label = (string) ($row->variant?->getLabel() ?? '');

        return $row->width !== null && $row->height !== null
            ? trim($label.' '.(int) $row->width.'×'.(int) $row->height)
            : $label;
    }

    /** Okunur boyut (Turkce sayi bicimi: "1,2 MB"). */
    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.').' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return max(0, $bytes).' B';
    }

    /** Medya dosyasinin kok-goreli adresi; rota yoksa null. */
    public static function url(SocialContentMedia $row): ?string
    {
        try {
            return route(self::FILE_ROUTE, ['media' => (int) $row->getKey()], false);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Galeri gruplari (kok + satirlar), kokun sirasina gore.
     *
     * @return Collection<int, array{root: SocialContentMedia, rows: Collection<int, SocialContentMedia>}>
     */
    private static function groups(SocialContent $c, bool $removed): Collection
    {
        // Iliskiler cagiran sorguda yuklenmis olmalidir; eksikse burada tamamlanir.
        if (! $c->relationLoaded('media')) {
            $c->load(['media.fileObject']);
        } elseif ($c->media->contains(fn (SocialContentMedia $row): bool => ! $row->relationLoaded('fileObject'))) {
            $c->media->loadMissing('fileObject');
        }

        $rows =$c->media->filter(fn (SocialContentMedia $row): bool => $row->usage === SocialMediaUsage::Gallery);

        return $rows
            ->filter(fn (SocialContentMedia $row): bool => $row->isRoot() && $row->isRemoved() === $removed)
            ->sortBy([
                fn (SocialContentMedia $a, SocialContentMedia $b): int => (int) $a->sort_order <=> (int) $b->sort_order,
                fn (SocialContentMedia $a, SocialContentMedia $b): int => (int) $a->getKey() <=> (int) $b->getKey(),
            ])
            ->values()
            ->map(fn (SocialContentMedia $root): array => [
                'root' => $root,
                'rows' => $rows
                    ->filter(fn (SocialContentMedia $row): bool => $row->rootId() === (int) $root->getKey())
                    ->values(),
            ]);
    }

    /**
     * Medya satiri basina acik isaret sayisi. Yorumlar yukluyse bellekten
     * sayilir; degilse yalniz $allowQuery iken sorgudan alinir (kart listesinde
     * kart basina sorgu atilmaz).
     *
     * @return array<int, int>
     */
    private static function openMarks(SocialContent $c, bool $allowQuery): array
    {
        if ($c->relationLoaded('comments')) {
            $counts = [];

            foreach ($c->comments as $comment) {
                if ($comment instanceof SocialComment && $comment->isOpenMark() && $comment->media_id !== null) {
                    $mediaId = (int) $comment->media_id;
                    $counts[$mediaId] = ($counts[$mediaId] ?? 0) + 1;
                }
            }

            return $counts;
        }

        if (! $allowQuery) {
            return [];
        }

        $counts = app(SocialContentQueries::class)->openMarkCounts($c);

        return is_array($counts) ? $counts : [];
    }
}
