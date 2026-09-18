<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialReactionType;
use App\Models\Activity\PersonnelActivity;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentPlatform;
use App\Models\SocialMedia\SocialContentRevision;
use App\Models\SocialMedia\SocialReaction;
use App\Policies\SocialContentPolicy;
use App\Query\SocialMedia\SocialContentQueries;
use App\Support\ActivityLabels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Icerigi React arayuzunun bekledigi JSON bicimine cevirir (B31, D-106;
 * SPEC 8 + F4-F7). Veritabanina yazmaz.
 *
 *  - card: akis karti.  - cardLite: takvim / ajanda ogesi (F6 ek alanlariyla).
 *  - detail: kart + govde, galeri, yorum agaci, begenenler, metin revizyonlari,
 *    gecmis, yetenekler, izinli durum gecisleri ve acil onay bilgisi.
 *  - person: kisi bicimi (modulun butun sunum siniflari bunu kullanir).
 *
 * Kimlikler yalniz istemcinin kayit secmesi icindir; ekranda gosterilmez. Her
 * enum deger + `_label` olarak gider; platform disi her `*_color` React
 * paletindeki renk adidir. Yetenekler izleyen kisi icin politikadan sorulur
 * (Gate::forUser). Dosya adresleri SocialMediaPresenter'dan kok-goreli gelir.
 *
 * Metotlar statiktir; hem `SocialContentPresenter::detail()` hem de enjekte
 * edilmis ornek uzerinden (`$presenter->detail()`) cagrilabilir.
 */
final class SocialContentPresenter
{
    /** Kart ozetinin en cok karakter sayisi. */
    public const EXCERPT_LENGTH = 180;

    /**
     * Kisi bicimi; kisi yoksa (kaydi sistem olusturduysa) null.
     *
     * @return array{id: int, name: string, initials: string, photo: ?string, job_title: ?string, department: ?string}|null
     */
    public static function person(?Personnel $p): ?array
    {
        if ($p === null) {
            return null;
        }

        return [
            'id' => (int) $p->getKey(),
            'name' => (string) $p->full_name,
            'initials' => self::initials((string) $p->full_name),
            'photo' => $p->getFilamentAvatarUrl(),
            'job_title' => filled($p->job_title) ? (string) $p->job_title : null,
            'department' => $p->orgUnit?->name,
        ];
    }

    /**
     * Akis karti.
     *
     * @return array<string, mixed>
     */
    public static function card(SocialContent $c, Personnel $viewer): array
    {
        $queries = app(SocialContentQueries::class);
        $media = app(SocialMediaPresenter::class);
        $category = $c->category;

        return [
            'id' => (int) $c->getKey(),
            'content_no' => (string) $c->content_no,
            'title' => (string) $c->title,
            'excerpt' => self::excerpt($c),
            'content_type' => $c->content_type->value,
            'content_type_label' => $c->content_type->getLabel(),
            'status' => $c->status->value,
            'status_label' => $c->status->getLabel(),
            'status_color' => $c->status->uiColor(),
            'profile_id' => (int) $c->profile_id,
            'category' => $category !== null ? [
                'id' => (int) $category->getKey(),
                'name' => (string) $category->name,
                'color' => (string) $category->color,
            ] : null,
            'platforms' => self::platforms($c),
            'planned_on' => $c->planned_on?->format('Y-m-d'),
            'planned_time' => filled($c->planned_time) ? (string) $c->planned_time : null,
            'planned_stage' => $queries->stageOf($c)?->value,
            'is_published' => $c->isPublished(),
            'published_at' => $c->published_at?->toIso8601String(),
            'creator' => self::person($c->createdBy),
            'created_at' => $c->created_at?->toIso8601String(),
            'media_count' => (int) $c->media_count,
            'cover' => $media->cover($c),
            'thumbs' => $media->thumbs($c, 4),
            'has_video' => $media->hasVideo($c),
            'video_url' => filled($c->video_url) ? (string) $c->video_url : null,
            'comment_count' => (int) $c->comment_count,
            'open_mark_count' => $queries->openMarkTotal($c),
            'like_count' => (int) $c->like_count,
            'dislike_count' => (int) $c->dislike_count,
            'my_reaction' => self::myReaction($c, (int) $viewer->getKey()),
            'urgent_requested_at' => $c->urgent_requested_at?->toIso8601String(),
        ];
    }

    /**
     * Takvim ve ajanda ogesi. Ajandanin ek alanlari (plan tarihi, platformlar,
     * hazirlayan, acil onay) burada hazir gelir.
     *
     * @return array<string, mixed>
     */
    public static function cardLite(SocialContent $c, Personnel $viewer): array
    {
        $queries = app(SocialContentQueries::class);

        return [
            'id' => (int) $c->getKey(),
            'content_no' => (string) $c->content_no,
            'title' => (string) $c->title,
            'profile_id' => (int) $c->profile_id,
            'content_type' => $c->content_type->value,
            'content_type_label' => $c->content_type->getLabel(),
            'status' => $c->status->value,
            'status_label' => $c->status->getLabel(),
            'status_color' => $c->status->uiColor(),
            'is_published' => $c->isPublished(),
            'planned_on' => $c->planned_on?->format('Y-m-d'),
            'planned_time' => filled($c->planned_time) ? (string) $c->planned_time : null,
            'planned_stage' => $queries->stageOf($c)?->value,
            'cover' => app(SocialMediaPresenter::class)->cover($c),
            'platforms' => self::platforms($c),
            'creator' => self::person($c->createdBy),
            'urgent_requested_at' => $c->urgent_requested_at?->toIso8601String(),
            'urgent_allowed' => self::urgent($c, $viewer)['allowed'],
        ];
    }

