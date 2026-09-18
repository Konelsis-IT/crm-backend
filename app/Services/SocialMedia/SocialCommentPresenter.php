<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialComment;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
use Illuminate\Support\Facades\Gate;

/**
 * Yorumlari React arayuzunun bekledigi JSON bicimine cevirir (B31, D-106;
 * SPEC 8 + F5). Veritabanina yazmaz.
 *
 *  - tree: kok yorumlar (eskiden yeniye), her birinin altinda yanitlari.
 *  - comment: tek yorum. Isaretli yorumda `media_id` isaretin cizildigi surum,
 *    `media_root_id` o surumun grubu, `media_label` surumun okunur adi,
 *    `media_available` surum hala galeride mi bilgisidir. `anchor` degerleri
 *    6 basamaga yuvarlanir; nokta icin w/h null'dur.
 *  - `author` null ise yorumu sistem yazmistir (arayuz "Sistem" gosterir).
 *
 * Metotlar statiktir; hem `SocialCommentPresenter::tree()` hem de enjekte
 * edilmis ornek uzerinden cagrilabilir.
 */
final class SocialCommentPresenter
{
    /**
     * Icerigin yorum agaci.
     *
     * @return list<array<string, mixed>>
     */
    public static function tree(SocialContent $c, Personnel $viewer): array
    {
        $c->loadMissing(['comments.createdBy.orgUnit', 'comments.resolvedBy.orgUnit', 'comments.media']);

        $comments = $c->comments->sortBy(fn (SocialComment $comment): int => (int) $comment->getKey())->values();
        $replies = [];

        /** @var SocialComment $comment */
        foreach ($comments as $comment) {
            // Yetki sorusu her yorumda icerigi yeniden okumasin.
            $comment->setRelation('content', $c);

            if ($comment->isReply()) {
                $replies[(int) $comment->parent_comment_id][] = $comment;
            }
        }

        $tree = [];

        foreach ($comments as $comment) {
            if ($comment->isReply()) {
                continue;
            }

            $tree[] = self::present($comment, $viewer, $replies[(int) $comment->getKey()] ?? []);
        }

        return $tree;
    }

    /**
     * Tek yorum. Kok yorumda yanitlar, iliski yukluyse eklenir; yanitin
     * `replies` alani her zaman bostur.
     *
     * @return array<string, mixed>
     */
    public static function comment(SocialComment $m, Personnel $viewer): array
    {
        $replies = ! $m->isReply() && $m->relationLoaded('replies')
            ? $m->replies->sortBy(fn (SocialComment $reply): int => (int) $reply->getKey())->values()->all()
            : [];

        return self::present($m, $viewer, $replies);
    }

    /**
     * @param  array<int, SocialComment>  $replies
     * @return array<string, mixed>
     */
    private static function present(SocialComment $m, Personnel $viewer, array $replies): array
    {
        $media = $m->media_id !== null ? $m->media : null;
        $content = $m->content;

        return [
            'id' => (int) $m->getKey(),
            'body' => (string) $m->body,
            'author' => SocialContentPresenter::person($m->createdBy),
            'created_at' => $m->created_at?->toIso8601String(),
            'parent_id' => $m->parent_comment_id !== null ? (int) $m->parent_comment_id : null,
            'media_id' => $m->media_id !== null ? (int) $m->media_id : null,
            'media_root_id' => $media instanceof SocialContentMedia ? $media->rootId() : null,
            'media_label' => $media instanceof SocialContentMedia ? self::mediaLabel($media) : null,
            'media_available' => $media instanceof SocialContentMedia && ! $media->isRemoved(),
            'anchor' => self::anchor($m),
            'resolved' => $m->isResolved(),
            'resolved_by' => SocialContentPresenter::person($m->resolvedBy),
            'resolved_at' => $m->resolved_at?->toIso8601String(),
            'replies' => array_values(array_map(
                function (SocialComment $reply) use ($viewer, $content): array {
                    if ($content instanceof SocialContent) {
                        $reply->setRelation('content', $content);
                    }

                    return self::present($reply, $viewer, []);
                },
                $replies,
            )),
            'can_resolve' => $content instanceof SocialContent
                && ! $m->isReply()
                && Gate::forUser($viewer)->allows('resolveComment', [$content, $m]),
        ];
    }

    /**
     * @return array{shape: string, x: float, y: float, w: ?float, h: ?float}|null
     */
    private static function anchor(SocialComment $m): ?array
    {
        if (! $m->isMark()) {
            return null;
        }

        $isRect = $m->anchor_shape === SocialComment::SHAPE_RECT;

        return [
            'shape' => (string) $m->anchor_shape,
            'x' => round((float) $m->anchor_x, 6),
            'y' => round((float) $m->anchor_y, 6),
            'w' => $isRect && $m->anchor_w !== null ? round((float) $m->anchor_w, 6) : null,
            'h' => $isRect && $m->anchor_h !== null ? round((float) $m->anchor_h, 6) : null,
        ];
    }

    /** Surumun okunur adi (medya sunumu ile ayni kural). */
    private static function mediaLabel(SocialContentMedia $media): string
    {
        return app(SocialMediaPresenter::class)->label($media);
    }
}
