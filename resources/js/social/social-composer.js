/*
 * Konelsis Sosyal Medya modulu - OLUSTURUCU (composer) (B31, D-106, 18 Eylul 2026).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement), dis kutuphane yok.
 * Yalniz social-core.js'in (window.KonelsisSocial = KS) sundugu API kullanilir. Stiller:
 * resources/css/filament/konelsis-social.css, bolum COMPOSER (sinif on eki ks-composer-).
 *
 * Kaydettigi bilesen:
 *   KS.views.Composer({ state, onClose })
 *     state   = depodaki `composer` anahtari { open, content|null, type|null, defaults|null }
 *     onClose = kapatma islevi (verilmezse KS.actions.closeComposer)
 *
 * Akis (SPEC 11.2, AMENDMENTS H3 / H8 / F2 / F9):
 *   - Tur yoksa 0. adim: bes tur karosu.
 *   - Form: hesap, baslik, platformlar, kategori, plan tarihi + saati, aciklama ve ture ozel alan.
 *   - Icerik ASLA sessizce olusturulmaz: POST contents yalniz "Kaydet" ile ya da metin
 *     duzenleyicisinin gorsel dugmesinde "once kaydet" onayindan sonra atilir.
 *   - Kaydet -> POST contents (duzenlemede yalniz degisen alanlar + row_version) -> yukleme
 *     kuyrugu sirayla calisir (dosya basina ilerleme, hata, tekrar dene) -> hepsi bitince
 *     kapanir ve ayrinti acilir; "Eksiklerle devam et" yarim kalan kuyrukla da kapatir.
 *   - Video: gizli <video> ile olcu/sure + kapak karesi, parcali yukleme (uploads.begin ->
 *     uploads.chunk ... -> uploads.complete -> media.poster), ag hatasinda uploads.status ile
 *     kaldigi yerden surdurme, "Iptal" -> uploads.abort.
 *   - 409 (stale_record): kullanicinin metni korunur; "Yeniden yukle" / "Metnimi kopyala".
 *
 * Diger gorunum dosyalarindan yalniz KS.RichEditor kullanilir; cizim aninda aranir, yoksa
 * duz metin alanina dusulur.
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, t, api, url, fmt, cx } = KS;
    const { useState, useEffect, useRef, useCallback } = KS;
    const {
        Icon, Button, IconButton, Badge, StatusBadge, Modal, Field, TextInput, TextArea, Select,
        DateInput, TimeInput, Segmented, Dropzone, Notice, ProgressBar, PlatformIcon, CopyButton,
    } = KS;

    /* ================================================================== */
    /* 1. Sabitler ve kucuk yardimcilar                                    */
    /* ================================================================== */

    const TYPE_ORDER = ['photo', 'video', 'short_text', 'long_text', 'blog'];
    const TYPE_ICONS = { photo: 'images', video: 'video', short_text: 'text', long_text: 'document', blog: 'blog' };
    const FORMAT_ICONS = {
        original: 'format-original',
        square: 'format-square',
        portrait: 'format-portrait',
        story: 'format-story',
        landscape: 'format-landscape',
    };
    const FORMAT_FALLBACK = [
        { value: 'original', width: null, height: null },
        { value: 'square', width: 1080, height: 1080 },
        { value: 'portrait', width: 1080, height: 1350 },
        { value: 'story', width: 1080, height: 1920 },
        { value: 'landscape', width: 1920, height: 1080 },
    ];
    const EXTENSION_MIMES = {
        jpg: 'image/jpeg',
        jpeg: 'image/jpeg',
        png: 'image/png',
        webp: 'image/webp',
        gif: 'image/gif',
        mp4: 'video/mp4',
        webm: 'video/webm',
        mov: 'video/quicktime',
    };
    const DEFAULT_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const DEFAULT_VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];
    const TITLE_MAX = 200;
    const URL_MAX = 500;
    const POSTER_LONG_EDGE = 1280;
    const CHUNK_RETRIES = 3;

    function isRichType(type) {
        return type === 'long_text' || type === 'blog';
    }

    function isValidType(type) {
        return TYPE_ORDER.indexOf(type) !== -1;
    }

    /** Sunucudaki mb_strlen ile ayni sayim: kod noktasi, satir sonu tek karakter. */
    function charCount(text) {
        const value = String(text === undefined || text === null ? '' : text).replace(/\r\n?/g, '\n');
        let count = 0;

        for (let index = 0; index < value.length; index += 1) {
            const code = value.charCodeAt(index);

            // Vekil cift (surrogate pair) tek karakter sayilir.
            if (code >= 0xD800 && code <= 0xDBFF && index + 1 < value.length) {
                const next = value.charCodeAt(index + 1);

                if (next >= 0xDC00 && next <= 0xDFFF) {
                    index += 1;
                }
            }

            count += 1;
        }

        return count;
    }

    function byteLength(text) {
        try {
            return new Blob([String(text || '')]).size;
        } catch (error) {
            return String(text || '').length;
        }
    }

    /** Govde bos mu? Her cizimde calistigi icin DOM ayristirmasi yerine ucuz bir metin denetimi yapilir. */
    function isHtmlEmpty(html) {
        if (!html) {
            return true;
        }

        if (/<(img|table|hr|pre)\b/i.test(html)) {
            return false;
        }

        return String(html).replace(/<[^>]*>/g, '').replace(/&nbsp;|&#160;|&#xa0;/gi, ' ').trim() === '';
    }

    function extensionOf(name) {
        const match = /\.([A-Za-z0-9]+)$/.exec(String(name || ''));

        return match ? match[1].toLowerCase() : '';
    }

    function mimeOf(file) {
        const declared = String((file && file.type) || '').toLowerCase();

        return declared || EXTENSION_MIMES[extensionOf(file && file.name)] || '';
    }

    function fileIdentity(file) {
        return [file.name, file.size, file.lastModified].join('|');
    }

    function wait(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function cancelledError() {
        return Object.assign(new Error('cancelled'), { aborted: true, cancelled: true });
    }

    function prefersReducedMotion() {
        try {
            return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (error) {
            return false;
        }
    }

    function revokeUrl(objectUrl) {
        if (!objectUrl) {
            return;
        }

        try {
            URL.revokeObjectURL(objectUrl);
        } catch (error) {
            // Zaten birakilmis adres.
        }
    }

    function typeLabel(type) {
        return KS.optionLabel('types', type) || t('composer_type_' + type);
    }

    function typeList() {
        const options = KS.options('types') || [];
        const known = options.map((item) => (item ? String(item.value) : ''));

        return TYPE_ORDER
            .filter((value) => !options.length || known.indexOf(value) !== -1)
            .map((value) => ({ value, label: typeLabel(value), description: t('composer_type_' + value + '_desc'), icon: TYPE_ICONS[value] }));
    }

    function formatList() {
        const options = KS.options('formats') || [];

        if (options.length) {
            return options.map((item) => ({
                value: String(item.value),
                label: item.label || t('composer_format_' + item.value),
                width: item.width ? Number(item.width) : null,
                height: item.height ? Number(item.height) : null,
            }));
        }

        return FORMAT_FALLBACK.map((item) => Object.assign({ label: t('composer_format_' + item.value) }, item));
    }

    /* ------------------------------------------------------------------ */
    /* Form durumu                                                         */
    /* ------------------------------------------------------------------ */

    function blankForm() {
        return {
            profile_id: '',
            title: '',
            platforms: [],
            category_id: '',
            planned_on: '',
            planned_time: '',
            caption: '',
            body_text: '',
            body_html: '',
            image_format: 'original',
            video_url: '',
            video_mode: 'file',
        };
    }

    function formFromContent(content) {
        const hasVideoMedia = (content.media || []).some((item) => item && item.kind === 'video');

        return Object.assign(blankForm(), {
            profile_id: content.profile_id || '',
            title: content.title || '',
            platforms: (content.platforms || []).map((item) => item && item.platform).filter(Boolean),
            category_id: content.category && content.category.id ? content.category.id : '',
            planned_on: content.planned_on || '',
            planned_time: content.planned_time ? String(content.planned_time).slice(0, 5) : '',
            caption: content.caption || '',
            body_text: content.body_text || '',
            body_html: content.body_html || '',
            image_format: content.image_format || 'original',
            video_url: content.video_url || '',
            video_mode: content.video_url && !hasVideoMedia ? 'link' : 'file',
        });
    }

    function formFromDefaults(defaults) {
        const state = KS.store.getState();
        const boot = state.boot || {};
        const profiles = boot.profiles || [];
        const categories = boot.categories || [];
        const platformValues = (KS.options('platforms') || []).map((item) => String(item.value));
        const wanted = defaults && typeof defaults === 'object' ? defaults : {};
        const hasProfile = (id) => profiles.some((profile) => Number(profile.id) === Number(id));
        const form = blankForm();

        if (wanted.profile_id && hasProfile(wanted.profile_id)) {
            form.profile_id = Number(wanted.profile_id);
        } else if (state.profileId && hasProfile(state.profileId)) {
            form.profile_id = Number(state.profileId);
        } else if (profiles.length) {
            form.profile_id = profiles[0].id;
        }

        if (wanted.category_id && categories.some((item) => Number(item.id) === Number(wanted.category_id))) {
            form.category_id = Number(wanted.category_id);
        }

        if (Array.isArray(wanted.platforms)) {
            form.platforms = wanted.platforms.map(String).filter((value, index, list) => platformValues.indexOf(value) !== -1 && list.indexOf(value) === index);
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(wanted.planned_on || '')) {
            form.planned_on = wanted.planned_on;

            if (/^\d{2}:\d{2}/.test(wanted.planned_time || '')) {
                form.planned_time = String(wanted.planned_time).slice(0, 5);
            }
        }

        return form;
    }

    /**
     * Sunucuya gidecek alanlar (F9). videoSelected: yuklenecek bir video dosyasi secili mi.
     * Video baglantisi: "Baglanti ver" kipinde alan degeri; "Dosya yukle" kipinde dosya
     * seciliyse bosaltilir, secili degilse hic gonderilmez (mevcut deger korunur).
     */
    function buildPayload(form, type, videoSelected) {
        const payload = {
            profile_id: form.profile_id === '' ? null : Number(form.profile_id),
            title: String(form.title || '').trim(),
            platforms: (form.platforms || []).slice(),
            category_id: form.category_id === '' || form.category_id === null ? null : Number(form.category_id),
            planned_on: form.planned_on || null,
            planned_time: form.planned_on && form.planned_time ? form.planned_time : null,
            caption: String(form.caption || '').trim() === '' ? null : form.caption,
        };

        if (type === 'photo') {
            payload.image_format = form.image_format || 'original';
        }

        if (type === 'video') {
            if (form.video_mode === 'link') {
                payload.video_url = String(form.video_url || '').trim() || null;
            } else if (videoSelected) {
                payload.video_url = null;
            }
        }

        if (type === 'short_text') {
            payload.body_text = String(form.body_text || '').trim() === '' ? null : form.body_text;
        }

        if (isRichType(type)) {
            payload.body_html = isHtmlEmpty(form.body_html) ? null : form.body_html;
        }

        return payload;
    }

    function comparable(name, value) {
        if (name === 'platforms' && Array.isArray(value)) {
            return JSON.stringify(value.slice().sort());
        }

        return JSON.stringify(value === undefined ? null : value);
    }

    /** Duzenlemede yalniz degisen anahtarlar gonderilir (sunucu: "yalniz gonderilen anahtarlar degisir"). */
    function diffPayload(current, base) {
        const changes = {};

        Object.keys(current).forEach((name) => {
            // Tabanda bulunmayan anahtar "bos" sayilir (ornegin dosya kipindeki video baglantisi).
            const before = Object.prototype.hasOwnProperty.call(base, name) ? base[name] : null;

            if (comparable(name, current[name]) !== comparable(name, before)) {
                changes[name] = current[name];
            }
        });

        return changes;
    }

    /* ------------------------------------------------------------------ */
    /* Video on incelemesi: olcu, sure ve kapak karesi (H8)                */
    /* ------------------------------------------------------------------ */

    /**
     * Dosyayi gizli bir <video> ogesine yukler; videoWidth/videoHeight/duration okur,
     * min(1 sn, sure/2) anina sarar, kareyi canvas'a cizip JPEG blob uretir.
     * Promise<{ meta: { width, height, duration_seconds }, poster: Blob|null } | null>
     * (null = tarayici videoyu cozemedi; yukleme yine de yapilabilir).
     */
    function probeVideo(file) {
        return new Promise((resolve) => {
            let settled = false;
            let objectUrl = null;
            let meta = null;
            let timer = 0;
            const video = document.createElement('video');

            const finish = (result) => {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearTimeout(timer);
                video.onloadedmetadata = null;
                video.onloadeddata = null;
                video.onseeked = null;
                video.onerror = null;

                try {
                    video.removeAttribute('src');
                    video.load();
                } catch (error) {
                    // yok sayilir
                }

                if (video.parentNode) {
                    video.parentNode.removeChild(video);
                }

                revokeUrl(objectUrl);
                resolve(result);
            };

            const capture = () => {
                const paint = () => {
                    try {
                        const width = video.videoWidth;
                        const height = video.videoHeight;

                        if (!width || !height) {
                            finish({ meta, poster: null });

                            return;
                        }

                        const scale = Math.min(1, POSTER_LONG_EDGE / Math.max(width, height));
                        const canvas = document.createElement('canvas');

                        canvas.width = Math.max(1, Math.round(width * scale));
                        canvas.height = Math.max(1, Math.round(height * scale));

                        const context = canvas.getContext('2d');

                        if (!context || typeof canvas.toBlob !== 'function') {
                            finish({ meta, poster: null });

                            return;
                        }

                        context.drawImage(video, 0, 0, canvas.width, canvas.height);
                        canvas.toBlob((blob) => finish({ meta, poster: blob || null }), 'image/jpeg', 0.85);
                    } catch (error) {
                        finish({ meta, poster: null });
                    }
                };

                if (typeof window.requestAnimationFrame === 'function') {
                    window.requestAnimationFrame(paint);
                } else {
                    paint();
                }
            };

            timer = window.setTimeout(() => finish(meta ? { meta, poster: null } : null), 12000);

            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.setAttribute('aria-hidden', 'true');
            video.tabIndex = -1;
            video.style.cssText = 'position:fixed;left:-9999px;top:0;width:2px;height:2px;opacity:0;pointer-events:none;';

            let seekTarget = 0;
            let dataReady = false;

            video.onloadedmetadata = () => {
                const duration = Number.isFinite(video.duration) ? video.duration : 0;

                meta = {
                    width: video.videoWidth || null,
                    height: video.videoHeight || null,
                    duration_seconds: duration > 0 ? Math.max(1, Math.round(duration)) : null,
                };

                seekTarget = duration > 0 ? Math.min(1, duration / 2) : 0;

                if (seekTarget > 0.01) {
                    try {
                        video.currentTime = seekTarget;
                    } catch (error) {
                        seekTarget = 0;

                        if (dataReady) {
                            capture();
                        }
                    }
                } else if (dataReady) {
                    capture();
                }
            };

            video.onloadeddata = () => {
                dataReady = true;

                if (meta && seekTarget <= 0.01) {
                    capture();
                }
            };

            video.onseeked = () => capture();
            video.onerror = () => finish(meta ? { meta, poster: null } : null);

            try {
                objectUrl = URL.createObjectURL(file);
                document.body.appendChild(video);
                video.src = objectUrl;
            } catch (error) {
                finish(null);
            }
        });
    }

    /* ================================================================== */
    /* 2. Alt bilesenler                                                   */
    /* ================================================================== */

    /** 0. adim: tur karolari. */
    function TypePicker(props) {
        const types = typeList();

        return h('div', { className: 'ks-composer-types' },
            h('p', { className: 'ks-composer-types__lead' }, t('composer_pick_type_lead')),
            h('div', { className: 'ks-composer-types__grid' },
                types.map((item, index) => h('button', Object.assign({
                    key: item.value,
                    type: 'button',
                    className: cx('ks-composer-type', 'ks-composer-type--' + item.value),
                    onClick: () => props.onPick(item.value),
                }, index === 0 ? { 'data-autofocus': '' } : {}),
                    h('span', { className: 'ks-composer-type__icon', 'aria-hidden': 'true' }, h(Icon, { name: item.icon })),
                    h('span', { className: 'ks-composer-type__text' },
                        h('span', { className: 'ks-composer-type__label' }, item.label),
                        h('span', { className: 'ks-composer-type__desc' }, item.description)),
                    h(Icon, { name: 'chevron-right', className: 'ks-composer-type__go' })))));
    }

    /** Platformlar: marka renginde coklu secim dugmeleri. */
    function PlatformPicker(props) {
        const options = KS.options('platforms') || [];
        const selected = props.value || [];

        if (!options.length) {
            return h(Notice, { tone: 'warning', compact: true }, t('load_failed'));
        }

        const toggle = (value) => {
            const next = selected.indexOf(value) === -1
                ? options.map((item) => String(item.value)).filter((item) => item === value || selected.indexOf(item) !== -1)
                : selected.filter((item) => item !== value);

            props.onChange(next);
        };

        return h('div', { className: 'ks-composer-platforms' },
            options.map((item) => {
                const value = String(item.value);
                const active = selected.indexOf(value) !== -1;

                return h('button', {
                    key: value,
                    type: 'button',
                    className: cx('ks-platform', KS.platformClass(value), 'ks-composer-platform', active && 'is-active'),
                    'aria-pressed': active ? 'true' : 'false',
                    disabled: props.disabled,
                    onClick: () => toggle(value),
                },
                    h(PlatformIcon, { platform: value }),
                    h('span', { className: 'ks-composer-platform__label' }, item.label),
                    h('span', { className: 'ks-composer-platform__check', 'aria-hidden': 'true' }, active ? h(Icon, { name: 'check' }) : null));
            }));
    }

    /** Gorsel formati: Ozgun / Kare / Dikey / Hikaye / Yatay + kucuk oran onizlemesi. */
    function FormatPicker(props) {
        const formats = formatList();

        return h('div', { className: 'ks-composer-formats' },
            formats.map((item) => {
                const active = String(props.value || 'original') === item.value;
                const hasShape = item.width && item.height;

                return h('button', {
                    key: item.value,
                    type: 'button',
                    className: cx('ks-composer-format', active && 'is-active'),
                    'aria-pressed': active ? 'true' : 'false',
                    disabled: props.disabled,
                    onClick: () => props.onChange(item.value),
                },
                    h('span', { className: 'ks-composer-format__stage', 'aria-hidden': 'true' },
                        hasShape
                            ? h('span', { className: 'ks-composer-format__shape', style: { aspectRatio: item.width + ' / ' + item.height } })
                            : h(Icon, { name: FORMAT_ICONS[item.value] || 'format-original' })),
                    h('span', { className: 'ks-composer-format__label' }, item.label),
                    h('span', { className: 'ks-composer-format__size' }, hasShape ? item.width + ' × ' + item.height : t('composer_format_original_size')));
            }));
    }

    /** X tarzi karakter halkasi: yumusak sinira kadar dolar, asilinca amber, kesin sinirda kirmizi. */
    function CharRing(props) {
        const radius = 9;
        const circumference = 2 * Math.PI * radius;
        const ratio = props.soft > 0 ? KS.clamp(props.count / props.soft, 0, 1) : 0;
        const remaining = props.soft - props.count;
        let tone = 'ok';

        if (props.count >= props.hard) {
            tone = 'full';
        } else if (props.count > props.soft) {
            tone = 'over';
        } else if (remaining <= 20) {
            tone = 'near';
        }

        return h('span', { className: cx('ks-composer-ring', 'is-' + tone) },
            h('svg', { className: 'ks-composer-ring__svg', viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false' },
                h('circle', { className: 'ks-composer-ring__track', cx: 12, cy: 12, r: radius, fill: 'none', strokeWidth: 2.5 }),
                h('circle', {
                    className: 'ks-composer-ring__bar',
                    cx: 12,
                    cy: 12,
                    r: radius,
                    fill: 'none',
                    strokeWidth: 2.5,
                    strokeLinecap: 'round',
                    strokeDasharray: circumference.toFixed(2),
                    strokeDashoffset: (circumference * (1 - ratio)).toFixed(2),
                    transform: 'rotate(-90 12 12)',
                })),
            tone === 'ok' ? null : h('span', { className: 'ks-composer-ring__num', 'aria-hidden': 'true' }, fmt.number(remaining)));
    }

    /** Duzenleme kipinde mevcut medya: salt okunur kucuk gorseller + "ayrintida yonetilir" ipucu. */
    function ExistingMedia(props) {
        const media = props.media || [];

        if (!media.length) {
            return null;
        }

        return h('div', { className: 'ks-composer-existing' },
            h('ul', { className: 'ks-composer-existing__list', 'aria-label': t('composer_existing_media') },
                media.map((item, index) => {
                    const source = KS.safeUrl(item.thumbnail_url || item.poster_url || (item.kind === 'image' ? item.preview_url : null));
                    const label = item.caption || item.name || t('composer_existing_media_item', { index: index + 1 });

                    return h('li', { key: item.root_id || item.id || index, className: 'ks-composer-existing__item', title: label },
                        source
                            ? h('img', { src: source, alt: label, loading: 'lazy', decoding: 'async' })
                            : h('span', { className: 'ks-composer-existing__placeholder', role: 'img', 'aria-label': label }, h(Icon, { name: item.kind === 'video' ? 'video' : 'image' })),
                        item.kind === 'video'
                            ? h('span', { className: 'ks-composer-existing__play', 'aria-hidden': 'true' }, h(Icon, { name: 'play' }))
                            : null,
                        item.kind === 'video' && item.duration_seconds
                            ? h('span', { className: 'ks-composer-existing__duration' }, fmt.duration(item.duration_seconds))
                            : null);
                })),
            h('p', { className: 'ks-composer-existing__hint' },
                h(Icon, { name: 'info' }),
                h('span', null, t('composer_media_managed_in_detail'))));
    }

    /** Fotograf kuyrugu: onizleme, surukleyerek siralama (+ ok dugmeleri), ilerleme, hata, tekrar dene. */
    function PhotoQueue(props) {
        const items = props.items || [];
        const dragKey = useRef(null);
        const [dragging, setDragging] = useState(null);
        const sortable = !!props.sortable && items.length > 1;

        if (!items.length) {
            return null;
        }

        return h('ul', {
            className: cx('ks-composer-thumbs', 'ks-composer-thumbs--' + (props.format || 'original')),
            'aria-label': t('composer_queue_label'),
        }, items.map((item, index) => {
            const removable = !props.locked && item.status !== 'done' && item.status !== 'uploading';
            let overlay = null;

            if (item.status === 'uploading') {
                overlay = h('div', { className: 'ks-composer-thumb__veil' },
                    h(ProgressBar, { value: Math.round((item.progress || 0) * 100), size: 'sm', ariaLabel: t('composer_uploading_name', { name: item.name }) }),
                    h('span', { className: 'ks-composer-thumb__percent' }, fmt.percent(item.progress || 0, 0)));
            } else if (item.status === 'done') {
                overlay = h('span', { className: 'ks-composer-thumb__state ks-composer-thumb__state--done', title: t('composer_uploaded') },
                    h(Icon, { name: 'check', title: t('composer_uploaded') }));
            } else if (item.status === 'error') {
                overlay = h('span', { className: 'ks-composer-thumb__state ks-composer-thumb__state--error', title: t('composer_upload_failed') },
                    h(Icon, { name: 'alert', title: t('composer_upload_failed') }));
            }

            return h('li', {
                key: item.key,
                className: cx('ks-composer-thumb', 'is-' + item.status, dragging === item.key && 'is-dragging', sortable && 'is-sortable'),
                draggable: sortable ? true : undefined,
                onDragStart: sortable ? (event) => {
                    dragKey.current = item.key;
                    setDragging(item.key);

                    try {
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', item.key);
                    } catch (error) {
                        // Bazi tarayicilar setData'yi kisitlar; siralama yine calisir.
                    }
                } : undefined,
                onDragOver: sortable ? (event) => {
                    if (!dragKey.current) {
                        return;
                    }

                    event.preventDefault();

                    if (dragKey.current !== item.key) {
                        props.onMoveTo(dragKey.current, item.key);
                    }
                } : undefined,
                onDrop: sortable ? (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    dragKey.current = null;
                    setDragging(null);
                } : undefined,
                onDragEnd: sortable ? () => {
                    dragKey.current = null;
                    setDragging(null);
                } : undefined,
            },
                h('div', { className: 'ks-composer-thumb__frame' },
                    h('img', { src: item.url, alt: item.name, draggable: false, decoding: 'async' }),
                    h('span', { className: 'ks-composer-thumb__index', 'aria-hidden': 'true' }, String(index + 1 + (props.offset || 0))),
                    index === 0 && !props.offset ? h(Badge, { color: 'stone', size: 'sm', className: 'ks-composer-thumb__cover' }, t('composer_cover')) : null,
                    overlay),
                h('div', { className: 'ks-composer-thumb__meta' },
                    h('span', { className: 'ks-composer-thumb__name', title: item.name }, item.name),
                    h('span', { className: 'ks-composer-thumb__size' }, fmt.bytes(item.size))),
                item.status === 'error' && item.error
                    ? h('p', { className: 'ks-composer-thumb__error', role: 'alert' }, item.error)
                    : null,
                h('div', { className: 'ks-composer-thumb__actions' },
                    sortable ? h(IconButton, {
                        icon: 'chevron-left',
                        size: 'sm',
                        variant: 'plain',
                        label: t('composer_move_earlier', { name: item.name }),
                        disabled: index === 0,
                        onClick: () => props.onMove(item.key, -1),
                    }) : null,
                    sortable ? h(IconButton, {
                        icon: 'chevron-right',
                        size: 'sm',
                        variant: 'plain',
                        label: t('composer_move_later', { name: item.name }),
                        disabled: index === items.length - 1,
                        onClick: () => props.onMove(item.key, 1),
                    }) : null,
                    h('span', { className: 'ks-composer-thumb__spacer' }),
                    item.status === 'error' && !props.locked ? h(IconButton, {
                        icon: 'refresh',
                        size: 'sm',
                        variant: 'soft',
                        label: t('retry'),
                        onClick: props.onRetry,
                    }) : null,
                    removable ? h(IconButton, {
                        icon: 'close',
                        size: 'sm',
                        variant: 'plain',
                        label: t('composer_remove_file', { name: item.name }),
                        onClick: () => props.onRemove(item.key),
                    }) : null));
        }));
    }

    /** Secili video dosyasinin karti: kapak, bilgiler, ilerleme, iptal / tekrar dene / kaldir. */
    function VideoCard(props) {
        const video = props.video;
        const meta = video.meta || {};
        const facts = [fmt.bytes(video.size)];

        if (meta.duration_seconds) {
            facts.push(fmt.duration(meta.duration_seconds));
        }

        if (meta.width && meta.height) {
            facts.push(meta.width + ' × ' + meta.height);
        }

        let status = null;

        if (video.status === 'uploading') {
            status = h('div', { className: 'ks-composer-video__progress' },
                h(ProgressBar, { value: Math.round((video.progress || 0) * 100), label: t('composer_video_uploading'), showValue: true }),
                h('span', { className: 'ks-composer-video__bytes' }, fmt.bytes(video.size * (video.progress || 0)) + ' / ' + fmt.bytes(video.size)));
        } else if (video.status === 'finishing') {
            status = h('div', { className: 'ks-composer-video__progress' },
                h(ProgressBar, { indeterminate: true, label: t('composer_video_finishing') }));
        } else if (video.status === 'done') {
            status = h(Badge, { color: 'emerald', icon: 'check' }, t('composer_uploaded'));
        } else if (video.status === 'error') {
            status = h('p', { className: 'ks-composer-video__error', role: 'alert' }, h(Icon, { name: 'alert' }), h('span', null, video.error || t('composer_upload_failed')));
        } else if (video.probing) {
            status = h('span', { className: 'ks-composer-video__probing' }, h(KS.Spinner, { size: 'sm', label: t('composer_video_probing') }));
        }

        return h('div', { className: cx('ks-composer-video', 'is-' + video.status) },
            h('div', { className: 'ks-composer-video__poster' },
                video.posterUrl
                    ? h('img', { src: video.posterUrl, alt: t('composer_video_poster_alt'), decoding: 'async' })
                    : h('span', { className: 'ks-composer-video__placeholder', 'aria-hidden': 'true' }, h(Icon, { name: 'video' })),
                h('span', { className: 'ks-composer-video__play', 'aria-hidden': 'true' }, h(Icon, { name: 'play' }))),
            h('div', { className: 'ks-composer-video__body' },
                h('p', { className: 'ks-composer-video__name', title: video.name }, video.name),
                h('p', { className: 'ks-composer-video__facts' }, facts.join(' · ')),
                status,
                h('div', { className: 'ks-composer-video__actions' },
                    video.status === 'uploading' ? h(Button, { size: 'sm', variant: 'danger', icon: 'close', onClick: props.onCancel }, t('composer_cancel_upload')) : null,
                    video.status === 'error' && !props.locked ? h(Button, { size: 'sm', variant: 'soft', icon: 'refresh', onClick: props.onRetry }, t('retry')) : null,
                    (video.status === 'pending' || video.status === 'error') && !props.locked
                        ? h(Button, { size: 'sm', variant: 'ghost', icon: 'trash-none', onClick: props.onRemove }, t('composer_video_remove'))
                        : null)));
    }

    /* ================================================================== */
    /* 3. Olusturucu penceresi                                             */
    /* ================================================================== */

    function ComposerDialog(props) {
        const composerState = props.state || {};
        const boot = KS.useBoot();
        const limits = KS.useLimits();
        const storage = KS.useStore((state) => state.storage);
        const isMobile = KS.useIsMobile();

        const initial = useRef(null);

        if (initial.current === null) {
            const content = composerState.content && composerState.content.id ? composerState.content : null;

            initial.current = {
                content,
                mediaIds: ((content && content.media) || []).map((item) => Number(item.root_id || item.id)),
                type: content ? content.content_type : (isValidType(composerState.type) ? composerState.type : null),
                form: content ? formFromContent(content) : formFromDefaults(composerState.defaults),
            };
        }

        const mounted = useRef(true);
        const formRef = useRef(null);
        const titleRef = useRef(null);
        const recordRef = useRef(initial.current.content);
        const baseline = useRef(initial.current.form);
        const createdHere = useRef(false);
        const creating = useRef(null);
        const closing = useRef(false);
        const jobRef = useRef(null);
        const cancelRef = useRef(false);
        const objectUrls = useRef([]);
        const filesRef = useRef([]);
        const videoRef = useRef(null);
        const inlineAdded = useRef(false);
        const changedSomething = useRef(false);

        const [record, setRecordState] = useState(initial.current.content);
        const [pickedType, setPickedType] = useState(initial.current.type);
        const [form, setForm] = useState(initial.current.form);
        const [errors, setErrors] = useState({});
        const [errorTick, setErrorTick] = useState(0);
        const [busy, setBusy] = useState(null);           // null | 'saving' | 'uploading' | 'inline' | 'reloading'
        const [stale, setStale] = useState(false);
        const [attempted, setAttempted] = useState(false);
        const [editorKey, setEditorKey] = useState(0);
        const [files, setFiles] = useState([]);
        const [video, setVideo] = useState(null);
        const [plainBody, setPlainBody] = useState(null);  // KS.RichEditor yoksa kullanilan duz metin

        const type = record ? record.content_type : pickedType;
        const rich = isRichType(type);

        /* ---- Sinirlar --------------------------------------------------- */
        const captionMax = Number(limits.caption_max) || 5000;
        const softLimit = Number(limits.short_text_soft) || 280;
        const hardLimit = Number(limits.short_text_hard) || 25000;
        const htmlMaxBytes = Number(limits.body_html_max_bytes) || 1048576;
        const maxImageBytes = (Number(limits.max_image_kb) || 25600) * 1024;
        const maxVideoBytes = (Number(limits.max_video_mb) || 1024) * 1048576;
        const preferLinkBytes = (Number(limits.prefer_link_over_mb) || 200) * 1048576;
        const imageMimes = Array.isArray(limits.image_mimes) && limits.image_mimes.length ? limits.image_mimes.map((item) => String(item).toLowerCase()) : DEFAULT_IMAGE_MIMES;
        const videoMimes = Array.isArray(limits.video_mimes) && limits.video_mimes.length ? limits.video_mimes.map((item) => String(item).toLowerCase()) : DEFAULT_VIDEO_MIMES;

        /* ---- Durum yardimcilari ------------------------------------------ */
        const adoptRecord = (detail) => {
            recordRef.current = detail;

            if (mounted.current) {
                setRecordState(detail);
            }
        };

        /** Bu pencerenin kendi isleminden donen ayrinti: surum devralinir ve onbellege uygulanir. */
        const applyOwn = (detail) => {
            if (detail && detail.id) {
                adoptRecord(detail);
                KS.actions.applyContent(detail);
            }
        };

        const commitFiles = (next) => {
            filesRef.current = next;

            if (mounted.current) {
                setFiles(next);
            }
        };

        const patchFile = (key, patch) => {
            commitFiles(filesRef.current.map((item) => (item.key === key ? Object.assign({}, item, patch) : item)));
        };

        const commitVideo = (next) => {
            videoRef.current = next;

            if (mounted.current) {
                setVideo(next);
            }
        };

        const patchVideo = (patch) => {
            if (videoRef.current) {
                commitVideo(Object.assign({}, videoRef.current, patch));
            }
        };

        const setField = useCallback((name, value) => {
            setForm((current) => Object.assign({}, current, { [name]: value }));
            setErrors((current) => (current[name] ? KS.omit(current, [name]) : current));
        }, []);

        const trackUrl = (objectUrl) => {
            objectUrls.current.push(objectUrl);

            return objectUrl;
        };

        const releaseUrl = (objectUrl) => {
            objectUrls.current = objectUrls.current.filter((item) => item !== objectUrl);
            revokeUrl(objectUrl);
        };

        const abortRemoteUpload = (token) => {
            if (token && KS.hasEndpoint('uploads.abort')) {
                api.post(url('uploads.abort', { token })).catch(() => undefined);
            }
        };

        /* ---- Yasam dongusu ------------------------------------------------ */
        useEffect(() => {
            mounted.current = true;

            return () => {
                mounted.current = false;
                cancelRef.current = true;

                if (jobRef.current && typeof jobRef.current.abort === 'function') {
                    jobRef.current.abort();
                }

                const current = videoRef.current;

                if (current && current.token && current.status !== 'done' && current.status !== 'finishing') {
                    abortRemoteUpload(current.token);
                }

                objectUrls.current.forEach(revokeUrl);
                objectUrls.current = [];
            };
        }, []);

        /*
         * row_version devri (H2): yalniz BU pencerenin kendi islemleri (kaydetme, medya yukleme)
         * surerken gelen ayrinti devralinir. Pencere bostayken baska birinin degisikligi
         * devralinmaz; boylece sonraki kayit 409 verir ve kullanici uyarilir (sessizce ezme olmaz).
         */
        const busyRef = useRef(null);

        busyRef.current = busy;

        useEffect(() => KS.events.on('content', (detail) => {
            if (busyRef.current && detail && recordRef.current && Number(detail.id) === Number(recordRef.current.id)) {
                adoptRecord(detail);
            }
        }), []);

        const propContent = composerState.content;

        useEffect(() => {
            if (busyRef.current && propContent && propContent.id && recordRef.current && propContent !== recordRef.current
                && Number(propContent.id) === Number(recordRef.current.id)
                && Number(propContent.row_version || 0) >= Number(recordRef.current.row_version || 0)) {
                adoptRecord(propContent);
            }
        }, [propContent]);

        // Dogrulama hatasinda ilk hatali alana gidilir.
        useEffect(() => {
            if (!errorTick || !formRef.current) {
                return;
            }

            const node = formRef.current.querySelector('.ks-composer-stale, .ks-field.has-error');

            if (!node) {
                return;
            }

            try {
                node.scrollIntoView({ block: 'center', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
            } catch (error) {
                node.scrollIntoView();
            }

            const control = node.querySelector('input:not([type="hidden"]), textarea, select, [contenteditable="true"], button');

            if (control && typeof control.focus === 'function') {
                try {
                    control.focus({ preventScroll: true });
                } catch (error) {
                    control.focus();
                }
            }
        }, [errorTick]);

        // Tur secildikten sonra odak basliga alinir (karolar ekrandan kalkti).
        const focusTitleOnType = useRef(false);

        useEffect(() => {
            if (focusTitleOnType.current && type && titleRef.current && !isMobile) {
                focusTitleOnType.current = false;

                try {
                    titleRef.current.focus({ preventScroll: true });
                } catch (error) {
                    titleRef.current.focus();
                }
            }
        }, [type, isMobile]);

        /* ---- Turetilen degerler ------------------------------------------- */
        const profiles = (boot && boot.profiles) || [];
        const categories = (boot && boot.categories) || [];
        const selectedProfile = profiles.find((item) => Number(item.id) === Number(form.profile_id)) || null;
        const readOnly = !!(record && record.abilities && record.abilities.update === false);
        const locked = busy !== null || readOnly;
        const existingMedia = record ? (record.media || []).filter((item) => initial.current.mediaIds.indexOf(Number(item.root_id || item.id)) !== -1) : [];
        const existingVideo = existingMedia.some((item) => item.kind === 'video');
        const pendingFiles = files.filter((item) => item.status !== 'done');
        const videoPending = !!(video && video.status !== 'done' && form.video_mode === 'file');
        const hasPendingUploads = (type === 'photo' && pendingFiles.length > 0) || (type === 'video' && videoPending);
        const failedUploads = (type === 'photo' && files.some((item) => item.status === 'error')) || (type === 'video' && !!video && video.status === 'error');
        const formChanged = Object.keys(diffPayload(buildPayload(form, type, false), buildPayload(baseline.current, type, false))).length > 0;
        const dirty = formChanged || hasPendingUploads || plainBody !== null;
        const profileLocked = !!record && (
            (record.media || []).length > 0
            || (record.removed_media || []).length > 0
            || Number(record.media_count) > 0
            || inlineAdded.current
            || /\/social\/media\/\d+\/file/.test(record.body_html || '')
        );

        const categoryOptions = categories
            .filter((item) => item.status === undefined || item.status === 'active' || String(item.id) === String(form.category_id))
            .map((item) => ({ value: item.id, label: item.name }));

        if (record && record.category && form.category_id !== '' && !categoryOptions.some((item) => String(item.value) === String(form.category_id))
            && String(record.category.id) === String(form.category_id)) {
            categoryOptions.push({ value: record.category.id, label: record.category.name });
        }

        /* ---- Dosya denetimleri --------------------------------------------- */
        const checkImage = (file) => {
            const name = file.name || t('composer_pasted_image');

            if (imageMimes.indexOf(mimeOf(file)) === -1) {
                return t('composer_file_type_image', { name });
            }

            if (!file.size) {
                return t('composer_file_empty', { name });
            }

            if (file.size > maxImageBytes) {
                return t('composer_file_too_large', { name, max: fmt.bytes(maxImageBytes) });
            }

            return null;
        };

        const checkVideo = (file) => {
            const name = file.name || '';

            if (videoMimes.indexOf(mimeOf(file)) === -1) {
                return t('composer_file_type_video', { name });
            }

            if (!file.size) {
                return t('composer_file_empty', { name });
            }

            if (file.size > maxVideoBytes) {
                return t('composer_file_too_large', { name, max: fmt.bytes(maxVideoBytes) });
            }

            return null;
        };

        /* ---- Fotograf kuyrugu ------------------------------------------------ */
        const addPhotoFiles = (list) => {
            const known = filesRef.current.map((item) => item.identity);
            const accepted = [];
            const rejected = [];

            (list || []).forEach((file) => {
                const problem = checkImage(file);

                if (problem) {
                    rejected.push(problem);

                    return;
                }

                const identity = fileIdentity(file);

                if (known.indexOf(identity) !== -1) {
                    return;
                }

                known.push(identity);
                accepted.push({
                    key: KS.uid('file'),
                    identity,
                    file,
                    name: file.name || t('composer_pasted_image'),
                    size: file.size,
                    url: trackUrl(URL.createObjectURL(file)),
                    status: 'pending',
                    progress: 0,
                    error: null,
                });
            });

            if (accepted.length) {
                commitFiles(filesRef.current.concat(accepted));
            }

            if (rejected.length) {
                const extra = rejected.length > 2 ? ' ' + t('composer_more_rejected', { count: rejected.length - 2 }) : '';

                KS.toast.error(rejected.slice(0, 2).join(' ') + extra);
            }
        };

        const removeFile = (key) => {
            const target = filesRef.current.find((item) => item.key === key);

            if (!target || target.status === 'uploading') {
                return;
            }

            releaseUrl(target.url);
            commitFiles(filesRef.current.filter((item) => item.key !== key));
        };

        const moveFile = (key, delta) => {
            const list = filesRef.current.slice();
            const from = list.findIndex((item) => item.key === key);
            const to = from + delta;

            if (from === -1 || to < 0 || to >= list.length) {
                return;
            }

            const moved = list.splice(from, 1)[0];

            list.splice(to, 0, moved);
            commitFiles(list);
        };

        const moveFileTo = (fromKey, toKey) => {
            const list = filesRef.current.slice();
            const from = list.findIndex((item) => item.key === fromKey);
            const to = list.findIndex((item) => item.key === toKey);

            if (from === -1 || to === -1 || from === to) {
                return;
            }

            const moved = list.splice(from, 1)[0];

            list.splice(to, 0, moved);
            commitFiles(list);
        };

        /** Kuyruk sirayla yuklenir; ilk hatada durur (galeri sirasi korunur). true = hepsi bitti. */
        const runPhotoQueue = async (contentId) => {
            commitFiles(filesRef.current.map((item) => (item.status === 'error' ? Object.assign({}, item, { status: 'pending', error: null, progress: 0 }) : item)));

            for (;;) {
                if (!mounted.current || cancelRef.current) {
                    return false;
                }

                const next = filesRef.current.find((item) => item.status === 'pending');

                if (!next) {
                    return true;
                }

                patchFile(next.key, { status: 'uploading', progress: 0, error: null });

                const data = new FormData();

                data.append('file', next.file, next.name);

                const job = api.upload(url('media.store', contentId), data, (ratio) => patchFile(next.key, { progress: ratio }));

                jobRef.current = job;

                try {
                    const response = await job;

                    jobRef.current = null;
                    changedSomething.current = true;

                    applyOwn(response && response.content);
                    patchFile(next.key, { status: 'done', progress: 1 });
                } catch (error) {
                    jobRef.current = null;

                    if (error && error.aborted) {
                        patchFile(next.key, { status: 'pending', progress: 0 });

                        return false;
                    }

                    patchFile(next.key, { status: 'error', progress: 0, error: KS.describeError(error) || t('error_generic') });

                    if (error && (error.status === 401 || error.status === 419)) {
                        KS.handleError(error);
                    }

                    return false;
                }
            }
        };

        /* ---- Video ------------------------------------------------------------ */
        const clearVideo = () => {
            const current = videoRef.current;

            if (!current) {
                return;
            }

            if (current.token) {
                abortRemoteUpload(current.token);
            }

            releaseUrl(current.posterUrl);
            commitVideo(null);
        };

        const pickVideo = (list) => {
            const file = (list || [])[0];

            if (!file) {
                return;
            }

            const problem = checkVideo(file);

            if (problem) {
                KS.toast.error(problem);

                return;
            }

            clearVideo();

            const key = KS.uid('video');
            const next = {
                key,
                file,
                name: file.name || t('composer_type_video'),
                size: file.size,
                mime: mimeOf(file),
                status: 'pending',
                progress: 0,
                error: null,
                token: null,
                chunkBytes: 0,
                meta: null,
                posterUrl: null,
                probing: true,
                playable: true,
                probe: null,
            };

            next.probe = probeVideo(file).then((result) => {
                if (mounted.current && videoRef.current && videoRef.current.key === key) {
                    patchVideo({
                        probing: false,
                        playable: !!result,
                        meta: result ? result.meta : null,
                        posterUrl: result && result.poster ? trackUrl(URL.createObjectURL(result.poster)) : null,
                    });
                }

                return result;
            }).catch(() => null);

            commitVideo(next);
            setErrors((current) => (current.video ? KS.omit(current, ['video']) : current));
        };

        const cancelVideoUpload = () => {
            const current = videoRef.current;

            cancelRef.current = true;

            if (jobRef.current && typeof jobRef.current.abort === 'function') {
                jobRef.current.abort();
            }

            if (current && current.token) {
                abortRemoteUpload(current.token);
            }

            patchVideo({ status: 'pending', progress: 0, token: null, error: null });
        };

        /** Parcali yukleme (F2, H8). true = video eklendi. */
        const runVideoUpload = async (contentId) => {
            const started = videoRef.current;

            if (!started || started.status === 'done') {
                return true;
            }

            const key = started.key;
            const alive = () => mounted.current && !cancelRef.current && videoRef.current && videoRef.current.key === key;

            patchVideo({ status: 'uploading', error: null });

            try {
                const probe = started.probe ? await started.probe : null;
                const meta = (probe && probe.meta) || {};
                const metaBody = {};

                ['width', 'height', 'duration_seconds'].forEach((name) => {
                    if (meta[name]) {
                        metaBody[name] = meta[name];
                    }
                });

                if (!alive()) {
                    throw cancelledError();
                }

                let token = videoRef.current.token;
                let chunkBytes = videoRef.current.chunkBytes;
                let index = 0;

                // Onceki denemeden kalan oturum varsa kaldigi yerden surdurulur.
                if (token) {
                    try {
                        const progress = await api.get(url('uploads.status', { token }));

                        index = Number(progress && progress.next_index) || 0;
                    } catch (error) {
                        if (error && (error.network || error.status === 401 || error.status === 419 || error.status === 403)) {
                            throw error;
                        }

                        // Oturum dusmus (suresi dolmus ya da temizlenmis): bastan baslanir.
                        token = null;
                    }
                }

                if (!token) {
                    const begun = await api.post(url('uploads.begin'), Object.assign({
                        content_id: contentId,
                        name: started.name,
                        size: started.size,
                        mime: started.mime,
                    }, metaBody));

                    token = begun && begun.token;
                    chunkBytes = Number(begun && begun.chunk_bytes) || (Number(limits.chunk_kb) || 5120) * 1024;
                    index = 0;

                    if (!token) {
                        throw new Error(t('error_generic'));
                    }

                    patchVideo({ token, chunkBytes });
                }

                const total = Math.max(1, Math.ceil(started.size / chunkBytes));
                let attempts = 0;

                while (index < total) {
                    if (!alive()) {
                        throw cancelledError();
                    }

                    const offset = index * chunkBytes;
                    const current = index;
                    const data = new FormData();

                    data.append('index', String(current));
                    data.append('chunk', started.file.slice(offset, Math.min(started.size, offset + chunkBytes)), 'chunk-' + current + '.part');

                    const job = api.upload(url('uploads.chunk', { token }), data, (ratio) => {
                        if (videoRef.current && videoRef.current.key === key && videoRef.current.status === 'uploading') {
                            patchVideo({ progress: Math.min(1, (current + ratio) / total) });
                        }
                    });

                    jobRef.current = job;

                    try {
                        const received = await job;

                        jobRef.current = null;
                        attempts = 0;
                        index = received && typeof received.next_index === 'number' ? received.next_index : current + 1;
                    } catch (error) {
                        jobRef.current = null;

                        if (error && error.aborted) {
                            throw error;
                        }

                        const transient = !!error && (error.network || !error.status || error.status >= 500 || error.status === 408 || error.status === 429);

                        if (!transient || attempts >= CHUNK_RETRIES) {
                            throw error;
                        }

                        attempts += 1;
                        await wait(1200 * attempts);

                        if (!alive()) {
                            throw cancelledError();
                        }

                        // Ag kesintisi: sunucunun bildirdigi siradaki parcadan devam edilir.
                        const progress = await api.get(url('uploads.status', { token }));

                        index = Number(progress && progress.next_index) || 0;
                    }
                }

                if (!alive()) {
                    throw cancelledError();
                }

                patchVideo({ status: 'finishing', progress: 1 });

                const completed = await api.post(url('uploads.complete', { token }), Object.assign({ content_id: contentId }, metaBody));

                changedSomething.current = true;
                applyOwn(completed && completed.content);

                const mediaId = completed && completed.media ? (completed.media.id || completed.media.media_id) : null;

                // Kapak karesi: cozulemediyse ya da yuklenemediyse sessizce atlanir (H8).
                if (probe && probe.poster && mediaId && KS.hasEndpoint('media.poster')) {
                    try {
                        const posterData = new FormData();

                        posterData.append('file', probe.poster, 'poster.jpg');

                        const postered = await api.form(url('media.poster', mediaId), posterData);

                        applyOwn(postered && postered.content);
                    } catch (error) {
                        // Kapak istege baglidir.
                    }
                }

                if (videoRef.current && videoRef.current.key === key) {
                    patchVideo({ status: 'done', progress: 1, token: null, error: null });
                }

                KS.actions.refreshStorage();

                return true;
            } catch (error) {
                jobRef.current = null;

                if (error && (error.aborted || error.cancelled)) {
                    if (mounted.current && videoRef.current && videoRef.current.key === key && videoRef.current.status !== 'pending') {
                        patchVideo({ status: 'pending', progress: 0 });
                    }

                    return false;
                }

                if (videoRef.current && videoRef.current.key === key) {
                    // Birlestirme asamasinda dusen oturum yeniden kullanilamaz.
                    const finishing = videoRef.current.status === 'finishing';

                    patchVideo({
                        status: 'error',
                        error: KS.describeError(error) || t('error_generic'),
                        token: finishing && error && error.status && error.status < 500 ? null : videoRef.current.token,
                    });
                }

                if (error && (error.status === 401 || error.status === 419)) {
                    KS.handleError(error);
                }

                return false;
            }
        };

        const switchVideoMode = (mode) => {
            if (mode === form.video_mode || busy) {
                return;
            }

            if (mode === 'link' && videoRef.current) {
                clearVideo();
            }

            setField('video_mode', mode);
            setErrors((current) => (current.video_url ? KS.omit(current, ['video_url']) : current));
        };

        /* ---- Dogrulama ---------------------------------------------------------- */
        const validate = (mode) => {
            const found = {};
            const title = String(form.title || '').trim();

            if (!form.profile_id) {
                found.profile_id = t('composer_profile_required');
            }

            if (title === '') {
                found.title = t('required_field');
            } else if (charCount(title) > TITLE_MAX) {
                found.title = t('composer_title_too_long', { max: fmt.number(TITLE_MAX) });
            }

            if (!form.platforms.length) {
                found.platforms = t('composer_platforms_required');
            }

            if (charCount(form.caption) > captionMax) {
                found.caption = t('composer_caption_too_long', { max: fmt.number(captionMax) });
            }

            if (type === 'short_text') {
                const length = charCount(String(form.body_text || '').trim());

                if (length === 0 && mode !== 'save_first') {
                    found.body_text = t('composer_body_required');
                } else if (length > hardLimit) {
                    found.body_text = t('composer_body_too_long', { max: fmt.number(hardLimit) });
                }
            }

            if (rich && plainBody === null) {
                if (isHtmlEmpty(form.body_html)) {
                    if (mode !== 'save_first') {
                        found.body_html = t('composer_body_required');
                    }
                } else if (byteLength(form.body_html) > htmlMaxBytes) {
                    found.body_html = t('composer_body_html_too_large', { max: fmt.bytes(htmlMaxBytes) });
                }
            }

            if (rich && plainBody !== null && String(plainBody).trim() === '' && mode !== 'save_first') {
                found.body_html = t('composer_body_required');
            }

            if (type === 'video' && form.video_mode === 'link') {
                const link = String(form.video_url || '').trim();

                if (link !== '' && (!/^https?:\/\/\S+$/i.test(link) || link.length > URL_MAX || !KS.safeUrl(link))) {
                    found.video_url = t('composer_video_url_invalid');
                }
            }

            return found;
        };

        const showErrors = (found) => {
            setErrors(found);
            setErrorTick((current) => current + 1);
        };

        /** Sunucunun alan hatalarini (422 errors) form alanlarina esler. */
        const mapServerErrors = (error) => {
            const found = {};

            Object.keys((error && error.errors) || {}).forEach((name) => {
                const base = name.split('.')[0];
                const list = error.errors[name];
                const message = Array.isArray(list) ? list[0] : list;

                if (message && !found[base]) {
                    found[base] = String(message);
                }
            });

            return found;
        };

        /* ---- Kaydetme ------------------------------------------------------------ */
        const effectiveForm = () => {
            if (!rich || plainBody === null) {
                return form;
            }

            // Duzenleyici yuklenemediyse duz metin paragraflara cevrilir (HTML kacirilarak).
            const escape = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const html = String(plainBody).split(/\n{2,}/).map((block) => block.trim()).filter(Boolean)
                .map((block) => '<p>' + escape(block).replace(/\n/g, '<br>') + '</p>').join('');

            return Object.assign({}, form, { body_html: html });
        };

        const videoSelected = () => type === 'video' && form.video_mode === 'file' && !!videoRef.current && videoRef.current.status !== 'done';

        const createContent = () => {
            if (creating.current) {
                return creating.current;
            }

            const snapshot = effectiveForm();
            const payload = Object.assign({ content_type: type }, buildPayload(snapshot, type, videoSelected()));

            const request = api.post(url('contents.store'), payload).then((response) => {
                const detail = response && response.content ? response.content : null;

                if (!detail || !detail.id) {
                    throw new Error(t('error_generic'));
                }

                createdHere.current = true;
                changedSomething.current = true;
                baseline.current = snapshot;
                adoptRecord(detail);
                KS.actions.applyContent(detail);
                KS.actions.refreshFeed();

                if (mounted.current && plainBody !== null) {
                    setForm(snapshot);
                    setPlainBody(null);
                }

                return detail;
            });

            creating.current = request.then((detail) => {
                creating.current = null;

                return detail;
            }, (error) => {
                creating.current = null;

                throw error;
            });

            return creating.current;
        };

        const updateContent = (target) => {
            const snapshot = effectiveForm();
            const changes = diffPayload(buildPayload(snapshot, type, videoSelected()), buildPayload(baseline.current, type, false));

            if (!Object.keys(changes).length) {
                return Promise.resolve(target);
            }

            return api.post(url('contents.update', target.id), Object.assign({ row_version: target.row_version }, changes)).then((response) => {
                const detail = response && response.content ? response.content : null;

                if (!detail || !detail.id) {
                    throw new Error(t('error_generic'));
                }

                changedSomething.current = true;
                baseline.current = snapshot;
                adoptRecord(detail);
                KS.actions.applyContent(detail);
                KS.actions.refreshFeed();

                if (mounted.current) {
                    setStale(false);

                    if (plainBody !== null) {
                        setForm(snapshot);
                        setPlainBody(null);
                    }

                    // Medyasi olan icerikte hesap sunucuda degismez; form gercege cekilir.
                    if (Number(detail.profile_id) !== Number(snapshot.profile_id)) {
                        baseline.current = Object.assign({}, snapshot, { profile_id: detail.profile_id });
                        setForm((current) => Object.assign({}, current, { profile_id: detail.profile_id }));
                    }
                }

                return detail;
            });
        };

        const handleSaveError = (error) => {
            if (!mounted.current) {
                return;
            }

            if (error && error.status === 409) {
                // Surum cakismasi: kullanicinin yazdiklari formda kalir (H2).
                setStale(true);
                setErrorTick((current) => current + 1);

                return;
            }

            if (error && error.status === 422 && error.errors) {
                const found = mapServerErrors(error);

                if (Object.keys(found).length) {
                    showErrors(found);
                }
            }

            KS.handleError(error);
        };

        const close = () => {
            if (closing.current) {
                return;
            }

            closing.current = true;

            if (typeof props.onClose === 'function') {
                props.onClose();
            } else {
                KS.actions.closeComposer();
            }
        };

        const finish = (detail) => {
            const target = detail || recordRef.current;

            if (changedSomething.current) {
                KS.toast.success(t(createdHere.current ? 'composer_created' : 'composer_updated'));
            }

            close();

            if (target && target.id) {
                if (createdHere.current && Number(KS.store.getState().profileId) !== Number(target.profile_id)) {
                    KS.actions.setProfile(target.profile_id);
                }

                KS.actions.openDetail(target.id);
            }
        };

        const runUploads = (contentId) => {
            cancelRef.current = false;

            if (type === 'photo') {
                return runPhotoQueue(contentId);
            }

            if (type === 'video' && form.video_mode === 'file') {
                return runVideoUpload(contentId);
            }

            return Promise.resolve(true);
        };

        const submit = async () => {
            if (busy || readOnly || !type) {
                return;
            }

            const found = validate('save');

            if (Object.keys(found).length) {
                showErrors(found);

                return;
            }

            setErrors({});
            setBusy('saving');

            let target = recordRef.current;

            try {
                target = target ? await updateContent(target) : await createContent();
            } catch (error) {
                handleSaveError(error);

                if (mounted.current) {
                    setBusy(null);
                }

                return;
            }

            if (!mounted.current) {
                return;
            }

            if (hasPendingUploads) {
                setBusy('uploading');
                setAttempted(true);

                const complete = await runUploads(target.id);

                if (!mounted.current) {
                    return;
                }

                setBusy(null);

                if (!complete) {
                    return;
                }
            } else {
                setBusy(null);
            }

            finish(recordRef.current || target);
        };

        const retryUploads = async () => {
            const target = recordRef.current;

            if (busy || !target) {
                return;
            }

            setBusy('uploading');
            setAttempted(true);

            const complete = await runUploads(target.id);

            if (!mounted.current) {
                return;
            }

            setBusy(null);

            if (complete && !formChanged) {
                finish(recordRef.current || target);
            }
        };

        /* ---- Metin ici gorsel: "once kaydet" kurali (H8) --------------------------- */
        const uploadInlineImage = async (file) => {
            const problem = file ? checkImage(file) : t('error_generic');

            if (problem) {
                KS.toast.error(problem);

                throw Object.assign(new Error(problem), { aborted: true });
            }

            if (readOnly || (busy && busy !== 'inline')) {
                throw cancelledError();
            }

            let target = recordRef.current;

            if (!target) {
                const found = validate('save_first');

                if (Object.keys(found).length) {
                    showErrors(found);
                    KS.toast.error(t('composer_save_first_fields'));

                    throw cancelledError();
                }

                const accepted = await KS.confirm({
                    title: t('composer_save_first_title'),
                    text: t('save_first'),
                    confirmLabel: t('composer_save_and_continue'),
                    icon: 'save',
                });

                if (!accepted || !mounted.current) {
                    throw cancelledError();
                }
            }

            setBusy('inline');

            try {
                if (!target) {
                    target = await createContent();
                }

                const data = new FormData();

                data.append('file', file, file.name || 'image');
                data.append('inline', '1');

                const response = await api.upload(url('media.store', target.id), data);
                const media = response && response.media ? response.media : null;
                const source = KS.safeUrl(media && (media.preview_url || media.url));

                inlineAdded.current = true;
                changedSomething.current = true;
                applyOwn(response && response.content);

                if (!source) {
                    throw new Error(t('error_generic'));
                }

                return source;
            } catch (error) {
                if (error && error.status === 422 && error.errors && !recordRef.current) {
                    const found = mapServerErrors(error);

                    if (Object.keys(found).length && mounted.current) {
                        showErrors(found);
                    }
                }

                KS.handleError(error);

                throw error;
            } finally {
                if (mounted.current) {
                    setBusy(null);
                }
            }
        };

        const uploadInlineRef = useRef(uploadInlineImage);

        uploadInlineRef.current = uploadInlineImage;

        const onUploadImage = useCallback((file) => uploadInlineRef.current(file), []);

        /* ---- 409: yeniden yukle / metnimi kopyala ----------------------------------- */
        const myText = () => {
            const snapshot = effectiveForm();
            const parts = [String(snapshot.title || '').trim()];

            if (type === 'short_text') {
                parts.push(String(snapshot.body_text || '').trim());
            } else if (rich) {
                parts.push(KS.htmlToPlainText(snapshot.body_html || ''));
            }

            parts.push(String(snapshot.caption || '').trim());

            return parts.filter(Boolean).join('\n\n');
        };

        const reloadStale = async () => {
            const target = recordRef.current;

            if (!target || busy) {
                return;
            }

            const accepted = await KS.confirm({
                title: t('composer_stale_reload_title'),
                text: t('composer_stale_reload_confirm'),
                confirmLabel: t('reload'),
                danger: true,
                icon: 'refresh',
            });

            if (!accepted || !mounted.current) {
                return;
            }

            setBusy('reloading');

            try {
                const detail = await KS.actions.fetchContent(target.id);

                if (!mounted.current) {
                    return;
                }

                const next = formFromContent(detail);

                adoptRecord(detail);
                baseline.current = next;
                setForm(next);
                setPlainBody(null);
                setErrors({});
                setStale(false);
                setEditorKey((current) => current + 1);
            } catch (error) {
                KS.handleError(error);
            } finally {
                if (mounted.current) {
                    setBusy(null);
                }
            }
        };

        /* ---- Kapatma korumasi --------------------------------------------------------- */
        const guardClose = () => {
            if (closing.current) {
                return Promise.resolve(true);
            }

            if (busy === 'uploading' || busy === 'inline') {
                return KS.confirm({
                    title: t('composer_close_uploading_title'),
                    text: t('composer_close_uploading_text'),
                    confirmLabel: t('composer_close_anyway'),
                    cancelLabel: t('composer_keep_uploading'),
                    danger: true,
                });
            }

            if (busy) {
                return Promise.resolve(false);
            }

            if (dirty) {
                return KS.confirm({
                    title: t('confirm_title'),
                    text: recordRef.current && hasPendingUploads && !formChanged ? t('composer_close_pending_text') : t('unsaved_changes_confirm'),
                    confirmLabel: t('discard'),
                    cancelLabel: t('keep_editing'),
                    danger: true,
                });
            }

            return Promise.resolve(true);
        };

        const guardRef = useRef(guardClose);

        guardRef.current = guardClose;

        useEffect(() => {
            // Geri tusu: cekirdek kapatmadan once bu korumaya sorar (H3).
            KS.router.setGuard('composer', () => guardRef.current());

            return () => KS.router.setGuard('composer', null);
        }, []);

        const requestClose = () => {
            guardRef.current().then((allowed) => {
                if (allowed && mounted.current) {
                    close();
                }
            }).catch(() => undefined);
        };

        // Sekme kapatilirken de uyarilir (yukleme ya da kaydedilmemis degisiklik varken).
        const leaveWarning = dirty || busy === 'uploading' || busy === 'inline';

        useEffect(() => {
            if (!leaveWarning) {
                return undefined;
            }

            const onBeforeUnload = (event) => {
                event.preventDefault();
                event.returnValue = '';

                return '';
            };

            window.addEventListener('beforeunload', onBeforeUnload);

            return () => window.removeEventListener('beforeunload', onBeforeUnload);
        }, [leaveWarning]);

        /* ---- Tur secimi ------------------------------------------------------------------ */
        const pickType = (value) => {
            if (record || !isValidType(value)) {
                return;
            }

            focusTitleOnType.current = true;
            setErrors({});
            setPickedType(value);
        };

        const changeType = async () => {
            if (record || busy) {
                return;
            }

            const hasTypeContent = filesRef.current.length > 0 || !!videoRef.current
                || String(form.body_text || '').trim() !== '' || !isHtmlEmpty(form.body_html);

            if (hasTypeContent) {
                const accepted = await KS.confirm({
                    title: t('composer_change_type'),
                    text: t('composer_change_type_confirm'),
                    confirmLabel: t('composer_change_type'),
                    danger: true,
                });

                if (!accepted || !mounted.current || recordRef.current) {
                    return;
                }
            }

            // Ture ozel secimler birakilir; ortak alanlar (baslik, platform, tarih ...) korunur.
            filesRef.current.forEach((item) => releaseUrl(item.url));
            commitFiles([]);
            clearVideo();
            setErrors({});
            setForm((current) => Object.assign({}, current, { body_text: '', body_html: '', video_url: '', video_mode: 'file', image_format: 'original' }));
            setPlainBody(null);
            setEditorKey((current) => current + 1);
            setPickedType(null);
        };

        /* ================================================================ */
        /* Cizim                                                            */
        /* ================================================================ */

        const title = record ? t('composer_title_edit') : t('composer_title_new');

        if (!type) {
            return h(Modal, {
                title,
                subtitle: t('composer_pick_type_subtitle'),
                icon: 'plus',
                size: 'lg',
                className: 'ks-composer ks-composer--picker',
                closeOnBackdrop: false,
                onClose: requestClose,
            }, h(TypePicker, { onPick: pickType }));
        }

        /* ---- Ture ozel alan ---- */
        let typeArea = null;

        if (type === 'photo') {
            const totalImages = existingMedia.length + files.length;
            const sortable = !locked && files.length > 1 && files.every((item) => item.status === 'pending');

            typeArea = h('section', { className: 'ks-composer-block' },
                h('header', { className: 'ks-composer-block__head' },
                    h('h3', { className: 'ks-composer-block__title' }, t('composer_photos')),
                    totalImages > 1
                        ? h(Badge, { color: 'sky', icon: 'layers' }, t('composer_carousel_badge', { count: totalImages }))
                        : null),
                h(ExistingMedia, { media: existingMedia }),
                h(Dropzone, {
                    accept: imageMimes,
                    multiple: true,
                    paste: !locked,
                    disabled: locked,
                    compact: totalImages > 0,
                    icon: 'images',
                    title: t(totalImages > 0 ? 'composer_photo_dropzone_more' : 'composer_photo_dropzone_title'),
                    hint: t('composer_photo_dropzone_hint', { max: fmt.bytes(maxImageBytes) }),
                    onFiles: addPhotoFiles,
                }),
                h(PhotoQueue, {
                    items: files,
                    format: form.image_format,
                    offset: existingMedia.length,
                    sortable,
                    locked,
                    onMove: moveFile,
                    onMoveTo: moveFileTo,
                    onRemove: removeFile,
                    onRetry: retryUploads,
                }),
                sortable ? h('p', { className: 'ks-composer-block__hint' }, h(Icon, { name: 'drag' }), h('span', null, t('composer_reorder_hint'))) : null,
                totalImages === 0
                    ? h(Notice, { tone: 'neutral', compact: true }, t('composer_photo_empty_hint'))
                    : null,
                h(Field, { label: t('composer_field_format'), group: true, hint: t('composer_format_hint') },
                    h(FormatPicker, { value: form.image_format, disabled: locked, onChange: (value) => setField('image_format', value) })));
        } else if (type === 'video') {
            const isLarge = !!video && video.size > preferLinkBytes;
            const isMov = !!video && (extensionOf(video.name) === 'mov' || video.mime === 'video/quicktime');

            typeArea = h('section', { className: 'ks-composer-block' },
                h('header', { className: 'ks-composer-block__head' },
                    h('h3', { className: 'ks-composer-block__title' }, t('composer_video')),
                    existingVideo ? null : h(Segmented, {
                        label: t('composer_video_source'),
                        value: form.video_mode,
                        onChange: switchVideoMode,
                        items: [
                            { value: 'file', label: t('composer_video_mode_file'), icon: 'upload', disabled: locked },
                            { value: 'link', label: t('composer_video_mode_link'), icon: 'link', disabled: locked },
                        ],
                    })),
                h(ExistingMedia, { media: existingMedia }),
                existingVideo ? null : (form.video_mode === 'file'
                    ? h('div', { className: 'ks-stack ks-stack--sm' },
                        storage && storage.low ? h(Notice, { tone: 'warning', compact: true, icon: 'hdd' }, t('storage_low')) : null,
                        video
                            ? h(VideoCard, { video, locked, onCancel: cancelVideoUpload, onRetry: retryUploads, onRemove: clearVideo })
                            : h(Dropzone, {
                                accept: videoMimes,
                                multiple: false,
                                disabled: locked,
                                icon: 'video',
                                title: t('composer_video_dropzone_title'),
                                hint: t('composer_video_dropzone_hint', { max: fmt.bytes(maxVideoBytes) }),
                                onFiles: pickVideo,
                            }),
                        isLarge && video.status !== 'done' ? h(Notice, {
                            tone: 'warning',
                            compact: true,
                            action: locked ? null : h(Button, { size: 'sm', variant: 'soft', icon: 'link', onClick: () => switchVideoMode('link') }, t('composer_video_mode_link')),
                        }, t('video_prefer_link')) : null,
                        isMov ? h(Notice, { tone: 'info', compact: true }, t('composer_mov_hint')) : null,
                        video && !video.probing && !video.playable ? h(Notice, { tone: 'neutral', compact: true }, t('composer_video_not_previewable')) : null,
                        !video && existingMedia.length === 0 ? h(Notice, { tone: 'neutral', compact: true }, t('composer_video_empty_hint')) : null)
                    : h(Field, { label: t('composer_field_video_url'), error: errors.video_url, hint: t('composer_video_link_hint') },
                        h(TextInput, {
                            type: 'url',
                            inputMode: 'url',
                            icon: 'link',
                            value: form.video_url,
                            maxLength: URL_MAX,
                            placeholder: 'https://',
                            clearable: !locked,
                            disabled: locked,
                            onChange: (value) => setField('video_url', value),
                        }))));
        } else if (type === 'short_text') {
            const length = charCount(form.body_text);
            const over = length - softLimit;

            typeArea = h('section', { className: 'ks-composer-block' },
                h(Field, { label: t('composer_field_short_text'), required: true, error: errors.body_text },
                    h(TextArea, {
                        value: form.body_text,
                        rows: 6,
                        autoGrow: true,
                        maxRows: 20,
                        maxLength: hardLimit,
                        disabled: locked,
                        placeholder: t('composer_short_text_placeholder'),
                        className: 'ks-composer-short',
                        onChange: (value) => setField('body_text', value),
                    })),
                h('div', { className: 'ks-composer-counter', 'aria-live': 'polite' },
                    h(CharRing, { count: length, soft: softLimit, hard: hardLimit }),
                    h('span', { className: 'ks-composer-counter__text' }, fmt.number(length) + ' / ' + fmt.number(softLimit)),
                    length >= hardLimit
                        ? h('span', { className: 'ks-composer-counter__note is-full' }, t('composer_short_hard_reached', { max: fmt.number(hardLimit) }))
                        : (over > 0 ? h('span', { className: 'ks-composer-counter__note is-over' }, t('composer_short_over_soft', { count: fmt.number(over), max: fmt.number(softLimit) })) : null)));
        } else {
            // Duzenleyici baska bir dosyada tanimlidir: cizim aninda aranir (yukleme sirasi sozlesmesi).
            const RichEditor = typeof KS.RichEditor === 'function' ? KS.RichEditor : null;
            const label = t(type === 'blog' ? 'composer_field_blog' : 'composer_field_article');

            typeArea = h('section', { className: 'ks-composer-block' },
                RichEditor
                    ? h(Field, { label, required: true, group: true, error: errors.body_html, hint: record ? null : t('composer_editor_image_hint') },
                        h('div', { className: cx('ks-composer-editor', errors.body_html && 'is-invalid') },
                            h(RichEditor, {
                                key: 'editor-' + editorKey,
                                value: form.body_html,
                                onChange: (html) => setField('body_html', html || ''),
                                placeholder: t(type === 'blog' ? 'composer_blog_placeholder' : 'composer_article_placeholder'),
                                onUploadImage,
                                minHeight: isMobile ? 280 : 420,
                                mode: type === 'blog' ? 'blog' : 'article',
                                disabled: readOnly || busy === 'saving' || busy === 'uploading' || busy === 'reloading',
                            })))
                    : h('div', { className: 'ks-stack ks-stack--sm' },
                        h(Notice, { tone: 'warning', compact: true }, t('composer_editor_missing')),
                        h(Field, { label, required: true, error: errors.body_html },
                            h(TextArea, {
                                value: plainBody !== null ? plainBody : KS.htmlToPlainText(form.body_html || ''),
                                rows: 12,
                                autoGrow: true,
                                maxRows: 28,
                                disabled: locked,
                                onChange: (value) => {
                                    setPlainBody(value);
                                    setErrors((current) => (current.body_html ? KS.omit(current, ['body_html']) : current));
                                },
                            }))));
        }

        /* ---- Aciklama ---- */
        const showCaption = type === 'photo' || type === 'video' || type === 'blog' || String(baseline.current.caption || '') !== '' || String(form.caption || '') !== '';
        const captionField = showCaption ? h(Field, {
            label: t(type === 'blog' ? 'composer_field_share_text' : 'composer_field_caption'),
            error: errors.caption,
            hint: t(type === 'blog' ? 'composer_share_text_hint' : 'composer_caption_hint'),
        }, h(TextArea, {
            value: form.caption,
            rows: 4,
            autoGrow: true,
            maxRows: 14,
            maxLength: captionMax,
            counter: true,
            disabled: locked,
            placeholder: t('composer_caption_placeholder'),
            onChange: (value) => setField('caption', value),
        })) : null;

        /* ---- Yan panel: hesap, platformlar, kategori, plan ---- */
        const profileLinks = selectedProfile && Array.isArray(selectedProfile.links) ? selectedProfile.links.map((item) => String(item.platform)) : [];
        const plannedPast = !!form.planned_on && form.planned_on < fmt.todayYmd();

        const side = h('aside', { className: 'ks-composer-side' },
            h('div', { className: 'ks-composer-panel' },
                h(Field, {
                    label: t('composer_field_profile'),
                    required: true,
                    error: errors.profile_id,
                    hint: profileLocked ? t('composer_profile_locked') : (selectedProfile ? selectedProfile.kind_label : null),
                }, h(Select, {
                    options: profiles.map((item) => ({ value: item.id, label: item.name })),
                    value: form.profile_id,
                    placeholder: form.profile_id ? false : undefined,
                    clearable: false,
                    icon: 'user',
                    disabled: locked || profileLocked,
                    onChange: (value) => setField('profile_id', value),
                })),
                h(Field, { label: t('composer_field_platforms'), required: true, group: true, error: errors.platforms, hint: t('composer_platforms_hint') },
                    h(PlatformPicker, { value: form.platforms, disabled: locked, onChange: (value) => setField('platforms', value) })),
                profileLinks.length && !locked && profileLinks.some((item) => form.platforms.indexOf(item) === -1)
                    ? h(Button, {
                        variant: 'link',
                        size: 'sm',
                        icon: 'check',
                        className: 'ks-composer-panel__link',
                        onClick: () => setField('platforms', (KS.options('platforms') || []).map((item) => String(item.value)).filter((item) => form.platforms.indexOf(item) !== -1 || profileLinks.indexOf(item) !== -1)),
                    }, t('composer_platforms_from_profile'))
                    : null),
            h('div', { className: 'ks-composer-panel' },
                h(Field, { label: t('composer_field_category'), error: errors.category_id },
                    h(Select, {
                        options: categoryOptions,
                        value: form.category_id,
                        placeholder: t('composer_no_category'),
                        icon: 'tag',
                        disabled: locked,
                        onChange: (value) => setField('category_id', value),
                    })),
                h('div', { className: 'ks-composer-plan' },
                    h(Field, { label: t('composer_field_planned_on'), error: errors.planned_on, hint: plannedPast ? t('composer_planned_past_hint') : null },
                        h(DateInput, {
                            value: form.planned_on,
                            disabled: locked,
                            onChange: (value) => {
                                setField('planned_on', value);

                                if (!value) {
                                    setField('planned_time', '');
                                }
                            },
                        })),
                    h(Field, { label: t('composer_field_planned_time'), error: errors.planned_time, hint: form.planned_on ? null : t('composer_time_needs_date') },
                        h(TimeInput, {
                            value: form.planned_time,
                            disabled: locked || !form.planned_on,
                            onChange: (value) => setField('planned_time', value),
                        })))));

        /* ---- Ust serit ---- */
        const strip = h('div', { className: 'ks-composer-strip' },
            h('span', { className: 'ks-composer-strip__icon', 'aria-hidden': 'true' }, h(Icon, { name: TYPE_ICONS[type] || 'document' })),
            h('div', { className: 'ks-composer-strip__text' },
                h('span', { className: 'ks-composer-strip__label' }, typeLabel(type)),
                h('span', { className: 'ks-composer-strip__desc' }, record ? t('composer_type_locked') : t('composer_type_' + type + '_desc'))),
            record
                ? h('div', { className: 'ks-composer-strip__meta' },
                    h(Badge, { color: 'stone', icon: 'hash' }, record.content_no),
                    h(StatusBadge, { status: record.status, label: record.status_label, color: record.status_color }))
                : h(Button, { variant: 'link', size: 'sm', icon: 'back', disabled: !!busy, onClick: changeType }, t('composer_change_type')));

        /* ---- Uyarilar ---- */
        const notices = [
            stale ? h('div', { key: 'stale', className: 'ks-composer-stale', tabIndex: -1 },
                h(Notice, {
                    tone: 'warning',
                    title: t('composer_stale_title'),
                    action: h('div', { className: 'ks-composer-stale__actions' },
                        h(CopyButton, { getText: myText, label: t('composer_copy_my_text'), variant: 'soft', size: 'sm' }),
                        h(Button, { variant: 'primary', size: 'sm', icon: 'refresh', loading: busy === 'reloading', disabled: !!busy && busy !== 'reloading', onClick: reloadStale }, t('reload'))),
                }, t('stale_reload'))) : null,
            readOnly ? h(Notice, { key: 'readonly', tone: 'danger' }, t(record && record.is_published ? 'unpublish_to_edit' : 'composer_not_editable')) : null,
            record && !readOnly && record.status === 'approved' ? h(Notice, { key: 'reapproval', tone: 'info', compact: true }, t('composer_reapproval_hint')) : null,
            createdHere.current && record && hasPendingUploads && !busy && attempted
                ? h(Notice, { key: 'paused', tone: 'warning', compact: true }, t(failedUploads ? 'composer_upload_paused' : 'composer_upload_cancelled'))
                : null,
        ];

        const titleLength = charCount(form.title);
        const titleInputProps = {
            value: form.title,
            size: 'lg',
            maxLength: TITLE_MAX,
            disabled: locked,
            inputRef: titleRef,
            placeholder: t('composer_title_placeholder'),
            onChange: (value) => setField('title', value),
        };

        if (!record && !isMobile) {
            titleInputProps['data-autofocus'] = '';
        }

        const body = h('div', { ref: formRef, className: 'ks-composer-form' },
            notices,
            strip,
            h('div', { className: 'ks-composer-layout' },
                h('div', { className: 'ks-composer-main' },
                    h(Field, { label: t('composer_field_title'), required: true, error: errors.title, counter: fmt.number(titleLength) + ' / ' + fmt.number(TITLE_MAX), hint: t('composer_title_hint') },
                        h(TextInput, titleInputProps)),
                    typeArea,
                    captionField),
                side));

        /* ---- Alt cubuk ---- */
        let info = null;

        if (busy === 'uploading' && type === 'photo') {
            const doneCount = files.filter((item) => item.status === 'done').length;

            info = t('composer_uploading_files', { current: Math.min(files.length, doneCount + 1), total: files.length });
        } else if (busy === 'uploading') {
            info = t(video && video.status === 'finishing' ? 'composer_video_finishing' : 'composer_video_uploading');
        } else if (busy === 'saving' || busy === 'inline') {
            info = t('saving');
        } else if (record && createdHere.current) {
            info = t('composer_saved_as', { number: record.content_no });
        } else if (!record) {
            info = t('composer_status_hint');
        } else if (record.updated_at) {
            info = t('composer_last_updated', { time: fmt.relative(record.updated_at) });
        }

        const canContinuePartial = !!record && hasPendingUploads && attempted && !busy;

        const footer = [
            h('div', { key: 'info', className: 'ks-composer-foot__info', 'aria-live': 'polite' },
                busy ? h(KS.Spinner, { size: 'sm', label: info }) : h('span', null, info)),
            h(Button, { key: 'cancel', variant: 'ghost', onClick: requestClose, disabled: busy === 'saving' || busy === 'reloading' }, t(record && !dirty ? 'close' : 'cancel')),
            canContinuePartial
                ? h(Button, { key: 'partial', variant: 'soft', icon: 'arrow-right', onClick: () => finish(recordRef.current) }, t('composer_continue_partial'))
                : null,
            canContinuePartial && !formChanged
                ? h(Button, { key: 'retry', variant: 'primary', icon: 'refresh', onClick: retryUploads }, t('retry'))
                : h(Button, {
                    key: 'save',
                    variant: 'primary',
                    icon: 'save',
                    loading: busy === 'saving' || busy === 'uploading',
                    disabled: readOnly || !!busy || (!!record && !dirty),
                    onClick: submit,
                }, busy === 'uploading' ? t('uploading') : (busy === 'saving' ? t('saving') : t('save'))),
        ];

        return h(Modal, {
            title,
            subtitle: record ? record.content_no + ' · ' + typeLabel(type) : typeLabel(type),
            icon: TYPE_ICONS[type] || 'plus',
            size: 'xl',
            className: cx('ks-composer', 'ks-composer--form', 'ks-composer--' + type),
            bodyClassName: 'ks-composer__body',
            closeOnBackdrop: false,
            onClose: requestClose,
            footer,
        }, body);
    }

    /* ================================================================== */
    /* 4. Kayit                                                            */
    /* ================================================================== */

    /**
     * KS.views.Composer({ state, onClose })
     * state verilmezse depodaki `composer` anahtari okunur; kapaliyken hicbir sey cizilmez.
     * Pencere her acilista bastan kurulur (form, kuyruk ve koruma o acilisa aittir).
     */
    KS.views.Composer = function Composer(props) {
        const stored = KS.useStore((state) => state.composer);
        const state = (props && props.state) || stored;

        if (!state || !state.open) {
            return null;
        }

        return h(ComposerDialog, { state, onClose: props ? props.onClose : undefined });
    };
}());
