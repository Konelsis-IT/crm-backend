<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialMediaUsage;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\RecordNotFoundException;
use App\Exceptions\SocialMedia\ContentArchivedException;
use App\Exceptions\SocialMedia\MediaNotImageException;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialComment;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Notification\PanelNotifier;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Icerik yorumlari ve gorsel uzerindeki isaretler (B31, D-106).
 *
 *  - Yorum duzenlenmez ve silinmez. Yanit tek gorsel seviyedir: yanita verilen
 *    yanit kok yoruma baglanir. Yanit isaret tasimaz.
 *  - Isaret (nokta ya da dikdortgen), cizildigi ANDA goruntulenen medya
 *    satirina (surume) baglanir; koordinatlar o satirin kutusuna gore 0..1
 *    araligina sikistirilir. Medya bu icerigin galerisinde, gorsel ve
 *    cikarilmamis olmalidir.
 *  - Duz yorum hacim nedeniyle Personel Hareketleri'ne yazilmaz; isaret,
 *    cozuldu ve yeniden acildi yazilir.
 *  - Isareti icerigi olusturandan baskasi koyduysa olusturana zil bildirimi gider.
 *  - Her yazma, ust icerik satiri kilitliyken tek transaction'da calisir.
 */
final class SocialCommentService extends AbstractService
{
    /** Hareket ozetindeki yorum alintisinin uzunlugu. */
    private const EXCERPT_LENGTH = 160;

    protected string $model = SocialComment::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly SocialContentService $contents,
        private readonly PanelNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Yorum ya da isaretli yorum ekler.
     *
     * @param  array<string, mixed>  $data  body, parent_id?, media_id?, anchor?{shape,x,y,w?,h?}
     */
    public function add(SocialContent $content, array $data): SocialComment
    {
        $me = $this->actorId();
        $body = trim(str_replace(["\r\n", "\r"], "\n", (string) ($data['body'] ?? '')));
        $max = (int) config('konelsis.social_media.comment_max', 4000);

        // Bos govde dogrulamada yakalanir; buraya ulasirsa islem reddedilir.
        if ($body === '') {
            throw InvalidTransitionException::make();
        }

        if ($max > 0) {
            $body = mb_substr($body, 0, $max);
        }

        $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;
        $mediaId = filled($data['media_id'] ?? null) ? (int) $data['media_id'] : null;
        $anchor = is_array($data['anchor'] ?? null) && filled($data['anchor']['shape'] ?? null) ? $data['anchor'] : null;

        /** @var SocialComment $comment */
        $comment = $this->transactions->run(function () use ($content, $me, $body, $parentId, $mediaId, $anchor): SocialComment {
            $locked = $this->lockContent($content);

            if ($locked->isArchived()) {
                throw ContentArchivedException::make();
            }

            $rootId = $parentId !== null ? $this->rootCommentId($locked, $parentId) : null;
            $attributes = [
                'content_id' => (int) $locked->getKey(),
                'parent_comment_id' => $rootId,
                'body' => $body,
            ];

            // Yanit isaret tasimaz; isaret ve medya baglantisi yalniz kok yorumdadir.
            if ($rootId === null) {
                if ($anchor !== null && $mediaId === null) {
                    throw MediaNotImageException::make();
                }

                if ($mediaId !== null) {
                    $media = $this->markableMedia($locked, $mediaId);
                    $attributes['media_id'] = (int) $media->getKey();

                    if ($anchor !== null) {
                        $attributes = [...$attributes, ...$this->anchorAttributes($anchor)];
                    }
                }
            }

            $comment = new SocialComment;
            $comment->fill($attributes)->save();

            $this->contents->recomputeCounters($locked);

            if ($comment->isMark()) {
                $comment->setRelation('media', $media ?? null);

                $this->recordActivity($comment, 'marked', array_filter([
                    'icerik_no' => $locked->content_no,
                    'gorsel' => $this->mediaLabel($comment->media),
                    'yorum' => Str::limit($body, self::EXCERPT_LENGTH),
                ], fn (mixed $value): bool => $value !== null && $value !== ''));
            }

            $comment->setRelation('content', $locked);

            return $comment;
        });

        if ($comment->isMark()) {
            $this->notifyMark($comment, $me);
        }

        return $comment;
    }

    /**
     * Yorumu / isareti cozuldu yapar ya da yeniden acar. Yanit verilirse kok
     * yorum uzerinde calisir. Durum zaten ayniysa hicbir sey yazilmaz.
     */
    public function resolve(SocialComment $comment, bool $resolved): SocialComment
    {
        $me = $this->actorId();

        return $this->transactions->run(function () use ($comment, $resolved, $me): SocialComment {
            $content = $comment->content()->first();

            if (! $content instanceof SocialContent) {
                throw RecordNotFoundException::make();
            }

            $locked = $this->lockContent($content);

            if ($locked->isArchived()) {
                throw ContentArchivedException::make();
            }

            /** @var SocialComment $target */
            $target = $this->lockForUpdate($comment->parent_comment_id ?? $comment->getKey());

            if ((int) $target->content_id !== (int) $locked->getKey()) {
                throw RecordNotFoundException::make();
            }

            if ($target->isResolved() !== $resolved) {
                $target->fill([
                    'resolved_at' => $resolved ? Carbon::now('UTC') : null,
                    'resolved_by_personnel_id' => $resolved ? $me : null,
                ])->save();

                if ($target->isMark()) {
                    $this->recordActivity($target, $resolved ? 'resolved' : 'reopened', array_filter([
                        'icerik_no' => $locked->content_no,
                        'gorsel' => $this->mediaLabel($target->media),
                        'yorum' => Str::limit((string) $target->body, self::EXCERPT_LENGTH),
                    ], fn (mixed $value): bool => $value !== null && $value !== ''));
                }
            }

            $target->setRelation('content', $locked);

            return $target;
        });
    }

