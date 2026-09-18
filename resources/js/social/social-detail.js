/*
 * Konelsis Sosyal Medya modulu - ICERIK AYRINTISI (B31, D-106, 18 Eylul 2026).
 *
 * KS.views.Detail({ id, onClose }): tam ekran kaplama. Solda sahne (gorsel / video /
 * metin okuma gorunumu), sagda yan panel (Bilgi, Yorumlar, Begeniler, Gecmis).
 *  - Gorsel sahnesi: 1x-6x yakinlastirma (dugme, tekerlek, iki parmak), kaydirma,
 *    sigdir / %100, serit + oklar + klavye oklari, surum onizleme.
 *  - Isaret kipi: Nokta / Alan secilir, gorsel uzerine cizilir, istek yazilir;
 *    yorum GORUNTULENEN surumun media_id degeri ve anchor ile kaydedilir.
 *    Isaretler yalniz goruntulenen surum === comment.media_id iken cizilir (H7).
 *  - Gorsel araclari: bicim (orijinal uzerinde oran kilitli kadraj), cozunurluk,
 *    surum secimi, aciklama, sira, galeriden cikarma.
 *  - Metin turleri: platform uslubunda okuma gorunumu + yerinde duzenleme
 *    (row_version ile; 409'da metin korunur, yeniden yukle / kopyala sunulur).
 * Baska gorunum dosyalarindan gelen her sey (KS.RichEditor, KS.RichContent,
 * KS.parts.*) cizim aninda ve varligi denetlenerek kullanilir.
 * Stiller: resources/css/filament/social-parts/detail.css (on ek ks-detail-).
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, Fragment, t, api, url, fmt, cx, clamp } = KS;
    const { useState, useEffect, useRef, useCallback, useMemo, useLayoutEffect } = KS;
    const {
        Icon, Button, IconButton, Badge, StatusBadge, StageBadge, Chip, Avatar, PersonLine, PlatformIcon, ExternalLink,
        Modal, Drawer, Overlay, Popover, Menu, Tabs, Segmented, Field, TextInput, TextArea, Checkbox,
        Empty, ErrorState, Notice, Spinner, Skeleton,
    } = KS;

    /* ================================================================== */
    /* 1. Sabitler ve yardimcilar                                          */
    /* ================================================================== */

    const MIN_ZOOM = 1;
    const MAX_ZOOM = 6;
    const MIN_RECT = 0.02;
    const TEXT_TYPES = ['short_text', 'long_text', 'blog'];
    const RICH_TYPES = ['long_text', 'blog'];
    const FORMAT_KEYS = ['square', 'portrait', 'story', 'landscape'];
    const PRESET_KEYS = ['sd', 'hd', 'full_hd', 'double'];
    const FORMAT_SIZES = { square: [1080, 1080], portrait: [1080, 1350], story: [1080, 1920], landscape: [1920, 1080] };
    const FORMAT_ICONS = { square: 'format-square', portrait: 'format-portrait', story: 'format-story', landscape: 'format-landscape' };
    const FORMAT_LABEL_KEYS = { square: 'detail_format_square', portrait: 'detail_format_portrait', story: 'detail_format_story', landscape: 'detail_format_landscape' };
    const PRESET_LABEL_KEYS = { sd: 'detail_preset_sd', hd: 'detail_preset_hd', full_hd: 'detail_preset_full_hd', double: 'detail_preset_double' };

    /* Durum dugmeleri: etiketler kullanicinin istedigi eylem adlaridir (H7). */
    const STATUS_ACTIONS = {
        approved: { label: 'detail_action_approve', variant: 'success', icon: 'check', note: 'optional' },
        rejected: { label: 'detail_action_reject', variant: 'danger', icon: 'x-mark', note: 'required' },
        revision_requested: { label: 'detail_action_revision', variant: 'soft', icon: 'refresh', note: 'required' },
        pending: { label: 'detail_action_resubmit', variant: 'primary', icon: 'send', note: null },
        archived: { label: 'detail_action_archive', variant: 'ghost', icon: 'archive', note: null },
        unarchive: { label: 'unarchive', variant: 'soft', icon: 'restore', note: null },
    };

    function isTextType(type) {
        return TEXT_TYPES.indexOf(type) !== -1;
    }

    function isRichType(type) {
        return RICH_TYPES.indexOf(type) !== -1;
    }

    function round6(value) {
        return Math.round(Number(value) * 1000000) / 1000000;
    }

    function parts() {
        return KS.parts || {};
    }

    function focusNode(node) {
        if (!node || typeof node.focus !== 'function') {
            return;
        }

        try {
            node.focus({ preventScroll: true });
        } catch (error) {
            node.focus();
        }
    }

    /** Klavye kisayollari yazi alanlarinda, oynaticida ve ustte baska pencere varken calismaz. */
    function shortcutsBlocked(event) {
        const target = event.target;

        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
            return true;
        }

        if (target && target.nodeName && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT|VIDEO|AUDIO|IFRAME)$/.test(target.nodeName))) {
            return true;
        }

        if (document.querySelector('.ks-modal-layer, .ks-drawer-layer')) {
            return true;
        }

        return !!KS.store.getState().composer.open;
    }

    function isStageUi(target) {
        return !!(target && typeof target.closest === 'function' && target.closest('[data-ks-stage-ui]'));
    }

    function optionOf(group, value) {
        return KS.options(group).find((item) => item && item.value === value) || null;
    }

    function formatLabel(value) {
        const option = optionOf('formats', value);

        return (option && option.label) || t(FORMAT_LABEL_KEYS[value] || 'detail_format_square');
    }

    function presetLabel(value) {
        const option = optionOf('presets', value);

        return (option && option.label) || t(PRESET_LABEL_KEYS[value] || 'detail_preset_hd');
    }

    function formatSize(value) {
        const option = optionOf('formats', value);
        const fallback = FORMAT_SIZES[value] || [1080, 1080];

        return [Number(option && option.width) || fallback[0], Number(option && option.height) || fallback[1]];
    }

    /** Icerigin kopyalanacak duz metni: kisa metin govdesi, zengin govdenin duz hali ya da aciklama. */
    function plainTextOf(detail) {
        if (detail.content_type === 'short_text') {
            return detail.body_text || '';
        }

        if (isRichType(detail.content_type)) {
            return KS.htmlToPlainText(detail.body_html || '');
        }

        return detail.caption || '';
    }

    /** Isaretli kok yorumlar, sirali numarayla. */
    function collectMarks(comments) {
        const out = [];

        (comments || []).forEach((comment) => {
            if (comment && comment.anchor && comment.media_id) {
                out.push({ id: comment.id, n: out.length + 1, media_id: comment.media_id, anchor: comment.anchor, resolved: !!comment.resolved, comment });
            }
        });

        return out;
    }

    function anchorStyle(anchor) {
        const style = { left: (Number(anchor.x) * 100) + '%', top: (Number(anchor.y) * 100) + '%' };

        if (anchor.shape === 'rect') {
            style.width = (Number(anchor.w || 0) * 100) + '%';
            style.height = (Number(anchor.h || 0) * 100) + '%';
        }

        return style;
    }

    function shorten(text, max) {
        const value = String(text || '').replace(/\s+/g, ' ').trim();

        return value.length > max ? value.slice(0, max - 1) + '…' : value;
    }

    /** "A, B ve C" / "A, B, C ve 2 kisi" kalibi icin adlar metni. */
    function namesText(people) {
        const info = fmt.names(people, 3);
        const shown = info.shown;

        if (!shown.length) {
            return { names: '', rest: 0 };
        }

        if (info.rest > 0 || shown.length === 1) {
            return { names: shown.join(', '), rest: info.rest };
        }

        return { names: t('detail_names_and', { first: shown.slice(0, -1).join(', '), last: shown[shown.length - 1] }), rest: 0 };
    }

    /** video_url -> gomulu oynatici adresi (YouTube / Vimeo) ya da null (H7). */
    function embedUrl(raw) {
        const safe = KS.safeUrl(raw);

        if (!safe || safe.charAt(0) === '/') {
            return null;
        }

        let parsed = null;

        try {
            parsed = new URL(safe);
        } catch (error) {
            return null;
        }

        const host = parsed.hostname.toLowerCase().replace(/^(www|m)\./, '');
        const segments = parsed.pathname.split('/').filter(Boolean);
        let id = null;

        if (host === 'youtube.com' && segments[0] === 'watch') {
            id = parsed.searchParams.get('v');
        } else if (host === 'youtube.com' && (segments[0] === 'shorts' || segments[0] === 'embed' || segments[0] === 'live')) {
            id = segments[1] || null;
        } else if (host === 'youtu.be') {
            id = segments[0] || null;
        }

        if (id && /^[A-Za-z0-9_-]{6,20}$/.test(id)) {
            return 'https://www.youtube-nocookie.com/embed/' + id;
        }

        if (host === 'vimeo.com' && segments.length && /^\d{4,14}$/.test(segments[segments.length - 1])) {
            return 'https://player.vimeo.com/video/' + segments[segments.length - 1];
        }

        return null;
    }

    function hostOf(raw) {
        try {
            return new URL(raw).hostname.replace(/^www\./, '');
        } catch (error) {
            return '';
        }
    }

    /** Duz metni baglanti, #etiket ve @anma vurgulariyla cizer (yalniz React dugumleri; HTML yok). */
    function renderPlainText(text) {
        const pieces = String(text || '').split(/(https?:\/\/[^\s<>"']+|[#@][\wÀ-ɏ]+)/);

        return pieces.map((piece, index) => {
            if (index % 2 === 0) {
                return piece;
            }

            if (piece.charAt(0) === '#' || piece.charAt(0) === '@') {
                const before = pieces[index - 1] || '';

                // "ad@alan.com" gibi yazimlar anma sayilmaz.
                if (before !== '' && !/\s$/.test(before)) {
                    return piece;
                }

                return h('span', { key: index, className: 'ks-detail-tag' }, piece);
            }

            const trailing = (/[.,;:!?)\]]+$/.exec(piece) || [''])[0];
            const address = trailing ? piece.slice(0, piece.length - trailing.length) : piece;

            return h(Fragment, { key: index }, h(ExternalLink, { href: address }, address), trailing);
        });
    }

    function confirmDiscard() {
        return KS.confirm({
            title: t('unsaved_changes_confirm'),
            confirmLabel: t('discard'),
            cancelLabel: t('keep_editing'),
            danger: true,
        });
    }

    function copyWithToast(text) {
        KS.copyText(text).then((ok) => {
            if (ok) {
                KS.toast.success(t('copied'));
            } else {
                KS.toast.error(t('copy_failed'));
            }
        });
    }

    /* ================================================================== */
    /* 2. Kucuk ortak parcalar                                             */
    /* ================================================================== */

    /** Sunucunun temizledigi HTML: KS.RichContent varsa onunla, yoksa duz metin olarak. */
    function RichBody(props) {
        const RichContent = KS.RichContent;

        if (typeof RichContent === 'function') {
            return h(RichContent, { html: props.html || '', className: props.className });
        }

        return h('div', { className: cx('ks-detail-plain', props.className) }, KS.htmlToPlainText(props.html || ''));
    }

    function PlanChip(props) {
        const detail = props.detail;

        if (!detail.planned_on) {
            return null;
        }

        const StageChip = parts().StageChip;

        if (typeof StageChip === 'function') {
            return h(StageChip, { stage: detail.is_published ? null : detail.planned_stage, date: detail.planned_on, time: detail.planned_time });
        }

        return h('span', { className: 'ks-detail-plan' },
            h(Icon, { name: 'calendar' }),
            h('span', null, fmt.planned(detail.planned_on, detail.planned_time)),
            detail.planned_stage && !detail.is_published ? h(StageBadge, { stage: detail.planned_stage, size: 'sm' }) : null,
        );
    }

    function PlatformList(props) {
        const platforms = props.platforms || [];

        if (!platforms.length) {
            return h('span', { className: 'ks-muted' }, t('detail_none'));
        }

        return h('span', { className: 'ks-detail-platforms' }, platforms.map((item) => h(PlatformIcon, {
            key: item.platform,
            platform: item.platform,
            variant: 'badge',
            label: item.platform_label,
            className: 'ks-detail-platforms__item',
        })));
    }

    /** Yorum / yanit yazma kutusu (Ctrl+Enter gonderir). onSubmit(metin) -> Promise<boolean>. */
    function CommentComposer(props) {
        const [value, setValue] = useState('');
        const ref = useRef(null);

        useEffect(() => {
            if (props.autoFocus) {
                focusNode(ref.current);
            }
        }, []);

        const submit = () => {
            const body = value.trim();

            if (!body || props.busy) {
                return;
            }

            Promise.resolve(props.onSubmit(body)).then((ok) => {
                if (ok) {
                    setValue('');
                }
            });
        };

        return h('div', { className: cx('ks-detail-composer', props.compact && 'ks-detail-composer--compact') },
            h(TextArea, {
                value,
                onChange: setValue,
                rows: 2,
                autoGrow: true,
                maxRows: 8,
                maxLength: props.max || undefined,
                placeholder: props.placeholder,
                inputRef: ref,
                'aria-label': props.placeholder,
                onKeyDown: (event) => {
                    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                        event.preventDefault();
                        submit();
                    }
                },
            }),
            h('div', { className: 'ks-detail-composer__foot' },
                h('span', { className: 'ks-detail-composer__hint' }, t('detail_send_shortcut')),
                props.onCancel ? h(Button, { size: 'sm', variant: 'ghost', onClick: props.onCancel }, t('cancel')) : null,
                h(Button, { size: 'sm', variant: 'primary', icon: 'send', loading: !!props.busy, disabled: value.trim() === '', onClick: submit }, t('send')),
            ),
        );
    }

    /* ================================================================== */
    /* 3. Gorsel sahnesi: yakinlastirma, kaydirma, isaretler, kadraj       */
    /* ================================================================== */

    /** Tek isaret: nokta (numarali igne) ya da alan (cerceve + kosede numara). Boyut yakinlastirmadan etkilenmez. */
    function MarkShape(props) {
        const mark = props.mark;
        const rect = mark.anchor.shape === 'rect';
        const label = t(mark.resolved ? 'detail_mark_aria_resolved' : 'detail_mark_aria', { n: mark.n });

        return h('div', {
            className: cx('ks-detail-mark', rect ? 'ks-detail-mark--rect' : 'ks-detail-mark--point', mark.resolved && 'is-resolved', props.active && 'is-active'),
            style: anchorStyle(mark.anchor),
        },
            h('button', {
                type: 'button',
                className: 'ks-detail-mark__pin',
                'data-ks-stage-ui': '',
                'aria-label': label,
                'aria-pressed': props.active ? 'true' : 'false',
                title: mark.comment ? shorten(mark.comment.body, 140) : label,
                onMouseEnter: () => props.onHover(mark.id),
                onMouseLeave: () => props.onHover(null),
                onFocus: () => props.onHover(mark.id),
                onBlur: () => props.onHover(null),
                onClick: () => props.onSelect(mark),
            }, mark.n),
        );
    }

    /** Cizilmekte olan ya da istegi yazilmayi bekleyen isaret. */
    function DraftShape(props) {
        const anchor = props.anchor;

        return h('div', {
            className: cx('ks-detail-mark', anchor.shape === 'rect' ? 'ks-detail-mark--rect' : 'ks-detail-mark--point', 'is-draft'),
            style: anchorStyle(anchor),
            'aria-hidden': 'true',
        }, h('span', { className: 'ks-detail-mark__pin' }, h(Icon, { name: 'plus' })));
    }

    /** Isaretin istegini yazma penceresi (sahnede isaretin yaninda; telefonda sahnenin altinda). */
    function MarkComposer(props) {
        const [value, setValue] = useState('');
        const ref = useRef(null);

        useEffect(() => focusNode(ref.current), []);

        const submit = () => {
            const body = value.trim();

            if (body && !props.busy) {
                props.onSubmit(body);
            }
        };

        return h('div', { className: 'ks-detail-markpop', style: props.style, 'data-ks-stage-ui': '', role: 'group', 'aria-label': t('detail_mark_request_title') },
            h('p', { className: 'ks-detail-markpop__title' },
                h(Icon, { name: props.shape === 'rect' ? 'square' : 'pin' }),
                h('span', null, t('detail_mark_request_title')),
            ),
            h(TextArea, {
                value,
                onChange: setValue,
                rows: 3,
                autoGrow: true,
                maxRows: 6,
                maxLength: props.max || undefined,
                placeholder: t('detail_mark_request_placeholder'),
                inputRef: ref,
                'aria-label': t('detail_mark_request_title'),
                onKeyDown: (event) => {
                    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                        event.preventDefault();
                        submit();
                    }
                },
            }),
            h('div', { className: 'ks-detail-markpop__foot' },
                h(Button, { size: 'sm', variant: 'ghost', onClick: props.onCancel, disabled: !!props.busy }, t('cancel')),
                h(Button, { size: 'sm', variant: 'primary', icon: 'send', loading: !!props.busy, disabled: value.trim() === '', onClick: submit }, t('send')),
            ),
        );
    }

    /**
     * Oran kilitli kadraj cercevesi. box ve ratio, ORIJINAL gorselin 0..1 olcegindedir
     * (ratio = genislik / yukseklik, normalize). Surukleme tasir, koseler boyutlandirir,
     * ok tuslari cerceveyi kaydirir.
     */
    function CropFrame(props) {
        const drag = useRef(null);
        const box = props.box;
        const ratio = props.ratio;

        const toNorm = (event) => {
            const node = props.canvasRef.current;
            const rect = node ? node.getBoundingClientRect() : null;

            if (!rect || !rect.width || !rect.height) {
                return null;
            }

            return { x: (event.clientX - rect.left) / rect.width, y: (event.clientY - rect.top) / rect.height };
        };

        const resize = (start, corner, point) => {
            const right = corner.indexOf('e') !== -1;
            const bottom = corner.indexOf('s') !== -1;
            const anchorX = right ? start.x : start.x + start.w;
            const anchorY = bottom ? start.y : start.y + start.h;
            const roomX = right ? 1 - anchorX : anchorX;
            const roomY = bottom ? 1 - anchorY : anchorY;
            const maxW = Math.min(roomX, roomY * ratio);
            const minW = Math.min(maxW, Math.max(0.08, 0.08 * ratio));
            const wanted = Math.max(Math.abs(point.x - anchorX), Math.abs(point.y - anchorY) * ratio);
            const w = clamp(wanted, minW, maxW);
            const hh = w / ratio;

            return { x: right ? anchorX : anchorX - w, y: bottom ? anchorY : anchorY - hh, w, h: hh };
        };

        const handlers = (mode) => ({
            onPointerDown: (event) => {
                if (event.pointerType === 'mouse' && event.button !== 0) {
                    return;
                }

                const origin = toNorm(event);

                if (!origin) {
                    return;
                }

                event.stopPropagation();
                event.preventDefault();

                try {
                    event.currentTarget.setPointerCapture(event.pointerId);
                } catch (error) {
                    // Yakalama desteklenmiyorsa surukleme yine calisir (isaretci cercevenin ustundeyken).
                }

                drag.current = { mode, id: event.pointerId, origin, box };
            },
            onPointerMove: (event) => {
                const state = drag.current;

                if (!state || state.id !== event.pointerId) {
                    return;
                }

                event.stopPropagation();

                const point = toNorm(event);

                if (!point) {
                    return;
                }

                if (state.mode === 'move') {
                    props.onChange({
                        x: clamp(state.box.x + point.x - state.origin.x, 0, 1 - state.box.w),
                        y: clamp(state.box.y + point.y - state.origin.y, 0, 1 - state.box.h),
                        w: state.box.w,
                        h: state.box.h,
                    });
                } else {
                    props.onChange(resize(state.box, state.mode, point));
                }
            },
            onPointerUp: (event) => {
                if (drag.current && drag.current.id === event.pointerId) {
                    drag.current = null;
                    event.stopPropagation();
                }
            },
            onPointerCancel: () => {
                drag.current = null;
            },
        });

        const onKeyDown = (event) => {
            const step = event.shiftKey ? 0.05 : 0.01;
            let dx = 0;
            let dy = 0;

            if (event.key === 'ArrowLeft') {
                dx = -step;
            } else if (event.key === 'ArrowRight') {
                dx = step;
            } else if (event.key === 'ArrowUp') {
                dy = -step;
            } else if (event.key === 'ArrowDown') {
                dy = step;
            } else {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            props.onChange({ x: clamp(box.x + dx, 0, 1 - box.w), y: clamp(box.y + dy, 0, 1 - box.h), w: box.w, h: box.h });
        };

        return h('div', Object.assign({
            className: 'ks-detail-crop',
            'data-ks-stage-ui': '',
            role: 'group',
            tabIndex: 0,
            'aria-label': t('detail_crop_frame'),
            style: { left: (box.x * 100) + '%', top: (box.y * 100) + '%', width: (box.w * 100) + '%', height: (box.h * 100) + '%' },
            onKeyDown,
        }, handlers('move')),
            h('span', { className: 'ks-detail-crop__grid', 'aria-hidden': 'true' }),
            ['nw', 'ne', 'sw', 'se'].map((corner) => h('span', Object.assign({
                key: corner,
                className: 'ks-detail-crop__handle ks-detail-crop__handle--' + corner,
                'aria-hidden': 'true',
            }, handlers(corner)))),
        );
    }

    /**
     * ImageStage: tek gorselin sahnesi.
     * props: item (goruntulenen medya satiri), alt, marks, highlightId, onMarkHover(id|null), onMarkSelect(mark),
     *        drawMode (null|'point'|'rect'), draft, onDraft(anchor|null), onDraftSubmit(metin), draftBusy, commentMax,
     *        crop ({ box, ratio }|null), onCropChange(box), hasPrev, hasNext, onPrev, onNext
     */
    function ImageStage(props) {
        const item = props.item;
        const isMobile = KS.useIsMobile();
        const pad = isMobile ? 8 : 24;
        const viewportRef = useRef(null);
        const canvasRef = useRef(null);
        const pointers = useRef(new Map());
        const gesture = useRef(null);
        const smoothTimer = useRef(0);
        const geo = useRef({ baseW: 0, baseH: 0, boxW: 0, boxH: 0, pad });
        const [box, setBox] = useState({ w: 0, h: 0 });
        const [natural, setNatural] = useState({ w: Number(item.width) || 0, h: Number(item.height) || 0 });
        const [view, setView] = useState({ z: 1, x: 0, y: 0 });
        const [drawing, setDrawing] = useState(null);
        const [smooth, setSmooth] = useState(false);
        const [fullReady, setFullReady] = useState(false);
        const [loaded, setLoaded] = useState(false);
        const [broken, setBroken] = useState(false);

        const fullSrc = KS.safeUrl(item.url);
        const previewSrc = item.mime === 'image/gif' ? null : KS.safeUrl(item.preview_url);
        const src = fullReady || !previewSrc ? fullSrc : previewSrc;
        const wantsFull = view.z >= 1.6;

        // Once hafif onizleme gosterilir; yakinlastirinca tam cozunurluk arka planda yuklenir.
        useEffect(() => {
            if (fullReady || !previewSrc || !fullSrc || !wantsFull) {
                return undefined;
            }

            let cancelled = false;
            const probe = new window.Image();

            probe.onload = () => {
                if (!cancelled) {
                    setFullReady(true);
                }
            };
            probe.src = fullSrc;

            return () => {
                cancelled = true;
                probe.onload = null;
            };
        }, [fullReady, previewSrc, fullSrc, wantsFull]);

        const aspect = natural.w > 0 && natural.h > 0 ? natural.w / natural.h : 1;
        const availW = Math.max(0, box.w - pad * 2);
        const availH = Math.max(0, box.h - pad * 2);
        let baseW = availW;
        let baseH = availW / aspect;

        if (baseH > availH) {
            baseH = availH;
            baseW = availH * aspect;
        }

        geo.current = { baseW, baseH, boxW: box.w, boxH: box.h, pad };

        const clampView = useCallback((next) => {
            const g = geo.current;
            const z = clamp(next.z, MIN_ZOOM, MAX_ZOOM);
            const maxX = Math.max(0, (g.baseW * z - g.boxW) / 2 + g.pad);
            const maxY = Math.max(0, (g.baseH * z - g.boxH) / 2 + g.pad);

            return { z, x: clamp(next.x, -maxX, maxX), y: clamp(next.y, -maxY, maxY) };
        }, []);

        const zoomBy = useCallback((factor, px, py) => {
            setView((current) => {
                const z = clamp(current.z * factor, MIN_ZOOM, MAX_ZOOM);

                if (z === current.z) {
                    return current;
                }

                const ratio = z / current.z;
                const cx0 = px || 0;
                const cy0 = py || 0;

                return clampView({ z, x: cx0 - (cx0 - current.x) * ratio, y: cy0 - (cy0 - current.y) * ratio });
            });
        }, [clampView]);

        const animate = (fn) => {
            setSmooth(true);
            window.clearTimeout(smoothTimer.current);
            smoothTimer.current = window.setTimeout(() => setSmooth(false), 240);
            fn();
        };

        useEffect(() => () => window.clearTimeout(smoothTimer.current), []);

        const zoom100 = natural.w > 0 && baseW > 0 ? clamp(natural.w / baseW, MIN_ZOOM, MAX_ZOOM) : 1;

        useLayoutEffect(() => {
            const node = viewportRef.current;

            if (!node) {
                return undefined;
            }

            const measure = () => {
                const rect = node.getBoundingClientRect();

                setBox((current) => (Math.abs(current.w - rect.width) < 0.5 && Math.abs(current.h - rect.height) < 0.5 ? current : { w: rect.width, h: rect.height }));
            };

            measure();

            if (typeof window.ResizeObserver === 'function') {
                const observer = new window.ResizeObserver(measure);

                observer.observe(node);

                return () => observer.disconnect();
            }

            window.addEventListener('resize', measure);

            return () => window.removeEventListener('resize', measure);
        }, []);

        // Kap olcusu degisince kaydirma sinirlari yeniden uygulanir.
        useEffect(() => {
            setView((current) => {
                const next = clampView(current);

                return next.x === current.x && next.y === current.y && next.z === current.z ? current : next;
            });
        }, [box.w, box.h, baseW, baseH, clampView]);

        // Tekerlek: React onWheel edilgen (passive) oldugu icin dinleyici elle ve { passive: false } ile eklenir (H7).
        useEffect(() => {
            const node = viewportRef.current;

            if (!node) {
                return undefined;
            }

            const onWheel = (event) => {
                if (isStageUi(event.target) && !(event.target.closest && event.target.closest('.ks-detail-mark, .ks-detail-crop'))) {
                    return;
                }

                event.preventDefault();

                const rect = node.getBoundingClientRect();
                let delta = event.deltaY;

                if (event.deltaMode === 1) {
                    delta *= 16;
                } else if (event.deltaMode === 2) {
                    delta *= rect.height;
                }

                zoomBy(Math.exp(-delta * (event.ctrlKey ? 0.01 : 0.0015)), event.clientX - rect.left - rect.width / 2, event.clientY - rect.top - rect.height / 2);
            };

            node.addEventListener('wheel', onWheel, { passive: false });

            return () => node.removeEventListener('wheel', onWheel);
        }, [zoomBy]);

        useEffect(() => {
            const onKey = (event) => {
                if (shortcutsBlocked(event)) {
                    return;
                }

                if (event.key === '+' || event.key === '=') {
                    event.preventDefault();
                    zoomBy(1.25);
                } else if (event.key === '-' || event.key === '_') {
                    event.preventDefault();
                    zoomBy(0.8);
                } else if (event.key === '0') {
                    event.preventDefault();
                    setView({ z: 1, x: 0, y: 0 });
                }
            };

            document.addEventListener('keydown', onKey);

            return () => document.removeEventListener('keydown', onKey);
        }, [zoomBy]);

        const toNorm = (event, force) => {
            const node = canvasRef.current;
            const rect = node ? node.getBoundingClientRect() : null;

            if (!rect || !rect.width || !rect.height) {
                return null;
            }

            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            if (!force && (x < 0 || x > 1 || y < 0 || y > 1)) {
                return null;
            }

            return { x: clamp(x, 0, 1), y: clamp(y, 0, 1) };
        };

        const centerOffset = (point) => {
            const rect = viewportRef.current.getBoundingClientRect();

            return { x: point.x - rect.left - rect.width / 2, y: point.y - rect.top - rect.height / 2 };
        };

        const startPinch = () => {
            const list = Array.from(pointers.current.values());
            const mid = centerOffset({ x: (list[0].x + list[1].x) / 2, y: (list[0].y + list[1].y) / 2 });

            gesture.current = {
                type: 'pinch',
                dist: Math.max(1, Math.hypot(list[0].x - list[1].x, list[0].y - list[1].y)),
                z: view.z,
                mx: mid.x,
                my: mid.y,
                ox: view.x,
                oy: view.y,
            };
        };

        const startPan = (pointerId, point) => {
            gesture.current = { type: 'pan', id: pointerId, sx: point.x, sy: point.y, ox: view.x, oy: view.y };
        };

        const rectFrom = (a, b) => ({ shape: 'rect', x: Math.min(a.x, b.x), y: Math.min(a.y, b.y), w: Math.abs(a.x - b.x), h: Math.abs(a.y - b.y) });

        const onPointerDown = (event) => {
            if ((event.pointerType === 'mouse' && event.button !== 0) || isStageUi(event.target) || broken) {
                return;
            }

            try {
                event.currentTarget.setPointerCapture(event.pointerId);
            } catch (error) {
                // Yakalama olmadan da calisir.
            }

            pointers.current.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.current.size === 2) {
                // Iki parmak her kipte yakinlastirir / kaydirir; yarim kalan cizim birakilir.
                setDrawing(null);
                startPinch();

                return;
            }

            if (pointers.current.size > 2) {
                return;
            }

            const start = props.drawMode ? toNorm(event, false) : null;

            if (start) {
                if (props.draft) {
                    props.onDraft(null);
                }

                gesture.current = { type: 'draw', id: event.pointerId, start, sx: event.clientX, sy: event.clientY };
                setDrawing(props.drawMode === 'rect' ? { shape: 'rect', x: start.x, y: start.y, w: 0, h: 0 } : { shape: 'point', x: start.x, y: start.y });
            } else {
                startPan(event.pointerId, { x: event.clientX, y: event.clientY });
            }
        };

        const onPointerMove = (event) => {
            if (!pointers.current.has(event.pointerId)) {
                return;
            }

            pointers.current.set(event.pointerId, { x: event.clientX, y: event.clientY });

            const state = gesture.current;

            if (!state) {
                return;
            }

            if (state.type === 'pinch' && pointers.current.size >= 2) {
                const list = Array.from(pointers.current.values());
                const dist = Math.max(1, Math.hypot(list[0].x - list[1].x, list[0].y - list[1].y));
                const mid = centerOffset({ x: (list[0].x + list[1].x) / 2, y: (list[0].y + list[1].y) / 2 });
                const z = clamp(state.z * (dist / state.dist), MIN_ZOOM, MAX_ZOOM);
                const ratio = z / state.z;

                setView(clampView({ z, x: mid.x - (state.mx - state.ox) * ratio, y: mid.y - (state.my - state.oy) * ratio }));
            } else if (state.type === 'pan' && state.id === event.pointerId) {
                setView((current) => clampView({ z: current.z, x: state.ox + event.clientX - state.sx, y: state.oy + event.clientY - state.sy }));
            } else if (state.type === 'draw' && state.id === event.pointerId) {
                const point = toNorm(event, true);

                if (point) {
                    setDrawing(props.drawMode === 'rect' ? rectFrom(state.start, point) : { shape: 'point', x: point.x, y: point.y });
                }
            }
        };

        const onPointerUp = (event) => {
            if (!pointers.current.has(event.pointerId)) {
                return;
            }

            pointers.current.delete(event.pointerId);

            const state = gesture.current;

            if (state && state.type === 'draw' && state.id === event.pointerId) {
                gesture.current = null;
                setDrawing(null);

                if (event.type === 'pointercancel') {
                    return;
                }

                const end = toNorm(event, true) || state.start;
                const moved = Math.hypot(event.clientX - state.sx, event.clientY - state.sy);
                let anchor = null;

                if (props.drawMode === 'rect' && moved >= 8) {
                    // En kucuk alan kutunun %2'sidir; daha kucugu sinira kadar buyutulur.
                    const raw = rectFrom(state.start, end);
                    const w = Math.max(raw.w, MIN_RECT);
                    const hh = Math.max(raw.h, MIN_RECT);

                    anchor = { shape: 'rect', x: round6(clamp(raw.x, 0, 1 - w)), y: round6(clamp(raw.y, 0, 1 - hh)), w: round6(w), h: round6(hh) };
                } else {
                    // Dokunma = nokta.
                    const at = props.drawMode === 'rect' ? state.start : end;

                    anchor = { shape: 'point', x: round6(at.x), y: round6(at.y), w: null, h: null };
                }

                props.onDraft(anchor);

                return;
            }

            if (state && state.type === 'pinch') {
                const rest = Array.from(pointers.current.entries());

                if (rest.length === 1) {
                    startPan(rest[0][0], rest[0][1]);
                } else if (rest.length === 0) {
                    gesture.current = null;
                }

                return;
            }

            if (state && state.id === event.pointerId) {
                gesture.current = null;
            }
        };

        const onDoubleClick = (event) => {
            if (isStageUi(event.target) || props.drawMode || props.crop) {
                return;
            }

            const offset = centerOffset({ x: event.clientX, y: event.clientY });

            animate(() => {
                if (view.z > 1.05) {
                    setView({ z: 1, x: 0, y: 0 });
                } else {
                    zoomBy(2.5, offset.x, offset.y);
                }
            });
        };

        let draftStyle = null;

        if (props.draft && box.w > 0) {
            const a = props.draft;
            const cw = baseW * view.z;
            const ch = baseH * view.z;
            const left0 = box.w / 2 + view.x - cw / 2;
            const top0 = box.h / 2 + view.y - ch / 2;
            const ax = left0 + (a.x + (a.w || 0) / 2) * cw;
            const top1 = top0 + a.y * ch;
            const bottom1 = top0 + (a.y + (a.h || 0)) * ch;
            const width = Math.min(320, Math.max(200, box.w - 16));
            const height = 220;
            let top = bottom1 + 18;

            if (top + height > box.h - 8) {
                top = top1 - height - 18;
            }

            if (top < 8) {
                top = clamp(bottom1 + 18, 8, Math.max(8, box.h - height - 8));
            }

            draftStyle = { left: clamp(ax - width / 2, 8, Math.max(8, box.w - width - 8)) + 'px', top: top + 'px', width: width + 'px' };
        }

        const ready = box.w > 0 && baseW > 0;

        return h('div', {
            ref: viewportRef,
            className: cx('ks-detail-viewport', props.drawMode && 'is-marking', view.z > 1.01 && 'is-zoomed', props.crop && 'is-cropping'),
            onPointerDown,
            onPointerMove,
            onPointerUp,
            onPointerCancel: onPointerUp,
            onDoubleClick,
        },
            broken || !src
                ? h('div', { className: 'ks-detail-stage-msg', 'data-ks-stage-ui': '' },
                    h(Icon, { name: 'image', size: 40 }),
                    h('p', null, t('detail_image_failed')),
                    item.download_url ? h(Button, { variant: 'soft', size: 'sm', icon: 'download', href: item.download_url, download: item.name || true }, t('download')) : null)
                : h('div', {
                    ref: canvasRef,
                    className: cx('ks-detail-canvas', smooth && 'is-smooth', props.crop && 'ks-detail-canvas--crop'),
                    style: {
                        width: baseW + 'px',
                        height: baseH + 'px',
                        visibility: ready ? 'visible' : 'hidden',
                        transform: 'translate(-50%, -50%) translate(' + view.x + 'px, ' + view.y + 'px) scale(' + view.z + ')',
                        '--ks-detail-inv': String(1 / view.z),
                    },
                },
                    h('img', {
                        className: 'ks-detail-canvas__img',
                        src,
                        alt: props.alt || '',
                        draggable: false,
                        decoding: 'async',
                        onLoad: (event) => {
                            setLoaded(true);

                            if (!(natural.w > 0 && natural.h > 0) && event.target.naturalWidth > 0) {
                                setNatural({ w: event.target.naturalWidth, h: event.target.naturalHeight });
                            }
                        },
                        onError: () => setBroken(true),
                    }),
                    (props.marks || []).map((mark) => h(MarkShape, { key: mark.id, mark, active: props.highlightId === mark.id, onHover: props.onMarkHover, onSelect: props.onMarkSelect })),
                    drawing ? h(DraftShape, { anchor: drawing }) : null,
                    !drawing && props.draft ? h(DraftShape, { anchor: props.draft }) : null,
                    props.crop ? h(CropFrame, { box: props.crop.box, ratio: props.crop.ratio, canvasRef, onChange: props.onCropChange }) : null,
                ),
            !loaded && !broken && src ? h('div', { className: 'ks-detail-viewport__loading' }, h(Spinner, { size: 'lg' })) : null,
            props.hasPrev ? h(IconButton, { icon: 'chevron-left', label: t('detail_prev_media'), variant: 'inverse', size: 'lg', className: 'ks-detail-arrow ks-detail-arrow--prev', 'data-ks-stage-ui': '', onClick: props.onPrev }) : null,
            props.hasNext ? h(IconButton, { icon: 'chevron-right', label: t('detail_next_media'), variant: 'inverse', size: 'lg', className: 'ks-detail-arrow ks-detail-arrow--next', 'data-ks-stage-ui': '', onClick: props.onNext }) : null,
            broken ? null : h('div', { className: 'ks-detail-zoom', 'data-ks-stage-ui': '', role: 'group', 'aria-label': t('detail_zoom_controls') },
                h(IconButton, { icon: 'zoom-out', label: t('detail_zoom_out'), variant: 'inverse', size: 'sm', disabled: view.z <= MIN_ZOOM + 0.001, onClick: () => animate(() => zoomBy(0.8)) }),
                h('span', { className: 'ks-detail-zoom__value', 'aria-live': 'polite' }, fmt.percent(view.z, 0)),
                h(IconButton, { icon: 'zoom-in', label: t('detail_zoom_in'), variant: 'inverse', size: 'sm', disabled: view.z >= MAX_ZOOM - 0.001, onClick: () => animate(() => zoomBy(1.25)) }),
                h('span', { className: 'ks-detail-zoom__sep', 'aria-hidden': 'true' }),
                h('button', { type: 'button', className: 'ks-detail-zoom__text', disabled: view.z <= MIN_ZOOM + 0.001 && view.x === 0 && view.y === 0, onClick: () => animate(() => setView({ z: 1, x: 0, y: 0 })) }, t('detail_zoom_fit')),
                h('button', { type: 'button', className: 'ks-detail-zoom__text', disabled: zoom100 <= 1.02, title: t('detail_zoom_actual_hint'), onClick: () => animate(() => setView((current) => clampView({ z: zoom100, x: current.x * (zoom100 / current.z), y: current.y * (zoom100 / current.z) }))) }, t('detail_zoom_actual')),
            ),
            props.draft && draftStyle ? h(MarkComposer, { key: 'draft', style: draftStyle, shape: props.draft.shape, busy: props.draftBusy, max: props.commentMax, onSubmit: props.onDraftSubmit, onCancel: () => props.onDraft(null) }) : null,
        );
    }

    /* ================================================================== */
    /* 4. Video                                                            */
    /* ================================================================== */

    /** Yuklenen video: tarayici oynatamazsa mesaj + indirme (H7). */
    function VideoStage(props) {
        const item = props.item;
        const [failed, setFailed] = useState(false);
        const src = KS.safeUrl(item.url);
        const poster = KS.safeUrl(item.poster_url);

        if (!src || failed) {
            return h('div', { className: 'ks-detail-stage-msg' },
                h(Icon, { name: 'video', size: 40 }),
                h('p', null, t('video_unplayable')),
                item.download_url ? h(Button, { variant: 'soft', size: 'sm', icon: 'download', href: item.download_url, download: item.name || true }, t('download')) : null,
            );
        }

        return h('div', { className: 'ks-detail-video' },
            h('video', {
                className: 'ks-detail-video__player',
                src,
                poster: poster || undefined,
                controls: true,
                playsInline: true,
                preload: 'metadata',
                'aria-label': props.alt || t('detail_video_label'),
                onError: () => setFailed(true),
            }),
        );
    }

    /** video_url: YouTube / Vimeo gomulu oynatici; diger adresler baglanti karti. */
    function ExternalVideo(props) {
        const address = KS.safeUrl(props.url);
        const embed = embedUrl(props.url);

        if (!address) {
            return null;
        }

        if (embed) {
            return h('div', { className: 'ks-detail-embed' },
                h('div', { className: 'ks-detail-embed__frame' },
                    h('iframe', {
                        src: embed,
                        title: props.title || t('detail_video_label'),
                        loading: 'lazy',
                        allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen',
                        allowFullScreen: true,
                        referrerPolicy: 'strict-origin-when-cross-origin',
                    })),
                h('p', { className: 'ks-detail-embed__link' }, h(ExternalLink, { href: address }, h(Icon, { name: 'external' }), ' ', t('detail_video_open_source'))),
            );
        }

        return h('div', { className: 'ks-detail-linkcard' },
            h('span', { className: 'ks-detail-linkcard__icon', 'aria-hidden': 'true' }, h(Icon, { name: 'link', size: 28 })),
            h('p', { className: 'ks-detail-linkcard__title' }, t('detail_video_link_title')),
            h('p', { className: 'ks-detail-linkcard__host' }, hostOf(address)),
            h('p', { className: 'ks-detail-linkcard__text' }, t('detail_video_link_text')),
            h(Button, { variant: 'primary', icon: 'external', href: address, target: '_blank' }, t('detail_video_open_link')),
        );
    }

    /* ================================================================== */
    /* 5. Metin turleri: okuma gorunumu ve yerinde duzenleme               */
    /* ================================================================== */

    /** Hesabin gorunen kimligi: yonetici hesabinda kisinin fotografi, kurumsal hesapta logo. */
    function profileFace(profile, detail) {
        const name = (profile && profile.name) || detail.profile_name || '';
        const owner = profile && profile.owner ? profile.owner : null;

        return {
            name,
            photo: owner && owner.photo ? owner.photo : (profile && profile.kind === 'corporate' ? KS.config.logo || null : null),
            initials: owner && owner.initials ? owner.initials : undefined,
        };
    }

    function handleOf(profile, platform) {
        const link = ((profile && profile.links) || []).find((item) => item.platform === platform);
        const handle = link && link.handle ? String(link.handle).trim() : '';

        if (!handle) {
            return '';
        }

        return handle.charAt(0) === '@' ? handle : '@' + handle;
    }

    /** X benzeri gonderi karti (kisa metin). */
    function ShortTextView(props) {
        const detail = props.detail;
        const limits = KS.useLimits();
        const soft = Number(limits.short_text_soft) || 280;
        const text = detail.body_text || '';
        const face = profileFace(props.profile, detail);
        const handle = handleOf(props.profile, 'x');

        return h('article', { className: 'ks-detail-post ks-detail-post--x' },
            h('header', { className: 'ks-detail-post__head' },
                h(Avatar, { person: face, size: 'lg', title: false }),
                h('div', { className: 'ks-detail-post__who' },
                    h('span', { className: 'ks-detail-post__name' }, face.name),
                    h('span', { className: 'ks-detail-post__meta' }, handle || (props.profile && props.profile.kind_label) || ''),
                ),
                h(PlatformIcon, { platform: 'x', variant: 'badge' }),
            ),
            text.trim() !== ''
                ? h('div', { className: 'ks-detail-post__text' }, renderPlainText(text))
                : h('p', { className: 'ks-detail-post__empty' }, t('detail_text_empty')),
            h('footer', { className: 'ks-detail-post__foot' },
                h('span', null, detail.planned_on ? fmt.planned(detail.planned_on, detail.planned_time) : fmt.dateTime(detail.created_at)),
                h('span', { className: cx('ks-detail-post__count', text.length > soft && 'is-over') },
                    text.length > soft
                        ? t('detail_chars_over', { count: fmt.number(text.length), max: fmt.number(soft) })
                        : t('detail_chars_of', { count: fmt.number(text.length), max: fmt.number(soft) })),
            ),
        );
    }

    /** LinkedIn benzeri gonderi karti (uzun metin). */
    function LongTextView(props) {
        const detail = props.detail;
        const face = profileFace(props.profile, detail);
        const html = detail.body_html || '';

        return h('article', { className: 'ks-detail-post ks-detail-post--linkedin' },
            h('header', { className: 'ks-detail-post__head' },
                h(Avatar, { person: face, size: 'lg', title: false }),
                h('div', { className: 'ks-detail-post__who' },
                    h('span', { className: 'ks-detail-post__name' }, face.name),
                    h('span', { className: 'ks-detail-post__meta' }, (props.profile && props.profile.kind_label) || detail.content_type_label),
                    h('span', { className: 'ks-detail-post__meta' }, detail.planned_on ? fmt.planned(detail.planned_on, detail.planned_time) : fmt.dateTime(detail.created_at)),
                ),
                h(PlatformIcon, { platform: 'linkedin', variant: 'badge' }),
            ),
            html.trim() !== ''
                ? h(RichBody, { html, className: 'ks-detail-post__rich' })
                : h('p', { className: 'ks-detail-post__empty' }, t('detail_text_empty')),
        );
    }

    /** Blog yazisi: makale duzeni. */
    function BlogView(props) {
        const detail = props.detail;
        const html = detail.body_html || '';

        return h('article', { className: 'ks-detail-article' },
            h('p', { className: 'ks-eyebrow' }, detail.category && detail.category.name ? detail.category.name : detail.content_type_label),
            h('h1', { className: 'ks-detail-article__title' }, detail.title),
            h('div', { className: 'ks-detail-article__byline' },
                h(Avatar, { person: detail.creator, size: 'sm', title: false }),
                h('span', null, fmt.personName(detail.creator)),
                h('span', { 'aria-hidden': 'true' }, '·'),
                h('span', null, detail.planned_on ? fmt.date(detail.planned_on) : fmt.date(detail.created_at)),
            ),
            detail.caption ? h('p', { className: 'ks-detail-article__lead' }, detail.caption) : null,
            html.trim() !== ''
                ? h(RichBody, { html, className: 'ks-detail-article__body' })
                : h('p', { className: 'ks-detail-post__empty' }, t('detail_text_empty')),
        );
    }

    /**
     * Yerinde metin duzenleyici. Kayitta HER ZAMAN guncel row_version gonderilir
     * (applyContent ile gelen); 409'da yazilan metin korunur, "Yeniden yukle" ve
     * "Metnimi kopyala" sunulur (H2).
     */
    function TextEditor(props) {
        const detail = props.detail;
        const rich = isRichType(detail.content_type);
        const limits = KS.useLimits();
        const initial = useRef({ title: detail.title || '', body: rich ? (detail.body_html || '') : (detail.body_text || '') });
        const [title, setTitle] = useState(initial.current.title);
        const [body, setBody] = useState(initial.current.body);
        const [saving, setSaving] = useState(false);
        const [stale, setStale] = useState(null);
        const [reloading, setReloading] = useState(false);
        const [titleError, setTitleError] = useState(null);
        const versionRef = useRef(detail.row_version);
        const alive = useRef(true);
        const RichEditor = KS.RichEditor;
        const dirty = title !== initial.current.title || body !== initial.current.body;

        versionRef.current = detail.row_version;

        useEffect(() => {
            alive.current = true;

            return () => {
                alive.current = false;
            };
        }, []);

        useEffect(() => {
            props.onDirty(dirty);
        }, [dirty]);

        const uploadImage = (file) => {
            const form = new FormData();

            form.append('file', file);
            form.append('inline', '1');

            return api.upload(url('media.store', detail.id), form).then((payload) => {
                if (payload && payload.content) {
                    KS.actions.applyContent(payload.content, { silent: true });
                }

                const media = (payload && payload.media) || {};
                const address = KS.safeUrl(media.preview_url) || KS.safeUrl(media.url);

                if (!address) {
                    throw new Error(t('error_generic'));
                }

                return address;
            }).catch((error) => {
                KS.handleError(error);

                throw error;
            });
        };

        const save = () => {
            if (saving) {
                return;
            }

            if (title.trim() === '') {
                setTitleError(t('required_field'));

                return;
            }

            const payload = { row_version: versionRef.current, title: title.trim() };

            payload[rich ? 'body_html' : 'body_text'] = body;
            setSaving(true);

            api.post(url('contents.update', detail.id), payload).then((result) => {
                if (result && result.content) {
                    KS.actions.applyContent(result.content);
                }

                KS.actions.refreshFeed();
                KS.toast.success(t('saved'));

                if (alive.current) {
                    setSaving(false);
                    props.onDone();
                }
            }).catch((error) => {
                if (!alive.current) {
                    return;
                }

                setSaving(false);

                if (Number(error && error.status) === 409) {
                    // Metin yerinde kalir; kullanici kopyalayabilir ya da guncel surumu yukleyip yeniden kaydedebilir.
                    setStale('conflict');
                } else {
                    if (error && error.errors && error.errors.title) {
                        setTitleError(String([].concat(error.errors.title)[0]));
                    }

                    KS.handleError(error);
                }
            });
        };

        const reloadLatest = () => {
            setReloading(true);

            KS.actions.fetchContent(detail.id).then(() => {
                if (alive.current) {
                    setReloading(false);
                    setStale('reloaded');
                }
            }).catch((error) => {
                if (alive.current) {
                    setReloading(false);
                }

                KS.handleError(error);
            });
        };

        const cancel = () => {
            if (!dirty) {
                props.onDone();

                return;
            }

            confirmDiscard().then((ok) => {
                if (ok) {
                    props.onDone();
                }
            });
        };

        let control = null;

        if (rich && typeof RichEditor === 'function') {
            control = h(RichEditor, {
                value: body,
                onChange: setBody,
                placeholder: t('detail_body_placeholder'),
                onUploadImage: uploadImage,
                minHeight: 360,
                mode: detail.content_type === 'blog' ? 'blog' : 'article',
                disabled: saving,
            });
        } else if (rich) {
            control = h(Notice, { tone: 'warning', title: t('detail_editor_missing'), action: h(Button, { size: 'sm', variant: 'soft', icon: 'edit', onClick: () => { props.onDone(); KS.actions.openComposer(detail); } }, t('edit')) });
        } else {
            control = h(TextArea, {
                value: body,
                onChange: setBody,
                rows: 8,
                autoGrow: true,
                maxRows: 24,
                maxLength: Number(limits.short_text_hard) || 25000,
                softLimit: Number(limits.short_text_soft) || 280,
                counter: true,
                placeholder: t('detail_body_placeholder'),
                disabled: saving,
            });
        }

        return h('div', { className: 'ks-detail-editor' },
            stale === 'conflict' ? h(Notice, {
                tone: 'warning',
                title: t('detail_stale_title'),
                action: h('div', { className: 'ks-row ks-row--wrap' },
                    h(Button, { size: 'sm', variant: 'soft', icon: 'copy', onClick: () => copyWithToast(rich ? KS.htmlToPlainText(body) : body) }, t('detail_copy_my_text')),
                    h(Button, { size: 'sm', variant: 'primary', icon: 'refresh', loading: reloading, onClick: reloadLatest }, t('reload')),
                ),
            }, t('detail_stale_text')) : null,
            stale === 'reloaded' ? h(Notice, { tone: 'info', title: t('detail_stale_reloaded_title') }, t('detail_stale_reloaded_text')) : null,
            h(Field, { label: t('detail_field_title'), required: true, error: titleError },
                h(TextInput, {
                    value: title,
                    maxLength: 200,
                    disabled: saving,
                    onChange: (next) => {
                        setTitle(next);

                        if (titleError) {
                            setTitleError(null);
                        }
                    },
                })),
            h(Field, { label: t('detail_field_body'), hint: rich ? null : t('detail_short_text_hint', { max: fmt.number(Number(limits.short_text_soft) || 280) }) }, control),
            h('div', { className: 'ks-detail-editor__foot' },
                h(Button, { variant: 'ghost', onClick: cancel, disabled: saving }, t('cancel')),
                h(Button, { variant: 'primary', icon: 'save', loading: saving, disabled: !dirty && stale !== 'reloaded', onClick: save }, saving ? t('saving') : t('save')),
            ),
        );
    }

    /** Metin sahnesi: okuma gorunumu + "Metni duzenle". */
    function TextStage(props) {
        const detail = props.detail;
        const abilities = detail.abilities || {};
        let view = null;

        if (props.editing) {
            return h('div', { className: 'ks-detail-text' },
                h('div', { className: 'ks-detail-text__inner' },
                    h(TextEditor, { detail, onDone: props.onEditEnd, onDirty: props.onDirty })));
        }

        if (detail.content_type === 'short_text') {
            view = h(ShortTextView, { detail, profile: props.profile });
        } else if (detail.content_type === 'long_text') {
            view = h(LongTextView, { detail, profile: props.profile });
        } else {
            view = h(BlogView, { detail });
        }

        return h('div', { className: 'ks-detail-text' },
            h('div', { className: 'ks-detail-text__inner' },
                h('div', { className: 'ks-detail-text__bar' },
                    h(Badge, { color: 'stone', icon: detail.content_type === 'blog' ? 'blog' : 'text' }, detail.content_type_label),
                    h('span', { className: 'ks-spacer' }),
                    abilities.update
                        ? h(Button, { size: 'sm', variant: 'soft', icon: 'edit', onClick: props.onEditStart }, t('detail_edit_text'))
                        : null,
                ),
                view,
            ),
        );
    }

    /* ================================================================== */
    /* 6. Yan panel: tepkiler, is akisi, sekmeler                          */
    /* ================================================================== */

    /** Begen / begenme dugmeleri (aria-pressed) + "A, B ve 2 kisi begendi" satiri. */
    function ReactionBar(props) {
        const detail = props.detail;
        const abilities = detail.abilities || {};
        const mine = detail.my_reaction || 'none';
        const likes = detail.likes || [];
        const dislikes = detail.dislikes || [];
        const liked = namesText(likes);
        const disliked = namesText(dislikes);
        const locked = !abilities.react || !!props.busy;

        const line = (info, plainKey, moreKey) => {
            if (!info.names) {
                return null;
            }

            return info.rest > 0 ? t(moreKey, { names: info.names, n: info.rest }) : t(plainKey, { names: info.names });
        };

        const likeLine = line(liked, 'detail_liked_by', 'detail_liked_by_more');
        const dislikeLine = line(disliked, 'detail_disliked_by', 'detail_disliked_by_more');

        return h('div', { className: 'ks-detail-react' },
            h('div', { className: 'ks-detail-react__buttons' },
                h(Button, {
                    variant: 'ghost',
                    icon: mine === 'like' ? 'heart-solid' : 'heart',
                    pressed: mine === 'like',
                    className: cx('ks-detail-react__btn', mine === 'like' && 'is-like'),
                    ariaLabel: t('detail_like'),
                    title: t('detail_like'),
                    loading: props.busy === 'react:like',
                    disabled: locked,
                    onClick: () => props.onReact('like'),
                }, fmt.number(detail.like_count || 0)),
                h(Button, {
                    variant: 'ghost',
                    icon: mine === 'dislike' ? 'thumb-down-solid' : 'thumb-down',
                    pressed: mine === 'dislike',
                    className: cx('ks-detail-react__btn', mine === 'dislike' && 'is-dislike'),
                    ariaLabel: t('detail_dislike'),
                    title: t('detail_dislike'),
                    loading: props.busy === 'react:dislike',
                    disabled: locked,
                    onClick: () => props.onReact('dislike'),
                }, fmt.number(detail.dislike_count || 0)),
                h(Button, {
                    variant: 'ghost',
                    icon: 'comment',
                    className: 'ks-detail-react__btn',
                    ariaLabel: t('detail_tab_comments'),
                    title: t('detail_tab_comments'),
                    onClick: props.onShowComments,
                }, fmt.number(detail.comment_count || 0)),
            ),
            likeLine || dislikeLine ? h('button', { type: 'button', className: 'ks-detail-react__names', onClick: props.onShowLikes, title: t('detail_show_reactions') },
                likeLine ? h('span', { className: 'ks-detail-react__line' }, h(Icon, { name: 'heart-solid' }), h('span', null, likeLine)) : null,
                dislikeLine ? h('span', { className: 'ks-detail-react__line ks-detail-react__line--dislike' }, h(Icon, { name: 'thumb-down' }), h('span', null, dislikeLine)) : null,
            ) : null,
        );
    }

    /** Durum dugmeleri + paylasim + acil onay. */
    function WorkflowBar(props) {
        const detail = props.detail;
        const abilities = detail.abilities || {};
        const urgent = detail.urgent || {};
        const busy = props.busy;
        const items = [];

        (detail.allowed_statuses || []).forEach((item) => {
            const spec = STATUS_ACTIONS[item.value];

            if (spec) {
                items.push(h(Button, {
                    key: 'status-' + item.value,
                    size: 'sm',
                    variant: spec.variant,
                    icon: spec.icon,
                    loading: busy === 'status:' + item.value,
                    disabled: !!busy,
                    onClick: () => props.onStatus(item.value),
                }, t(spec.label)));
            }
        });

        if (abilities.publish && !detail.is_published && detail.status === 'approved') {
            items.unshift(h(Button, { key: 'publish', size: 'sm', variant: 'primary', icon: 'share', disabled: !!busy, onClick: props.onPublish }, t('detail_mark_published')));
        }

        if (abilities.unpublish && detail.is_published) {
            items.push(h(Button, { key: 'unpublish', size: 'sm', variant: 'ghost', icon: 'link-off', loading: busy === 'unpublish', disabled: !!busy, onClick: props.onUnpublish }, t('detail_unpublish')));
        }

        const showUrgent = abilities.request_urgent && !detail.is_published && (urgent.allowed || urgent.reason);

        if (showUrgent) {
            items.push(h(Button, {
                key: 'urgent',
                size: 'sm',
                variant: 'soft',
                icon: 'bolt',
                className: 'ks-detail-urgent',
                loading: busy === 'urgent',
                disabled: !!busy || !urgent.allowed,
                title: urgent.allowed ? t('detail_urgent_hint') : urgent.reason,
                onClick: props.onUrgent,
            }, t('detail_request_urgent')));
        }

        const hints = [];

        if (showUrgent && !urgent.allowed && urgent.reason) {
            hints.push(h('p', { key: 'urgent-reason', className: 'ks-detail-flow__hint' }, h(Icon, { name: 'info' }), h('span', null, urgent.reason)));
        }

        if (urgent.requested_at && !detail.is_published) {
            hints.push(h('p', { key: 'urgent-at', className: 'ks-detail-flow__hint ks-detail-flow__hint--urgent' }, h(Icon, { name: 'bolt' }), h('span', null, t('detail_urgent_requested_at', { date: fmt.dateTime(urgent.requested_at) }))));
        }

        if (detail.is_published && detail.status !== 'archived') {
            hints.push(h('p', { key: 'lock', className: 'ks-detail-flow__hint' }, h(Icon, { name: 'lock' }), h('span', null, t('unpublish_to_edit'))));
        }

        if (detail.decision_note && (detail.status === 'rejected' || detail.status === 'revision_requested')) {
            hints.push(h('div', { key: 'note', className: cx('ks-detail-flow__note', 'ks-c-' + KS.statusColor(detail.status)) },
                h('p', { className: 'ks-detail-flow__note-title' }, t(detail.status === 'rejected' ? 'detail_reject_reason' : 'detail_revision_request')),
                h('p', { className: 'ks-detail-flow__note-text' }, detail.decision_note),
                detail.decided_by ? h('p', { className: 'ks-detail-flow__note-meta' }, fmt.personName(detail.decided_by) + (detail.decided_at ? ' · ' + fmt.dateTime(detail.decided_at) : '')) : null,
            ));
        }

        if (!items.length && !hints.length) {
            return null;
        }

        return h('div', { className: 'ks-detail-flow' },
            items.length ? h('div', { className: 'ks-detail-flow__buttons' }, items) : null,
            hints,
        );
    }

    function MetaRow(props) {
        if (props.value === null || props.value === undefined || props.value === '' || props.value === false) {
            return null;
        }

        return h(Fragment, null,
            h('dt', { className: 'ks-meta__label' }, props.label),
            h('dd', { className: 'ks-meta__value' }, props.value),
        );
    }

    /** Bilgi sekmesi. */
    function InfoTab(props) {
        const detail = props.detail;
        const textual = isTextType(detail.content_type);
        const links = (detail.platforms || []).filter((item) => KS.safeUrl(item.published_url));
        const person = (who, at) => (who ? h(PersonLine, { person: who, meta: at ? fmt.dateTime(at) : undefined }) : null);

        return h('div', { className: 'ks-detail-info' },
            !textual ? h('section', { className: 'ks-detail-caption' },
                h('div', { className: 'ks-detail-caption__head' },
                    h('h3', { className: 'ks-detail-h' }, t('detail_caption')),
                    detail.caption ? h(Button, { size: 'sm', variant: 'ghost', icon: 'copy', onClick: () => copyWithToast(detail.caption) }, t('detail_copy_text')) : null,
                ),
                detail.caption
                    ? h('p', { className: 'ks-detail-caption__text' }, renderPlainText(detail.caption))
                    : h('p', { className: 'ks-muted' }, t('detail_caption_empty')),
            ) : null,
            h('dl', { className: 'ks-meta' },
                h(MetaRow, { label: t('detail_info_profile'), value: detail.profile_name || (props.profile && props.profile.name) }),
                h(MetaRow, { label: t('detail_info_type'), value: detail.content_type_label }),
                h(MetaRow, { label: t('detail_info_platforms'), value: h(PlatformList, { platforms: detail.platforms }) }),
                h(MetaRow, { label: t('detail_info_planned'), value: detail.planned_on ? h(PlanChip, { detail }) : h('span', { className: 'ks-muted' }, t('detail_not_planned')) }),
                h(MetaRow, { label: t('detail_info_category'), value: detail.category ? h(Badge, { color: detail.category.color }, detail.category.name) : null }),
                h(MetaRow, { label: t('detail_info_format'), value: detail.image_format_label }),
                h(MetaRow, { label: t('status'), value: h('span', { className: 'ks-row ks-row--wrap' },
                    h(StatusBadge, { status: detail.status, label: detail.status_label, color: detail.status_color }),
                    detail.is_published ? h(Badge, { color: 'emerald', icon: 'check-circle' }, t('detail_published')) : null) }),
                h(MetaRow, { label: t('detail_info_before_archive'), value: detail.status === 'archived' ? detail.status_before_archive_label : null }),
                h(MetaRow, { label: t('detail_info_creator'), value: h(PersonLine, { person: detail.creator, meta: fmt.dateTime(detail.created_at) }) }),
                h(MetaRow, { label: t('detail_info_decided_by'), value: person(detail.decided_by, detail.decided_at) }),
                h(MetaRow, { label: t('detail_info_decision_note'), value: detail.decision_note ? h('span', { className: 'ks-detail-quote' }, detail.decision_note) : null }),
                h(MetaRow, { label: t('detail_info_published_by'), value: detail.is_published ? (person(detail.published_by, detail.published_at) || fmt.dateTime(detail.published_at)) : null }),
                h(MetaRow, { label: t('detail_info_publish_note'), value: detail.is_published && detail.publish_note ? h('span', { className: 'ks-detail-quote' }, detail.publish_note) : null }),
                h(MetaRow, { label: t('detail_info_links'), value: detail.is_published && links.length ? h('span', { className: 'ks-detail-links' }, links.map((item) => h(ExternalLink, { key: item.platform, href: item.published_url, className: 'ks-detail-links__item' },
                    h(PlatformIcon, { platform: item.platform }), h('span', null, item.platform_label), h(Icon, { name: 'external' })))) : null }),
                h(MetaRow, { label: t('detail_info_updated'), value: detail.updated_at ? fmt.dateTime(detail.updated_at) : null }),
                h(MetaRow, { label: t('detail_info_number'), value: detail.content_no }),
            ),
        );
    }

    /** Tek yorum (kok ya da yanit). */
    function CommentItem(props) {
        const comment = props.comment;
        const number = props.markNumbers[comment.id];
        const highlighted = props.highlightId === comment.id;
        const hoverable = !!comment.anchor;

        return h('div', {
            id: props.reply ? undefined : 'ks-detail-c-' + comment.id,
            className: cx('ks-detail-comment', props.reply && 'ks-detail-comment--reply', comment.resolved && 'is-resolved', highlighted && 'is-active'),
            onMouseEnter: hoverable ? () => props.onHover(comment.id) : undefined,
            onMouseLeave: hoverable ? () => props.onHover(null) : undefined,
        },
            h(Avatar, { person: comment.author, size: props.reply ? 'xs' : 'sm', title: false }),
            h('div', { className: 'ks-detail-comment__main' },
                h('div', { className: 'ks-detail-comment__head' },
                    h('span', { className: 'ks-detail-comment__name' }, fmt.personName(comment.author)),
                    h('span', { className: 'ks-detail-comment__time', title: fmt.dateTime(comment.created_at) }, fmt.relative(comment.created_at)),
                    comment.resolved ? h(Badge, { color: 'emerald', icon: 'check', size: 'sm', title: comment.resolved_by ? fmt.personName(comment.resolved_by) + (comment.resolved_at ? ' · ' + fmt.dateTime(comment.resolved_at) : '') : undefined }, t('detail_resolved')) : null,
                ),
                comment.anchor ? h('div', { className: 'ks-detail-comment__mark' },
                    h(Chip, {
                        size: 'sm',
                        icon: comment.anchor.shape === 'rect' ? 'square' : 'pin',
                        color: comment.resolved ? 'emerald' : 'red',
                        label: t('detail_mark_chip', { n: number || '' }) + (comment.media_label ? ' · ' + comment.media_label : ''),
                        disabled: !comment.media_available,
                        title: comment.media_available ? t('detail_mark_show') : t('detail_mark_media_removed'),
                        onClick: () => props.onJump(comment),
                    })) : null,
                h('p', { className: 'ks-detail-comment__body' }, renderPlainText(comment.body)),
                props.reply ? null : h('div', { className: 'ks-detail-comment__actions' },
                    props.canComment ? h(Button, { size: 'sm', variant: 'link', icon: 'reply', onClick: () => props.onReplyToggle(comment.id) }, t('reply')) : null,
                    comment.can_resolve ? h(Button, {
                        size: 'sm',
                        variant: 'link',
                        icon: comment.resolved ? 'refresh' : 'check',
                        loading: props.busy === 'resolve:' + comment.id,
                        disabled: !!props.busy,
                        onClick: () => props.onResolve(comment, !comment.resolved),
                    }, t(comment.resolved ? 'detail_reopen' : 'detail_resolve')) : null,
                ),
                (comment.replies || []).length ? h('div', { className: 'ks-detail-comment__replies' },
                    comment.replies.map((reply) => h(CommentItem, Object.assign({}, props, { key: reply.id, comment: reply, reply: true })))) : null,
                !props.reply && props.replyTo === comment.id ? h(CommentComposer, {
                    compact: true,
                    autoFocus: true,
                    max: props.max,
                    busy: props.busy === 'comment:' + comment.id,
                    placeholder: t('detail_reply_placeholder', { name: fmt.personName(comment.author) }),
                    onCancel: () => props.onReplyToggle(null),
                    onSubmit: (body) => props.onSubmit(body, comment.id),
                }) : null,
            ),
        );
    }

    /** Yorumlar sekmesi: Acik / Tumu suzgeci, agac, satir ici yanit. */
    function CommentsTab(props) {
        const detail = props.detail;
        const abilities = detail.abilities || {};
        const comments = detail.comments || [];
        const [replyTo, setReplyTo] = useState(null);
        const openCount = comments.filter((comment) => !comment.resolved).length;
        const visible = props.filter === 'all' ? comments : comments.filter((comment) => !comment.resolved);

        return h('div', { className: 'ks-detail-comments' },
            comments.length ? h('div', { className: 'ks-detail-comments__bar' },
                h(Segmented, {
                    size: 'sm',
                    label: t('detail_filter_label'),
                    value: props.filter,
                    onChange: props.onFilter,
                    items: [
                        { value: 'open', label: t('detail_filter_open') + ' (' + fmt.number(openCount) + ')' },
                        { value: 'all', label: t('all') + ' (' + fmt.number(comments.length) + ')' },
                    ],
                }),
            ) : null,
            !comments.length ? h(Empty, { compact: true, icon: 'comment', title: t('detail_comments_empty'), text: abilities.comment ? t('detail_comments_empty_text') : null }) : null,
            comments.length && !visible.length ? h(Empty, {
                compact: true,
                icon: 'check-circle',
                title: t('detail_comments_all_resolved'),
                action: h(Button, { size: 'sm', variant: 'soft', onClick: () => props.onFilter('all') }, t('detail_show_all_comments')),
            }) : null,
            visible.map((comment) => h(CommentItem, {
                key: comment.id,
                comment,
                markNumbers: props.markNumbers,
                highlightId: props.highlightId,
                canComment: !!abilities.comment,
                busy: props.busy,
                max: props.max,
                replyTo,
                onReplyToggle: (id) => setReplyTo((current) => (current === id ? null : id)),
                onHover: props.onHover,
                onJump: props.onJump,
                onResolve: props.onResolve,
                onSubmit: (body, parentId) => props.onSubmit(body, parentId).then((ok) => {
                    if (ok) {
                        setReplyTo(null);
                    }

                    return ok;
                }),
            })),
        );
    }

    function PeopleList(props) {
        const people = props.people || [];

        return h('section', { className: 'ks-detail-people' },
            h('h3', { className: 'ks-detail-h' }, h(Icon, { name: props.icon }), h('span', null, props.title), h('span', { className: 'ks-detail-h__count' }, fmt.number(people.length))),
            people.length
                ? h('ul', { className: 'ks-detail-people__list' }, people.map((person) => h('li', { key: person.id }, h(PersonLine, { person, size: 'md' }))))
                : h('p', { className: 'ks-muted ks-text-13' }, props.empty),
        );
    }

    /** Begeniler sekmesi. */
    function LikesTab(props) {
        return h('div', { className: 'ks-detail-likes' },
            h(PeopleList, { icon: 'heart-solid', title: t('detail_likes_title'), people: props.detail.likes, empty: t('detail_likes_empty') }),
            h(PeopleList, { icon: 'thumb-down', title: t('detail_dislikes_title'), people: props.detail.dislikes, empty: t('detail_dislikes_empty') }),
        );
    }

    /** Gecmis sekmesi: hareketler, metin revizyonlari, galeriden cikarilan medya. */
    function HistoryTab(props) {
        const detail = props.detail;
        const history = detail.history || [];
        const revisions = detail.revisions || [];
        const removed = detail.removed_media || [];

        return h('div', { className: 'ks-detail-history' },
            removed.length ? h('section', null,
                h('h3', { className: 'ks-detail-h' }, h(Icon, { name: 'trash-none' }), h('span', null, t('detail_removed_media'))),
                h('ul', { className: 'ks-detail-removed' }, removed.map((item) => {
                    const thumb = KS.safeUrl(item.thumbnail_url);

                    return h('li', { key: item.id, className: 'ks-detail-removed__item' },
                        h('span', { className: 'ks-detail-removed__thumb' }, thumb ? h('img', { src: thumb, alt: '', loading: 'lazy' }) : h(Icon, { name: item.kind === 'video' ? 'video' : 'image' })),
                        h('span', { className: 'ks-detail-removed__text' },
                            h('span', { className: 'ks-strong ks-truncate' }, item.name || item.variant_label || item.kind_label),
                            h('span', { className: 'ks-muted ks-text-12' }, [item.kind_label, item.removed_at ? fmt.dateTime(item.removed_at) : null].filter(Boolean).join(' · ')),
                        ),
                        h(Button, { size: 'sm', variant: 'soft', icon: 'restore', loading: props.busy === 'restore:' + item.id, disabled: !!props.busy, onClick: () => props.onRestore(item) }, t('restore')),
                    );
                })),
            ) : null,
            revisions.length ? h('section', null,
                h('h3', { className: 'ks-detail-h' }, h(Icon, { name: 'document' }), h('span', null, t('detail_revisions'))),
                h('ul', { className: 'ks-detail-revisions' }, revisions.map((item) => h('li', { key: item.revision_no, className: 'ks-detail-revisions__item' },
                    h('span', { className: 'ks-detail-revisions__text' },
                        h('span', { className: 'ks-strong' }, t('detail_revision_n', { n: item.revision_no })),
                        h('span', { className: 'ks-muted ks-text-12' }, fmt.personName(item.author) + ' · ' + fmt.dateTime(item.created_at)),
                    ),
                    h(Button, { size: 'sm', variant: 'ghost', icon: 'eye', onClick: () => props.onViewRevision(item) }, t('view')),
                ))),
            ) : null,
            h('section', null,
                h('h3', { className: 'ks-detail-h' }, h(Icon, { name: 'history' }), h('span', null, t('detail_timeline'))),
                history.length
                    ? h('ol', { className: 'ks-detail-timeline' }, history.map((item, index) => h('li', { key: index, className: 'ks-detail-timeline__item' },
                        h('span', { className: 'ks-detail-timeline__dot', 'aria-hidden': 'true' }),
                        h('div', { className: 'ks-detail-timeline__body' },
                            h('p', { className: 'ks-detail-timeline__text' }, item.text),
                            (item.lines || []).length ? h('ul', { className: 'ks-detail-timeline__lines' }, item.lines.map((line, lineIndex) => h('li', { key: lineIndex }, line))) : null,
                            h('p', { className: 'ks-detail-timeline__meta' }, (item.actor || t('system')) + (item.at ? ' · ' + fmt.dateTime(item.at) : '')),
                        ),
                    )))
                    : h('p', { className: 'ks-muted ks-text-13' }, t('detail_history_empty')),
            ),
        );
    }

    /* ================================================================== */
    /* 7. Gorsel araclari, paylasim penceresi, revizyon penceresi          */
    /* ================================================================== */

    /**
     * Gorsel araclari paneli (abilities.update iken): bicim, cozunurluk, surumler, aciklama, sira, galeriden cikarma.
     * props: group, total, index, busy, applyAll, onApplyAll, onFormat(deger), onPreset(deger), onSelect(surum),
     *        onCaption(metin) -> Promise, onMove(-1|1), onRemove(), close()
     */
    function ImageTools(props) {
        const group = props.group;
        const versions = group.versions || [];
        const limits = KS.useLimits();
        const [caption, setCaption] = useState(group.caption || '');
        const busy = props.busy;
        const maxVersions = Number(limits.max_versions_per_media) || 0;
        const full = maxVersions > 0 && versions.length >= maxVersions;

        useEffect(() => setCaption(group.caption || ''), [group.root_id, group.caption]);

        return h('div', { className: 'ks-detail-tools' },
            h('section', { className: 'ks-detail-tools__section' },
                h('h4', { className: 'ks-detail-tools__title' }, h(Icon, { name: 'crop' }), h('span', null, t('detail_tools_format'))),
                h('div', { className: 'ks-detail-tools__grid' }, FORMAT_KEYS.map((value) => {
                    const size = formatSize(value);

                    return h('button', {
                        key: value,
                        type: 'button',
                        className: cx('ks-detail-tools__tile', group.variant === value && 'is-active'),
                        'aria-pressed': group.variant === value ? 'true' : 'false',
                        disabled: !!busy,
                        onClick: () => {
                            props.close();
                            props.onFormat(value);
                        },
                    },
                        h(Icon, { name: FORMAT_ICONS[value], size: 22 }),
                        h('span', { className: 'ks-detail-tools__tile-label' }, formatLabel(value)),
                        h('span', { className: 'ks-detail-tools__tile-hint' }, size[0] + '×' + size[1]),
                    );
                })),
                h('p', { className: 'ks-detail-tools__hint' }, t('detail_tools_format_hint')),
            ),
            h('section', { className: 'ks-detail-tools__section' },
                h('h4', { className: 'ks-detail-tools__title' }, h(Icon, { name: 'sparkles' }), h('span', null, t('detail_tools_resolution'))),
                h('div', { className: 'ks-detail-tools__row' }, PRESET_KEYS.map((value) => h(Button, {
                    key: value,
                    size: 'sm',
                    variant: 'soft',
                    loading: busy === 'preset:' + value,
                    disabled: !!busy,
                    onClick: () => props.onPreset(value),
                }, presetLabel(value)))),
                h('p', { className: 'ks-detail-tools__hint' }, t('resample_hint')),
            ),
            props.imageCount > 1 ? h(Checkbox, {
                checked: props.applyAll,
                onChange: props.onApplyAll,
                label: t('detail_apply_all'),
                hint: t('detail_apply_all_hint', { n: props.imageCount }),
            }) : null,
            full ? h(Notice, { tone: 'warning', compact: true }, t('detail_versions_full', { max: maxVersions })) : null,
            h('section', { className: 'ks-detail-tools__section' },
                h('h4', { className: 'ks-detail-tools__title' }, h(Icon, { name: 'layers' }), h('span', null, t('detail_tools_versions'))),
                h('ul', { className: 'ks-detail-tools__versions' }, versions.map((version) => {
                    const thumb = KS.safeUrl(version.thumbnail_url);
                    const selected = version.id === group.id;

                    return h('li', { key: version.id },
                        h('button', {
                            type: 'button',
                            className: cx('ks-detail-tools__version', selected && 'is-active'),
                            'aria-pressed': selected ? 'true' : 'false',
                            disabled: !!busy || selected,
                            title: selected ? t('selected') : t('detail_select_version'),
                            onClick: () => props.onSelect(version),
                        },
                            h('span', { className: 'ks-detail-tools__version-thumb' }, thumb ? h('img', { src: thumb, alt: '', loading: 'lazy' }) : h(Icon, { name: 'image' })),
                            h('span', { className: 'ks-detail-tools__version-text' },
                                h('span', { className: 'ks-strong ks-truncate' }, version.variant_label),
                                h('span', { className: 'ks-muted ks-text-12' }, version.size_human),
                            ),
                            selected
                                ? h(Badge, { color: 'emerald', size: 'sm', icon: 'check' }, t('selected'))
                                : (busy === 'select:' + version.id ? h(Spinner, { size: 'sm' }) : h('span', { className: 'ks-detail-tools__version-pick' }, t('select'))),
                        ));
                })),
            ),
            h('section', { className: 'ks-detail-tools__section' },
                h(Field, { label: t('detail_media_caption') },
                    h(TextArea, { value: caption, onChange: setCaption, rows: 2, autoGrow: true, maxRows: 5, maxLength: 300, counter: true, placeholder: t('detail_media_caption_placeholder'), disabled: !!busy })),
                h('div', { className: 'ks-detail-tools__row ks-detail-tools__row--end' },
                    h(Button, { size: 'sm', variant: 'primary', icon: 'save', loading: busy === 'caption', disabled: !!busy || caption.trim() === (group.caption || '').trim(), onClick: () => props.onCaption(caption.trim()) }, t('save'))),
            ),
            h('section', { className: 'ks-detail-tools__section' },
                h('h4', { className: 'ks-detail-tools__title' }, h(Icon, { name: 'sort' }), h('span', null, t('detail_tools_order'))),
                h('div', { className: 'ks-detail-tools__row' },
                    h(Button, { size: 'sm', variant: 'soft', icon: 'chevron-left', loading: busy === 'move:-1', disabled: !!busy || props.index <= 0, onClick: () => props.onMove(-1) }, t('detail_move_left')),
                    h(Button, { size: 'sm', variant: 'soft', iconRight: 'chevron-right', loading: busy === 'move:1', disabled: !!busy || props.index >= props.total - 1, onClick: () => props.onMove(1) }, t('detail_move_right')),
                    h('span', { className: 'ks-spacer' }),
                    h(Button, {
                        size: 'sm',
                        variant: 'danger',
                        icon: 'trash-none',
                        loading: busy === 'remove',
                        disabled: !!busy,
                        onClick: () => {
                            props.close();
                            props.onRemove();
                        },
                    }, t('detail_remove_media')),
                ),
            ),
        );
    }

    /** "Paylasildi olarak isaretle": platform basina baglanti + hesabin platform adresine "Ac" + not. */
    function PublishModal(props) {
        const detail = props.detail;
        const platforms = detail.platforms || [];
        const limits = KS.useLimits();
        const [urls, setUrls] = useState(() => {
            const initial = {};

            platforms.forEach((item) => {
                initial[item.platform] = item.published_url || '';
            });

            return initial;
        });
        const [note, setNote] = useState('');
        const [errors, setErrors] = useState({});

        const ownLink = (platform) => {
            const link = ((props.profile && props.profile.links) || []).find((item) => item.platform === platform);

            return link ? KS.safeUrl(link.url) : null;
        };

        const submit = () => {
            const clean = {};
            const problems = {};

            platforms.forEach((item) => {
                const value = String(urls[item.platform] || '').trim();

                if (value === '') {
                    return;
                }

                if (!/^https?:\/\//i.test(value) || !KS.safeUrl(value) || value.length > 500) {
                    problems[item.platform] = t('detail_publish_url_invalid');
                } else {
                    clean[item.platform] = value;
                }
            });

            setErrors(problems);

            if (Object.keys(problems).length) {
                return;
            }

            props.onSubmit({ urls: clean, note: note.trim() === '' ? null : note.trim() });
        };

        return h(Modal, {
            title: t('detail_mark_published'),
            subtitle: t('detail_publish_subtitle'),
            icon: 'share',
            size: 'md',
            onClose: props.busy ? undefined : props.onClose,
            dismissible: !props.busy,
            footer: [
                h(Button, { key: 'cancel', variant: 'ghost', disabled: !!props.busy, onClick: props.onClose }, t('cancel')),
                h(Button, { key: 'ok', variant: 'primary', icon: 'check', loading: !!props.busy, onClick: submit }, t('detail_mark_published')),
            ],
        },
            h('div', { className: 'ks-form' },
                platforms.map((item, index) => {
                    const own = ownLink(item.platform);

                    return h(Field, {
                        key: item.platform,
                        label: h('span', { className: 'ks-detail-publish__label' }, h(PlatformIcon, { platform: item.platform }), h('span', null, item.platform_label)),
                        error: errors[item.platform],
                        hint: index === 0 ? t('detail_publish_url_hint') : null,
                    },
                        h(TextInput, Object.assign({
                            type: 'url',
                            inputMode: 'url',
                            icon: 'link',
                            value: urls[item.platform] || '',
                            placeholder: 'https://',
                            maxLength: 500,
                            disabled: !!props.busy,
                            onChange: (value) => setUrls((current) => Object.assign({}, current, { [item.platform]: value })),
                            onEnter: submit,
                            trailing: own ? h(Button, { size: 'sm', variant: 'ghost', icon: 'external', href: own, target: '_blank', title: t('detail_publish_open_hint', { platform: item.platform_label }), className: 'ks-detail-publish__open' }, t('open')) : null,
                        }, index === 0 ? { 'data-autofocus': '' } : {})));
                }),
                h(Field, { label: t('note'), hint: t('detail_publish_note_hint') },
                    h(TextArea, { value: note, onChange: setNote, rows: 2, autoGrow: true, maxRows: 6, maxLength: Number(limits.note_max) || 4000, disabled: !!props.busy })),
            ),
        );
    }

    /** Metin revizyonunun (degisiklikten ONCEKI halin) goruntulenmesi. */
    function RevisionModal(props) {
        const revision = props.revision;
        const hasHtml = !!(revision.body_html && String(revision.body_html).trim() !== '');
        const hasText = !!(revision.body_text && String(revision.body_text).trim() !== '');

        return h(Modal, {
            title: t('detail_revision_n', { n: revision.revision_no }),
            subtitle: fmt.personName(revision.author) + ' · ' + fmt.dateTime(revision.created_at),
            icon: 'history',
            size: 'lg',
            onClose: props.onClose,
            footer: [
                h(Button, { key: 'copy', variant: 'ghost', icon: 'copy', onClick: () => copyWithToast(hasHtml ? KS.htmlToPlainText(revision.body_html) : (revision.body_text || revision.caption || '')) }, t('detail_copy_text')),
                h(Button, { key: 'close', variant: 'primary', onClick: props.onClose }, t('close')),
            ],
        },
            h('div', { className: 'ks-detail-revision' },
                h(Notice, { tone: 'neutral', compact: true }, t('detail_revision_hint')),
                h('h3', { className: 'ks-detail-revision__title' }, revision.title),
                revision.caption ? h('p', { className: 'ks-detail-revision__caption' }, revision.caption) : null,
                hasHtml ? h(RichBody, { html: revision.body_html, className: 'ks-detail-revision__body' }) : null,
                hasText ? h('p', { className: 'ks-detail-revision__plain' }, revision.body_text) : null,
                !hasHtml && !hasText && !revision.caption ? h('p', { className: 'ks-muted' }, t('detail_text_empty')) : null,
            ),
        );
    }

    /* ================================================================== */
    /* 8. Ana bilesen: KS.views.Detail                                     */
    /* ================================================================== */

    function galleryOf(detail) {
        return (detail && detail.media) || [];
    }

    function isImageGroup(group) {
        return !!(group && group.kind === 'image');
    }

    function centeredCrop(ratio) {
        let w = 1;
        let hh = 1;

        if (ratio >= 1) {
            hh = clamp(1 / ratio, 0.05, 1);
            w = clamp(hh * ratio, 0.05, 1);
        } else {
            w = clamp(ratio, 0.05, 1);
            hh = clamp(w / ratio, 0.05, 1);
        }

        return { x: (1 - w) / 2, y: (1 - hh) / 2, w, h: hh };
    }

    function DetailSkeleton() {
        return h('div', { className: 'ks-detail-boot' },
            h(Spinner, { size: 'lg', center: true, label: t('loading') }));
    }

    /** Ust cubukta profil kimligi (avatar/logo + hesap adi). */
    function DetailLeading(props) {
        const profile = props.profile;

        return h('div', { className: 'ks-detail-lead' },
            h(Avatar, { person: profile ? { name: profile.name, photo: profile.owner && profile.owner.photo } : null, size: 'sm' }),
            h('span', { className: 'ks-detail-lead__text' },
                h('span', { className: 'ks-strong ks-truncate' }, profile ? profile.name : props.detail.profile_name),
                h('span', { className: 'ks-muted ks-text-12' }, props.detail.content_no)));
    }

    /**
     * KS.views.Detail({ id, onClose }): tam ekran ayrinti kaplamasi.
     * Veri KS.store.contents[id] onbelleginden okunur (KS.useStore); ilk acilista
     * ve id degisince KS.actions.fetchContent(id) cagrilir. Her mutasyon yaniti
     * KS.actions.applyContent(payload.content) ile aym onbellege yazilir, bu
     * yuzden bilesen yeniden veri cekmeden guncel kalir.
     */
    function Detail(props) {
        const id = props.id;
        const detail = KS.useStore(useCallback((state) => state.contents[id], [id]));
        const [loading, setLoading] = useState(!detail);
        const [error, setError] = useState(null);
        const alive = useRef(true);

        const load = useCallback(() => {
            setLoading(true);
            setError(null);

            return KS.actions.fetchContent(id).then(() => {
                if (alive.current) {
                    setLoading(false);
                }
            }).catch((err) => {
                if (alive.current) {
                    setLoading(false);
                    setError(err);
                }
            });
        }, [id]);

        useEffect(() => {
            alive.current = true;

            if (detail) {
                setLoading(false);
            } else {
                load();
            }

            return () => {
                alive.current = false;
            };
        }, [id]);

        if (!detail && error) {
            return h(Overlay, { onClose: props.onClose, label: t('detail_title') },
                h('div', { className: 'ks-overlay__body--padded' }, h(ErrorState, { error, onRetry: load })));
        }

        if (!detail || loading && !detail) {
            return h(Overlay, { onClose: props.onClose, label: t('detail_title') }, h(DetailSkeleton));
        }

        return h(DetailBody, { detail, onClose: props.onClose });
    }

    /** Ayrinti govdesi: detail her zaman dolu (Detail bunu garanti eder). */
    function DetailBody(props) {
        const detail = props.detail;
        const boot = KS.useBoot();
        const limits = KS.useLimits();
        const commentMax = Number(limits.comment_max) || 4000;
        const abilities = detail.abilities || {};
        const gallery = galleryOf(detail);
        const isTextual = isTextType(detail.content_type);
        const isVideoType = detail.content_type === 'video';
        const isPhotoType = detail.content_type === 'photo';

        const profile = useMemo(() => ((boot && boot.profiles) || []).find((item) => Number(item.id) === Number(detail.profile_id)) || null, [boot, detail.profile_id]);

        const [mediaIndex, setMediaIndex] = useState(0);
        const [previewMediaId, setPreviewMediaId] = useState(null);
        const [tab, setTab] = useState('info');
        const [commentFilter, setCommentFilter] = useState('open');
        const [highlightId, setHighlightId] = useState(null);
        const [drawMode, setDrawMode] = useState(null);
        const [draft, setDraft] = useState(null);
        const [draftBusy, setDraftBusy] = useState(false);
        const [cropAdjust, setCropAdjust] = useState(false);
        const [cropBox, setCropBox] = useState(null);
        const [cropRatio, setCropRatio] = useState(1);
        const [applyAll, setApplyAll] = useState(false);
        const [busy, setBusy] = useState(null);
        const [editingText, setEditingText] = useState(false);
        const [textDirty, setTextDirty] = useState(false);
        const [showPublish, setShowPublish] = useState(false);
        const [revisionView, setRevisionView] = useState(null);
        const alive = useRef(true);

        useEffect(() => {
            alive.current = true;

            return () => {
                alive.current = false;
            };
        }, []);

        useEffect(() => {
            setMediaIndex(0);
            setPreviewMediaId(null);
            setDrawMode(null);
            setDraft(null);
            setCropAdjust(false);
            setEditingText(false);
            setTab('info');
        }, [detail.id]);

        useEffect(() => {
            if (mediaIndex >= gallery.length) {
                setMediaIndex(Math.max(0, gallery.length - 1));
            }
        }, [gallery.length]);

        const currentGroup = gallery[mediaIndex] || null;

        useEffect(() => {
            setPreviewMediaId(null);
            setCropAdjust(false);
        }, [mediaIndex]);

        const displayedItem = useMemo(() => {
            if (!currentGroup) {
                return null;
            }

            if (previewMediaId && previewMediaId !== currentGroup.id) {
                const found = (currentGroup.versions || []).find((version) => version.id === previewMediaId);

                if (found) {
                    return found;
                }
            }

            if (cropAdjust) {
                const original = (currentGroup.versions || []).find((version) => version.variant === 'original');

                if (original) {
                    return original;
                }
            }

            return currentGroup;
        }, [currentGroup, previewMediaId, cropAdjust]);

        const allMarks = useMemo(() => collectMarks(detail.comments), [detail.comments]);
        const markNumbers = useMemo(() => {
            const map = {};

            allMarks.forEach((mark) => {
                map[mark.id] = mark.n;
            });

            return map;
        }, [allMarks]);

        const stageMarks = useMemo(() => (displayedItem ? allMarks.filter((mark) => mark.media_id === displayedItem.id) : []), [allMarks, displayedItem]);

        const otherVersionMarks = useMemo(() => {
            if (!currentGroup || !displayedItem) {
                return [];
            }

            const versions = currentGroup.versions || [];
            const counts = {};

            allMarks.forEach((mark) => {
                if (mark.media_id === displayedItem.id) {
                    return;
                }

                const version = versions.find((item) => item.id === mark.media_id);

                if (!version) {
                    return;
                }

                if (!counts[version.id]) {
                    counts[version.id] = { media_id: version.id, label: version.variant_label, count: 0 };
                }

                counts[version.id].count += 1;
            });

            return Object.keys(counts).map((key) => counts[key]);
        }, [allMarks, currentGroup, displayedItem]);

        /* -------------------------------------------------------------- */
        /* Ortak yardimcilar                                               */
        /* -------------------------------------------------------------- */

        const applyPayload = (payload) => {
            if (payload && payload.content) {
                KS.actions.applyContent(payload.content);
            }

            return payload;
        };

        const runBusy = (tag, promise) => {
            setBusy(tag);

            return promise.then((payload) => {
                applyPayload(payload);

                return payload;
            }).catch((err) => {
                KS.handleError(err);
                throw err;
            }).finally(() => {
                if (alive.current) {
                    setBusy(null);
                }
            });
        };

        const go = (delta) => {
            const next = mediaIndex + delta;

            if (next < 0 || next >= gallery.length) {
                return;
            }

            setMediaIndex(next);
        };

        /* -------------------------------------------------------------- */
        /* Tepkiler, yorumlar, isaretler                                   */
        /* -------------------------------------------------------------- */

        const onReact = (value) => {
            if (busy) {
                return;
            }

            const next = detail.my_reaction === value ? 'none' : value;

            runBusy('react:' + value, api.post(url('contents.reaction', detail.id), { reaction: next })).catch(() => undefined);
        };

        const onCommentSubmit = (body, parentId) => {
            if (busy) {
                return Promise.resolve(false);
            }

            return runBusy('comment:' + (parentId || 'root'), api.post(url('comments.store', detail.id), {
                body,
                parent_id: parentId || undefined,
            })).then(() => true).catch(() => false);
        };

        const onCommentResolve = (comment, resolved) => {
            if (busy) {
                return;
            }

            runBusy('resolve:' + comment.id, api.post(url('comments.resolve', comment.id), { resolved })).catch(() => undefined);
        };

        const onJumpToMark = (comment) => {
            if (!comment.media_available) {
                return;
            }

            const idx = gallery.findIndex((group) => group.id === comment.media_id || (group.versions || []).some((version) => version.id === comment.media_id));

            if (idx === -1) {
                return;
            }

            const group = gallery[idx];

            setMediaIndex(idx);
            setPreviewMediaId(group.id === comment.media_id ? null : comment.media_id);
            setHighlightId(comment.id);
            setDrawMode(null);
        };

        const onMarkSelect = (mark) => {
            setHighlightId(mark.id);
            setTab('comments');
            requestAnimationFrame(() => {
                const node = document.getElementById('ks-detail-c-' + mark.id);

                if (node && typeof node.scrollIntoView === 'function') {
                    node.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            });
        };

        const onDraft = (anchor) => {
            setDraft(anchor);
        };

        const onDraftSubmit = (body) => {
            if (!draft || !displayedItem || draftBusy) {
                return;
            }

            const anchor = draft.shape === 'rect'
                ? { shape: 'rect', x: draft.x, y: draft.y, w: draft.w, h: draft.h }
                : { shape: 'point', x: draft.x, y: draft.y };

            setDraftBusy(true);
            api.post(url('comments.store', detail.id), { body, media_id: displayedItem.id, anchor }).then((payload) => {
                applyPayload(payload);

                if (alive.current) {
                    setDraft(null);
                    setDrawMode(null);
                    setTab('comments');
                }
            }).catch((err) => KS.handleError(err)).finally(() => {
                if (alive.current) {
                    setDraftBusy(false);
                }
            });
        };

        const toggleDraw = (shape) => {
            setDrawMode((current) => (current === shape ? null : shape));
            setDraft(null);
            setPreviewMediaId(null);
            setCropAdjust(false);
        };

        /* -------------------------------------------------------------- */
        /* Gorsel araclari: bicim, cozunurluk, surum, kadraj, sira          */
        /* -------------------------------------------------------------- */

        const onFormat = (value) => {
            if (busy || !currentGroup) {
                return;
            }

            const targets = applyAll ? gallery.filter(isImageGroup) : [currentGroup];

            setBusy('format:' + value);

            let chain = Promise.resolve();

            targets.forEach((group) => {
                chain = chain.then(() => api.post(url('media.variant', group.root_id), { format: value })).then((payload) => applyPayload(payload));
            });

            chain.catch((err) => KS.handleError(err)).finally(() => {
                if (alive.current) {
                    setBusy(null);
                }
            });
        };

        const onPreset = (value) => {
            if (busy || !currentGroup) {
                return;
            }

            const targets = applyAll ? gallery.filter(isImageGroup) : [currentGroup];

            setBusy('preset:' + value);

            let chain = Promise.resolve();

            targets.forEach((group) => {
                chain = chain.then(() => api.post(url('media.variant', group.root_id), { preset: value })).then((payload) => applyPayload(payload));
            });

            chain.catch((err) => KS.handleError(err)).finally(() => {
                if (alive.current) {
                    setBusy(null);
                }
            });
        };

        const onSelectVersion = (version) => {
            if (busy) {
                return;
            }

            runBusy('select:' + version.id, api.post(url('media.select', version.id), {})).catch(() => undefined);
        };

        const onMediaCaption = (text) => {
            if (busy || !currentGroup) {
                return;
            }

            runBusy('caption', api.post(url('media.update', currentGroup.id), { caption: text })).catch(() => undefined);
        };

        const onMoveMedia = (delta) => {
            if (busy) {
                return;
            }

            const target = mediaIndex + delta;

            if (target < 0 || target >= gallery.length) {
                return;
            }

            const ids = gallery.map((group) => group.root_id);
            const swap = ids[mediaIndex];

            ids[mediaIndex] = ids[target];
            ids[target] = swap;

            runBusy('move:' + delta, api.post(url('media.order', detail.id), { ids })).then(() => {
                if (alive.current) {
                    setMediaIndex(target);
                }
            }).catch(() => undefined);
        };

        const onRemoveMedia = () => {
            if (busy || !currentGroup) {
                return;
            }

            KS.confirm({
                title: t('detail_remove_media_title'),
                text: t('detail_remove_media_text'),
                confirmLabel: t('detail_remove_media'),
                danger: true,
            }).then((ok) => {
                if (!ok) {
                    return;
                }

                runBusy('remove', api.post(url('media.remove', currentGroup.root_id), {})).then(() => {
                    if (alive.current) {
                        setMediaIndex((current) => Math.max(0, current - 1));
                    }
                }).catch(() => undefined);
            });
        };

        const onRestoreMedia = (item) => {
            if (busy) {
                return;
            }

            runBusy('restore:' + item.id, api.post(url('media.restore', item.id), {})).catch(() => undefined);
        };

        const startCropAdjust = () => {
            if (!currentGroup || FORMAT_KEYS.indexOf(currentGroup.variant) === -1) {
                return;
            }

            const size = formatSize(currentGroup.variant);
            const ratio = size[0] / size[1];

            setCropRatio(ratio);
            setCropBox(currentGroup.crop || centeredCrop(ratio));
            setCropAdjust(true);
            setPreviewMediaId(null);
            setDrawMode(null);
        };

        const applyCropAdjust = () => {
            if (busy || !currentGroup || !cropBox) {
                return;
            }

            runBusy('crop', api.post(url('media.variant', currentGroup.root_id), { format: currentGroup.variant, crop: cropBox })).then(() => {
                if (alive.current) {
                    setCropAdjust(false);
                }
            }).catch(() => undefined);
        };

        /* -------------------------------------------------------------- */
        /* Is akisi: durum, paylasim, acil onay                            */
        /* -------------------------------------------------------------- */

        const onStatus = (value) => {
            if (busy) {
                return;
            }

            const spec = STATUS_ACTIONS[value];

            const proceed = (note) => {
                runBusy('status:' + value, api.post(url('contents.status', detail.id), { status: value, note: note || undefined })).then(() => {
                    if (alive.current) {
                        KS.toast.success(t('detail_status_updated'));
                    }
                }).catch(() => undefined);
            };

            if (spec && spec.note === 'required') {
                KS.askNote({
                    title: t(spec.label),
                    label: t('note'),
                    required: true,
                    requiredMessage: t('required_field'),
                    confirmLabel: t(spec.label),
                    danger: value === 'rejected',
                    maxLength: Number(limits.note_max) || 4000,
                }).then((note) => {
                    if (note !== null) {
                        proceed(note);
                    }
                });

                return;
            }

            proceed(undefined);
        };

        const onPublishSubmit = (payload) => {
            runBusy('publish', api.post(url('contents.publish', detail.id), payload)).then(() => {
                if (alive.current) {
                    setShowPublish(false);
                    KS.toast.success(t('detail_published_toast'));
                }
            }).catch(() => undefined);
        };

        const onUnpublish = () => {
            if (busy) {
                return;
            }

            KS.confirm({
                title: t('detail_unpublish_title'),
                text: t('detail_unpublish_text'),
                confirmLabel: t('detail_unpublish'),
            }).then((ok) => {
                if (ok) {
                    runBusy('unpublish', api.post(url('contents.unpublish', detail.id), {})).catch(() => undefined);
                }
            });
        };

        const onUrgent = () => {
            if (busy) {
                return;
            }

            runBusy('urgent', api.post(url('contents.urgent', detail.id), {})).then(() => {
                if (alive.current) {
                    KS.toast.success(t('detail_urgent_sent'));
                }
            }).catch(() => undefined);
        };

        /* -------------------------------------------------------------- */
        /* Metin duzenleme, kapama, klavye                                 */
        /* -------------------------------------------------------------- */

        const requestClose = () => {
            if (editingText && textDirty) {
                confirmDiscard().then((ok) => {
                    if (ok) {
                        setEditingText(false);
                        props.onClose();
                    }
                });

                return;
            }

            props.onClose();
        };

        useEffect(() => {
            const onKey = (event) => {
                if (shortcutsBlocked(event) || editingText) {
                    return;
                }

                if (event.key === 'ArrowLeft' && !cropAdjust && !drawMode) {
                    if (mediaIndex > 0) {
                        event.preventDefault();
                        go(-1);
                    }
                } else if (event.key === 'ArrowRight' && !cropAdjust && !drawMode) {
                    if (mediaIndex < gallery.length - 1) {
                        event.preventDefault();
                        go(1);
                    }
                }
            };

            document.addEventListener('keydown', onKey);

            return () => document.removeEventListener('keydown', onKey);
        }, [mediaIndex, gallery.length, cropAdjust, drawMode, editingText]);

        /* -------------------------------------------------------------- */
        /* Sahne                                                           */
        /* -------------------------------------------------------------- */

        let stage;

        if (editingText) {
            stage = h(TextStage, { detail, editing: true, onEditEnd: () => setEditingText(false), onDirty: setTextDirty });
        } else if (isTextual) {
            stage = h(TextStage, { detail, editing: false, profile, onEditStart: () => setEditingText(true) });
        } else if (isVideoType) {
            if (currentGroup) {
                stage = h(VideoStage, { item: currentGroup, alt: detail.title });
            } else if (detail.video_url) {
                stage = h(ExternalVideo, { url: detail.video_url, title: detail.title });
            } else {
                stage = h(Empty, { icon: 'video', title: t('detail_no_media') });
            }
        } else if (isPhotoType && currentGroup) {
            stage = h(Fragment, null,
                otherVersionMarks.length && !cropAdjust ? h('div', { className: 'ks-detail-otherbar', 'data-ks-stage-ui': '' },
                    otherVersionMarks.map((entry) => h(Chip, {
                        key: entry.media_id,
                        size: 'sm',
                        icon: 'pin',
                        label: t('detail_marks_on_version', { label: entry.label, n: entry.count }),
                        onClick: () => {
                            setPreviewMediaId(entry.media_id);
                            setHighlightId(null);
                        },
                    }))) : null,
                previewMediaId && !cropAdjust ? h('div', { className: 'ks-detail-previewbar', 'data-ks-stage-ui': '' },
                    h(Icon, { name: 'eye' }),
                    h('span', null, t('detail_previewing_version', { label: displayedItem ? displayedItem.variant_label : '' })),
                    h(Button, { size: 'sm', variant: 'primary', onClick: () => setPreviewMediaId(null) }, t('detail_back_to_selected'))) : null,
                h(ImageStage, {
                    item: displayedItem,
                    alt: detail.title,
                    marks: cropAdjust ? [] : stageMarks,
                    highlightId,
                    onMarkHover: setHighlightId,
                    onMarkSelect,
                    drawMode: cropAdjust ? null : drawMode,
                    draft,
                    onDraft,
                    onDraftSubmit,
                    draftBusy,
                    commentMax,
                    crop: cropAdjust && cropBox ? { box: cropBox, ratio: cropRatio } : null,
                    onCropChange: setCropBox,
                    hasPrev: !cropAdjust && mediaIndex > 0,
                    hasNext: !cropAdjust && mediaIndex < gallery.length - 1,
                    onPrev: () => go(-1),
                    onNext: () => go(1),
                }),
                cropAdjust ? h('div', { className: 'ks-detail-cropbar', 'data-ks-stage-ui': '' },
                    h('span', { className: 'ks-detail-cropbar__text' }, t('detail_crop_adjust_hint')),
                    h(Button, { size: 'sm', variant: 'ghost', className: 'ks-detail-cropbar__btn', disabled: busy === 'crop', onClick: () => setCropAdjust(false) }, t('cancel')),
                    h(Button, { size: 'sm', variant: 'primary', className: 'ks-detail-cropbar__btn', loading: busy === 'crop', onClick: applyCropAdjust }, t('apply'))) : null,
            );
        } else {
            stage = h(Empty, { icon: 'image', title: t('detail_no_media') });
        }

        const strip = isPhotoType && gallery.length > 1 && !editingText ? h('div', { className: 'ks-detail-strip', role: 'listbox', 'aria-label': t('detail_media_strip') },
            gallery.map((group, index) => {
                const thumb = KS.safeUrl(group.thumbnail_url);

                return h('button', {
                    key: group.root_id,
                    type: 'button',
                    role: 'option',
                    'aria-selected': index === mediaIndex ? 'true' : 'false',
                    className: cx('ks-detail-strip__item', index === mediaIndex && 'is-active'),
                    onClick: () => setMediaIndex(index),
                },
                    thumb ? h('img', { src: thumb, alt: '', loading: 'lazy' }) : h(Icon, { name: group.kind === 'video' ? 'video' : 'image' }),
                    group.open_mark_count ? h('span', { className: 'ks-detail-strip__dot', 'aria-hidden': 'true', title: t('detail_mark_aria', { n: group.open_mark_count }) }) : null,
                );
            })) : null;

        const stageDownloadUrl = isPhotoType && displayedItem ? KS.safeUrl(displayedItem.download_url)
            : (isVideoType && currentGroup ? KS.safeUrl(currentGroup.download_url) : null);
        const seriesDownloadUrl = isPhotoType && gallery.filter(isImageGroup).length > 1 ? KS.safeUrl(url('media.zip', detail.id)) : null;

        const stageToolbar = (isPhotoType || isVideoType) && currentGroup && !editingText ? h('div', { className: 'ks-overlay__actions ks-detail-stage-tools' },
            isPhotoType && gallery.length > 1 ? h('span', { className: 'ks-detail-stage-tools__count' }, (mediaIndex + 1) + ' / ' + gallery.length) : null,
            isPhotoType && abilities.comment ? h('div', { className: 'ks-segmented ks-segmented--sm ks-detail-drawmode', role: 'group', 'aria-label': t('detail_mark_mode') },
                h(IconButton, { icon: 'pin', label: t('detail_mark_point'), size: 'sm', variant: 'inverse', active: drawMode === 'point', onClick: () => toggleDraw('point') }),
                h(IconButton, { icon: 'square', label: t('detail_mark_rect'), size: 'sm', variant: 'inverse', active: drawMode === 'rect', onClick: () => toggleDraw('rect') })) : null,
            isPhotoType && currentGroup.variant && FORMAT_KEYS.indexOf(currentGroup.variant) !== -1 && abilities.update ? h(IconButton, { icon: 'crop', label: t('detail_crop_adjust'), size: 'sm', variant: 'inverse', onClick: startCropAdjust }) : null,
            isPhotoType && abilities.update ? h(Popover, {
                trigger: (triggerProps) => h(IconButton, Object.assign({ icon: 'sparkles', label: t('detail_tools_title'), size: 'sm', variant: 'inverse' }, triggerProps)),
                align: 'end',
                panelClassName: 'ks-detail-tools-pop',
            }, (close) => h(ImageTools, {
                group: currentGroup,
                total: gallery.length,
                index: mediaIndex,
                busy,
                applyAll,
                onApplyAll: setApplyAll,
                imageCount: gallery.filter(isImageGroup).length,
                onFormat: (value) => {
                    close();
                    onFormat(value);
                },
                onPreset,
                onSelect: onSelectVersion,
                onCaption: onMediaCaption,
                onMove: onMoveMedia,
                onRemove: () => {
                    close();
                    onRemoveMedia();
                },
                close,
            })) : null,
            stageDownloadUrl ? h(IconButton, { icon: 'download', label: t('detail_download_current'), size: 'sm', variant: 'inverse', href: stageDownloadUrl, download: true }) : null,
            seriesDownloadUrl ? h(IconButton, { icon: 'archive', label: t('detail_download_series'), size: 'sm', variant: 'inverse', href: seriesDownloadUrl, download: true }) : null,
        ) : null;

        /* -------------------------------------------------------------- */
        /* Yan panel                                                       */
        /* -------------------------------------------------------------- */

        const tabItems = [
            { value: 'info', label: t('detail_tab_info'), icon: 'info' },
            { value: 'comments', label: t('detail_tab_comments'), icon: 'comment', count: detail.comment_count || undefined },
            { value: 'likes', label: t('detail_tab_likes'), icon: 'heart' },
            { value: 'history', label: t('detail_tab_history'), icon: 'history' },
        ];

        const sidePanel = h('div', { className: 'ks-overlay__side' },
            h('div', { className: 'ks-detail-panel' },
                h('div', { className: 'ks-detail-panel__tabs' },
                    h(Tabs, { items: tabItems, value: tab, onChange: setTab, variant: 'underline', stretch: true, label: t('detail_tabs_label') })),
                h('div', { className: 'ks-detail-panel__body' },
                    tab === 'info' ? h(InfoTab, { detail, profile }) : null,
                    tab === 'comments' ? h(Fragment, null,
                        abilities.comment ? h(CommentComposer, {
                            placeholder: t('detail_comment_placeholder'),
                            max: commentMax,
                            busy: busy === 'comment:root',
                            onSubmit: (body) => onCommentSubmit(body, null),
                        }) : null,
                        h(CommentsTab, {
                            detail,
                            filter: commentFilter,
                            onFilter: setCommentFilter,
                            markNumbers,
                            highlightId,
                            busy,
                            max: commentMax,
                            onHover: setHighlightId,
                            onJump: onJumpToMark,
                            onResolve: onCommentResolve,
                            onSubmit: onCommentSubmit,
                        })) : null,
                    tab === 'likes' ? h(LikesTab, { detail }) : null,
                    tab === 'history' ? h(HistoryTab, { detail, busy, onRestore: onRestoreMedia, onViewRevision: setRevisionView }) : null),
                h('div', { className: 'ks-detail-panel__foot' },
                    h(ReactionBar, {
                        detail,
                        busy,
                        onReact,
                        onShowComments: () => setTab('comments'),
                        onShowLikes: () => setTab('likes'),
                    }),
                    h(WorkflowBar, {
                        detail,
                        busy,
                        onStatus,
                        onPublish: () => setShowPublish(true),
                        onUnpublish,
                        onUrgent,
                    }))));

        const headerActions = [];

        if (abilities.update && !detail.is_published && !editingText) {
            headerActions.push(h(IconButton, {
                key: 'edit',
                icon: 'edit',
                label: t('detail_edit_content'),
                variant: 'inverse',
                onClick: () => {
                    props.onClose();
                    KS.actions.openComposer(detail);
                },
            }));
        }

        return h(Fragment, null,
            h(Overlay, {
                onClose: requestClose,
                label: t('detail_title'),
                leading: h(DetailLeading, { detail, profile }),
                actions: headerActions.length ? headerActions : null,
                bodyClassName: 'ks-overlay__body--split',
            },
                h('div', { className: cx('ks-overlay__stage', isTextual && 'ks-overlay__stage--light') },
                    stageToolbar,
                    h('div', { className: 'ks-detail-stage' }, stage),
                    strip),
                sidePanel),
            showPublish ? h(PublishModal, { detail, profile, busy: busy === 'publish', onSubmit: onPublishSubmit, onClose: () => (busy === 'publish' ? undefined : setShowPublish(false)) }) : null,
            revisionView ? h(RevisionModal, { revision: revisionView, onClose: () => setRevisionView(null) }) : null,
        );
    }

    KS.views.Detail = Detail;
}());