    /** Ajanda ogesi (cardLite ile aynidir; F6 ek alanlarini tasir). */
    public static function agendaItem(SocialContent $c, Personnel $viewer): array
    {
        return self::cardLite($c, $viewer);
    }

    /**
     * Ayrinti: kart + govde, galeri, yorumlar, begenenler, revizyonlar, gecmis,
     * yetenekler. Icerik SocialContentQueries::findForDetail() ile yuklenmis
     * olmalidir (eksik iliski tembel yuklenir).
     *
     * @return array<string, mixed>
     */
    public static function detail(SocialContent $c, Personnel $viewer): array
    {
        $queries = app(SocialContentQueries::class);
        $media = app(SocialMediaPresenter::class);
        $abilities = self::abilities($c, $viewer);

        $c->loadMissing(['comments', 'reactions.personnel.orgUnit', 'revisions.createdBy.orgUnit']);

        $likes = [];
        $dislikes = [];

        /** @var SocialReaction $reaction */
        foreach ($c->reactions as $reaction) {
            $person = self::person($reaction->personnel);

            if ($person === null) {
                continue;
            }

            if ($reaction->reaction === SocialReactionType::Like) {
                $likes[] = $person;
            } elseif ($reaction->reaction === SocialReactionType::Dislike) {
                $dislikes[] = $person;
            }
        }

        return [
            ...self::card($c, $viewer),
            'profile_name' => $c->profile?->name,
            'caption' => $c->caption,
            'body_text' => $c->body_text,
            'body_html' => $c->body_html,
            'image_format' => $c->image_format?->value,
            'image_format_label' => $c->image_format?->getLabel(),
            'row_version' => (int) $c->getAttribute('row_version'),
            'updated_at' => $c->updated_at?->toIso8601String(),
            'decision_note' => $c->decision_note,
            'decided_by' => self::person($c->decidedBy),
            'decided_at' => $c->decided_at?->toIso8601String(),
            'published_by' => self::person($c->publishedBy),
            'publish_note' => $c->publish_note,
            'status_before_archive' => $c->status_before_archive?->value,
            'status_before_archive_label' => $c->status_before_archive?->getLabel(),
            'media' => $media->gallery($c, $queries->openMarkCounts($c)),
            'removed_media' => $abilities['restore_media'] ? $media->removed($c) : [],
            'comments' => app(SocialCommentPresenter::class)->tree($c, $viewer),
            'likes' => $likes,
            'dislikes' => $dislikes,
            'revisions' => $c->revisions
                ->sortByDesc(fn (SocialContentRevision $revision): int => (int) $revision->revision_no)
                ->values()
                ->map(fn (SocialContentRevision $revision): array => self::revision($revision))
                ->all(),
            'history' => self::history($c),
            'abilities' => $abilities,
            'allowed_statuses' => self::allowedStatuses($c, $viewer),
            'urgent' => self::urgent($c, $viewer),
        ];
    }

    /**
     * Izleyenin bu icerikteki yetenekleri (politikadan).
     *
     * @return array<string, bool>
     */
    public static function abilities(SocialContent $c, Personnel $viewer): array
    {
        $gate = Gate::forUser($viewer);
        $update = $gate->allows('update', $c);

        return [
            'update' => $update,
            'approve' => $gate->allows('approve', $c),
            'resubmit' => $gate->allows('resubmit', $c),
            'publish' => $gate->allows('publish', $c),
            'unpublish' => $gate->allows('unpublish', $c),
            'archive' => $gate->allows('archive', $c),
            'unarchive' => $gate->allows('unarchive', $c),
            'comment' => $gate->allows('comment', $c),
            'react' => $gate->allows('react', $c),
            'request_urgent' => $gate->allows('requestUrgent', $c),
            'restore_media' => $update,
        ];
    }