    /** Yorum duzenlenmez. */
    public function update(Model|int|string $record, array $data): Model
    {
        throw InvalidTransitionException::make();
    }

    /** Yorum silinmez. */
    public function delete(Model|int|string $record): bool
    {
        throw InvalidTransitionException::make();
    }

    /** Ust icerik satirini kilitleyerek okur. */
    private function lockContent(SocialContent $content): SocialContent
    {
        /** @var SocialContent|null $locked */
        $locked = SocialContent::query()->lockForUpdate()->whereKey($content->getKey())->first();

        if ($locked === null) {
            throw RecordNotFoundException::make();
        }

        return $locked;
    }

    /** Yanitlanan yorum bu icerige ait olmalidir; yanitin yaniti kok yoruma baglanir. */
    private function rootCommentId(SocialContent $content, int $parentId): int
    {
        /** @var SocialComment|null $parent */
        $parent = SocialComment::query()
            ->where('content_id', (int) $content->getKey())
            ->whereKey($parentId)
            ->first();

        if ($parent === null) {
            throw RecordNotFoundException::make();
        }

        return (int) ($parent->parent_comment_id ?? $parent->getKey());
    }

    /**
     * Isaretlenebilir medya: bu icerigin galerisinde, gorsel ve cikarilmamis.
     * Surum satiri oldugu gibi kullanilir (koke cevrilmez).
     */
    private function markableMedia(SocialContent $content, int $mediaId): SocialContentMedia
    {
        /** @var SocialContentMedia|null $media */
        $media = SocialContentMedia::query()
            ->where('content_id', (int) $content->getKey())
            ->whereKey($mediaId)
            ->first();

        if ($media === null) {
            throw RecordNotFoundException::make();
        }

        if (! $media->isImage() || $media->usage !== SocialMediaUsage::Gallery || $media->isRemoved()) {
            throw MediaNotImageException::make();
        }

        return $media;
    }

    /**
     * Isaret koordinatlari 0..1 araligina sikistirilir (veritabani CHECK'i ile
     * ayni sinir); dikdortgen gorselin disina tasmaz. Nokta icin w/h bostur.
     *
     * @param  array<string, mixed>  $anchor
     * @return array<string, mixed>
     */
    private function anchorAttributes(array $anchor): array
    {
        $shape = (string) ($anchor['shape'] ?? '');

        if (! in_array($shape, SocialComment::SHAPES, true)) {
            throw MediaNotImageException::make();
        }

        $x = $this->unit($anchor['x'] ?? 0);
        $y = $this->unit($anchor['y'] ?? 0);

        if ($shape === SocialComment::SHAPE_POINT) {
            return ['anchor_shape' => $shape, 'anchor_x' => $x, 'anchor_y' => $y, 'anchor_w' => null, 'anchor_h' => null];
        }

        $w = min($this->unit($anchor['w'] ?? 0), round(1 - $x, 6));
        $h = min($this->unit($anchor['h'] ?? 0), round(1 - $y, 6));

        // Alani olmayan dikdortgen noktadir.
        if ($w <= 0.0 || $h <= 0.0) {
            return ['anchor_shape' => SocialComment::SHAPE_POINT, 'anchor_x' => $x, 'anchor_y' => $y, 'anchor_w' => null, 'anchor_h' => null];
        }

        return ['anchor_shape' => $shape, 'anchor_x' => $x, 'anchor_y' => $y, 'anchor_w' => $w, 'anchor_h' => $h];
    }

    private function unit(mixed $value): float
    {
        return round(min(1.0, max(0.0, is_numeric($value) ? (float) $value : 0.0)), 6);
    }

    /** Isaretin cizildigi surumun okunur adi (orn. "Kare 1080x1080"). */
    private function mediaLabel(?SocialContentMedia $media): ?string
    {
        if ($media === null) {
            return null;
        }

        return filled($media->variant_label) ? (string) $media->variant_label : $media->variant?->getLabel();
    }

    /** Isaret bildirimi: icerigi olusturana (isareti kendisi koymadiysa). */
    private function notifyMark(SocialComment $comment, int $me): void
    {
        $content = $comment->content;

        if (! $content instanceof SocialContent) {
            return;
        }

        $creator = $content->createdBy;

        if (! $creator instanceof Personnel || ! $creator->isReachable() || (int) $creator->getKey() === $me) {
            return;
        }

        $actor = Personnel::query()->whereKey($me)->value('full_name');
        $replacements = [
            'no' => (string) $content->content_no,
            'title' => (string) $content->title,
            'note' => Str::limit((string) $comment->body, self::EXCERPT_LENGTH),
            'actor' => filled($actor) ? (string) $actor : (string) __('activity.system'),
            'date' => $content->planned_on?->format('d.m.Y') ?? '-',
        ];

        $actions = [];

        try {
            $actions[] = Action::make('open')
                ->label(__('social_content.notifications.actions.open'))
                ->button()
                ->url(SocialContentResource::getUrl('index', ['icerik' => (int) $content->getKey()]))
                ->markAsRead();
        } catch (Throwable) {
            // Rota ya da kaynak yoksa dugmesiz bildirim.
        }

        $this->notifier->send(
            [$creator],
            (string) __('social_content.notifications.marked.title', $replacements),
            (string) __('social_content.notifications.marked.body', $replacements),
            Heroicon::OutlinedMapPin,
            'warning',
            $actions,
        );
    }

    private function actorId(): int
    {
        $id = $this->actor->personnelId();

        if ($id === null) {
            throw ActorRequiredException::make();
        }

        return $id;
    }
}
