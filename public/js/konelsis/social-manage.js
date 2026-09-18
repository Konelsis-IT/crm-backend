/*
 * Konelsis Sosyal Medya modulu - ANALIZ ve AYARLAR gorunumleri (B31, D-106, 18 Eylul 2026).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement), dis kutuphane yok.
 * social-core.js'ten SONRA yuklenir ve yalniz window.KonelsisSocial (KS) API'sini kullanir.
 * Stiller: resources/css/filament/konelsis-social.css, bolum MANAGE (sinif on eki ks-manage-).
 *
 * Kaydettikleri:
 *   KS.views.Analytics   Analiz: donem secimi, gostergeler, elle cizilen SVG grafikler (aylik
 *                        cubuklar, platform cubuklari, tur halkasi, takipci egrileri), atlanan
 *                        listeleri, platform ozet kutulari ve "Istatistik kayitlari" (liste +
 *                        ekle / duzenle penceresi, istege bagli rapor dosyasi).
 *   KS.views.Settings    Ayarlar (manage_data): kategoriler, ozel gunler, hesap baglantilari ve
 *                        (yalniz manage_settings) sorumlu gorevler.
 *   KS.manage            { openSettings(sekme), openMetricForm() } - baska gorunumlerin ayar
 *                        sekmesine / istatistik penceresine yonlendirmesi icin.
 *
 * Uclar: analytics, metrics, metrics.store, metrics.update, categories.store, categories.update,
 * days, days.store, days.update, profiles.update, responsibles, responsibles.sync.
 *
 * Kurallar: gorunen her metin KS.t() ile gelir; kimlik / ham enum degeri ekrana yazilmaz;
 * API'den gelen her adres KS.safeUrl'den (Button href) gecer; silme islemi yoktur (pasife alma var).
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, Fragment, t, api, url, fmt, cx } = KS;
    const { useState, useEffect, useRef, useMemo, useLayoutEffect } = KS;
    const {
        Icon, PlatformIcon, Button, IconButton, Badge, StatusBadge, Avatar, PersonLine, Modal, Tabs, Segmented,
        Field, TextInput, TextArea, Select, DateInput, Checkbox, Switch, Dropzone, Empty, ErrorState, Notice,
        Section, Skeleton, ProgressBar, LoadMore,
    } = KS;

    /* ================================================================== */
    /* 1. Sabitler ve kucuk yardimcilar                                    */
    /* ================================================================== */

    const EMPTY_LIST = Object.freeze([]);
    const METRICS = ['followers', 'posts_count', 'impressions', 'reach', 'engagements', 'profile_visits', 'link_clicks', 'video_views'];
    const SUMMARY_METRICS = ['posts_count', 'impressions', 'reach', 'engagements', 'profile_visits', 'link_clicks', 'video_views'];
    const REPORT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'xls', 'xlsx'];
    const TYPE_ICONS = { photo: 'image', video: 'video', short_text: 'text', long_text: 'document', blog: 'blog' };
    const TYPE_TONES = { photo: 'sky', video: 'violet', short_text: 'stone', long_text: 'teal', blog: 'amber' };
    const ENTRY_PAGE = 9;

    // Gorunumler arasi niyet: Ayarlar'in belirli sekmesine / istatistik penceresine gecis.
    let pendingSettingsTab = null;
    let pendingMetricForm = false;

    function useMounted() {
        const ref = useRef(true);

        useEffect(() => {
            ref.current = true;

            return () => {
                ref.current = false;
            };
        }, []);

        return ref;
    }

    /** Kap genisligini olcer (grafikler gercek piksel olcusunde cizilir; yazi kuculmez). */
    function useMeasure() {
        const [node, setNode] = useState(null);
        const [width, setWidth] = useState(0);

        useLayoutEffect(() => {
            if (!node) {
                return undefined;
            }

            const measure = () => {
                const next = Math.round(node.getBoundingClientRect().width);

                setWidth((current) => (current === next ? current : next));
            };

            measure();

            if (typeof window.ResizeObserver === 'function') {
                const observer = new window.ResizeObserver(measure);

                observer.observe(node);

                return () => observer.disconnect();
            }

            window.addEventListener('resize', measure);

            return () => window.removeEventListener('resize', measure);
        }, [node]);

        return [setNode, width];
    }

    /**
     * Grafik ipucu durumu: fare / kalem uzerine gelince gosterir; dokunmatik ekranda
     * dokunus ipucunu sabitler, ikinci dokunus ya da bos alana dokunus kapatir.
     */
    function useChartHover() {
        const [state, setState] = useState({ key: null, pinned: false });
        const lastPointer = useRef('mouse');

        const bind = (key) => ({
            onPointerDown: (event) => {
                lastPointer.current = event.pointerType || 'mouse';
            },
            onPointerEnter: (event) => {
                if (event.pointerType !== 'touch') {
                    setState((current) => (current.pinned ? current : { key, pinned: false }));
                }
            },
            onPointerLeave: (event) => {
                if (event.pointerType !== 'touch') {
                    setState((current) => (current.pinned ? current : { key: null, pinned: false }));
                }
            },
            onClick: (event) => {
                event.stopPropagation();

                if (lastPointer.current !== 'touch') {
                    return;
                }

                setState((current) => (current.pinned && current.key === key ? { key: null, pinned: false } : { key, pinned: true }));
            },
        });

        return { active: state.key, bind, clear: () => setState({ key: null, pinned: false }) };
    }

    function shiftDays(ymd, days) {
        const date = fmt.parse(ymd);

        return date ? fmt.toYmd(new Date(date.getFullYear(), date.getMonth(), date.getDate() + days)) : ymd;
    }

    function monthStart(ymd, offset) {
        const date = fmt.parse(ymd);

        return date ? fmt.toYmd(new Date(date.getFullYear(), date.getMonth() + (offset || 0), 1)) : ymd;
    }

    function monthEnd(ymd, offset) {
        const date = fmt.parse(ymd);

        return date ? fmt.toYmd(new Date(date.getFullYear(), date.getMonth() + (offset || 0) + 1, 0)) : ymd;
    }

    /** Donem on ayarlari ("bugun" kurum saatine goredir). */
    function presetRange(preset) {
        const now = fmt.todayYmd();

        if (preset === 'this_month') {
            return { from: monthStart(now, 0), to: monthEnd(now, 0) };
        }

        if (preset === 'last_30') {
            return { from: shiftDays(now, -29), to: now };
        }

        if (preset === 'this_year') {
            return { from: now.slice(0, 4) + '-01-01', to: now.slice(0, 4) + '-12-31' };
        }

        // last_3_months: iki ay oncesinin ilk gununden bu ayin sonuna (sunucunun varsayilani ile ayni).
        return { from: monthStart(now, -2), to: monthEnd(now, 0) };
    }

    function rangeText(range) {
        return range && range.from && range.to ? fmt.date(range.from) + ' – ' + fmt.date(range.to) : '';
    }

    /** Grafik ekseni icin "guzel" adim: 1 / 2 / 5 x 10^n. */
    function niceStep(range, target) {
        const raw = range / Math.max(1, target);

        if (!(raw > 0) || !Number.isFinite(raw)) {
            return 1;
        }

        const magnitude = Math.pow(10, Math.floor(Math.log10(raw)));
        const normalized = raw / magnitude;
        const nice = normalized <= 1 ? 1 : (normalized <= 2 ? 2 : (normalized <= 5 ? 5 : 10));

        return nice * magnitude;
    }

    /** Sunucudan gelen marka rengi yalniz SVG serilerinde kullanilir; siyah, metin rengine cevrilir (H5). */
    function seriesColor(hex) {
        const value = String(hex || '').trim();

        if (/^#0{3}(0{3})?$/.test(value)) {
            return 'var(--ks-text)';
        }

        return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value) ? value : 'var(--ks-text-muted)';
    }

    function typeColor(type, index) {
        const tone = TYPE_TONES[type] || KS.PALETTE[index % KS.PALETTE.length];

        return 'var(--ks-' + tone + '-solid)';
    }

    function signed(value) {
        const number = Number(value) || 0;

        return (number > 0 ? '+' : (number < 0 ? '−' : '')) + fmt.number(Math.abs(number));
    }

    function monthShort(value, withYear) {
        const date = fmt.parse(value);

        if (!date) {
            return '';
        }

        return date.toLocaleDateString(KS.locale, withYear ? { month: 'short', year: '2-digit' } : { month: 'short' });
    }

    function monthName(month) {
        const text = new Date(2024, Number(month) - 1, 1).toLocaleDateString(KS.locale, { month: 'long' });

        return text.charAt(0).toLocaleUpperCase(KS.locale) + text.slice(1);
    }

    function formatHours(hours) {
        const value = Number(hours);

        if (hours === null || hours === undefined || !Number.isFinite(value)) {
            return '—';
        }

        if (value < 1) {
            return t('duration_minutes', { n: fmt.number(Math.max(1, Math.round(value * 60))) });
        }

        if (value < 48) {
            return t('duration_hours', { n: fmt.number(value, 1) });
        }

        return t('duration_days', { n: fmt.number(value / 24, 1) });
    }

    function lower(value) {
        return String(value || '').toLocaleLowerCase(KS.locale);
    }

    function fileExtension(name) {
        const match = /\.([A-Za-z0-9]+)$/.exec(String(name || ''));

        return match ? match[1].toLowerCase() : '';
    }

    /** Laravel alan hatalari -> { alan: ilk mesaj }. */
    function fieldErrors(error) {
        const out = {};

        if (error && error.errors && typeof error.errors === 'object') {
            Object.keys(error.errors).forEach((key) => {
                const list = error.errors[key];

                out[key] = String(Array.isArray(list) ? list[0] : list);
            });
        }

        return out;
    }

    /**
     * Form kaydi hatasi: butun alan hatalari formda gosterilebiliyorsa yalniz alanlara yazilir,
     * aksi halde (is hatasi, yetki, ag, bilinmeyen alan) KS.handleError bildirimi de gosterilir.
     */
    function handleFormError(error, known, setErrors) {
        const map = fieldErrors(error);
        const keys = Object.keys(map);

        if (keys.length) {
            setErrors(map);
        }

        if (Number(error && error.status) === 422 && keys.length && keys.every((key) => known.indexOf(key) !== -1)) {
            return;
        }

        KS.handleError(error);
    }

    /** Depodaki acilis verisini yerinde gunceller (kategori / hesap degisiklikleri). */
    function patchBoot(patch) {
        KS.store.setState((state) => (state.boot ? { boot: Object.assign({}, state.boot, patch) } : null));
    }

    /** Kaydedilmemis degisiklik varken kapatma onayi. */
    function confirmDiscard() {
        return KS.confirm({
            title: t('unsaved_changes_confirm'),
            confirmLabel: t('discard'),
            cancelLabel: t('keep_editing'),
            danger: true,
        });
    }

    function goToFeed(filters) {
        KS.actions.resetFilters(filters);
        KS.actions.setView('akis');
    }

    function openSettings(tab) {
        pendingSettingsTab = tab || null;
        KS.actions.setView('ayarlar');
    }

    /* ================================================================== */
    /* 2. Grafik parcalari (elle cizilen SVG)                              */
    /* ================================================================== */

    /** Ekran okuyucu icin grafik verisi (gorsel olarak gizli tablo). */
    function SrTable(props) {
        // Tablo dogrudan gizlenmez: kutu modeli 1px'e inmez; tasmayi kesen bir kap icine alinir.
        return h('div', { className: 'ks-sr-only' },
            h('table', null,
                h('caption', null, props.caption),
                h('thead', null,
                    h('tr', null, props.head.map((cell, index) => h('th', { key: index, scope: 'col' }, cell))),
                ),
                h('tbody', null, props.rows.map((row, rowIndex) => h('tr', { key: rowIndex },
                    row.map((cell, cellIndex) => (cellIndex === 0
                        ? h('th', { key: cellIndex, scope: 'row' }, cell)
                        : h('td', { key: cellIndex }, cell))),
                ))),
            ),
        );
    }

    function ChartTip(props) {
        const tip = props.tip;

        if (!tip) {
            return null;
        }

        const half = 88;
        const left = props.width > half * 2 ? KS.clamp(tip.x, half, props.width - half) : props.width / 2;
        const below = tip.y < 72;

        return h('div', {
            className: cx('ks-manage-tip', below && 'ks-manage-tip--below'),
            style: { left: left + 'px', top: tip.y + 'px' },
            'aria-hidden': 'true',
        },
            h('p', { className: 'ks-manage-tip__title' }, tip.title),
            tip.lines.map((line, index) => h('p', { key: index, className: 'ks-manage-tip__line' },
                line.color ? h('span', { className: 'ks-manage-tip__dot', style: { background: line.color } }) : null,
                h('span', { className: 'ks-manage-tip__label' }, line.label),
                h('span', { className: 'ks-manage-tip__value' }, line.value),
            )),
        );
    }

    function LegendDot(props) {
        return h('span', { className: 'ks-manage-legend__static' },
            h('span', { className: 'ks-manage-legend__swatch', style: { background: props.color }, 'aria-hidden': 'true' }),
            h('span', null, props.label),
        );
    }

    /** Aylik cubuklar: paylasilan ve atlanan planli icerik. rows: [{ month: 'Y-m', published, missed }] */
    function MonthlyBars(props) {
        const rows = props.rows || EMPTY_LIST;
        const [setNode, width] = useMeasure();
        const hover = useChartHover();
        const total = rows.reduce((sum, row) => sum + (Number(row.published) || 0) + (Number(row.missed) || 0), 0);

        if (!rows.length || total === 0) {
            return h(Empty, { compact: true, icon: 'chart', title: t('analytics_monthly_empty'), text: t('analytics_monthly_empty_text') });
        }

        const publishedColor = 'var(--ks-emerald-solid)';
        const missedColor = 'var(--ks-rose-solid)';
        const height = 236;
        const pad = { top: 14, right: 8, bottom: 30, left: 34 };
        const innerW = Math.max(0, width - pad.left - pad.right);
        const innerH = height - pad.top - pad.bottom;
        const max = rows.reduce((value, row) => Math.max(value, Number(row.published) || 0, Number(row.missed) || 0), 0);
        const step = Math.max(1, niceStep(max, 4));
        const top = Math.max(step, Math.ceil(max / step) * step);
        const ticks = [];

        for (let value = 0; value <= top; value += step) {
            ticks.push(value);
        }

        const band = rows.length ? innerW / rows.length : innerW;
        const group = Math.min(band * 0.68, 46);
        const bar = Math.max(2, group / 2 - 1.5);
        const labelEvery = Math.max(1, Math.ceil(40 / Math.max(1, band)));
        const spansYears = rows.length > 1 && String(rows[0].month).slice(0, 4) !== String(rows[rows.length - 1].month).slice(0, 4);
        const yOf = (value) => pad.top + innerH - (value / top) * innerH;
        const activeRow = hover.active !== null && rows[hover.active] ? rows[hover.active] : null;
        const tip = activeRow ? {
            x: pad.left + band * hover.active + band / 2,
            y: yOf(Math.max(Number(activeRow.published) || 0, Number(activeRow.missed) || 0)),
            title: fmt.monthLabel(activeRow.month),
            lines: [
                { color: publishedColor, label: t('analytics_series_published'), value: fmt.number(activeRow.published) },
                { color: missedColor, label: t('analytics_series_missed'), value: fmt.number(activeRow.missed) },
            ],
        } : null;

        return h('div', { className: 'ks-manage-chart' },
            h('div', { className: 'ks-manage-legend' },
                h(LegendDot, { color: publishedColor, label: t('analytics_series_published') }),
                h(LegendDot, { color: missedColor, label: t('analytics_series_missed') }),
            ),
            h('div', { ref: setNode, className: 'ks-manage-chart__plot', style: { height: height + 'px' }, onClick: hover.clear },
                width > 0 ? h('svg', { className: 'ks-manage-svg', width, height, viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': t('analytics_monthly_title') },
                    ticks.map((value) => h('g', { key: 'tick' + value },
                        h('line', { className: value === 0 ? 'ks-manage-svg__axis' : 'ks-manage-svg__grid', x1: pad.left, x2: width - pad.right, y1: yOf(value), y2: yOf(value) }),
                        h('text', { className: 'ks-manage-svg__label', x: pad.left - 8, y: yOf(value) + 4, textAnchor: 'end' }, fmt.number(value)),
                    )),
                    rows.map((row, index) => {
                        const center = pad.left + band * index + band / 2;
                        const published = Number(row.published) || 0;
                        const missed = Number(row.missed) || 0;
                        const showLabel = index % labelEvery === 0;

                        return h('g', { key: row.month, className: cx('ks-manage-svg__band', hover.active === index && 'is-active') },
                            h('rect', { className: 'ks-manage-svg__band-bg', x: pad.left + band * index, y: pad.top, width: band, height: innerH }),
                            published > 0 ? h('rect', { x: center - bar - 1, y: yOf(published), width: bar, height: Math.max(1, yOf(0) - yOf(published)), rx: 3, style: { fill: publishedColor } }) : null,
                            missed > 0 ? h('rect', { x: center + 1, y: yOf(missed), width: bar, height: Math.max(1, yOf(0) - yOf(missed)), rx: 3, style: { fill: missedColor } }) : null,
                            showLabel ? h('text', { className: 'ks-manage-svg__label', x: center, y: height - 10, textAnchor: 'middle' },
                                monthShort(row.month, spansYears && (index === 0 || String(row.month).slice(5, 7) === '01'))) : null,
                            h('rect', Object.assign({ className: 'ks-manage-svg__hit', x: pad.left + band * index, y: pad.top, width: band, height: innerH + pad.bottom }, hover.bind(index))),
                        );
                    }),
                ) : null,
                h(ChartTip, { tip, width }),
            ),
            h(SrTable, {
                caption: t('analytics_monthly_title'),
                head: [t('analytics_month'), t('analytics_series_published'), t('analytics_series_missed')],
                rows: rows.map((row) => [fmt.monthLabel(row.month), fmt.number(row.published), fmt.number(row.missed)]),
            }),
        );
    }

    /** Platform cubuklari: rows: [{ platform, platform_label, color, published }] */
    function PlatformBars(props) {
        const rows = (props.rows || EMPTY_LIST).slice().sort((a, b) => (Number(b.published) || 0) - (Number(a.published) || 0));
        const max = rows.reduce((value, row) => Math.max(value, Number(row.published) || 0), 0);

        if (!rows.length || max === 0) {
            return h(Empty, { compact: true, icon: 'share', title: t('analytics_platforms_empty') });
        }

        return h('ul', { className: 'ks-manage-bars' }, rows.map((row) => {
            const value = Number(row.published) || 0;
            const percent = max > 0 ? Math.max(value > 0 ? 2 : 0, (value / max) * 100) : 0;

            return h('li', { key: row.platform, className: cx('ks-manage-bars__row', value === 0 && 'is-zero') },
                h(PlatformIcon, { platform: row.platform, variant: 'badge' }),
                h('div', { className: 'ks-manage-bars__main' },
                    h('div', { className: 'ks-manage-bars__head' },
                        h('span', { className: 'ks-manage-bars__label' }, row.platform_label),
                        h('span', { className: 'ks-manage-bars__value' }, fmt.number(value)),
                    ),
                    h('svg', { className: 'ks-manage-bars__svg', width: '100%', height: 10, 'aria-hidden': 'true', focusable: 'false' },
                        h('rect', { className: 'ks-manage-bars__track', x: 0, y: 0, width: '100%', height: 10, rx: 5 }),
                        value > 0 ? h('rect', { x: 0, y: 0, width: percent + '%', height: 10, rx: 5, style: { fill: seriesColor(row.color) } }) : null,
                    ),
                ),
            );
        }));
    }

    /** Tur halkasi: rows: [{ content_type, label, published }] */
    function TypeDonut(props) {
        const rows = (props.rows || EMPTY_LIST).filter(Boolean);
        const [active, setActive] = useState(null);
        const total = rows.reduce((sum, row) => sum + (Number(row.published) || 0), 0);

        if (!total) {
            return h(Empty, { compact: true, icon: 'layers', title: t('analytics_types_empty') });
        }

        const size = 176;
        const stroke = 24;
        const radius = (size - stroke) / 2;
        const center = size / 2;
        const circumference = 2 * Math.PI * radius;
        const positive = rows.map((row, index) => ({ row, index, value: Number(row.published) || 0 })).filter((item) => item.value > 0);
        const gap = positive.length > 1 ? 2.5 : 0;
        const segments = [];
        let offset = 0;

        positive.forEach((item) => {
            const length = (item.value / total) * circumference;

            segments.push({ key: item.row.content_type, row: item.row, value: item.value, length, offset, color: typeColor(item.row.content_type, item.index) });
            offset += length;
        });

        const focus = segments.find((segment) => segment.key === active) || null;

        return h('div', { className: 'ks-manage-donut' },
            h('svg', { className: 'ks-manage-svg ks-manage-donut__svg', width: size, height: size, viewBox: '0 0 ' + size + ' ' + size, role: 'img', 'aria-label': t('analytics_types_title') },
                h('circle', { className: 'ks-manage-donut__track', cx: center, cy: center, r: radius, fill: 'none', strokeWidth: stroke }),
                segments.map((segment) => {
                    const drawn = Math.max(0.75, segment.length - gap);

                    return h('circle', {
                        key: segment.key,
                        className: cx('ks-manage-donut__segment', active && active !== segment.key && 'is-dimmed'),
                        cx: center,
                        cy: center,
                        r: radius,
                        fill: 'none',
                        strokeWidth: active === segment.key ? stroke + 4 : stroke,
                        strokeDasharray: drawn + ' ' + (circumference - drawn),
                        strokeDashoffset: -segment.offset,
                        transform: 'rotate(-90 ' + center + ' ' + center + ')',
                        style: { stroke: segment.color },
                        onPointerEnter: () => setActive(segment.key),
                        onPointerLeave: () => setActive(null),
                    });
                }),
                h('text', { className: 'ks-manage-donut__value', x: center, y: center + 2, textAnchor: 'middle' }, fmt.number(focus ? focus.value : total)),
                h('text', { className: 'ks-manage-donut__caption', x: center, y: center + 20, textAnchor: 'middle' }, focus ? focus.row.label : t('analytics_total')),
            ),
            h('ul', { className: 'ks-manage-donut__legend' }, rows.map((row, index) => {
                const value = Number(row.published) || 0;

                return h('li', {
                    key: row.content_type,
                    className: cx('ks-manage-donut__item', value === 0 && 'is-zero', active === row.content_type && 'is-active'),
                    onPointerEnter: () => setActive(value > 0 ? row.content_type : null),
                    onPointerLeave: () => setActive(null),
                },
                    h('span', { className: 'ks-manage-legend__swatch', style: { background: typeColor(row.content_type, index) }, 'aria-hidden': 'true' }),
                    h('span', { className: 'ks-manage-donut__label' }, row.label),
                    h('span', { className: 'ks-manage-donut__count' }, fmt.number(value)),
                    h('span', { className: 'ks-manage-donut__percent' }, fmt.percent(value / total, 0)),
                );
            })),
        );
    }

    /** Takipci egrileri: series: [{ platform, platform_label, color, points: [{ date, value }] }] */
    function FollowerLines(props) {
        const series = (props.series || EMPTY_LIST).filter((item) => item && Array.isArray(item.points) && item.points.length);
        const [setNode, width] = useMeasure();
        const hover = useChartHover();
        const [hidden, setHidden] = useState({});

        if (!series.length) {
            return h(Empty, {
                compact: true,
                icon: 'trend',
                title: t('analytics_followers_empty'),
                text: t('analytics_followers_empty_text'),
                action: props.onAdd ? h(Button, { variant: 'soft', icon: 'plus', onClick: props.onAdd }, t('metrics_add')) : null,
            });
        }

        const timeOf = (value) => {
            const date = fmt.parse(value);

            return date ? date.getTime() : 0;
        };
        const visible = series.filter((item) => !hidden[item.platform]);
        const height = 252;
        const pad = { top: 16, right: 14, bottom: 30, left: 46 };
        const innerW = Math.max(0, width - pad.left - pad.right);
        const innerH = height - pad.top - pad.bottom;
        let minValue = Infinity;
        let maxValue = -Infinity;
        let minTime = Infinity;
        let maxTime = -Infinity;

        visible.forEach((item) => item.points.forEach((point) => {
            const value = Number(point.value) || 0;
            const time = timeOf(point.date);

            minValue = Math.min(minValue, value);
            maxValue = Math.max(maxValue, value);
            minTime = Math.min(minTime, time);
            maxTime = Math.max(maxTime, time);
        }));

        const legend = h('div', { className: 'ks-manage-legend', role: 'group', 'aria-label': t('analytics_followers_legend') }, series.map((item) => {
            const off = !!hidden[item.platform];

            return h('button', {
                key: item.platform,
                type: 'button',
                className: cx('ks-manage-legend__item', off && 'is-off'),
                'aria-pressed': off ? 'false' : 'true',
                title: t(off ? 'analytics_series_show' : 'analytics_series_hide'),
                onClick: () => {
                    hover.clear();
                    setHidden((current) => {
                        const next = Object.assign({}, current);

                        next[item.platform] = !off;

                        return next;
                    });
                },
            },
                h('span', { className: 'ks-manage-legend__swatch', style: { background: seriesColor(item.color) }, 'aria-hidden': 'true' }),
                h('span', null, item.platform_label),
            );
        }));

        const table = h(SrTable, {
            caption: t('analytics_followers_title'),
            head: [t('analytics_platform'), t('date'), t('metric_followers')],
            rows: series.reduce((list, item) => list.concat(item.points.map((point) => [item.platform_label, fmt.date(point.date), fmt.number(point.value)])), []),
        });

        if (!visible.length) {
            return h('div', { className: 'ks-manage-chart' }, legend, h(Empty, { compact: true, icon: 'eye-off', title: t('analytics_followers_none_selected') }), table);
        }

        const spread = maxValue - minValue;
        const padValue = spread > 0 ? spread * 0.12 : Math.max(1, maxValue * 0.05);
        const step = Math.max(1, niceStep((maxValue + padValue) - Math.max(0, minValue - padValue), 4));
        const low = Math.floor(Math.max(0, minValue - padValue) / step) * step;
        let high = Math.ceil((maxValue + padValue) / step) * step;

        if (high <= low) {
            high = low + step;
        }

        const ticks = [];

        for (let value = low; value <= high + step / 2; value += step) {
            ticks.push(value);
        }

        const singleTime = maxTime === minTime;
        const xOf = (time) => (singleTime ? pad.left + innerW / 2 : pad.left + ((time - minTime) / (maxTime - minTime)) * innerW);
        const yOf = (value) => pad.top + innerH - ((value - low) / (high - low)) * innerH;
        const tickCount = singleTime ? 1 : KS.clamp(Math.floor(innerW / 96), 2, 6);
        const spansYears = new Date(minTime).getFullYear() !== new Date(maxTime).getFullYear();
        const timeTicks = [];

        for (let index = 0; index < tickCount; index += 1) {
            timeTicks.push(singleTime ? minTime : minTime + ((maxTime - minTime) * index) / (tickCount - 1));
        }

        let tip = null;

        visible.forEach((item) => item.points.forEach((point, index) => {
            if (hover.active !== item.platform + '|' + index) {
                return;
            }

            const previous = index > 0 ? item.points[index - 1] : null;
            const lines = [{ color: seriesColor(item.color), label: fmt.date(point.date), value: fmt.number(point.value) }];

            if (previous) {
                lines.push({ label: t('analytics_change'), value: signed((Number(point.value) || 0) - (Number(previous.value) || 0)) });
            }

            tip = { x: xOf(timeOf(point.date)), y: yOf(Number(point.value) || 0), title: item.platform_label, lines };
        }));

        return h('div', { className: 'ks-manage-chart' },
            legend,
            h('div', { ref: setNode, className: 'ks-manage-chart__plot', style: { height: height + 'px' }, onClick: hover.clear },
                width > 0 ? h('svg', { className: 'ks-manage-svg', width, height, viewBox: '0 0 ' + width + ' ' + height, role: 'img', 'aria-label': t('analytics_followers_title') },
                    ticks.map((value, index) => h('g', { key: 'y' + index },
                        h('line', { className: index === 0 ? 'ks-manage-svg__axis' : 'ks-manage-svg__grid', x1: pad.left, x2: width - pad.right, y1: yOf(value), y2: yOf(value) }),
                        h('text', { className: 'ks-manage-svg__label', x: pad.left - 8, y: yOf(value) + 4, textAnchor: 'end' }, fmt.compact(value)),
                    )),
                    timeTicks.map((time, index) => h('text', {
                        key: 'x' + index,
                        className: 'ks-manage-svg__label',
                        x: xOf(time),
                        y: height - 10,
                        textAnchor: singleTime ? 'middle' : (index === 0 ? 'start' : (index === tickCount - 1 ? 'end' : 'middle')),
                    }, spansYears ? monthShort(new Date(time), true) : fmt.dateShort(new Date(time)))),
                    visible.map((item) => {
                        const color = seriesColor(item.color);
                        const path = item.points.map((point, index) => (index === 0 ? 'M' : 'L') + xOf(timeOf(point.date)).toFixed(1) + ' ' + yOf(Number(point.value) || 0).toFixed(1)).join(' ');

                        return h('g', { key: item.platform },
                            item.points.length > 1 ? h('path', { className: 'ks-manage-svg__line', d: path, style: { stroke: color } }) : null,
                            item.points.map((point, index) => {
                                const key = item.platform + '|' + index;
                                const px = xOf(timeOf(point.date));
                                const py = yOf(Number(point.value) || 0);

                                return h('g', { key },
                                    h('circle', { className: cx('ks-manage-svg__point', hover.active === key && 'is-active'), cx: px, cy: py, r: hover.active === key ? 5.5 : 3.5, style: { stroke: color } }),
                                    h('circle', Object.assign({ className: 'ks-manage-svg__hit', cx: px, cy: py, r: 13 }, hover.bind(key))),
                                );
                            }),
                        );
                    }),
                ) : null,
                h(ChartTip, { tip, width }),
            ),
            table,
        );
    }

    /* ================================================================== */
    /* 3. Analiz: gostergeler ve listeler                                  */
    /* ================================================================== */

    function KpiTile(props) {
        const clickable = typeof props.onClick === 'function';
        const className = cx('ks-manage-kpi', 'ks-c-' + KS.paletteColor(props.tone), clickable && 'ks-manage-kpi--button');
        const body = [
            h('span', { key: 'icon', className: 'ks-manage-kpi__icon', 'aria-hidden': 'true' }, h(Icon, { name: props.icon })),
            h('span', { key: 'value', className: 'ks-manage-kpi__value' }, props.value),
            h('span', { key: 'label', className: 'ks-manage-kpi__label' }, props.label),
            props.hint ? h('span', { key: 'hint', className: 'ks-manage-kpi__hint' }, props.hint) : null,
        ];

        if (clickable) {
            return h('button', { type: 'button', className, onClick: props.onClick, title: props.title },
                body,
                h(Icon, { key: 'go', name: 'arrow-right', className: 'ks-manage-kpi__go' }),
            );
        }

        return h('div', { className }, body, props.children || null);
    }

    function KpiGroups(props) {
        const kpis = props.kpis || {};
        const number = (key) => fmt.number(kpis[key] === undefined || kpis[key] === null ? 0 : kpis[key]);
        const showInFeed = t('analytics_show_in_feed');

        return h('div', { className: 'ks-manage-kpi-groups' },
            h('div', { className: 'ks-manage-kpi-group' },
                h('p', { className: 'ks-eyebrow' }, t('analytics_group_period')),
                h('div', { className: 'ks-manage-kpis' },
                    h(KpiTile, { icon: 'check-circle', tone: 'emerald', label: t('kpi_published'), value: number('published') }),
                    h(KpiTile, { icon: 'calendar', tone: 'sky', label: t('kpi_planned'), value: number('planned') }),
                    h(KpiTile, { icon: 'alert', tone: 'rose', label: t('kpi_missed_planned'), value: number('missed_planned') }),
                    h(KpiTile, {
                        icon: 'star',
                        tone: 'violet',
                        label: t('kpi_special_days'),
                        value: number('special_days'),
                        hint: props.specialDaysDefined ? null : t('no_special_days_defined'),
                    }, !props.specialDaysDefined && props.canManage
                        ? h('button', { type: 'button', className: 'ks-link ks-manage-kpi__link', onClick: () => openSettings('days') }, t('analytics_go_settings'))
                        : null),
                    h(KpiTile, { icon: 'flag', tone: 'amber', label: t('kpi_missed_special_days'), value: number('missed_special_days') }),
                    h(KpiTile, { icon: 'history', tone: 'stone', label: t('kpi_avg_approval'), value: formatHours(kpis.avg_approval_hours), hint: t('kpi_avg_approval_hint') }),
                ),
            ),
            h('div', { className: 'ks-manage-kpi-group' },
                h('p', { className: 'ks-eyebrow' }, t('analytics_group_now')),
                h('div', { className: 'ks-manage-kpis' },
                    h(KpiTile, { icon: 'clock', tone: 'amber', label: t('kpi_pending'), value: number('pending'), title: showInFeed, onClick: () => goToFeed({ status: 'pending' }) }),
                    h(KpiTile, { icon: 'edit', tone: 'violet', label: t('kpi_revision_requested'), value: number('revision_requested'), title: showInFeed, onClick: () => goToFeed({ status: 'revision_requested' }) }),
                    h(KpiTile, { icon: 'send', tone: 'teal', label: t('kpi_approved_unpublished'), value: number('approved_unpublished'), title: showInFeed, onClick: () => goToFeed({ status: 'approved', published: 'no' }) }),
                    h(KpiTile, { icon: 'x-mark', tone: 'red', label: t('kpi_rejected'), value: number('rejected'), title: showInFeed, onClick: () => goToFeed({ status: 'rejected' }) }),
                ),
            ),
        );
    }

    function PlatformDots(props) {
        const platforms = props.platforms || EMPTY_LIST;

        if (!platforms.length) {
            return null;
        }

        return h('span', { className: 'ks-manage-platforms' }, platforms.map((item) => h(PlatformIcon, {
            key: item.platform,
            platform: item.platform,
            label: item.platform_label,
        })));
    }

    function MissedPlannedList(props) {
        const items = props.items || EMPTY_LIST;

        if (!items.length) {
            return h(Empty, { compact: true, icon: 'check-circle', title: t('analytics_missed_planned_empty') });
        }

        return h('ul', { className: 'ks-manage-list' }, items.map((item) => {
            const thumb = item.cover ? KS.safeUrl(item.cover.thumbnail_url || item.cover.poster_url || null) : null;

            return h('li', { key: item.id },
                h('button', {
                    type: 'button',
                    className: 'ks-manage-row ks-manage-row--button',
                    onClick: () => KS.actions.openDetail(item.id),
                    title: t('analytics_open_content'),
                },
                    h('span', { className: 'ks-manage-thumb', 'aria-hidden': 'true' },
                        thumb
                            ? h('img', { src: thumb, alt: '', loading: 'lazy', decoding: 'async' })
                            : h(Icon, { name: TYPE_ICONS[item.content_type] || 'document' }),
                    ),
                    h('span', { className: 'ks-manage-row__main' },
                        h('span', { className: 'ks-manage-row__title' }, item.title),
                        h('span', { className: 'ks-manage-row__meta' },
                            h('span', null, fmt.planned(item.planned_on, item.planned_time)),
                            item.content_type_label ? h('span', null, item.content_type_label) : null,
                            h(PlatformDots, { platforms: item.platforms }),
                        ),
                    ),
                    h(StatusBadge, { status: item.status, label: item.status_label, color: item.status_color, size: 'sm' }),
                    h(Icon, { name: 'chevron-right', className: 'ks-manage-row__chevron' }),
                ),
            );
        }));
    }

    function MissedSpecialList(props) {
        const items = props.items || EMPTY_LIST;

        if (!items.length) {
            return h(Empty, {
                compact: true,
                icon: props.defined ? 'check-circle' : 'star',
                title: props.defined ? t('analytics_missed_special_empty') : t('no_special_days_defined'),
                action: !props.defined && props.canManage
                    ? h(Button, { variant: 'soft', size: 'sm', icon: 'settings', onClick: () => openSettings('days') }, t('analytics_go_settings'))
                    : null,
            });
        }

        return h('ul', { className: 'ks-manage-list' }, items.map((item, index) => {
            const date = fmt.parse(item.date);

            return h('li', { key: item.date + '|' + index, className: 'ks-manage-row' },
                h('span', { className: 'ks-manage-daybox ks-c-amber', 'aria-hidden': 'true' },
                    h('span', { className: 'ks-manage-daybox__day' }, date ? date.getDate() : ''),
                    h('span', { className: 'ks-manage-daybox__month' }, monthShort(item.date, false)),
                ),
                h('span', { className: 'ks-manage-row__main' },
                    h('span', { className: 'ks-manage-row__title' }, item.name),
                    h('span', { className: 'ks-manage-row__meta' }, h('span', null, fmt.dateLong(item.date))),
                ),
            );
        }));
    }

    function TopCreators(props) {
        const rows = props.rows || EMPTY_LIST;
        const max = rows.reduce((value, row) => Math.max(value, Number(row.count) || 0), 0);

        if (!rows.length) {
            return h(Empty, { compact: true, icon: 'users', title: t('analytics_top_creators_empty') });
        }

        return h('ul', { className: 'ks-manage-list' }, rows.map((row, index) => h('li', { key: (row.person && row.person.id) || index, className: 'ks-manage-row ks-manage-row--creator' },
            h('span', { className: 'ks-manage-rank', 'aria-hidden': 'true' }, index + 1),
            h('span', { className: 'ks-manage-row__main' },
                h(PersonLine, { person: row.person }),
                h(ProgressBar, { value: Number(row.count) || 0, max: Math.max(1, max), size: 'sm', tone: 'neutral', ariaLabel: fmt.personName(row.person) }),
            ),
            h('span', { className: 'ks-manage-row__count', title: t('analytics_published_count', { count: fmt.number(row.count) }) }, fmt.number(row.count)),
        )));
    }

    /** Platform basina ozet kutusu (metrics_summary, F10 / H9). */
    function PlatformSummary(props) {
        const row = props.row;
        const delta = row.followers_delta;
        const stats = SUMMARY_METRICS
            .filter((metric) => row[metric] !== null && row[metric] !== undefined)
            .map((metric) => ({ key: metric, label: t('metric_' + metric), value: fmt.compact(row[metric]), title: fmt.number(row[metric]) }));

        if (row.engagement_rate !== null && row.engagement_rate !== undefined) {
            stats.push({ key: 'engagement_rate', label: t('metric_engagement_rate'), value: fmt.percent(Number(row.engagement_rate) / 100, 2), title: t('metric_engagement_rate_hint') });
        }

        return h('article', { className: cx('ks-manage-platform', 'ks-platform', KS.platformClass(row.platform)) },
            h('header', { className: 'ks-manage-platform__head' },
                h(PlatformIcon, { platform: row.platform, variant: 'solid' }),
                h('div', { className: 'ks-manage-platform__titles' },
                    h('h4', { className: 'ks-manage-platform__name' }, row.platform_label),
                    h('p', { className: 'ks-manage-platform__sub' }, t('metrics_entries_count', { count: fmt.number(row.entries || 0) })),
                ),
            ),
            h('div', { className: 'ks-manage-platform__followers' },
                h('span', { className: 'ks-manage-platform__value' }, row.followers_latest === null || row.followers_latest === undefined ? '—' : fmt.number(row.followers_latest)),
                h('span', { className: 'ks-manage-platform__unit' }, t('metric_followers')),
                delta === null || delta === undefined ? null : h(Badge, {
                    color: delta > 0 ? 'emerald' : (delta < 0 ? 'rose' : 'stone'),
                    icon: delta > 0 ? 'arrow-up' : (delta < 0 ? 'arrow-down' : undefined),
                    size: 'sm',
                    title: t('analytics_followers_delta_hint'),
                }, signed(delta)),
            ),
            stats.length ? h('dl', { className: 'ks-manage-facts' }, stats.map((stat) => h('div', { key: stat.key, className: 'ks-manage-facts__item', title: stat.title },
                h('dt', null, stat.label),
                h('dd', null, stat.value),
            ))) : h('p', { className: 'ks-manage-platform__none' }, t('metrics_no_numbers')),
        );
    }

    /* ================================================================== */
    /* 4. Analiz: istatistik kayitlari (liste + pencere)                   */
    /* ================================================================== */

    function reportLimits() {
        const limits = KS.limits();
        const extensions = Array.isArray(limits.report_extensions) && limits.report_extensions.length
            ? limits.report_extensions.map((item) => String(item).toLowerCase())
            : REPORT_EXTENSIONS;

        return { extensions, maxKb: Number(limits.max_report_kb) > 0 ? Number(limits.max_report_kb) : 20480 };
    }

    function MetricEntryCard(props) {
        const entry = props.entry;
        const facts = METRICS
            .filter((metric) => entry[metric] !== null && entry[metric] !== undefined)
            .map((metric) => ({ key: metric, label: t('metric_' + metric), value: fmt.number(entry[metric]) }));

        return h('article', { className: cx('ks-manage-entry', 'ks-platform', KS.platformClass(entry.platform)) },
            h('header', { className: 'ks-manage-entry__head' },
                h(PlatformIcon, { platform: entry.platform, variant: 'badge' }),
                h('div', { className: 'ks-manage-entry__titles' },
                    h('h4', { className: 'ks-manage-entry__name' }, entry.platform_label),
                    h('p', { className: 'ks-manage-entry__period' }, rangeText({ from: entry.period_start_on, to: entry.period_end_on })),
                ),
                entry.source_label ? h(Badge, { color: entry.source === 'upload' ? 'sky' : 'stone', icon: entry.source === 'upload' ? 'upload' : 'edit', size: 'sm' }, entry.source_label) : null,
                props.canManage ? h(IconButton, { icon: 'edit', label: t('metrics_edit'), size: 'sm', onClick: () => props.onEdit(entry) }) : null,
            ),
            facts.length
                ? h('dl', { className: 'ks-manage-facts' }, facts.map((fact) => h('div', { key: fact.key, className: 'ks-manage-facts__item' },
                    h('dt', null, fact.label),
                    h('dd', null, fact.value),
                )))
                : h('p', { className: 'ks-manage-entry__none' }, t('metrics_no_numbers')),
            entry.note ? h('p', { className: 'ks-manage-entry__note' }, entry.note) : null,
            entry.report_url ? h('div', { className: 'ks-manage-entry__report' },
                h('span', { className: 'ks-manage-entry__file' },
                    h(Icon, { name: 'document' }),
                    h('span', { className: 'ks-truncate' }, entry.report_name || t('metrics_report')),
                    entry.report_size ? h('span', { className: 'ks-faint ks-nowrap' }, entry.report_size) : null,
                ),
                h('span', { className: 'ks-manage-entry__links' },
                    entry.report_previewable ? h(Button, { size: 'sm', variant: 'ghost', icon: 'external', href: entry.report_url, target: '_blank' }, t('open')) : null,
                    h(Button, { size: 'sm', variant: 'ghost', icon: 'download', href: entry.report_download_url || entry.report_url, download: true }, t('download')),
                ),
            ) : null,
            h('footer', { className: 'ks-manage-entry__foot' },
                h(Avatar, { person: entry.author || null, size: 'xs', title: false }),
                h('span', { className: 'ks-truncate' }, fmt.personName(entry.author || null)),
                entry.created_at ? h('span', { className: 'ks-faint ks-nowrap', title: fmt.dateTime(entry.created_at) }, fmt.relative(entry.created_at)) : null,
            ),
        );
    }

    function MetricEntries(props) {
        const profile = props.profile;
        const [platform, setPlatform] = useState('');
        const [limit, setLimit] = useState(ENTRY_PAGE);
        const resource = KS.useResource(
            () => api.get(url('metrics'), { profile: profile.id, platform }),
            [profile.id, platform, props.version],
        );

        useEffect(() => setLimit(ENTRY_PAGE), [profile.id, platform]);

        const entries = (resource.data && resource.data.entries) || EMPTY_LIST;
        const platformOptions = KS.options('platforms').map((item) => ({ value: item.value, label: item.label }));
        let body = null;

        if (resource.loading && !resource.data) {
            body = h('div', { className: 'ks-manage-entries' }, [0, 1, 2].map((index) => h('div', { key: index, className: 'ks-manage-entry ks-manage-entry--skeleton' },
                h(Skeleton, { variant: 'text', width: '55%' }),
                h(Skeleton, { variant: 'rect', height: 72 }),
                h(Skeleton, { variant: 'text', width: '35%' }),
            )));
        } else if (resource.error && !resource.data) {
            body = h(ErrorState, { error: resource.error, onRetry: resource.reload, compact: true });
        } else if (!entries.length) {
            body = h(Empty, {
                icon: 'chart',
                title: platform ? t('metrics_empty_filtered') : t('metrics_empty'),
                text: platform ? null : t('metrics_empty_text'),
                action: platform
                    ? h(Button, { variant: 'soft', onClick: () => setPlatform('') }, t('clear_filters'))
                    : (props.canManage ? h(Button, { variant: 'primary', icon: 'plus', onClick: props.onAdd }, t('metrics_add')) : null),
            });
        } else {
            body = h(Fragment, null,
                resource.error ? h(ErrorState, { error: resource.error, onRetry: resource.reload, compact: true }) : null,
                h('div', { className: cx('ks-manage-entries', resource.loading && 'is-loading') },
                    entries.slice(0, limit).map((entry) => h(MetricEntryCard, { key: entry.id, entry, canManage: props.canManage, onEdit: props.onEdit })),
                ),
                h(LoadMore, { hasMore: entries.length > limit, onClick: () => setLimit((current) => current + ENTRY_PAGE) }),
            );
        }

        return h(Section, {
            title: t('metrics_title'),
            description: t('metrics_description'),
            icon: 'chart',
            className: 'ks-manage-section',
            actions: h(Fragment, null,
                h('span', { className: 'ks-manage-section__filter' },
                    h(Select, { options: platformOptions, value: platform, onChange: setPlatform, placeholder: t('metrics_all_platforms'), size: 'sm', 'aria-label': t('analytics_platform') }),
                ),
                props.canManage ? h(Button, { variant: 'primary', size: 'sm', icon: 'plus', onClick: props.onAdd }, t('metrics_add')) : null,
            ),
        }, body);
    }

    function metricFormFrom(entry) {
        const now = fmt.todayYmd();
        const form = {
            platform: entry ? entry.platform : '',
            period_start_on: entry ? (entry.period_start_on || '') : monthStart(now, -1),
            period_end_on: entry ? (entry.period_end_on || '') : monthEnd(now, -1),
            note: entry && entry.note ? entry.note : '',
        };

        METRICS.forEach((metric) => {
            form[metric] = entry && entry[metric] !== null && entry[metric] !== undefined ? String(entry[metric]) : '';
        });

        return form;
    }

    /** Istatistik ekle / duzenle penceresi (manage_data). */
    function MetricFormModal(props) {
        const entry = props.entry || null;
        const profile = props.profile;
        const limits = KS.useLimits();
        const report = reportLimits();
        const mounted = useMounted();
        const initial = useMemo(() => metricFormFrom(entry), [entry]);
        const [form, setForm] = useState(initial);
        const [file, setFile] = useState(null);
        const [removeReport, setRemoveReport] = useState(false);
        const [errors, setErrors] = useState({});
        const [saving, setSaving] = useState(false);
        const [progress, setProgress] = useState(0);
        const noteMax = Number(limits.note_max) > 0 ? Number(limits.note_max) : 4000;
        const platformOptions = KS.options('platforms').map((item) => ({ value: item.value, label: item.label }));
        const dirty = file !== null || removeReport || Object.keys(initial).some((key) => initial[key] !== form[key]);

        const set = (key, value) => {
            setForm((current) => {
                const next = Object.assign({}, current);

                next[key] = value;

                return next;
            });

            if (errors[key]) {
                setErrors((current) => KS.omit(current, [key]));
            }
        };

        const pickFile = (files) => {
            const picked = files && files[0];

            if (!picked) {
                return;
            }

            if (report.extensions.indexOf(fileExtension(picked.name)) === -1) {
                setErrors((current) => Object.assign({}, current, { report: t('metrics_report_type_error', { types: report.extensions.join(', ') }) }));

                return;
            }

            if (picked.size <= 0 || picked.size > report.maxKb * 1024) {
                setErrors((current) => Object.assign({}, current, { report: t('metrics_report_size_error', { max: fmt.bytes(report.maxKb * 1024) }) }));

                return;
            }

            setErrors((current) => KS.omit(current, ['report']));
            setFile(picked);
            setRemoveReport(false);
        };

        const validate = () => {
            const next = {};

            if (!form.platform) {
                next.platform = t('required_field');
            }

            if (!form.period_start_on) {
                next.period_start_on = t('required_field');
            }

            if (!form.period_end_on) {
                next.period_end_on = t('required_field');
            } else if (form.period_start_on && form.period_end_on < form.period_start_on) {
                next.period_end_on = t('metrics_period_error');
            }

            METRICS.forEach((metric) => {
                const value = String(form[metric] || '').trim();

                if (value !== '' && !/^\d{1,15}$/.test(value)) {
                    next[metric] = t('metrics_number_error');
                }
            });

            if (form.note.length > noteMax) {
                next.note = t('metrics_note_error', { max: fmt.number(noteMax) });
            }

            return next;
        };

        const close = (reason) => {
            if (saving) {
                return;
            }

            if (!dirty || reason === 'saved') {
                props.onClose();

                return;
            }

            confirmDiscard().then((ok) => {
                if (ok) {
                    props.onClose();
                }
            });
        };

        const submit = () => {
            const problems = validate();

            if (Object.keys(problems).length) {
                setErrors(problems);

                return;
            }

            const endpoint = entry ? url('metrics.update', entry.id) : url('metrics.store');
            let job = null;

            setSaving(true);
            setProgress(0);
            setErrors({});

            if (file) {
                const data = new FormData();

                data.append('profile_id', String(profile.id));
                data.append('platform', form.platform);
                data.append('period_start_on', form.period_start_on);
                data.append('period_end_on', form.period_end_on);
                METRICS.forEach((metric) => data.append(metric, String(form[metric] || '').trim()));
                data.append('note', form.note.trim());
                data.append('report', file, file.name);

                job = api.upload(endpoint, data, (ratio) => {
                    if (mounted.current) {
                        setProgress(ratio);
                    }
                });
            } else {
                const payload = {
                    profile_id: profile.id,
                    platform: form.platform,
                    period_start_on: form.period_start_on,
                    period_end_on: form.period_end_on,
                    note: form.note.trim() === '' ? null : form.note.trim(),
                };

                METRICS.forEach((metric) => {
                    const value = String(form[metric] || '').trim();

                    payload[metric] = value === '' ? null : Number(value);
                });

                if (entry && removeReport) {
                    payload.remove_report = true;
                }

                job = api.post(endpoint, payload);
            }

            job.then((response) => {
                KS.toast.success(t('metrics_saved'));
                props.onSaved(response && response.entry ? response.entry : null);
            }).catch((error) => {
                if (!mounted.current) {
                    return;
                }

                setSaving(false);
                handleFormError(error, METRICS.concat(['platform', 'period_start_on', 'period_end_on', 'note', 'report']), setErrors);
            });
        };

        const numberField = (metric) => h(Field, { key: metric, label: t('metric_' + metric), error: errors[metric] },
            h(TextInput, {
                type: 'text',
                inputMode: 'numeric',
                pattern: '[0-9]*',
                maxLength: 15,
                value: form[metric],
                placeholder: '—',
                disabled: saving,
                onChange: (value) => set(metric, value.replace(/[^\d]/g, '')),
            }));

        return h(Modal, {
            title: entry ? t('metrics_edit') : t('metrics_add'),
            subtitle: profile.name,
            icon: 'chart',
            size: 'lg',
            dismissible: !saving,
            onClose: close,
            footer: [
                h(Button, { key: 'cancel', variant: 'ghost', disabled: saving, onClick: () => close('button') }, t('cancel')),
                h(Button, { key: 'save', variant: 'primary', icon: 'save', loading: saving, onClick: submit }, t('save')),
            ],
        },
            h('div', { className: 'ks-form' },
                h('div', { className: 'ks-form-row ks-form-row--3' },
                    h(Field, { label: t('analytics_platform'), required: true, error: errors.platform },
                        h(Select, { options: platformOptions, value: form.platform, onChange: (value) => set('platform', value), clearable: false, disabled: saving, 'data-autofocus': entry ? undefined : '' })),
                    h(Field, { label: t('metrics_period_start'), required: true, error: errors.period_start_on },
                        h(DateInput, { value: form.period_start_on, onChange: (value) => set('period_start_on', value), max: form.period_end_on || undefined, clearable: false, disabled: saving })),
                    h(Field, { label: t('metrics_period_end'), required: true, error: errors.period_end_on },
                        h(DateInput, { value: form.period_end_on, onChange: (value) => set('period_end_on', value), min: form.period_start_on || undefined, clearable: false, disabled: saving })),
                ),
                h('div', { className: 'ks-manage-formgroup' },
                    h('p', { className: 'ks-eyebrow' }, t('metrics_numbers')),
                    h('p', { className: 'ks-manage-formgroup__hint' }, t('metrics_numbers_hint')),
                    h('div', { className: 'ks-manage-metric-grid' }, METRICS.map(numberField)),
                ),
                h(Field, { label: t('note'), error: errors.note },
                    h(TextArea, { value: form.note, onChange: (value) => set('note', value), rows: 3, autoGrow: true, maxRows: 8, maxLength: noteMax, counter: true, disabled: saving, placeholder: t('metrics_note_placeholder') })),
                h(Field, { label: t('metrics_report'), group: true, error: errors.report, hint: t('metrics_report_hint', { types: report.extensions.join(', '), max: fmt.bytes(report.maxKb * 1024) }) },
                    h('div', { className: 'ks-stack ks-stack--sm' },
                        entry && entry.report_url && !file ? h('div', { className: cx('ks-manage-file', removeReport && 'is-removed') },
                            h(Icon, { name: 'document' }),
                            h('span', { className: 'ks-manage-file__name ks-truncate' }, entry.report_name || t('metrics_report')),
                            entry.report_size ? h('span', { className: 'ks-faint ks-nowrap' }, entry.report_size) : null,
                            h(Checkbox, { checked: removeReport, onChange: setRemoveReport, label: t('metrics_report_remove'), disabled: saving }),
                        ) : null,
                        file ? h('div', { className: 'ks-manage-file' },
                            h(Icon, { name: 'upload' }),
                            h('span', { className: 'ks-manage-file__name ks-truncate' }, file.name),
                            h('span', { className: 'ks-faint ks-nowrap' }, fmt.bytes(file.size)),
                            h(IconButton, { icon: 'close', label: t('remove'), size: 'sm', disabled: saving, onClick: () => setFile(null) }),
                        ) : h(Dropzone, {
                            compact: true,
                            accept: report.extensions.map((item) => '.' + item),
                            onFiles: pickFile,
                            disabled: saving,
                            icon: 'upload',
                            title: entry && entry.report_url ? t('metrics_report_replace') : t('metrics_report_choose'),
                            text: false,
                        }),
                        saving && file ? h(ProgressBar, { value: Math.round(progress * 100), label: t('uploading'), showValue: true, size: 'sm' }) : null,
                    )),
            ),
        );
    }

    /* ================================================================== */
    /* 5. Analiz gorunumu                                                  */
    /* ================================================================== */

    function AnalyticsSkeleton() {
        return h('div', { className: 'ks-stack ks-stack--lg', 'aria-hidden': 'true' },
            h('div', { className: 'ks-manage-kpis' }, [0, 1, 2, 3, 4, 5].map((index) => h('div', { key: index, className: 'ks-manage-kpi' },
                h(Skeleton, { variant: 'circle' }),
                h(Skeleton, { variant: 'text', width: '40%', height: 22 }),
                h(Skeleton, { variant: 'text', width: '70%' }),
            ))),
            h('div', { className: 'ks-manage-charts' },
                h('div', { className: 'ks-panel ks-manage-chart--wide' }, h('div', { className: 'ks-panel__body' }, h(Skeleton, { variant: 'rect', height: 240 }))),
                h('div', { className: 'ks-panel ks-manage-chart--narrow' }, h('div', { className: 'ks-panel__body' }, h(Skeleton, { variant: 'rect', height: 240 }))),
            ),
        );
    }

    function Analytics() {
        const profile = KS.useProfile();
        const abilities = KS.useAbilities();
        const isMobile = KS.useIsMobile();
        const canManage = !!abilities.manage_data;
        const profileId = profile ? profile.id : null;
        const [preset, setPreset] = useState('last_3_months');
        const [applied, setApplied] = useState(() => presetRange('last_3_months'));
        const [custom, setCustom] = useState(() => presetRange('last_3_months'));
        const [metricForm, setMetricForm] = useState(() => {
            const wanted = pendingMetricForm && canManage;

            pendingMetricForm = false;

            return wanted ? { entry: null } : null;
        });
        const [entriesVersion, setEntriesVersion] = useState(0);

        const resource = KS.useResource(
            () => (profileId ? api.get(url('analytics'), { profile: profileId, from: applied.from, to: applied.to }) : Promise.resolve(null)),
            [profileId, applied.from, applied.to],
        );
        const reload = resource.reload;

        // Icerik degisince (ornegin atlanan icerik paylasildi olarak isaretlendi) analiz tazelenir.
        useEffect(() => {
            const later = KS.debounce(() => reload(), 800);
            const off = KS.events.on('content', later);

            return () => {
                off();
                later.cancel();
            };
        }, [reload]);

        // Baska bir gorunum "istatistik ekle" penceresini istemis olabilir (KS.manage.openMetricForm).
        useEffect(() => KS.events.on('manage:metric-form', () => {
            pendingMetricForm = false;

            if (canManage) {
                setMetricForm((current) => current || { entry: null });
            }
        }), [canManage]);

        if (!profile) {
            return h(Empty, { icon: 'chart', title: t('analytics_no_profile') });
        }

        const presets = [
            { value: 'this_month', label: t('analytics_preset_this_month') },
            { value: 'last_30', label: t('analytics_preset_last_30') },
            { value: 'last_3_months', label: t('analytics_preset_last_3_months') },
            { value: 'this_year', label: t('analytics_preset_this_year') },
            { value: 'custom', label: t('analytics_preset_custom') },
        ];

        const changePreset = (value) => {
            const next = value || 'last_3_months';

            setPreset(next);

            if (next === 'custom') {
                setCustom(applied);
            } else {
                setApplied(presetRange(next));
            }
        };

        const changeCustom = (key, value) => {
            const next = Object.assign({}, custom);

            next[key] = value;
            setCustom(next);

            if (next.from && next.to && next.to >= next.from) {
                setApplied(next);
            }
        };

        const customError = custom.from && custom.to && custom.to < custom.from ? t('analytics_range_invalid') : null;
        const data = resource.data;
        const canAdd = canManage && KS.hasEndpoint('metrics.store');
        const openAdd = canAdd ? () => setMetricForm({ entry: null }) : null;
        const summary = (data && data.metrics_summary) || EMPTY_LIST;
        let body = null;

        if (resource.loading && !data) {
            body = h(AnalyticsSkeleton);
        } else if (resource.error && !data) {
            body = h(ErrorState, { error: resource.error, onRetry: reload });
        } else if (data) {
            body = h('div', { className: cx('ks-stack ks-stack--lg', resource.loading && 'ks-manage-is-loading') },
                resource.error ? h(ErrorState, { error: resource.error, onRetry: reload, compact: true }) : null,
                h(KpiGroups, { kpis: data.kpis, specialDaysDefined: data.special_days_defined !== false, canManage }),
                h('div', { className: 'ks-manage-charts' },
                    h(Section, { title: t('analytics_monthly_title'), description: t('analytics_monthly_desc'), className: 'ks-manage-chart--wide' },
                        h(MonthlyBars, { rows: data.by_month })),
                    h(Section, { title: t('analytics_types_title'), description: t('analytics_types_desc'), className: 'ks-manage-chart--narrow' },
                        h(TypeDonut, { rows: data.by_type })),
                    h(Section, { title: t('analytics_platforms_title'), description: t('analytics_platforms_desc'), className: 'ks-manage-chart--narrow' },
                        h(PlatformBars, { rows: data.by_platform })),
                    h(Section, { title: t('analytics_followers_title'), description: t('analytics_followers_desc'), className: 'ks-manage-chart--wide' },
                        h(FollowerLines, { series: data.followers, onAdd: openAdd })),
                ),
                h('div', { className: 'ks-manage-lists' },
                    h(Section, { title: t('analytics_missed_planned_title'), description: t('analytics_missed_planned_desc'), icon: 'alert', flush: true },
                        h(MissedPlannedList, { items: data.missed_planned_list })),
                    h(Section, { title: t('analytics_missed_special_title'), description: t('analytics_missed_special_desc'), icon: 'flag', flush: true },
                        h(MissedSpecialList, { items: data.missed_special_list, defined: data.special_days_defined !== false, canManage })),
                    h(Section, { title: t('analytics_top_creators_title'), description: t('analytics_top_creators_desc'), icon: 'users', flush: true },
                        h(TopCreators, { rows: data.top_creators })),
                ),
                h(Section, { title: t('analytics_summary_title'), description: t('analytics_summary_desc'), icon: 'trend', className: 'ks-manage-section' },
                    summary.length
                        ? h('div', { className: 'ks-manage-platform-grid' }, summary.map((row) => h(PlatformSummary, { key: row.platform, row })))
                        : h(Empty, {
                            compact: true,
                            icon: 'trend',
                            title: t('analytics_summary_empty'),
                            text: t('analytics_summary_empty_text'),
                            action: openAdd ? h(Button, { variant: 'soft', icon: 'plus', onClick: openAdd }, t('metrics_add')) : null,
                        })),
            );
        }

        return h('div', { className: 'ks-manage ks-manage--analytics' },
            h('header', { className: 'ks-manage-head' },
                h('div', { className: 'ks-manage-head__titles' },
                    h('h2', { className: 'ks-title' }, t('analytics_title')),
                    h('p', { className: 'ks-subtitle' }, profile.name + ' · ' + rangeText((data && data.range) || applied)),
                ),
                h('div', { className: 'ks-manage-head__controls' },
                    isMobile
                        ? h(Select, { options: presets, value: preset, onChange: changePreset, placeholder: false, icon: 'calendar', 'aria-label': t('analytics_range') })
                        : h(Segmented, { items: presets, value: preset, onChange: changePreset, size: 'sm', label: t('analytics_range') }),
                    h(IconButton, { icon: 'refresh', label: t('refresh'), loading: resource.loading && !!data, onClick: reload }),
                ),
            ),
            preset === 'custom' ? h('div', { className: 'ks-manage-range' },
                h(Field, { label: t('analytics_range_from') },
                    h(DateInput, { value: custom.from, onChange: (value) => changeCustom('from', value), max: custom.to || undefined, clearable: false })),
                h(Field, { label: t('analytics_range_to'), error: customError },
                    h(DateInput, { value: custom.to, onChange: (value) => changeCustom('to', value), min: custom.from || undefined, clearable: false })),
                h('p', { className: 'ks-manage-range__hint' }, t('analytics_range_hint')),
            ) : null,
            body,
            KS.hasEndpoint('metrics') ? h(MetricEntries, {
                profile,
                canManage: canAdd,
                version: entriesVersion,
                onAdd: () => setMetricForm({ entry: null }),
                onEdit: (entry) => setMetricForm({ entry }),
            }) : null,
            metricForm ? h(MetricFormModal, {
                key: metricForm.entry ? 'entry-' + metricForm.entry.id : 'new',
                profile,
                entry: metricForm.entry,
                onClose: () => setMetricForm(null),
                onSaved: () => {
                    setMetricForm(null);
                    setEntriesVersion((current) => current + 1);
                    reload();
                },
            }) : null,
        );
    }

    /* ================================================================== */
    /* 6. Ayarlar: kategoriler                                             */
    /* ================================================================== */

    function paletteOptions() {
        const fromBoot = KS.options('category_colors');

        return Array.isArray(fromBoot) && fromBoot.length ? fromBoot.map(String) : KS.PALETTE;
    }

    function ColorSwatches(props) {
        return h('div', { className: 'ks-manage-swatches' }, props.colors.map((color) => {
            const active = props.value === color;

            return h('button', {
                key: color,
                type: 'button',
                className: cx('ks-manage-swatch', 'ks-c-' + KS.paletteColor(color), active && 'is-active'),
                'aria-pressed': active ? 'true' : 'false',
                'aria-label': t('color_' + color),
                title: t('color_' + color),
                disabled: props.disabled,
                onClick: () => props.onChange(color),
            }, active ? h(Icon, { name: 'check' }) : null);
        }));
    }

    function CategoryFormModal(props) {
        const category = props.category || null;
        const mounted = useMounted();
        const colors = paletteOptions();
        const initial = useMemo(() => ({
            name: category ? String(category.name || '') : '',
            color: category && category.color ? category.color : colors[0],
            status: category ? (category.status || 'active') : 'active',
            sort_order: category && category.sort_order !== null && category.sort_order !== undefined ? String(category.sort_order) : '',
        }), [category]);
        const [form, setForm] = useState(initial);
        const [errors, setErrors] = useState({});
        const [saving, setSaving] = useState(false);
        const dirty = Object.keys(initial).some((key) => initial[key] !== form[key]);

        const set = (key, value) => {
            setForm((current) => {
                const next = Object.assign({}, current);

                next[key] = value;

                return next;
            });

            if (errors[key]) {
                setErrors((current) => KS.omit(current, [key]));
            }
        };

        const close = () => {
            if (saving) {
                return;
            }

            if (!dirty) {
                props.onClose();

                return;
            }

            confirmDiscard().then((ok) => {
                if (ok) {
                    props.onClose();
                }
            });
        };

        const submit = () => {
            const next = {};
            const name = form.name.trim();

            if (name === '') {
                next.name = t('required_field');
            } else if (name.length > 120) {
                next.name = t('settings_name_too_long', { max: 120 });
            }

            if (form.sort_order !== '' && (!/^\d{1,5}$/.test(form.sort_order) || Number(form.sort_order) > 65535)) {
                next.sort_order = t('settings_sort_error');
            }

            if (Object.keys(next).length) {
                setErrors(next);

                return;
            }

            const payload = { name, color: form.color, status: form.status };

            if (form.sort_order !== '') {
                payload.sort_order = Number(form.sort_order);
            }

            setSaving(true);
            setErrors({});

            api.post(category ? url('categories.update', category.id) : url('categories.store'), payload).then((response) => {
                if (response && Array.isArray(response.categories)) {
                    patchBoot({ categories: response.categories });
                }

                KS.toast.success(t('settings_category_saved'));
                props.onClose();
            }).catch((error) => {
                if (!mounted.current) {
                    return;
                }

                setSaving(false);
                handleFormError(error, ['name', 'color', 'status', 'sort_order'], setErrors);
            });
        };

        return h(Modal, {
            title: category ? t('settings_category_edit') : t('settings_category_add'),
            icon: 'tag',
            size: 'md',
            dismissible: !saving,
            onClose: close,
            footer: [
                h(Button, { key: 'cancel', variant: 'ghost', disabled: saving, onClick: close }, t('cancel')),
                h(Button, { key: 'save', variant: 'primary', icon: 'save', loading: saving, onClick: submit }, t('save')),
            ],
        },
            h('div', { className: 'ks-form' },
                h(Field, { label: t('name'), required: true, error: errors.name },
                    h(TextInput, { value: form.name, onChange: (value) => set('name', value), maxLength: 120, disabled: saving, onEnter: submit, placeholder: t('settings_category_name_placeholder'), 'data-autofocus': '' })),
                h(Field, { label: t('settings_category_color'), group: true, error: errors.color, hint: t('settings_category_color_hint') },
                    h(ColorSwatches, { colors, value: form.color, onChange: (value) => set('color', value), disabled: saving })),
                h('div', { className: 'ks-form-row ks-form-row--2' },
                    h(Field, { label: t('settings_sort_order'), error: errors.sort_order, hint: t('settings_sort_hint') },
                        h(TextInput, { value: form.sort_order, onChange: (value) => set('sort_order', value.replace(/[^\d]/g, '')), inputMode: 'numeric', pattern: '[0-9]*', maxLength: 5, disabled: saving })),
                    h(Field, { label: t('status'), group: true, error: errors.status },
                        h(Switch, { checked: form.status === 'active', onChange: (checked) => set('status', checked ? 'active' : 'inactive'), label: form.status === 'active' ? t('active') : t('inactive'), hint: t('settings_category_status_hint'), disabled: saving })),
                ),
            ),
        );
    }

    function CategoriesSection() {
        const categories = KS.useStore((state) => (state.boot && state.boot.categories) || EMPTY_LIST);
        const mounted = useMounted();
        const [form, setForm] = useState(null);
        const [busy, setBusy] = useState(false);

        const payloadOf = (category, patch) => Object.assign({
            name: category.name,
            color: category.color,
            status: category.status,
            sort_order: Number(category.sort_order) || 0,
        }, patch || {});

        const finish = () => {
            if (mounted.current) {
                setBusy(false);
            }
        };

        const toggle = (category, active) => {
            setBusy(true);

            api.post(url('categories.update', category.id), payloadOf(category, { status: active ? 'active' : 'inactive' })).then((response) => {
                if (response && Array.isArray(response.categories)) {
                    patchBoot({ categories: response.categories });
                }
            }).catch((error) => {
                KS.handleError(error);
            }).then(finish);
        };

        // Sira degisimi: liste 10'ar artan siraya cekilir, yalniz degisen kayitlar gonderilir.
        const move = (index, delta) => {
            const target = index + delta;

            if (busy || target < 0 || target >= categories.length) {
                return;
            }

            const previous = categories;
            const ordered = categories.slice();
            const moved = ordered.splice(index, 1)[0];

            ordered.splice(target, 0, moved);

            const changed = [];

            ordered.forEach((category, position) => {
                const order = (position + 1) * 10;

                if (Number(category.sort_order) !== order) {
                    changed.push({ category, order });
                }
            });

            let lastGood = null;

            setBusy(true);
            patchBoot({ categories: ordered.map((category, position) => Object.assign({}, category, { sort_order: (position + 1) * 10 })) });

            changed.reduce((chain, item) => chain.then(() => api.post(url('categories.update', item.category.id), payloadOf(item.category, { sort_order: item.order })).then((response) => {
                if (response && Array.isArray(response.categories)) {
                    lastGood = response.categories;
                }
            })), Promise.resolve()).then(() => {
                if (lastGood) {
                    patchBoot({ categories: lastGood });
                }
            }).catch((error) => {
                patchBoot({ categories: lastGood || previous });
                KS.handleError(error);
            }).then(finish);
        };

        return h(Section, {
            title: t('settings_categories_title'),
            description: t('settings_categories_desc'),
            icon: 'tag',
            flush: true,
            actions: h(Button, { variant: 'primary', size: 'sm', icon: 'plus', onClick: () => setForm({ category: null }) }, t('settings_category_add')),
        },
            categories.length
                ? h('ul', { className: cx('ks-manage-list', busy && 'is-busy') }, categories.map((category, index) => {
                    const active = category.status !== 'inactive';

                    return h('li', { key: category.id, className: cx('ks-manage-row ks-manage-row--setting', !active && 'is-inactive') },
                        h('span', { className: cx('ks-manage-dot', 'ks-c-' + KS.paletteColor(category.color)), 'aria-hidden': 'true' }),
                        h('span', { className: 'ks-manage-row__main' },
                            h('span', { className: 'ks-manage-row__title' }, category.name),
                            h('span', { className: 'ks-manage-row__meta' },
                                h('span', null, t('color_' + KS.paletteColor(category.color))),
                                !active ? h(Badge, { color: 'stone', size: 'sm' }, category.status_label || t('inactive')) : null,
                            ),
                        ),
                        h('span', { className: 'ks-manage-row__actions' },
                            h(IconButton, { icon: 'arrow-up', label: t('settings_move_up'), size: 'sm', disabled: busy || index === 0, onClick: () => move(index, -1) }),
                            h(IconButton, { icon: 'arrow-down', label: t('settings_move_down'), size: 'sm', disabled: busy || index === categories.length - 1, onClick: () => move(index, 1) }),
                            h(Switch, { checked: active, disabled: busy, ariaLabel: t('settings_toggle_active', { name: category.name }), onChange: (checked) => toggle(category, checked) }),
                            h(IconButton, { icon: 'edit', label: t('edit'), size: 'sm', disabled: busy, onClick: () => setForm({ category }) }),
                        ),
                    );
                }))
                : h(Empty, {
                    icon: 'tag',
                    title: t('settings_categories_empty'),
                    text: t('settings_categories_empty_text'),
                    action: h(Button, { variant: 'primary', icon: 'plus', onClick: () => setForm({ category: null }) }, t('settings_category_add')),
                }),
            form ? h(CategoryFormModal, { key: form.category ? 'c' + form.category.id : 'new', category: form.category, onClose: () => setForm(null) }) : null,
        );
    }

    /* ================================================================== */
    /* 7. Ayarlar: ozel gunler                                             */
    /* ================================================================== */

    function sortDays(list) {
        return list.slice().sort((a, b) => (Number(a.month) - Number(b.month))
            || (Number(a.day) - Number(b.day))
            || String(a.name).localeCompare(String(b.name), KS.locale));
    }

    function isCalendarDate(month, day, year) {
        const probe = new Date(year || 2024, month - 1, day);

        return probe.getMonth() === month - 1 && probe.getDate() === day;
    }

    function dayPayload(day, patch) {
        return Object.assign({
            name: day.name,
            month: Number(day.month),
            day: Number(day.day),
            year: day.year === null || day.year === undefined || day.year === '' ? null : Number(day.year),
            profile_id: day.profile_id === null || day.profile_id === undefined || day.profile_id === '' ? null : Number(day.profile_id),
            note: day.note ? day.note : null,
            status: day.status || 'active',
        }, patch || {});
    }

    function DayFormModal(props) {
        const day = props.day || null;
        const profiles = props.profiles;
        const mounted = useMounted();
        const initial = useMemo(() => ({
            name: day ? String(day.name || '') : '',
            day: day ? Number(day.day) : '',
            month: day ? Number(day.month) : '',
            year: day && day.year ? String(day.year) : '',
            profile_id: day && day.profile_id ? Number(day.profile_id) : 'all',
            note: day && day.note ? String(day.note) : '',
            status: day ? (day.status || 'active') : 'active',
        }), [day]);
        const [form, setForm] = useState(initial);
        const [errors, setErrors] = useState({});
        const [saving, setSaving] = useState(false);
        const dirty = Object.keys(initial).some((key) => initial[key] !== form[key]);
        const dayOptions = useMemo(() => {
            const list = [];

            for (let value = 1; value <= 31; value += 1) {
                list.push({ value, label: String(value) });
            }

            return list;
        }, []);
        const monthOptions = useMemo(() => {
            const list = [];

            for (let value = 1; value <= 12; value += 1) {
                list.push({ value, label: monthName(value) });
            }

            return list;
        }, []);
        const scopeOptions = [{ value: 'all', label: t('settings_day_scope_all') }].concat(profiles.map((profile) => ({ value: profile.id, label: profile.name })));

        const set = (key, value) => {
            setForm((current) => {
                const next = Object.assign({}, current);

                next[key] = value;

                return next;
            });

            if (errors[key]) {
                setErrors((current) => KS.omit(current, [key]));
            }
        };

        const close = () => {
            if (saving) {
                return;
            }

            if (!dirty) {
                props.onClose();

                return;
            }

            confirmDiscard().then((ok) => {
                if (ok) {
                    props.onClose();
                }
            });
        };

        const submit = () => {
            const next = {};
            const name = form.name.trim();
            const year = form.year === '' ? null : Number(form.year);

            if (name === '') {
                next.name = t('required_field');
            } else if (name.length > 160) {
                next.name = t('settings_name_too_long', { max: 160 });
            }

            if (!form.day) {
                next.day = t('required_field');
            }

            if (!form.month) {
                next.month = t('required_field');
            }

            if (year !== null && (!/^\d{4}$/.test(form.year) || year < 2000 || year > 2100)) {
                next.year = t('settings_day_year_error');
            }

            if (!next.day && !next.month && !next.year && !isCalendarDate(Number(form.month), Number(form.day), year)) {
                next.day = t('settings_day_invalid_date');
            }

            if (form.note.length > 300) {
                next.note = t('metrics_note_error', { max: 300 });
            }

            if (Object.keys(next).length) {
                setErrors(next);

                return;
            }

            setSaving(true);
            setErrors({});

            api.post(day ? url('days.update', day.id) : url('days.store'), dayPayload({
                name,
                month: form.month,
                day: form.day,
                year,
                profile_id: form.profile_id === 'all' ? null : form.profile_id,
                note: form.note.trim(),
                status: form.status,
            })).then((response) => {
                KS.toast.success(t('settings_day_saved'));
                props.onSaved(response && response.day ? response.day : null);
            }).catch((error) => {
                if (!mounted.current) {
                    return;
                }

                setSaving(false);
                handleFormError(error, ['name', 'month', 'day', 'year', 'profile_id', 'note', 'status'], setErrors);
            });
        };

        return h(Modal, {
            title: day ? t('settings_day_edit') : t('settings_day_add'),
            icon: 'star',
            size: 'md',
            dismissible: !saving,
            onClose: close,
            footer: [
                h(Button, { key: 'cancel', variant: 'ghost', disabled: saving, onClick: close }, t('cancel')),
                h(Button, { key: 'save', variant: 'primary', icon: 'save', loading: saving, onClick: submit }, t('save')),
            ],
        },
            h('div', { className: 'ks-form' },
                h(Field, { label: t('name'), required: true, error: errors.name },
                    h(TextInput, { value: form.name, onChange: (value) => set('name', value), maxLength: 160, disabled: saving, placeholder: t('settings_day_name_placeholder'), 'data-autofocus': '' })),
                h('div', { className: 'ks-form-row ks-form-row--3' },
                    h(Field, { label: t('settings_day_day'), required: true, error: errors.day },
                        h(Select, { options: dayOptions, value: form.day, onChange: (value) => set('day', value), clearable: false, disabled: saving })),
                    h(Field, { label: t('settings_day_month'), required: true, error: errors.month },
                        h(Select, { options: monthOptions, value: form.month, onChange: (value) => set('month', value), clearable: false, disabled: saving })),
                    h(Field, { label: t('settings_day_year'), error: errors.year, hint: t('settings_day_year_hint') },
                        h(TextInput, { value: form.year, onChange: (value) => set('year', value.replace(/[^\d]/g, '')), inputMode: 'numeric', pattern: '[0-9]*', maxLength: 4, disabled: saving, placeholder: t('settings_day_every_year') })),
                ),
                h(Field, { label: t('settings_day_scope'), error: errors.profile_id, hint: t('settings_day_scope_hint') },
                    h(Select, { options: scopeOptions, value: form.profile_id, onChange: (value) => set('profile_id', value === '' ? 'all' : value), placeholder: false, disabled: saving })),
                h(Field, { label: t('note'), error: errors.note },
                    h(TextArea, { value: form.note, onChange: (value) => set('note', value), rows: 2, autoGrow: true, maxRows: 5, maxLength: 300, counter: true, disabled: saving, placeholder: t('settings_day_note_placeholder') })),
                h(Field, { label: t('status'), group: true, error: errors.status },
                    h(Switch, { checked: form.status === 'active', onChange: (checked) => set('status', checked ? 'active' : 'inactive'), label: form.status === 'active' ? t('active') : t('inactive'), hint: t('settings_day_status_hint'), disabled: saving })),
            ),
        );
    }

    function DaysSection() {
        const profiles = KS.useStore((state) => (state.boot && state.boot.profiles) || EMPTY_LIST);
        const mounted = useMounted();
        const resource = KS.useResource(() => api.get(url('days')), []);
        const [scope, setScope] = useState('all');
        const [form, setForm] = useState(null);
        const [busyId, setBusyId] = useState(null);
        const days = (resource.data && resource.data.days) || EMPTY_LIST;
        const setData = resource.setData;

        const upsert = (day) => {
            if (!day) {
                resource.reload();

                return;
            }

            setData((current) => {
                const list = ((current && current.days) || []).filter((item) => Number(item.id) !== Number(day.id));

                return { days: sortDays(list.concat([day])) };
            });
        };

        const toggle = (day, active) => {
            setBusyId(day.id);

            api.post(url('days.update', day.id), dayPayload(day, { status: active ? 'active' : 'inactive' })).then((response) => {
                upsert(response && response.day ? response.day : null);
            }).catch((error) => {
                KS.handleError(error);
            }).then(() => {
                if (mounted.current) {
                    setBusyId(null);
                }
            });
        };

        const visible = scope === 'all' ? days : days.filter((day) => day.profile_id === null || day.profile_id === undefined || Number(day.profile_id) === Number(scope));
        const groups = [];

        sortDays(visible).forEach((day) => {
            const last = groups[groups.length - 1];

            if (last && last.month === Number(day.month)) {
                last.days.push(day);
            } else {
                groups.push({ month: Number(day.month), days: [day] });
            }
        });

        const scopeItems = [{ value: 'all', label: t('all') }].concat(profiles.map((profile) => ({ value: profile.id, label: profile.name })));
        let body = null;

        if (resource.loading && !resource.data) {
            body = h('div', { className: 'ks-manage-pad' }, h(Skeleton, { variant: 'text', lines: 6 }));
        } else if (resource.error && !resource.data) {
            body = h(ErrorState, { error: resource.error, onRetry: resource.reload, compact: true });
        } else if (!days.length) {
            body = h(Empty, {
                icon: 'star',
                title: t('settings_days_empty'),
                text: t('settings_days_empty_text'),
                action: h(Button, { variant: 'primary', icon: 'plus', onClick: () => setForm({ day: null }) }, t('settings_day_add')),
            });
        } else if (!groups.length) {
            body = h(Empty, { compact: true, icon: 'star', title: t('settings_days_empty_scope'), action: h(Button, { variant: 'soft', onClick: () => setScope('all') }, t('clear_filters')) });
        } else {
            body = h('div', { className: 'ks-manage-months' }, groups.map((group) => h('section', { key: group.month, className: 'ks-manage-month' },
                h('h4', { className: 'ks-manage-month__title' },
                    h('span', null, monthName(group.month)),
                    h('span', { className: 'ks-manage-month__count' }, fmt.number(group.days.length)),
                ),
                h('ul', { className: 'ks-manage-list' }, group.days.map((day) => {
                    const active = day.status !== 'inactive';

                    return h('li', { key: day.id, className: cx('ks-manage-row ks-manage-row--setting', !active && 'is-inactive') },
                        h('span', { className: 'ks-manage-daybox ks-c-violet', 'aria-hidden': 'true' },
                            h('span', { className: 'ks-manage-daybox__day' }, day.day),
                            h('span', { className: 'ks-manage-daybox__month' }, monthShort(new Date(2024, Number(day.month) - 1, 1), false)),
                        ),
                        h('span', { className: 'ks-manage-row__main' },
                            h('span', { className: 'ks-manage-row__title' }, day.name),
                            h('span', { className: 'ks-manage-row__meta' },
                                h('span', null, day.date_label || (day.day + ' ' + monthName(day.month))),
                                h(Badge, { color: day.profile_id ? 'sky' : 'stone', size: 'sm' }, day.profile_id ? (day.profile_name || t('settings_day_scope_single')) : t('settings_day_scope_all')),
                                h(Badge, { color: 'stone', size: 'sm', icon: day.recurring === false ? 'calendar' : 'refresh' }, day.recurring === false ? t('settings_day_once') : t('settings_day_yearly')),
                                !active ? h(Badge, { color: 'amber', size: 'sm' }, day.status_label || t('inactive')) : null,
                                day.next_on ? h('span', { className: 'ks-nowrap' }, t('settings_day_next', { date: fmt.date(day.next_on) })) : null,
                            ),
                            day.note ? h('span', { className: 'ks-manage-row__note' }, day.note) : null,
                        ),
                        h('span', { className: 'ks-manage-row__actions' },
                            h(Switch, { checked: active, disabled: busyId !== null, ariaLabel: t('settings_toggle_active', { name: day.name }), onChange: (checked) => toggle(day, checked) }),
                            h(IconButton, { icon: 'edit', label: t('edit'), size: 'sm', disabled: busyId !== null, onClick: () => setForm({ day }) }),
                        ),
                    );
                })),
            )));
        }

        return h(Section, {
            title: t('settings_days_title'),
            description: t('settings_days_desc'),
            icon: 'star',
            flush: true,
            actions: h(Button, { variant: 'primary', size: 'sm', icon: 'plus', onClick: () => setForm({ day: null }) }, t('settings_day_add')),
        },
            days.length && profiles.length > 1 ? h('div', { className: 'ks-manage-subbar' },
                h(Segmented, { items: scopeItems, value: scope, onChange: setScope, size: 'sm', label: t('settings_day_scope') }),
                h('span', { className: 'ks-manage-subbar__hint' }, t('settings_days_scope_hint')),
            ) : null,
            resource.error && resource.data ? h(ErrorState, { error: resource.error, onRetry: resource.reload, compact: true }) : null,
            body,
            form ? h(DayFormModal, {
                key: form.day ? 'd' + form.day.id : 'new',
                day: form.day,
                profiles,
                onClose: () => setForm(null),
                onSaved: (day) => {
                    setForm(null);
                    upsert(day);
                },
            }) : null,
        );
    }

    /* ================================================================== */
    /* 8. Ayarlar: hesap baglantilari                                      */
    /* ================================================================== */

    function profileFormFrom(profile) {
        const links = {};

        (profile.links || []).forEach((link) => {
            links[link.platform] = { url: link.url || '', handle: link.handle || '' };
        });

        return { bio: profile.bio || '', links };
    }

    function serializeProfileForm(form, platforms) {
        return JSON.stringify([form.bio, platforms.map((platform) => {
            const link = form.links[platform.value] || {};

            return [platform.value, link.url || '', link.handle || ''];
        })]);
    }

    function ProfileLinksCard(props) {
        const profile = props.profile;
        const platforms = KS.options('platforms');
        const mounted = useMounted();
        const [form, setForm] = useState(() => profileFormFrom(profile));
        const [baseline, setBaseline] = useState(() => serializeProfileForm(profileFormFrom(profile), platforms));
        const [errors, setErrors] = useState({});
        const [saving, setSaving] = useState(false);
        const dirty = serializeProfileForm(form, platforms) !== baseline;
        const dirtyRef = useRef(dirty);

        dirtyRef.current = dirty;

        // Hesap verisi disaridan yenilenirse: kaydedilmemis degisiklik yoksa form da yenilenir.
        useEffect(() => {
            const fresh = profileFormFrom(profile);

            setBaseline(serializeProfileForm(fresh, platforms));

            if (!dirtyRef.current) {
                setForm(fresh);
            }
        }, [profile]);

        const setLink = (platform, key, value) => {
            setForm((current) => {
                const links = Object.assign({}, current.links);
                const link = Object.assign({ url: '', handle: '' }, links[platform] || {});

                link[key] = value;
                links[platform] = link;

                return { bio: current.bio, links };
            });

            if (errors[key + ':' + platform]) {
                setErrors((current) => KS.omit(current, [key + ':' + platform]));
            }
        };

        const reset = () => {
            setForm(profileFormFrom(profile));
            setErrors({});
        };

        const submit = () => {
            const next = {};
            const sent = [];

            if (form.bio.length > 2000) {
                next.bio = t('metrics_note_error', { max: fmt.number(2000) });
            }

            platforms.forEach((platform) => {
                const link = form.links[platform.value] || {};
                const address = String(link.url || '').trim();
                const handle = String(link.handle || '').trim();

                if (address === '') {
                    if (handle !== '') {
                        next['url:' + platform.value] = t('settings_link_url_needed');
                    }

                    return;
                }

                if (!/^https?:\/\/[^\s]+$/i.test(address) || address.length > 500) {
                    next['url:' + platform.value] = t('settings_link_url_error');

                    return;
                }

                if (handle.length > 120) {
                    next['handle:' + platform.value] = t('settings_name_too_long', { max: 120 });

                    return;
                }

                sent.push({ platform: platform.value, url: address, handle: handle === '' ? null : handle });
            });

            if (Object.keys(next).length) {
                setErrors(next);

                return;
            }

            setSaving(true);
            setErrors({});

            api.post(url('profiles.update', profile.id), { bio: form.bio.trim() === '' ? null : form.bio.trim(), links: sent }).then((response) => {
                const saved = response && response.profile ? response.profile : null;

                if (saved) {
                    const fresh = profileFormFrom(saved);

                    if (mounted.current) {
                        setForm(fresh);
                        setBaseline(serializeProfileForm(fresh, platforms));
                    }

                    KS.store.setState((state) => (state.boot ? {
                        boot: Object.assign({}, state.boot, {
                            profiles: (state.boot.profiles || []).map((item) => (Number(item.id) === Number(saved.id) ? Object.assign({}, item, saved) : item)),
                        }),
                    } : null));
                }

                KS.toast.success(t('settings_profile_saved'));
            }).catch((error) => {
                const map = fieldErrors(error);
                const mapped = {};
                let unknown = false;

                Object.keys(map).forEach((key) => {
                    const match = /^links\.(\d+)\.(url|handle|platform)$/.exec(key);

                    if (key === 'bio') {
                        mapped.bio = map[key];
                    } else if (match && sent[Number(match[1])]) {
                        mapped[(match[2] === 'handle' ? 'handle:' : 'url:') + sent[Number(match[1])].platform] = map[key];
                    } else {
                        unknown = true;
                    }
                });

                if (mounted.current) {
                    setErrors(mapped);
                }

                if (unknown || !Object.keys(mapped).length || Number(error && error.status) !== 422) {
                    KS.handleError(error);
                }
            }).then(() => {
                if (mounted.current) {
                    setSaving(false);
                }
            });
        };

        return h('article', { className: 'ks-manage-profile' },
            h('header', { className: 'ks-manage-profile__head' },
                h('span', { className: 'ks-manage-monogram', 'aria-hidden': 'true' }, profile.initials || String(profile.name || '?').charAt(0)),
                h('div', { className: 'ks-manage-profile__titles' },
                    h('h4', { className: 'ks-manage-profile__name' }, profile.name),
                    h('p', { className: 'ks-manage-profile__kind' }, profile.kind_label || ''),
                ),
                dirty ? h(Badge, { color: 'amber', size: 'sm', dot: true }, t('settings_unsaved')) : null,
            ),
            h('div', { className: 'ks-manage-profile__body' },
                h(Field, { label: t('settings_profile_bio'), error: errors.bio, hint: t('settings_profile_bio_hint') },
                    h(TextArea, { value: form.bio, onChange: (value) => setForm((current) => ({ bio: value, links: current.links })), rows: 3, autoGrow: true, maxRows: 8, maxLength: 2000, counter: true, disabled: saving })),
                h('div', { className: 'ks-manage-links', role: 'group', 'aria-label': t('settings_profile_links') },
                    platforms.map((platform) => {
                        const link = form.links[platform.value] || {};
                        const urlError = errors['url:' + platform.value];
                        const handleError = errors['handle:' + platform.value];

                        return h('div', { key: platform.value, className: cx('ks-manage-link', 'ks-platform', KS.platformClass(platform.value)) },
                            h('span', { className: 'ks-manage-link__platform' },
                                h(PlatformIcon, { platform: platform.value, variant: 'badge' }),
                                h('span', { className: 'ks-manage-link__name' }, platform.label),
                            ),
                            h(Field, { className: 'ks-manage-link__url', error: urlError },
                                h(TextInput, {
                                    type: 'url',
                                    inputMode: 'url',
                                    value: link.url || '',
                                    onChange: (value) => setLink(platform.value, 'url', value),
                                    placeholder: 'https://',
                                    icon: 'link',
                                    clearable: true,
                                    maxLength: 500,
                                    disabled: saving,
                                    'aria-label': t('settings_link_url_label', { platform: platform.label }),
                                })),
                            platform.value === 'website' ? h('span', { className: 'ks-manage-link__handle' }) : h(Field, { className: 'ks-manage-link__handle', error: handleError },
                                h(TextInput, {
                                    value: link.handle || '',
                                    onChange: (value) => setLink(platform.value, 'handle', value),
                                    placeholder: t('settings_link_handle_placeholder'),
                                    icon: 'user',
                                    maxLength: 120,
                                    disabled: saving,
                                    'aria-label': t('settings_link_handle_label', { platform: platform.label }),
                                })),
                        );
                    }),
                ),
            ),
            h('footer', { className: 'ks-manage-profile__foot' },
                // Birincil dugme solda: sag alt kose sohbet dugmesine aittir.
                h(Button, { variant: 'primary', icon: 'save', loading: saving, disabled: !dirty, onClick: submit }, t('save')),
                h(Button, { variant: 'ghost', disabled: !dirty || saving, onClick: reset }, t('reset')),
            ),
        );
    }

    function ProfilesSection() {
        const profiles = KS.useStore((state) => (state.boot && state.boot.profiles) || EMPTY_LIST);

        return h(Section, { title: t('settings_profiles_title'), description: t('settings_profiles_desc'), icon: 'link' },
            profiles.length
                ? h('div', { className: 'ks-manage-profiles' }, profiles.map((profile) => h(ProfileLinksCard, { key: profile.id, profile })))
                : h(Empty, { compact: true, icon: 'link', title: t('analytics_no_profile') }),
        );
    }

    /* ================================================================== */
    /* 9. Ayarlar: sorumlu gorevler (yalniz manage_settings)               */
    /* ================================================================== */

    function ResponsiblesSection() {
        const mounted = useMounted();
        const resource = KS.useResource(() => api.get(url('responsibles')), []);
        const [selected, setSelected] = useState(EMPTY_LIST);
        const [query, setQuery] = useState('');
        const [onlySelected, setOnlySelected] = useState(false);
        const [saving, setSaving] = useState(false);
        const data = resource.data;
        const positions = (data && data.positions) || EMPTY_LIST;
        const people = (data && data.people) || EMPTY_LIST;
        const setData = resource.setData;

        useEffect(() => {
            if (data) {
                setSelected(((data.positions) || []).filter((position) => position.selected).map((position) => Number(position.id)));
            }
        }, [data]);

        const savedKey = positions.filter((position) => position.selected).map((position) => Number(position.id)).sort((a, b) => a - b).join(',');
        const dirty = selected.slice().sort((a, b) => a - b).join(',') !== savedKey;
        const needle = lower(query.trim());
        const visible = positions.filter((position) => {
            if (onlySelected && selected.indexOf(Number(position.id)) === -1) {
                return false;
            }

            return needle === '' || lower(position.label).indexOf(needle) !== -1;
        });

        const toggle = (id, checked) => {
            setSelected((current) => {
                const rest = current.filter((item) => item !== id);

                return checked ? rest.concat([id]) : rest;
            });
        };

        const save = () => {
            if (selected.length > 200) {
                KS.toast.error(t('settings_resp_too_many', { max: 200 }));

                return;
            }

            const proceed = selected.length
                ? Promise.resolve(true)
                : KS.confirm({ title: t('settings_resp_none_title'), text: t('settings_resp_none_text'), confirmLabel: t('save'), danger: true });

            proceed.then((ok) => {
                if (!ok) {
                    return;
                }

                setSaving(true);

                api.post(url('responsibles.sync'), { position_ids: selected }).then((response) => {
                    if (response && Array.isArray(response.positions)) {
                        setData(response);
                    }

                    KS.toast.success(t('settings_resp_saved'));
                }).catch((error) => {
                    KS.handleError(error);
                }).then(() => {
                    if (mounted.current) {
                        setSaving(false);
                    }
                });
            });
        };

        let body = null;

        if (resource.loading && !data) {
            body = h('div', { className: 'ks-manage-resp' },
                h('div', { className: 'ks-manage-resp__pane' }, h(Skeleton, { variant: 'text', lines: 7 })),
                h('div', { className: 'ks-manage-resp__pane' }, h(Skeleton, { variant: 'text', lines: 5 })),
            );
        } else if (resource.error && !data) {
            body = h(ErrorState, { error: resource.error, onRetry: resource.reload, compact: true });
        } else {
            body = h('div', { className: 'ks-manage-resp' },
                h('div', { className: 'ks-manage-resp__pane' },
                    h('div', { className: 'ks-manage-resp__head' },
                        h('h4', { className: 'ks-manage-resp__title' }, t('settings_resp_positions')),
                        h('span', { className: 'ks-manage-resp__count' }, t('settings_resp_selected_count', { count: fmt.number(selected.length) })),
                    ),
                    h(TextInput, { type: 'search', icon: 'search', value: query, onChange: setQuery, clearable: true, placeholder: t('settings_resp_search'), 'aria-label': t('settings_resp_search') }),
                    h('div', { className: 'ks-manage-resp__tools' },
                        h(Checkbox, { checked: onlySelected, onChange: setOnlySelected, label: t('settings_resp_only_selected') }),
                        h(Button, { variant: 'link', size: 'sm', disabled: !selected.length || saving, onClick: () => setSelected([]) }, t('settings_resp_clear')),
                    ),
                    positions.length
                        ? (visible.length
                            ? h('ul', { className: 'ks-manage-checklist ks-scroll' }, visible.map((position) => h('li', { key: position.id },
                                h(Checkbox, {
                                    checked: selected.indexOf(Number(position.id)) !== -1,
                                    disabled: saving,
                                    label: position.label,
                                    onChange: (checked) => toggle(Number(position.id), checked),
                                }),
                            )))
                            : h(Empty, { compact: true, icon: 'search', title: t('no_results') }))
                        : h(Empty, { compact: true, icon: 'users', title: t('settings_resp_no_positions') }),
                ),
                h('div', { className: 'ks-manage-resp__pane' },
                    h('div', { className: 'ks-manage-resp__head' },
                        h('h4', { className: 'ks-manage-resp__title' }, t('settings_resp_people')),
                        h('span', { className: 'ks-manage-resp__count' }, fmt.number(people.length)),
                    ),
                    dirty ? h(Notice, { tone: 'warning', compact: true }, t('settings_resp_dirty_hint')) : null,
                    people.length
                        ? h('ul', { className: 'ks-manage-people ks-scroll' }, people.map((person) => h('li', { key: person.id }, h(PersonLine, { person, size: 'md' }))))
                        : h(Empty, { compact: true, icon: 'users', title: t('settings_resp_people_empty'), text: t('settings_resp_people_empty_text') }),
                ),
            );
        }

        return h(Section, {
            title: t('settings_resp_title'),
            description: t('settings_resp_desc'),
            icon: 'users',
            actions: data ? h(Button, { variant: 'primary', size: 'sm', icon: 'save', loading: saving, disabled: !dirty, onClick: save }, t('save')) : null,
        },
            h('div', { className: 'ks-stack' },
                h(Notice, { tone: 'info', title: t('settings_resp_info_title') }, t('settings_resp_info_text')),
                resource.error && data ? h(ErrorState, { error: resource.error, onRetry: resource.reload, compact: true }) : null,
                body,
            ),
        );
    }

    /* ================================================================== */
    /* 10. Ayarlar gorunumu                                                */
    /* ================================================================== */

    function Settings() {
        const abilities = KS.useAbilities();
        const [tab, setTab] = useState(() => {
            const wanted = pendingSettingsTab;

            pendingSettingsTab = null;

            return wanted || 'categories';
        });

        useEffect(() => KS.events.on('manage:settings-tab', (wanted) => {
            pendingSettingsTab = null;

            if (wanted) {
                setTab(wanted);
            }
        }), []);

        if (!abilities.manage_data) {
            return h(Empty, { icon: 'lock', title: t('forbidden'), text: t('settings_forbidden_text') });
        }

        const items = [
            KS.hasEndpoint('categories.store') ? { value: 'categories', label: t('settings_tab_categories'), icon: 'tag' } : null,
            KS.hasEndpoint('days') ? { value: 'days', label: t('settings_tab_days'), icon: 'star' } : null,
            KS.hasEndpoint('profiles.update') ? { value: 'profiles', label: t('settings_tab_profiles'), icon: 'link' } : null,
            abilities.manage_settings && KS.hasEndpoint('responsibles') ? { value: 'responsibles', label: t('settings_tab_responsibles'), icon: 'users' } : null,
        ].filter(Boolean);

        if (!items.length) {
            return h(Empty, { icon: 'settings', title: t('settings_unavailable') });
        }

        const active = items.some((item) => item.value === tab) ? tab : items[0].value;
        let section = null;

        if (active === 'categories') {
            section = h(CategoriesSection);
        } else if (active === 'days') {
            section = h(DaysSection);
        } else if (active === 'profiles') {
            section = h(ProfilesSection);
        } else {
            section = h(ResponsiblesSection);
        }

        return h('div', { className: 'ks-manage ks-manage--settings' },
            h('header', { className: 'ks-manage-head' },
                h('div', { className: 'ks-manage-head__titles' },
                    h('h2', { className: 'ks-title' }, t('settings')),
                    h('p', { className: 'ks-subtitle' }, t('settings_subtitle')),
                ),
            ),
            h(Tabs, { items, value: active, onChange: setTab, variant: 'pill', label: t('settings') }),
            h('div', { className: 'ks-manage-settings__body', role: 'tabpanel', 'aria-label': (items.find((item) => item.value === active) || {}).label }, section),
        );
    }

    /* ================================================================== */
    /* 11. Kayit                                                           */
    /* ================================================================== */

    KS.views.Analytics = Analytics;
    KS.views.Settings = Settings;

    KS.manage = {
        /** Ayarlar gorunumunu belirli sekmeyle acar: 'categories' | 'days' | 'profiles' | 'responsibles'. */
        openSettings(tab) {
            openSettings(tab);
            KS.events.emit('manage:settings-tab', tab || null);
        },
        /** Analiz gorunumunu "istatistik ekle" penceresiyle acar (manage_data yoksa yalniz gorunum acilir). */
        openMetricForm() {
            pendingMetricForm = true;
            KS.actions.setView('analiz');
            KS.events.emit('manage:metric-form', null);
        },
    };
}());