    /**
     * Izinli durum gecisleri: durumun hedefleri ile izleyenin yeteneklerinin
     * kesisimi. Arsivde tek secenek "arsivden cikar"dir; paylasilmis icerik
     * yalniz arsive kalkar; "bekliyor"a elle yalniz ret / revizeden donulur.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function allowedStatuses(SocialContent $c, Personnel $viewer): array
    {
        $gate = Gate::forUser($viewer);

        if ($c->isArchived()) {
            return $gate->allows(SocialContentPolicy::transitionAbility('unarchive'), $c)
                ? [['value' => 'unarchive', 'label' => self::uiLabel('unarchive', ActivityLabels::action('social_content.unarchived'))]]
                : [];
        }

        $allowed = [];

        foreach ($c->status->allowedTargets() as $target) {
            if ($c->isPublished() && $target !== SocialContentStatus::Archived) {
                continue;
            }

            if ($target === SocialContentStatus::Pending && ! $c->status->canBeResubmitted()) {
                continue;
            }

            if (! $gate->allows(SocialContentPolicy::transitionAbility($target->value), $c)) {
                continue;
            }

            $allowed[] = ['value' => $target->value, 'label' => $target->getLabel()];
        }

        return $allowed;
    }

    /**
     * Acil onay bilgisi: istenebilir mi, istenemiyorsa neden, en son ne zaman istendi.
     *
     * @return array{allowed: bool, reason: ?string, requested_at: ?string}
     */
    public static function urgent(SocialContent $c, Personnel $viewer): array
    {
        $queries = app(SocialContentQueries::class);
        $permitted = Gate::forUser($viewer)->allows('requestUrgent', $c);
        $blocker = $queries->urgentBlocker($c);

        $reason = match ($blocker) {
            'window' => (string) __('exceptions.social_media.urgent_not_allowed', ['days' => SocialClock::urgentWindowDays()]),
            'cooldown' => (string) __('exceptions.social_media.urgent_cooldown', ['hours' => $queries->urgentCooldownHours()]),
            'archived' => (string) __('exceptions.social_media.content_archived'),
            'published' => (string) __('exceptions.social_media.content_published'),
            default => null,
        };

        return [
            'allowed' => $permitted && $blocker === null,
            // Karar beklemeyen icerikte dugme hic gosterilmez; neden yazilmaz.
            'reason' => $permitted ? $reason : null,
            'requested_at' => $c->urgent_requested_at?->toIso8601String(),
        ];
    }

    /**
     * Hedef platformlar ve paylasim baglantilari.
     *
     * @return list<array{platform: string, platform_label: string, color: string, published_url: ?string}>
     */
    public static function platforms(SocialContent $c): array
    {
        return $c->platforms
            ->sortBy(fn (SocialContentPlatform $row): int => (int) $row->getKey())
            ->values()
            ->map(fn (SocialContentPlatform $row): array => [
                'platform' => $row->platform->value,
                'platform_label' => $row->platform->getLabel(),
                'color' => $row->platform->brandColor(),
                'published_url' => filled($row->published_url) ? (string) $row->published_url : null,
            ])
            ->all();
    }

    /**
     * Metin revizyonu (saklanan ONCEKI hal; govde kaydedilirken temizlenmisti).
     *
     * @return array<string, mixed>
     */
    public static function revision(SocialContentRevision $revision): array
    {
        return [
            'revision_no' => (int) $revision->revision_no,
            'title' => (string) $revision->title,
            'caption' => $revision->caption,
            'body_text' => $revision->body_text,
            'body_html' => $revision->body_html,
            'created_at' => $revision->created_at?->toIso8601String(),
            'author' => self::person($revision->createdBy),
        ];
    }

    /**
     * Gecmis: Personel Hareketleri satirlari okunur metne cevrilir.
     *
     * @return list<array{at: ?string, actor: string, text: string, lines: list<string>}>
     */
    public static function history(SocialContent $c): array
    {
        return app(SocialContentQueries::class)->historyFor($c)
            ->map(fn (PersonnelActivity $activity): array => [
                'at' => $activity->occurred_at?->toIso8601String(),
                'actor' => $activity->actorName(),
                'text' => ActivityLabels::action($activity->action_code),
                'lines' => ActivityLabels::changeLines($activity->changes),
            ])
            ->values()
            ->all();
    }

    /** Kart ozeti: ture gore aciklama ya da govdenin duz metni. */
    private static function excerpt(SocialContent $c): string
    {
        $text = match (true) {
            $c->content_type->hasPlainBody() => (string) $c->body_text,
            $c->content_type->hasRichBody() => app(SocialHtmlSanitizer::class)->plainText($c->body_html),
            default => (string) $c->caption,
        };

        if (trim($text) === '') {
            $text = (string) $c->caption;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, self::EXCERPT_LENGTH, "\u{2026}");
    }

    /** Izleyenin tepkisi: like, dislike ya da none. */
    private static function myReaction(SocialContent $c, int $viewerId): string
    {
        $reaction = $c->relationLoaded('reactions')
            ? $c->reactions->first(fn (SocialReaction $row): bool => (int) $row->personnel_id === $viewerId)
            : $c->reactions()->where('personnel_id', $viewerId)->first();

        return $reaction instanceof SocialReaction && $reaction->reaction !== null
            ? $reaction->reaction->value
            : SocialReactionType::None->value;
    }

    /** React etiketi (social_content.ui.<anahtar>); ceviri yoksa verilen yedek. */
    private static function uiLabel(string $key, string $fallback): string
    {
        $full = 'social_content.ui.'.$key;
        $label = __($full);

        return is_string($label) && $label !== $full ? $label : $fallback;
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice(array_values(array_filter($words)), 0, 2),
        );

        return implode('', $letters) ?: '?';
    }
}
