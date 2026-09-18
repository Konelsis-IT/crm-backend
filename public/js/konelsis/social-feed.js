/*
 * Konelsis Sosyal Medya modulu - AKIS gorunumu ve ortak kart parcalari (B31, D-106).
 *
 * React 18 UMD, JSX yok, derleme yok. Yalniz social-core.js (KS) API'sini kullanir.
 * Stiller: resources/css/filament/konelsis-social.css, bolum FEED (sinif on eki ks-feed-).
 *
 * Kaydettikleri / registers:
 *   KS.views.Feed                          Akis gorunumu (hesap basligi, depolama kutusu, durum sekmeleri,
 *                                          suzgec cubugu, kart izgarasi, "Rakipler ve kurumlar" seridi).
 *   KS.parts.ContentCard({ card, onOpen?(card), variant?: 'grid'|'row', className? })
 *                                          card = SPEC 8 `card` ya da `cardLite` (eksik alanlar hosgorulur).
 *                                          onOpen verilmezse KS.actions.openDetail(card.id) cagrilir.
 *   KS.parts.StageChip({ stage, date, time, size?, className? })
 *   KS.parts.PlatformIcons({ platforms, size?, max?, className? })   platforms: [{ platform, platform_label }] | ['instagram']
 *   KS.parts.MediaMosaic({ card, className? })                        en cok 4 kucuk gorsel + "+N"
 *
 * Sunucu sozlesmesi (gercek kod): GET contents `status` yalniz gecerli durum degeridir
 * ("all" gonderilmez); "Paylasildi" sekmesi `published=yes` ile calisir. Akis asla
 * <video> olusturmaz: video karti kapak gorseli ya da notr yer tutucu kullanir (H6).
 * Icerik degisince (KS.events 'content') kart yerinde yamalanir; kaydirma konumu korunur.
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, t, fmt, cx } = KS;
    const { useState, useEffect, useRef, useCallback, useMemo } = KS;
    const memo = (window.React && typeof window.React.memo === 'function') ? window.React.memo : ((component) => component);

    KS.parts = KS.parts || {};

    const TYPE_ICONS = { photo: 'image', video: 'video', short_text: 'text', long_text: 'document', blog: 'blog' };
    const WATCH_KINDS = {
        competitor_company: { color: 'sky', icon: 'building' },
        competitor_executive: { color: 'violet', icon: 'user' },
        official_institution: { color: 'teal', icon: 'institution' },
    };
    const STATUS_TABS = ['pending', 'approved', 'revision_requested', 'rejected', 'archived'];
    const ADVANCED_FILTERS = ['type', 'platform', 'category', 'creator', 'from', 'to', 'stage'];
    const NARROW_SCOPES = ['awaiting_approval', 'to_publish'];
    const WATCH_LIMIT = 8;
    const LAYOUT_KEY = 'ks_feed_layout';
    // Ayrinti yanitindan karta tasinmayan agir alanlar (kart yamasi hafif kalsin).
    const DETAIL_ONLY_KEYS = ['caption', 'body_text', 'body_html', 'comments', 'likes', 'dislikes', 'revisions', 'history', 'media', 'removed_media', 'abilities', 'allowed_statuses', 'urgent'];

    /* ================================================================== */
    /* 1. Kucuk yardimcilar                                                */
    /* ================================================================== */

    function isBlank(value) {
        return value === undefined || value === null || value === '';
    }

    function monogram(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

        if (!parts.length) {
            return '?';
        }

        const first = parts[0].charAt(0);
        const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

        return (first + last).toLocaleUpperCase(KS.locale);
    }

    function findProfile(id) {
        const boot = KS.store.getState().boot;
        const list = (boot && boot.profiles) || [];

        for (let index = 0; index < list.length; index += 1) {
            if (Number(list[index].id) === Number(id)) {
                return list[index];
            }
        }

        return null;
    }

    /** Hesabin verilen platformdaki kullanici adi ("@konelsis") ya da null. */
    function linkHandle(profile, platform) {
        const links = (profile && profile.links) || [];

        for (let index = 0; index < links.length; index += 1) {
            if (links[index].platform === platform && links[index].handle) {
                const handle = String(links[index].handle).trim();

                return handle === '' ? null : (handle.charAt(0) === '@' ? handle : '@' + handle);
            }
        }

        return null;
    }

    /**
     * Medya ogesinin akis icin gorsel adresi. Video ogesinde YALNIZ kapak (poster) adresleri
     * kullanilir; video dosyasinin kendisi asla <img>/<video> kaynagi yapilmaz.
     */
    function imageSource(item, size) {
        if (!item) {
            return null;
        }

        if (item.kind === 'video') {
            return KS.safeUrl(size === 'thumb' ? (item.thumbnail_url || item.poster_url) : (item.poster_url || item.preview_url || item.thumbnail_url));
        }

        return KS.safeUrl(size === 'thumb' ? (item.thumbnail_url || item.preview_url) : (item.preview_url || item.thumbnail_url));
    }

    function isUrgent(card) {
        return !!card.urgent_requested_at && !card.is_published && (card.status === 'pending' || card.status === 'revision_requested');
    }

    function stageLabel(stage) {
        return KS.optionLabel('stages', stage) || t('stage_' + stage);
    }

    /* ================================================================== */
    /* 2. Ortak parcalar: gorsel, asama cipi, platform simgeleri, mozaik    */
    /* ================================================================== */

    /** Guvenli, tembel yuklenen gorsel; adres yoksa ya da yuklenemezse notr yer tutucu cizer. */
    function SafeImage(props) {
        const source = KS.safeUrl(props.src);
        const [broken, setBroken] = useState(false);

        useEffect(() => {
            setBroken(false);
        }, [source]);

        if (!source || broken) {
            return h('span', {
                className: cx('ks-feed-ph', props.fill && 'ks-feed-fill', props.placeholderClassName),
                role: props.alt ? 'img' : undefined,
                'aria-label': props.alt || undefined,
                'aria-hidden': props.alt ? undefined : 'true',
            }, h(KS.Icon, { name: props.icon || 'image' }));
        }

        return h('img', {
            className: cx(props.fill && 'ks-feed-fill', props.className),
            src: source,
            alt: props.alt || '',
            loading: 'lazy',
            decoding: 'async',
            width: props.width || undefined,
            height: props.height || undefined,
            draggable: false,
            onError: () => setBroken(true),
        });
    }

    /**
     * StageChip({ stage, date, time, size, className }) - planlanan tarih cipi.
     * Renk asamaya gore sabittir (B3): bugun kirmizi, yarin amber, yaklasiyor gok, gecikti gul; asama yoksa notr.
     */
    function StageChip(props) {
        if (!props.date) {
            return null;
        }

        const stage = props.stage || null;
        const time = props.time ? fmt.time(props.time) : '';
        let label = fmt.planned(props.date, props.time);

        if (stage === 'today' || stage === 'tomorrow') {
            label = stageLabel(stage) + (time ? ' · ' + time : '');
        } else if (stage === 'approaching' || stage === 'missed') {
            label = stageLabel(stage) + ' · ' + fmt.dateShort(props.date);
        }

        return h(KS.Badge, {
            color: stage ? KS.stageColor(stage) : 'stone',
            icon: stage === 'missed' ? 'alert' : 'calendar',
            size: props.size,
            className: cx('ks-feed-stage', props.className),
            title: t('feed_planned_for', { date: fmt.planned(props.date, props.time) }),
        }, label);
    }

    /** PlatformIcons({ platforms, size, max = 5, className }) - hedef platformlarin marka renkli simgeleri. */
    function PlatformIcons(props) {
        const list = (props.platforms || []).map((item) => {
            if (typeof item === 'string') {
                return { platform: item, label: KS.optionLabel('platforms', item) || '' };
            }

            if (item && item.platform) {
                return { platform: item.platform, label: item.platform_label || item.label || KS.optionLabel('platforms', item.platform) || '' };
            }

            return null;
        }).filter(Boolean);

        if (!list.length) {
            return null;
        }

        const max = props.max || 5;
        const shown = list.slice(0, max);
        const rest = list.length - shown.length;
        const names = list.map((item) => item.label).filter(Boolean).join(', ');

        return h('span', {
            className: cx('ks-feed-platforms', props.className),
            role: 'img',
            'aria-label': names || undefined,
            title: names || undefined,
        },
            shown.map((item) => h(KS.PlatformIcon, { key: item.platform, platform: item.platform, variant: 'badge', size: props.size || 'sm' })),
            rest > 0 ? h('span', { className: 'ks-feed-platforms__more', 'aria-hidden': 'true' }, '+' + fmt.number(rest)) : null,
        );
    }

    /**
     * MediaMosaic({ card, className }) - coklu icerigi "coklu" hissettiren mini izgara:
     * en cok 4 kucuk gorsel (thumbnail_url), fazlasi son hucrede "+N".
     */
    function MediaMosaic(props) {
        const card = props.card || {};
        const source = Array.isArray(card.thumbs) && card.thumbs.length ? card.thumbs : (card.cover ? [card.cover] : []);
        const thumbs = source.slice(0, 4);
        const total = Math.max(Number(card.media_count) || 0, thumbs.length);
        const extra = total - thumbs.length;

        if (!thumbs.length) {
            return h('span', { className: cx('ks-feed-mosaic', 'ks-feed-mosaic--1', props.className) },
                h('span', { className: 'ks-feed-mosaic__cell' }, h(SafeImage, { src: null, fill: true })));
        }

        return h('span', { className: cx('ks-feed-mosaic', 'ks-feed-mosaic--' + thumbs.length, props.className) },
            thumbs.map((item, index) => h('span', { key: item.id || index, className: 'ks-feed-mosaic__cell' },
                h(SafeImage, {
                    src: imageSource(item, thumbs.length === 1 ? 'full' : 'thumb'),
                    alt: item.caption || t('feed_media_alt', { title: card.title || '', n: index + 1 }),
                    icon: item.kind === 'video' ? 'video' : 'image',
                    width: item.width,
                    height: item.height,
                    fill: true,
                    className: 'ks-feed-mosaic__img',
                }),
                item.kind === 'video' ? h('span', { className: 'ks-feed-mosaic__play', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'play' })) : null,
                extra > 0 && index === thumbs.length - 1
                    ? h('span', { className: 'ks-feed-mosaic__more', title: t('feed_more_media', { n: fmt.number(extra) }) }, '+' + fmt.number(extra))
                    : null,
            )),
        );
    }

    /* ================================================================== */
    /* 3. Kart sahnesi (ture gore onizleme)                                */
    /* ================================================================== */

    function StagePlaceholder(props) {
        return h('span', { className: 'ks-feed-fill ks-feed-blank' },
            h('span', { className: 'ks-feed-blank__icon', 'aria-hidden': 'true' }, h(KS.Icon, { name: props.icon })),
            props.text ? h('span', { className: 'ks-feed-blank__text' }, props.text) : null,
        );
    }

    /** Video: kapak gorseli ya da notr yer tutucu + oynat rozeti + sure cipi. <video> OLUSTURULMAZ. */
    function VideoPreview(props) {
        const card = props.card;
        const cover = card.cover || null;
        const source = imageSource(cover, 'full');
        const seconds = cover && cover.duration_seconds ? Number(cover.duration_seconds) : 0;
        const hasFile = !!cover || !!card.has_video;
        const external = !hasFile && !!KS.safeUrl(card.video_url);

        if (!hasFile && !external) {
            return h(StagePlaceholder, { icon: 'video', text: t('feed_no_video') });
        }

        return h('span', { className: 'ks-feed-fill ks-feed-video' },
            source
                ? h(SafeImage, { src: source, alt: (cover && cover.caption) || card.title || '', icon: 'video', width: cover.width, height: cover.height, fill: true, className: 'ks-feed-card__img', placeholderClassName: 'ks-feed-video__blank' })
                : h('span', { className: 'ks-feed-fill ks-feed-ph ks-feed-video__blank', 'aria-hidden': 'true' }),
            h('span', { className: 'ks-feed-video__play', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'play' })),
            seconds > 0
                ? h('span', { className: 'ks-feed-scrimchip ks-feed-scrimchip--br', title: t('feed_video_duration', { duration: fmt.duration(seconds) }) }, h(KS.Icon, { name: 'clock' }), fmt.duration(seconds))
                : (external ? h('span', { className: 'ks-feed-scrimchip ks-feed-scrimchip--br' }, h(KS.Icon, { name: 'link' }), t('feed_video_link')) : null),
        );
    }

    /** Kisa metin (X benzeri) ve uzun metin (LinkedIn benzeri) tipografik onizleme. */
    function PostPreview(props) {
        const card = props.card;
        const isShort = card.content_type === 'short_text';
        const platform = isShort ? 'x' : 'linkedin';
        const profile = findProfile(card.profile_id);
        const name = profile ? profile.name : (card.profile_name || '');
        const meta = profile ? (isShort ? (linkHandle(profile, 'x') || profile.kind_label) : profile.kind_label) : null;
        const text = String(card.excerpt || '').trim();

        return h('span', { className: cx('ks-feed-fill', 'ks-feed-post', 'ks-feed-post--' + platform) },
            h('span', { className: 'ks-feed-post__head' },
                h('span', { className: 'ks-feed-post__mono', 'aria-hidden': 'true' }, (profile && profile.initials) || monogram(name)),
                h('span', { className: 'ks-feed-post__who' },
                    h('span', { className: 'ks-feed-post__name' }, name),
                    meta ? h('span', { className: 'ks-feed-post__meta' }, meta) : null,
                ),
                h(KS.PlatformIcon, { platform, variant: 'badge', size: 'sm' }),
            ),
            text
                ? h('span', { className: 'ks-feed-post__text' }, text)
                : h('span', { className: 'ks-feed-post__text is-empty' }, t('feed_no_text')),
            !isShort && text ? h('span', { className: 'ks-feed-post__more', 'aria-hidden': 'true' }, t('feed_see_more')) : null,
        );
    }

    /** Blog: makale benzeri onizleme; kapak gorseli varsa ustte, yoksa notr bant. */
    function ArticlePreview(props) {
        const card = props.card;
        const cover = card.cover || null;
        const source = imageSource(cover, 'full');
        const text = String(card.excerpt || '').trim();

        return h('span', { className: cx('ks-feed-fill', 'ks-feed-article', source ? 'has-cover' : 'no-cover') },
            source
                ? h('span', { className: 'ks-feed-article__cover' },
                    h(SafeImage, { src: source, alt: (cover && cover.caption) || card.title || '', icon: 'blog', width: cover.width, height: cover.height, fill: true, className: 'ks-feed-card__img' }))
                : h('span', { className: 'ks-feed-article__band', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'blog' })),
            h('span', { className: 'ks-feed-article__text' },
                h('span', { className: 'ks-feed-article__eyebrow' }, card.content_type_label || t('feed_blog_eyebrow')),
                text
                    ? h('span', { className: 'ks-feed-article__lead' }, text)
                    : h('span', { className: 'ks-feed-article__lead is-empty' }, t('feed_no_text')),
            ),
        );
    }

    function CardStage(props) {
        const card = props.card;
        const type = card.content_type;
        const thumbs = Array.isArray(card.thumbs) ? card.thumbs : [];
        const mediaCount = Math.max(Number(card.media_count) || 0, thumbs.length, card.cover ? 1 : 0);
        const isMulti = type === 'photo' && mediaCount > 1;
        const textual = type === 'short_text' || type === 'long_text' || (type === 'blog' && !imageSource(card.cover, 'full'));
        let inner = null;

        if (type === 'short_text' || type === 'long_text') {
            inner = h(PostPreview, { card });
        } else if (type === 'blog') {
            inner = h(ArticlePreview, { card });
        } else if (type === 'video') {
            inner = h(VideoPreview, { card });
        } else if (isMulti && thumbs.length > 1) {
            inner = h(MediaMosaic, { card });
        } else if (card.cover) {
            inner = h(SafeImage, {
                src: imageSource(card.cover, 'full'),
                alt: card.cover.caption || card.title || '',
                width: card.cover.width,
                height: card.cover.height,
                fill: true,
                className: 'ks-feed-card__img',
            });
        } else {
            inner = h(StagePlaceholder, { icon: 'image', text: t('feed_no_media') });
        }

        return h('span', { className: cx('ks-feed-card__stage', textual && 'ks-feed-card__stage--text') },
            inner,
            isMulti
                ? h('span', { className: 'ks-feed-scrimchip ks-feed-scrimchip--tl', title: t('feed_multi_count', { n: fmt.number(mediaCount) }) }, h(KS.Icon, { name: 'layers' }), fmt.number(mediaCount))
                : null,
            (type === 'photo' || type === 'video') && card.content_type_label
                ? h('span', { className: 'ks-feed-scrimchip ks-feed-scrimchip--bl' }, h(KS.Icon, { name: isMulti ? 'images' : (TYPE_ICONS[type] || 'image') }), card.content_type_label)
                : null,
            card.is_published
                ? h('span', { className: 'ks-feed-ribbon', 'aria-hidden': 'true' }, h('span', { className: 'ks-feed-ribbon__band' }, t('feed_published_ribbon')))
                : null,
        );
    }

    /* ================================================================== */
    /* 4. ContentCard                                                       */
    /* ================================================================== */

    function Counter(props) {
        return h('span', { className: cx('ks-feed-counter', props.className, props.active && 'is-active'), title: props.label },
            h(KS.Icon, { name: props.icon }),
            h('span', null, fmt.number(props.value)),
        );
    }

    function Counters(props) {
        const card = props.card;

        if (card.like_count === undefined && card.comment_count === undefined && card.open_mark_count === undefined) {
            return null;
        }

        const likes = Number(card.like_count) || 0;
        const dislikes = Number(card.dislike_count) || 0;
        const comments = Number(card.comment_count) || 0;
        const marks = Number(card.open_mark_count) || 0;

        return h('span', { className: 'ks-feed-counters' },
            h(Counter, { icon: card.my_reaction === 'like' ? 'heart-solid' : 'heart', value: likes, label: t('feed_likes', { n: fmt.number(likes) }), className: 'ks-feed-counter--like', active: card.my_reaction === 'like' }),
            dislikes > 0 ? h(Counter, { icon: card.my_reaction === 'dislike' ? 'thumb-down-solid' : 'thumb-down', value: dislikes, label: t('feed_dislikes', { n: fmt.number(dislikes) }), className: 'ks-feed-counter--dislike', active: card.my_reaction === 'dislike' }) : null,
            h(Counter, { icon: 'comment', value: comments, label: t('feed_comments', { n: fmt.number(comments) }) }),
            h(Counter, { icon: 'pin', value: marks, label: marks > 0 ? t('feed_open_marks', { n: fmt.number(marks) }) : t('feed_no_open_marks'), className: 'ks-feed-counter--marks', active: marks > 0 }),
        );
    }

    function CardBadges(props) {
        const card = props.card;

        return h('span', { className: 'ks-feed-card__badges' },
            card.status_label ? h(KS.StatusBadge, { status: card.status, label: card.status_label, color: card.status_color, size: 'sm' }) : null,
            h(StageChip, { stage: card.planned_stage, date: card.planned_on, time: card.planned_time, size: 'sm' }),
            props.urgent
                ? h(KS.Badge, { color: 'red', solid: true, icon: 'bolt', size: 'sm', className: 'ks-feed-urgent', title: t('feed_urgent_title', { date: fmt.dateTime(card.urgent_requested_at) }) }, t('feed_urgent'))
                : null,
            props.row && card.is_published
                ? h(KS.Badge, { color: 'emerald', icon: 'check', size: 'sm' }, t('feed_published_ribbon'))
                : null,
        );
    }

    /** Liste (satir) gorunumundeki kucuk gorsel: kapak, mozaik isareti ya da tur simgesi. */
    function RowThumb(props) {
        const card = props.card;
        const type = card.content_type;
        const cover = card.cover || (Array.isArray(card.thumbs) && card.thumbs[0]) || null;
        const source = imageSource(cover, 'thumb');
        const count = Number(card.media_count) || 0;

        return h('span', { className: cx('ks-feed-row__thumb', !source && 'ks-feed-row__thumb--icon') },
            source
                ? h(SafeImage, { src: source, alt: '', icon: TYPE_ICONS[type] || 'image', width: cover.width, height: cover.height, fill: true, className: 'ks-feed-card__img' })
                : h(KS.Icon, { name: TYPE_ICONS[type] || 'image' }),
            type === 'video' && source ? h('span', { className: 'ks-feed-row__play', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'play' })) : null,
            type === 'photo' && count > 1 ? h('span', { className: 'ks-feed-row__multi', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'layers' }), fmt.number(count)) : null,
        );
    }

    /**
     * ContentCard({ card, onOpen?(card), variant?: 'grid'|'row', className? })
     * Tum kart tek bir <button>'dir (klavye + ekran okuyucu); icinde baska etkilesimli oge yoktur.
     */
    function ContentCard(props) {
        const card = props.card;

        if (!card) {
            return null;
        }

        const row = props.variant === 'row';
        const urgent = isUrgent(card);
        const type = String(card.content_type || 'photo');
        const tooltip = [card.content_no, card.title].filter(Boolean).join(' · ');
        const label = t('feed_card_label', { type: card.content_type_label || '', title: card.title || '', status: card.status_label || '' });
        const open = () => {
            if (typeof props.onOpen === 'function') {
                props.onOpen(card);
            } else {
                KS.actions.openDetail(card.id);
            }
        };
        const category = card.category && card.category.name
            ? h(KS.Badge, { color: card.category.color, icon: 'tag', size: 'sm', className: 'ks-feed-card__category' }, card.category.name)
            : null;
        const platforms = h(PlatformIcons, { platforms: card.platforms });
        const creator = card.creator !== undefined
            ? h('span', { className: 'ks-feed-card__creator', title: t('feed_created_by', { name: fmt.personName(card.creator) }) },
                h(KS.Avatar, { person: card.creator, size: 'xs', title: false }),
                h('span', { className: 'ks-feed-card__creator-name' }, fmt.personName(card.creator)),
                card.created_at ? h('span', { className: 'ks-feed-card__when' }, fmt.relative(card.created_at)) : null,
            )
            : null;

        if (row) {
            return h('button', {
                type: 'button',
                className: cx('ks-feed-row', card.is_published && 'is-published', urgent && 'is-urgent', props.className),
                'aria-label': label,
                onClick: open,
            },
                h(RowThumb, { card }),
                h('span', { className: 'ks-feed-row__main' },
                    h('span', { className: 'ks-feed-row__eyebrow' },
                        h(KS.Icon, { name: TYPE_ICONS[type] || 'image' }),
                        h('span', null, [card.content_type_label, card.content_no].filter(Boolean).join(' · ')),
                    ),
                    h('span', { className: 'ks-feed-row__title', title: tooltip }, card.title),
                    h(CardBadges, { card, urgent, row: true }),
                ),
                h('span', { className: 'ks-feed-row__side' },
                    h('span', { className: 'ks-feed-row__tags' }, category, platforms),
                    h('span', { className: 'ks-feed-row__foot' }, creator, h(Counters, { card })),
                ),
            );
        }

        const showExcerpt = (type === 'photo' || type === 'video') && !isBlank(card.excerpt);

        return h('button', {
            type: 'button',
            className: cx('ks-feed-card', 'ks-feed-card--' + type.replace(/_/g, '-'), card.is_published && 'is-published', urgent && 'is-urgent', props.className),
            'aria-label': label,
            onClick: open,
        },
            h(CardStage, { card }),
            h('span', { className: 'ks-feed-card__body' },
                h(CardBadges, { card, urgent }),
                h('span', { className: 'ks-feed-card__title', title: tooltip }, card.title),
                showExcerpt ? h('span', { className: 'ks-feed-card__excerpt' }, card.excerpt) : null,
                category || (card.platforms && card.platforms.length)
                    ? h('span', { className: 'ks-feed-card__tags' }, category, platforms)
                    : null,
                h('span', { className: 'ks-feed-card__foot' }, creator, h(Counters, { card })),
            ),
        );
    }

    const FeedCard = memo(ContentCard);

    /* ================================================================== */
    /* 5. Akis verisi                                                       */
    /* ================================================================== */

    /** Depodaki suzgecler -> GET contents sorgusu. `status` yalniz gecerli durum degeriyle gonderilir. */
    function buildQuery(profileId, filters) {
        const f = filters || {};
        const query = { profile: profileId };

        if (f.status && f.status !== 'all') {
            query.status = f.status;
        }

        ['type', 'platform', 'category', 'creator', 'from', 'to', 'stage', 'published'].forEach((name) => {
            if (!isBlank(f[name])) {
                query[name] = f[name];
            }
        });

        // Ters girilmis tarih araligi sessizce duzeltilir.
        if (query.from && query.to && String(query.from) > String(query.to)) {
            const swap = query.from;

            query.from = query.to;
            query.to = swap;
        }

        if (f.scope && f.scope !== 'all') {
            query.scope = f.scope;
        }

        const term = String(f.q || '').trim().slice(0, 100);

        if (term) {
            query.q = term;
        }

        return query;
    }

    function readPage(payload) {
        const meta = (payload && payload.meta) || {};

        return {
            data: payload && Array.isArray(payload.data) ? payload.data : [],
            total: Number(meta.total) || 0,
            hasMore: !!meta.has_more,
        };
    }

    function mergeCards(base, extra) {
        const seen = {};
        const out = [];

        base.concat(extra).forEach((card) => {
            if (card && card.id !== undefined && !seen[card.id]) {
                seen[card.id] = true;
                out.push(card);
            }
        });

        return out;
    }

    /** Yamalanan icerik gecerli sekme / kapsamda listede kalmali mi? */
    function stillListed(detail, filters, profileId) {
        const f = filters || {};

        if (Number(detail.profile_id) !== Number(profileId)) {
            return false;
        }

        const status = f.status && f.status !== 'all' ? f.status : null;

        if (status ? detail.status !== status : detail.status === 'archived') {
            return false;
        }

        if ((f.published === 'yes' && !detail.is_published) || (f.published === 'no' && detail.is_published)) {
            return false;
        }

        if (f.scope === 'awaiting_approval' && detail.status !== 'pending') {
            return false;
        }

        if (f.scope === 'to_publish' && (detail.status !== 'approved' || detail.is_published)) {
            return false;
        }

        return true;
    }

    const EMPTY_FEED = { items: [], page: 0, total: 0, hasMore: false, loading: true, refreshing: false, loadingMore: false, error: null };

    /**
     * Akis listesi. Sorgu (hesap + suzgecler) degisince bastan yuklenir; yalniz feedVersion artinca
     * "yumusak" tazelenir: yuklenmis sayfalar arka planda yeniden cekilir, liste yerinde degisir ve
     * kaydirma konumu korunur. 'content' olayi tek karti yerinde yamalar.
     */
    function useFeed(profileId, filters, feedVersion) {
        const query = useMemo(() => (profileId ? buildQuery(profileId, filters) : null), [profileId, filters]);
        const queryKey = query ? JSON.stringify(query) : '';
        const [state, setState] = useState(EMPTY_FEED);
        const stateRef = useRef(state);
        const queryRef = useRef(query);
        const filtersRef = useRef(filters);
        const profileRef = useRef(profileId);
        const jobRef = useRef({ seq: 0, controller: null });
        const lastKeyRef = useRef(null);

        stateRef.current = state;
        queryRef.current = query;
        filtersRef.current = filters;
        profileRef.current = profileId;

        const controls = useMemo(() => {
            const begin = () => {
                if (jobRef.current.controller) {
                    jobRef.current.controller.abort();
                }

                const controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;

                jobRef.current = { seq: jobRef.current.seq + 1, controller };

                return { seq: jobRef.current.seq, signal: controller ? controller.signal : undefined };
            };

            const stale = (ticket) => jobRef.current.seq !== ticket.seq;
            const fetchPage = (page, signal) => KS.api.get(KS.url('contents'), Object.assign({}, queryRef.current, { page }), { signal });

            const hard = () => {
                if (!queryRef.current) {
                    return;
                }

                const ticket = begin();

                setState(EMPTY_FEED);

                fetchPage(1, ticket.signal).then((payload) => {
                    if (stale(ticket)) {
                        return;
                    }

                    const result = readPage(payload);

                    setState({ items: mergeCards([], result.data), page: 1, total: result.total, hasMore: result.hasMore, loading: false, refreshing: false, loadingMore: false, error: null });
                }).catch((error) => {
                    if (stale(ticket) || (error && error.aborted)) {
                        return;
                    }

                    setState(Object.assign({}, EMPTY_FEED, { loading: false, error }));

                    // Oturum dusmusse "sayfayi yenile" eylemli bildirim de gosterilir.
                    if (error && (error.status === 401 || error.status === 419)) {
                        KS.handleError(error);
                    }
                });
            };

            const soft = () => {
                const current = stateRef.current;

                if (!queryRef.current) {
                    return;
                }

                if (current.loading || current.error || current.page < 1) {
                    hard();

                    return;
                }

                const pages = current.page;
                const ticket = begin();
                let collected = [];
                let last = { total: current.total, hasMore: current.hasMore };

                setState((cur) => Object.assign({}, cur, { refreshing: true, loadingMore: false }));

                const step = (page) => fetchPage(page, ticket.signal).then((payload) => {
                    last = readPage(payload);
                    collected = mergeCards(collected, last.data);

                    return page < pages && last.hasMore ? step(page + 1) : page;
                });

                step(1).then((reached) => {
                    if (stale(ticket)) {
                        return;
                    }

                    setState({ items: collected, page: reached, total: last.total, hasMore: last.hasMore, loading: false, refreshing: false, loadingMore: false, error: null });
                }).catch((error) => {
                    if (stale(ticket) || (error && error.aborted)) {
                        return;
                    }

                    setState((cur) => Object.assign({}, cur, { refreshing: false }));
                    KS.handleError(error);
                });
            };

            const more = () => {
                const current = stateRef.current;

                if (!queryRef.current || current.loading || current.loadingMore || current.refreshing || !current.hasMore) {
                    return;
                }

                const ticket = begin();
                const nextPage = current.page + 1;

                setState((cur) => Object.assign({}, cur, { loadingMore: true }));

                fetchPage(nextPage, ticket.signal).then((payload) => {
                    if (stale(ticket)) {
                        return;
                    }

                    const result = readPage(payload);

                    setState((cur) => Object.assign({}, cur, { items: mergeCards(cur.items, result.data), page: nextPage, total: result.total, hasMore: result.hasMore, loadingMore: false }));
                }).catch((error) => {
                    if (stale(ticket) || (error && error.aborted)) {
                        return;
                    }

                    setState((cur) => Object.assign({}, cur, { loadingMore: false }));
                    KS.handleError(error);
                });
            };

            const abort = () => {
                if (jobRef.current.controller) {
                    jobRef.current.controller.abort();
                }

                jobRef.current = { seq: jobRef.current.seq + 1, controller: null };
            };

            return { hard, soft, more, abort };
        }, []);

        useEffect(() => {
            if (!queryKey) {
                return;
            }

            const same = lastKeyRef.current === queryKey;

            lastKeyRef.current = queryKey;

            if (same) {
                controls.soft();
            } else {
                controls.hard();
            }
        }, [queryKey, feedVersion, controls]);

        useEffect(() => () => controls.abort(), [controls]);

        useEffect(() => {
            const softLater = KS.debounce(() => controls.soft(), 400);

            const off = KS.events.on('content', (detail) => {
                if (!detail || !detail.id) {
                    return;
                }

                const id = Number(detail.id);
                const exists = stateRef.current.items.some((item) => Number(item.id) === id);

                if (!exists) {
                    // Listede olmayan icerik (yeni eklenen ya da baska sayfadaki): sunucu sirasiyla tazelenir.
                    if (Number(detail.profile_id) === Number(profileRef.current)) {
                        softLater();
                    }

                    return;
                }

                const keep = stillListed(detail, filtersRef.current, profileRef.current);
                const patch = KS.omit(detail, DETAIL_ONLY_KEYS);

                setState((cur) => {
                    const had = cur.items.some((item) => Number(item.id) === id);

                    if (!had) {
                        return cur;
                    }

                    return Object.assign({}, cur, {
                        items: keep
                            ? cur.items.map((item) => (Number(item.id) === id ? Object.assign({}, item, patch) : item))
                            : cur.items.filter((item) => Number(item.id) !== id),
                        total: keep ? cur.total : Math.max(0, cur.total - 1),
                    });
                });
            });

            return () => {
                softLater.cancel();
                off();
            };
        }, [controls]);

        return Object.assign({}, state, { more: controls.more, reload: controls.hard });
    }

    function useWatch(profileId) {
        return KS.useResource(() => {
            if (!profileId || !KS.hasEndpoint('watch')) {
                return Promise.resolve({ groups: [] });
            }

            return KS.api.get(KS.url('watch'), { profile: profileId });
        }, [profileId]);
    }

    function readLayout() {
        try {
            return window.localStorage.getItem(LAYOUT_KEY) === 'row' ? 'row' : 'grid';
        } catch (error) {
            return 'grid';
        }
    }

    function useLayout() {
        const [layout, setLayout] = useState(readLayout);

        const change = useCallback((next) => {
            const value = next === 'row' ? 'row' : 'grid';

            setLayout(value);

            try {
                window.localStorage.setItem(LAYOUT_KEY, value);
            } catch (error) {
                // Depolama kapaliysa tercih yalniz bu oturumda gecerlidir.
            }
        }, []);

        return [layout, change];
    }

    /* ================================================================== */
    /* 6. Hesap basligi                                                     */
    /* ================================================================== */

    function ProfileAvatar(props) {
        const profile = props.profile;
        const executive = profile.kind === 'executive';
        const source = executive
            ? KS.safeUrl(profile.owner && profile.owner.photo)
            : KS.safeUrl(KS.config.logo);
        const [broken, setBroken] = useState(false);

        useEffect(() => {
            setBroken(false);
        }, [source]);

        return h('span', { className: cx('ks-feed-profile__avatar', executive ? 'is-round' : 'is-brand', source && !broken && 'has-image'), 'aria-hidden': 'true' },
            source && !broken
                ? h('img', { src: source, alt: '', decoding: 'async', draggable: false, onError: () => setBroken(true) })
                : h('span', null, profile.initials || monogram(profile.name)),
        );
    }

    function ProfileHeader(props) {
        const profile = props.profile;
        const counts = props.counts || {};
        const [expanded, setExpanded] = useState(false);
        const bio = String(profile.bio || '').trim();
        const longBio = bio.length > 220;
        const links = (profile.links || []).filter((link) => KS.safeUrl(link.url));
        const executive = profile.kind === 'executive';
        const owner = executive ? profile.owner : null;
        const ownerLine = owner ? [owner.name, owner.job_title].filter(Boolean).join(' · ') : '';
        const stats = [
            { key: 'all', value: counts.all, label: t('feed_stat_contents') },
            { key: 'pending', value: counts.pending, label: t('feed_stat_pending') },
            { key: 'published', value: counts.published, label: t('feed_stat_published') },
        ];

        useEffect(() => {
            setExpanded(false);
        }, [profile.id]);

        return h('section', { className: 'ks-feed-profile', 'aria-label': profile.name },
            h('div', { className: 'ks-feed-profile__cover', 'aria-hidden': 'true' }),
            h('div', { className: 'ks-feed-profile__body' },
                h(ProfileAvatar, { profile }),
                h('div', { className: 'ks-feed-profile__info' },
                    h('div', { className: 'ks-feed-profile__heading' },
                        h('h2', { className: 'ks-feed-profile__name' }, profile.name),
                        profile.kind_label ? h(KS.Badge, { color: executive ? 'violet' : 'sky', icon: executive ? 'user' : 'building' }, profile.kind_label) : null,
                    ),
                    ownerLine ? h('p', { className: 'ks-feed-profile__owner' }, ownerLine) : null,
                    bio
                        ? h('p', { className: cx('ks-feed-profile__bio', longBio && !expanded && 'is-clamped') }, bio)
                        : h('p', { className: 'ks-feed-profile__bio is-empty' }, t('feed_profile_no_bio')),
                    longBio
                        ? h(KS.Button, { variant: 'link', size: 'sm', className: 'ks-feed-profile__toggle', 'aria-expanded': expanded ? 'true' : 'false', onClick: () => setExpanded(!expanded) }, expanded ? t('show_less') : t('show_more'))
                        : null,
                ),
                h('div', { className: 'ks-feed-profile__stats', role: 'group', 'aria-label': t('feed_stats_label') },
                    stats.map((stat) => h('button', {
                        key: stat.key,
                        type: 'button',
                        className: cx('ks-feed-stat', props.tab === stat.key && 'is-active'),
                        'aria-pressed': props.tab === stat.key ? 'true' : 'false',
                        onClick: () => props.onTab(stat.key),
                    },
                        h('span', { className: 'ks-feed-stat__value' }, stat.value === undefined || stat.value === null ? '–' : fmt.number(stat.value)),
                        h('span', { className: 'ks-feed-stat__label' }, stat.label),
                    )),
                ),
            ),
            h('div', { className: 'ks-feed-profile__links', role: 'group', 'aria-label': t('feed_profile_links_label') },
                links.length
                    ? links.map((link) => h(KS.PlatformLink, { key: link.platform, platform: link.platform, label: link.platform_label, url: link.url, handle: link.handle }))
                    : h('p', { className: 'ks-feed-profile__nolinks' }, h(KS.Icon, { name: 'link-off' }), h('span', null, t('feed_profile_no_links'))),
                props.canManage && (!links.length || !bio)
                    ? h(KS.Button, { variant: 'link', size: 'sm', icon: 'edit', onClick: () => KS.actions.setView('ayarlar') }, t('feed_profile_manage'))
                    : null,
            ),
        );
    }

    /* ================================================================== */
    /* 7. Depolama kutusu                                                   */
    /* ================================================================== */

    function StorageBox(props) {
        const storage = props.storage;
        const [busy, setBusy] = useState(false);
        const alive = useRef(true);

        useEffect(() => {
            alive.current = true;

            return () => {
                alive.current = false;
            };
        }, []);

        const refresh = () => {
            setBusy(true);

            KS.actions.refreshStorage().then((result) => {
                if (!alive.current) {
                    return;
                }

                setBusy(false);

                if (!result) {
                    KS.toast.error(t('feed_storage_unavailable'));
                }
            });
        };

        const head = h('header', { className: 'ks-feed-storage__head' },
            h('span', { className: 'ks-feed-storage__icon', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'hdd' })),
            h('h3', { className: 'ks-feed-storage__title' }, t('feed_storage_title')),
            KS.hasEndpoint('storage')
                ? h(KS.IconButton, { icon: 'refresh', label: t('feed_storage_refresh'), variant: 'plain', size: 'sm', loading: busy, onClick: refresh })
                : null,
        );

        if (!storage) {
            return h('section', { className: 'ks-feed-storage', 'aria-label': t('feed_storage_title') },
                head,
                h('p', { className: 'ks-feed-storage__hint' }, t('feed_storage_unavailable')),
            );
        }

        const videoBytes = Number(storage.video_bytes) || 0;
        const imageBytes = Number(storage.image_bytes) || 0;
        const otherBytes = Number(storage.other_bytes) || 0;
        const totalBytes = Math.max(Number(storage.total_bytes) || 0, videoBytes + imageBytes + otherBytes);
        const share = (bytes) => (totalBytes > 0 ? Math.max(bytes > 0 ? 2 : 0, (bytes / totalBytes) * 100) : 0);
        const hasFree = storage.free_bytes !== null && storage.free_bytes !== undefined;
        const freeBytes = Number(storage.free_bytes) || 0;
        const diskBytes = Number(storage.disk_total_bytes) || 0;
        const usedRatio = hasFree && diskBytes > 0 ? KS.clamp((diskBytes - freeBytes) / diskBytes, 0, 1) : null;
        const low = !!storage.low;
        const freeHuman = storage.free_human || fmt.bytes(freeBytes);
        const diskHuman = storage.disk_total_human || (diskBytes > 0 ? fmt.bytes(diskBytes) : '');

        return h('section', { className: cx('ks-feed-storage', low && 'is-low'), 'aria-label': t('feed_storage_title') },
            head,
            h('div', { className: 'ks-feed-storage__cols' },
                h('div', { className: 'ks-feed-storage__usage' },
                    h('div', { className: 'ks-feed-storage__tiles' },
                        h('div', { className: 'ks-feed-storage__tile ks-feed-storage__tile--video' },
                            h('span', { className: 'ks-feed-storage__dot', 'aria-hidden': 'true' }),
                            h('span', { className: 'ks-feed-storage__label' }, t('feed_storage_video')),
                            h('span', { className: 'ks-feed-storage__value' }, storage.video_human || fmt.bytes(videoBytes)),
                        ),
                        h('div', { className: 'ks-feed-storage__tile ks-feed-storage__tile--photo' },
                            h('span', { className: 'ks-feed-storage__dot', 'aria-hidden': 'true' }),
                            h('span', { className: 'ks-feed-storage__label' }, t('feed_storage_photo')),
                            h('span', { className: 'ks-feed-storage__value' }, storage.image_human || fmt.bytes(imageBytes)),
                        ),
                    ),
                    h('div', { className: 'ks-feed-storage__mix', 'aria-hidden': 'true' },
                        h('span', { className: 'ks-feed-storage__seg ks-feed-storage__seg--video', style: { width: share(videoBytes) + '%' } }),
                        h('span', { className: 'ks-feed-storage__seg ks-feed-storage__seg--photo', style: { width: share(imageBytes) + '%' } }),
                        h('span', { className: 'ks-feed-storage__seg ks-feed-storage__seg--other', style: { width: share(otherBytes) + '%' } }),
                    ),
                    h('p', { className: 'ks-feed-storage__line' },
                        h('span', null, t('feed_storage_total')),
                        h('strong', null, storage.total_human || fmt.bytes(totalBytes)),
                    ),
                    otherBytes > 0
                        ? h('p', { className: 'ks-feed-storage__line ks-feed-storage__line--minor' },
                            h('span', null, t('feed_storage_other')),
                            h('span', null, storage.other_human || fmt.bytes(otherBytes)))
                        : null,
                ),
                h('div', { className: 'ks-feed-storage__server' },
                    h('div', { className: 'ks-feed-storage__disk' },
                        hasFree
                            ? h(KS.Fragment, null,
                                h('p', { className: 'ks-feed-storage__free' },
                                    h('span', { className: 'ks-feed-storage__free-label' }, t('feed_storage_free')),
                                    h('strong', { className: 'ks-feed-storage__free-value' }, freeHuman),
                                ),
                                usedRatio !== null
                                    ? h(KS.ProgressBar, { value: Math.round(usedRatio * 100), max: 100, size: 'sm', tone: low ? 'warning' : 'neutral', label: diskHuman ? t('feed_storage_disk', { total: diskHuman }) : t('feed_storage_disk_plain'), showValue: true })
                                    : null,
                            )
                            : h('p', { className: 'ks-feed-storage__hint' }, t('feed_storage_free_unknown')),
                    ),
                    low ? h(KS.Notice, { tone: 'warning', compact: true, title: t('storage_low') }) : null,
                    h('p', { className: 'ks-feed-storage__hint' }, t('storage_never_reclaimed')),
                ),
            ),
        );
    }

    /* ================================================================== */
    /* 8. Rakipler ve kurumlar (sag serit / yatay serit)                    */
    /* ================================================================== */

    function AccountCard(props) {
        const account = props.account;
        const style = WATCH_KINDS[props.kind] || { color: 'stone', icon: 'users' };
        const links = (account.links || []).filter((link) => KS.safeUrl(link.url));

        return h('li', { className: 'ks-feed-account' },
            h('div', { className: 'ks-feed-account__head' },
                h('span', { className: cx('ks-feed-account__mono', 'ks-c-' + style.color), 'aria-hidden': 'true' }, account.initials || monogram(account.name)),
                h('div', { className: 'ks-feed-account__text' },
                    props.showKind && props.kindLabel ? h('span', { className: 'ks-feed-account__kind' }, props.kindLabel) : null,
                    h('span', { className: 'ks-feed-account__name', title: account.name }, account.name),
                    account.subtitle ? h('span', { className: 'ks-feed-account__sub', title: account.subtitle }, account.subtitle) : null,
                ),
            ),
            links.length
                ? h('div', { className: 'ks-feed-account__links' },
                    links.map((link) => h(KS.PlatformLink, { key: link.platform, platform: link.platform, label: link.platform_label, url: link.url, compact: true })))
                : h('p', { className: 'ks-feed-account__nolinks' }, t('feed_watch_no_links')),
        );
    }

    function WatchPanel(props) {
        const resource = props.resource;
        const strip = props.mode === 'strip';
        const allGroups = (resource.data && Array.isArray(resource.data.groups)) ? resource.data.groups : [];
        const total = allGroups.reduce((sum, group) => sum + ((group.accounts || []).length), 0);
        const groups = [];
        let left = WATCH_LIMIT;

        allGroups.forEach((group) => {
            const accounts = (group.accounts || []).slice(0, Math.max(0, left));

            left -= accounts.length;

            if (accounts.length) {
                groups.push({ kind: group.kind, kind_label: group.kind_label, accounts });
            }
        });

        const hidden = total - groups.reduce((sum, group) => sum + group.accounts.length, 0);
        const seeAll = () => KS.actions.setView('ilham');
        let body = null;

        if (resource.loading) {
            body = h('div', { className: cx('ks-feed-watch__loading', strip && 'ks-feed-watch__loading--strip'), 'aria-busy': 'true' },
                [0, 1, 2].map((index) => h('div', { key: index, className: 'ks-feed-account ks-feed-account--skeleton', 'aria-hidden': 'true' },
                    h('div', { className: 'ks-feed-account__head' },
                        h(KS.Skeleton, { variant: 'rect', width: 36, height: 36, radius: 12 }),
                        h('div', { className: 'ks-feed-account__text' }, h(KS.Skeleton, { variant: 'text', width: '70%' }), h(KS.Skeleton, { variant: 'text', width: '45%' })),
                    ),
                    h(KS.Skeleton, { variant: 'text', width: '60%' }),
                )),
                h('span', { className: 'ks-sr-only' }, t('loading')),
            );
        } else if (resource.error) {
            body = h(KS.ErrorState, { error: resource.error, onRetry: resource.reload, compact: true });
        } else if (!groups.length) {
            body = h(KS.Empty, {
                compact: true,
                icon: 'users',
                title: t('feed_watch_empty_title'),
                text: t('feed_watch_empty_text'),
                action: props.canManage ? h(KS.Button, { variant: 'soft', size: 'sm', icon: 'plus', onClick: seeAll }, t('feed_watch_add')) : null,
            });
        } else if (strip) {
            body = h('ul', { className: 'ks-feed-watch__scroller' },
                groups.map((group) => group.accounts.map((account) => h(AccountCard, { key: account.id, account, kind: group.kind, kindLabel: group.kind_label, showKind: true }))),
                hidden > 0
                    ? h('li', { className: 'ks-feed-account ks-feed-account--more' },
                        h(KS.Button, { variant: 'soft', iconRight: 'arrow-right', onClick: seeAll }, t('feed_watch_more', { n: fmt.number(hidden) })))
                    : null,
            );
        } else {
            body = h('div', { className: 'ks-feed-watch__groups' },
                groups.map((group) => {
                    const style = WATCH_KINDS[group.kind] || { icon: 'users' };

                    return h('div', { key: group.kind, className: 'ks-feed-watch__group' },
                        h('h4', { className: 'ks-feed-watch__group-title' }, h(KS.Icon, { name: style.icon }), h('span', null, group.kind_label)),
                        h('ul', { className: 'ks-feed-watch__list' },
                            group.accounts.map((account) => h(AccountCard, { key: account.id, account, kind: group.kind, kindLabel: group.kind_label }))),
                    );
                }),
                hidden > 0
                    ? h(KS.Button, { variant: 'soft', size: 'sm', block: true, iconRight: 'arrow-right', onClick: seeAll }, t('feed_watch_more', { n: fmt.number(hidden) }))
                    : null,
            );
        }

        return h('section', { className: cx('ks-feed-watch', strip ? 'ks-feed-watch--strip' : 'ks-feed-watch--rail'), 'aria-label': t('feed_watch_title') },
            h('header', { className: 'ks-feed-watch__head' },
                h('h3', { className: 'ks-feed-watch__title' }, t('feed_watch_title')),
                h(KS.Button, { variant: 'link', size: 'sm', iconRight: 'chevron-right', onClick: seeAll }, t('feed_watch_all')),
            ),
            body,
        );
    }

    /* ================================================================== */
    /* 9. Suzgec cubugu                                                     */
    /* ================================================================== */

    function FilterBar(props) {
        const filters = props.filters;
        const counts = props.counts || {};
        const abilities = props.abilities || {};
        const boot = KS.useBoot();
        const panelId = useMemo(() => KS.uid('ks-feed-filters'), []);
        const activeAdvanced = ADVANCED_FILTERS.filter((name) => !isBlank(filters[name]));
        const [open, setOpen] = useState(activeAdvanced.length > 0);
        const [term, setTerm] = useState(filters.q || '');
        const debounced = KS.useDebounced(term, 350);
        const pushed = useRef(filters.q || '');

        // Yazilan arama metni gecikmeli olarak depoya; depodaki disaridan degisirse kutuya.
        useEffect(() => {
            const next = String(debounced || '').trim();

            if (next !== pushed.current) {
                pushed.current = next;
                KS.actions.setFilters({ q: next });
            }
        }, [debounced]);

        useEffect(() => {
            const value = filters.q || '';

            if (value !== pushed.current) {
                pushed.current = value;
                setTerm(value);
            }
        }, [filters.q]);

        const categories = (boot && boot.categories) || [];
        const creators = (boot && boot.creators) || [];
        const typeOptions = KS.options('types').map((item) => ({ value: item.value, label: item.label }));
        const platformOptions = KS.options('platforms').map((item) => ({ value: item.value, label: item.label }));
        const stageOptions = (KS.options('stages').length ? KS.options('stages') : ['today', 'tomorrow', 'approaching', 'missed'].map((value) => ({ value, label: t('stage_' + value) })))
            .map((item) => ({ value: item.value, label: item.label }));
        const categoryOptions = categories.map((item) => ({ value: item.id, label: item.name }));
        const creatorOptions = creators.filter(Boolean).map((item) => ({ value: item.id, label: item.name }));

        const withCount = (label, count) => (Number(count) > 0 ? label + ' (' + fmt.number(count) + ')' : label);
        const scopeItems = [
            { value: 'all', label: t('feed_scope_all') },
            { value: 'mine', label: t('feed_scope_mine') },
            abilities.approve_any ? { value: 'awaiting_approval', label: withCount(t('feed_scope_awaiting'), counts.awaiting_my_approval) } : null,
            abilities.publish_any ? { value: 'to_publish', label: withCount(t('feed_scope_to_publish'), counts.approved_unpublished) } : null,
        ].filter(Boolean);
        const scope = scopeItems.some((item) => item.value === filters.scope) ? filters.scope : 'all';

        const setScope = (value) => {
            const patch = { scope: value };

            // Dar kapsamlar durumu kendileri belirler; celisen sekme "Tumu"ne doner.
            if (NARROW_SCOPES.indexOf(value) !== -1) {
                patch.status = 'all';
                patch.published = '';
            }

            KS.actions.setFilters(patch);
        };

        const set = (name) => (value) => {
            const patch = {};

            patch[name] = isBlank(value) ? '' : value;
            KS.actions.setFilters(patch);
        };

        const clearAll = () => {
            pushed.current = '';
            setTerm('');
            KS.actions.resetFilters({ status: filters.status, published: filters.published });
        };

        const findLabel = (list, value) => {
            const found = list.find((item) => String(item.value) === String(value));

            return found ? found.label : '';
        };

        const chips = [];
        const pushChip = (name, title, value, extra) => {
            if (!isBlank(filters[name]) && value) {
                chips.push(Object.assign({ name, label: title + ': ' + value }, extra || {}));
            }
        };

        if (!isBlank(filters.q)) {
            chips.push({ name: 'q', label: t('feed_filter_search_chip') + ': ' + filters.q, icon: 'search' });
        }

        pushChip('type', t('feed_filter_type'), findLabel(typeOptions, filters.type), { icon: TYPE_ICONS[filters.type] || 'grid' });
        pushChip('platform', t('feed_filter_platform'), findLabel(platformOptions, filters.platform), { platform: filters.platform });
        pushChip('category', t('feed_filter_category'), findLabel(categoryOptions, filters.category), { icon: 'tag' });
        pushChip('creator', t('feed_filter_creator'), findLabel(creatorOptions, filters.creator), { icon: 'user' });
        pushChip('from', t('feed_filter_from'), fmt.date(filters.from), { icon: 'calendar' });
        pushChip('to', t('feed_filter_to'), fmt.date(filters.to), { icon: 'calendar' });
        pushChip('stage', t('feed_filter_stage'), findLabel(stageOptions, filters.stage), { icon: 'clock' });

        const removeChip = (name) => {
            if (name === 'q') {
                pushed.current = '';
                setTerm('');
            }

            set(name)('');
        };

        const hasFilters = chips.length > 0 || scope !== 'all';

        return h('div', { className: 'ks-feed-filterbar' },
            h('div', { className: 'ks-toolbar ks-feed-toolbar' },
                h(KS.TextInput, {
                    type: 'search',
                    value: term,
                    onChange: (value) => setTerm(value),
                    onEnter: (value) => {
                        const next = String(value || '').trim();

                        pushed.current = next;
                        KS.actions.setFilters({ q: next });
                    },
                    icon: 'search',
                    clearable: true,
                    maxLength: 100,
                    placeholder: t('feed_search_placeholder'),
                    'aria-label': t('feed_search_label'),
                    wrapClassName: 'ks-input-wrap--search ks-feed-search',
                    enterKeyHint: 'search',
                }),
                h(KS.Button, {
                    variant: 'ghost',
                    icon: 'filter',
                    active: open || activeAdvanced.length > 0,
                    'aria-expanded': open ? 'true' : 'false',
                    'aria-controls': panelId,
                    onClick: () => setOpen(!open),
                },
                    t('filters'),
                    activeAdvanced.length ? h('span', { className: 'ks-feed-filtercount' }, fmt.number(activeAdvanced.length)) : null,
                ),
                h(KS.Segmented, {
                    items: [
                        { value: 'grid', label: t('feed_layout_grid'), icon: 'grid' },
                        { value: 'row', label: t('feed_layout_rows'), icon: 'list' },
                    ],
                    value: props.layout,
                    onChange: props.onLayout,
                    label: t('feed_layout_label'),
                    iconOnly: true,
                    className: 'ks-feed-layout',
                }),
            ),
            scopeItems.length > 1
                ? h('div', { className: 'ks-feed-scope' },
                    h(KS.Segmented, { items: scopeItems, value: scope, onChange: setScope, label: t('feed_scope_label'), size: 'sm' }))
                : null,
            open
                ? h('div', { id: panelId, className: 'ks-feed-filters', role: 'group', 'aria-label': t('filters') },
                    h(KS.Field, { label: t('feed_filter_type') },
                        h(KS.Select, { options: typeOptions, value: filters.type, onChange: set('type'), placeholder: t('all') })),
                    h(KS.Field, { label: t('feed_filter_platform') },
                        h(KS.Select, { options: platformOptions, value: filters.platform, onChange: set('platform'), placeholder: t('all') })),
                    h(KS.Field, { label: t('feed_filter_category') },
                        h(KS.Select, { options: categoryOptions, value: filters.category, onChange: set('category'), placeholder: t('all'), disabled: !categoryOptions.length })),
                    h(KS.Field, { label: t('feed_filter_creator') },
                        h(KS.Select, { options: creatorOptions, value: filters.creator, onChange: set('creator'), placeholder: t('all'), disabled: !creatorOptions.length })),
                    h(KS.Field, { label: t('feed_filter_from') },
                        h(KS.DateInput, { value: filters.from, onChange: set('from'), max: filters.to || undefined })),
                    h(KS.Field, { label: t('feed_filter_to') },
                        h(KS.DateInput, { value: filters.to, onChange: set('to'), min: filters.from || undefined })),
                    h(KS.Field, { label: t('feed_filter_stage') },
                        h(KS.Select, { options: stageOptions, value: filters.stage, onChange: set('stage'), placeholder: t('all') })),
                )
                : null,
            hasFilters
                ? h('div', { className: 'ks-feed-active', role: 'group', 'aria-label': t('feed_active_filters') },
                    chips.map((chip) => h(KS.Chip, { key: chip.name, label: chip.label, icon: chip.icon, platform: chip.platform, size: 'sm', onRemove: () => removeChip(chip.name) })),
                    h(KS.Button, { variant: 'link', size: 'sm', icon: 'close', onClick: clearAll }, t('clear_filters')),
                )
                : null,
        );
    }

    /* ================================================================== */
    /* 10. Liste durumlari                                                  */
    /* ================================================================== */

    function SkeletonList(props) {
        const items = [];

        for (let index = 0; index < (props.count || 8); index += 1) {
            items.push(index);
        }

        if (props.layout === 'row') {
            return h('ul', { className: 'ks-feed-rows', 'aria-busy': 'true' },
                items.slice(0, 6).map((index) => h('li', { key: index, className: 'ks-feed-item' },
                    h('div', { className: 'ks-feed-row ks-feed-row--skeleton', 'aria-hidden': 'true' },
                        h(KS.Skeleton, { variant: 'rect', className: 'ks-feed-skel__thumb' }),
                        h('div', { className: 'ks-feed-row__main' },
                            h(KS.Skeleton, { variant: 'text', width: '30%' }),
                            h(KS.Skeleton, { variant: 'text', width: '75%' }),
                            h(KS.Skeleton, { variant: 'text', width: '45%' }),
                        ),
                    ))),
                h('li', { className: 'ks-sr-only' }, t('loading')),
            );
        }

        return h('ul', { className: 'ks-grid ks-feed-grid', 'aria-busy': 'true' },
            items.map((index) => h('li', { key: index, className: 'ks-feed-item' },
                h('div', { className: 'ks-feed-card ks-feed-card--skeleton', 'aria-hidden': 'true' },
                    h(KS.Skeleton, { variant: 'rect', className: 'ks-feed-skel__stage' }),
                    h('div', { className: 'ks-feed-card__body' },
                        h(KS.Skeleton, { variant: 'text', width: '40%' }),
                        h(KS.Skeleton, { variant: 'text', width: '85%' }),
                        h(KS.Skeleton, { variant: 'text', width: '60%' }),
                        h('div', { className: 'ks-feed-skel__foot' },
                            h(KS.Skeleton, { variant: 'circle', width: 20, height: 20 }),
                            h(KS.Skeleton, { variant: 'text', width: '35%' }),
                        ),
                    ),
                ))),
            h('li', { className: 'ks-sr-only' }, t('loading')),
        );
    }

    function EmptyFeed(props) {
        const filters = props.filters;
        const filtered = !isBlank(filters.q) || (filters.scope && filters.scope !== 'all') || ADVANCED_FILTERS.some((name) => !isBlank(filters[name]));
        const tabbed = (filters.status && filters.status !== 'all') || !isBlank(filters.published);
        const addButton = props.canCreate
            ? h(KS.Button, { key: 'add', variant: 'primary', icon: 'plus', onClick: props.onAdd }, t('feed_add_content'))
            : null;

        if (filtered) {
            return h(KS.Empty, {
                icon: 'search',
                title: t('feed_empty_filtered_title'),
                text: t('feed_empty_filtered_text'),
                action: [h(KS.Button, { key: 'clear', variant: 'soft', icon: 'close', onClick: props.onClear }, t('clear_filters')), addButton],
            });
        }

        if (tabbed) {
            return h(KS.Empty, {
                icon: 'layers',
                title: t('feed_empty_tab_title'),
                text: t('feed_empty_tab_text'),
                action: [h(KS.Button, { key: 'all', variant: 'soft', onClick: props.onShowAll }, t('feed_show_all')), addButton],
            });
        }

        return h(KS.Empty, {
            icon: 'images',
            title: t('feed_empty_title'),
            text: props.canCreate ? t('feed_empty_text') : t('feed_empty_text_readonly'),
            action: addButton,
        });
    }

    /* ================================================================== */
    /* 11. Akis gorunumu                                                    */
    /* ================================================================== */

    function Feed() {
        const profile = KS.useProfile();
        const filters = KS.useStore((state) => state.filters);
        const feedVersion = KS.useStore((state) => state.feedVersion);
        const storage = KS.useStore((state) => state.storage);
        const counts = KS.useCounts();
        const abilities = KS.useAbilities();
        const wide = KS.useMediaQuery('(min-width: 1280px)');
        const profileId = profile ? profile.id : null;
        const feed = useFeed(profileId, filters, feedVersion);
        const watch = useWatch(profileId);
        const [layout, setLayout] = useLayout();
        const firstVersion = useRef(feedVersion);
        const storageAt = useRef(0);
        const showWatch = KS.hasEndpoint('watch');

        // Depolama: acilista yoksa ve akis tazelenince (yeni medya yuklenmis olabilir) guncellenir;
        // toplama sorgusu agir oldugu icin en cok 20 saniyede bir istenir.
        useEffect(() => {
            const missing = !KS.store.getState().storage;
            const changed = feedVersion !== firstVersion.current;

            if (missing || (changed && Date.now() - storageAt.current > 20000)) {
                storageAt.current = Date.now();
                KS.actions.refreshStorage();
            }
        }, [feedVersion]);

        const tab = filters.published === 'yes' ? 'published' : (filters.status || 'all');

        const onTab = useCallback((value) => {
            const current = KS.store.getState().filters;
            const patch = value === 'published' ? { status: 'all', published: 'yes' } : { status: value, published: '' };

            if (NARROW_SCOPES.indexOf(current.scope) !== -1) {
                patch.scope = 'all';
            }

            KS.actions.setFilters(patch);
        }, []);

        const onAdd = useCallback(() => {
            KS.actions.openComposer({ defaults: { profile_id: KS.store.getState().profileId } });
        }, []);

        const onClear = useCallback(() => {
            const current = KS.store.getState().filters;

            KS.actions.resetFilters({ status: current.status, published: current.published });
        }, []);

        if (!profile) {
            return h(KS.Empty, { icon: 'users', title: t('feed_no_profile_title'), text: t('feed_no_profile_text') });
        }

        const statusLabel = (value) => KS.optionLabel('statuses', value) || t('feed_tab_' + value);
        const tabs = [{ value: 'all', label: t('all'), count: counts.all }]
            .concat(STATUS_TABS.map((value) => ({
                value,
                label: statusLabel(value),
                count: counts[value],
                countTone: value === 'pending' && Number(counts.pending) > 0 ? 'amber' : undefined,
            })))
            .concat([{ value: 'published', label: t('feed_tab_published'), count: counts.published, icon: 'check-circle' }]);

        let results = null;

        if (feed.loading) {
            results = h(SkeletonList, { layout });
        } else if (feed.error) {
            results = h('div', { className: 'ks-feed-state' }, h(KS.ErrorState, { error: feed.error, onRetry: feed.reload }));
        } else if (!feed.items.length) {
            results = h('div', { className: 'ks-feed-state' },
                h(EmptyFeed, { filters, canCreate: !!abilities.create, onAdd, onClear, onShowAll: () => onTab('all') }));
        } else {
            results = h(KS.Fragment, null,
                h('ul', {
                    className: cx(layout === 'row' ? 'ks-feed-rows' : 'ks-grid ks-feed-grid', feed.refreshing && 'is-refreshing'),
                    'aria-busy': feed.refreshing ? 'true' : undefined,
                },
                    feed.items.map((card) => h('li', { key: card.id, className: 'ks-feed-item' }, h(FeedCard, { card, variant: layout })))),
                h('div', { className: 'ks-feed-more' },
                    h('p', { className: 'ks-feed-count', 'aria-live': 'polite' }, t('feed_showing', { shown: fmt.number(feed.items.length), total: fmt.number(Math.max(feed.total, feed.items.length)) })),
                    h(KS.LoadMore, { hasMore: feed.hasMore, loading: feed.loadingMore, onClick: feed.more }),
                ),
            );
        }

        return h('div', { className: 'ks-feed' },
            h('div', { className: 'ks-feed-top-wrap' },
                h('div', { className: 'ks-feed-top' },
                    h(ProfileHeader, { profile, counts, tab, onTab, canManage: !!abilities.manage_data }),
                    h(StorageBox, { storage }),
                ),
            ),
            showWatch && !wide ? h(WatchPanel, { mode: 'strip', resource: watch, canManage: !!abilities.manage_data }) : null,
            h('div', { className: 'ks-content ks-content--with-rail ks-feed-content' },
                h('div', { className: 'ks-main' },
                    h(KS.Tabs, { items: tabs, value: tab, onChange: onTab, label: t('feed_tabs_label'), className: 'ks-feed-tabs' }),
                    h(FilterBar, { filters, counts, abilities, layout, onLayout: setLayout }),
                    results,
                ),
                showWatch && wide
                    ? h('aside', { className: 'ks-rail ks-feed-rail' }, h(WatchPanel, { mode: 'rail', resource: watch, canManage: !!abilities.manage_data }))
                    : null,
            ),
        );
    }

    /* ================================================================== */
    /* 12. Kayit                                                            */
    /* ================================================================== */

    KS.parts = KS.parts || {};
    KS.parts.ContentCard = ContentCard;
    KS.parts.StageChip = StageChip;
    KS.parts.PlatformIcons = PlatformIcons;
    KS.parts.MediaMosaic = MediaMosaic;
    KS.views.Feed = Feed;
}());
