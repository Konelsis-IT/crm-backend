/*
 * Konelsis - DEPARTMAN PANOLARI CEKIRDEGI (D-173, 8 Ekim 2026; kullanici onayi:
 * panolar "tamamen React ile").
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement). Departman
 * panolari (dash-app.js) ve pano bilesen katalogu (dash-catalog.js) bu dosyanin
 * kurdugu window.KonelsisDash (KD) ad alanini kullanir; alan bilesenleri
 * dash-widgets.js'tedir. Stiller konelsis-dash.css (kd- on eki, .kd kok).
 *
 * Kok: <div data-kd-root="dash-app" data-config="{...}">; yapilandirma
 * App\Filament\Support\DashboardAppConfig::make(). Veri salt okunurdur.
 *
 * API: KD.h, KD.hooks, KD.mount(ad, Bilesen), KD.useApp() -> { config, t, fmt, toast, url }
 * Bilesenler: Icon, TypeChip, StatusPill, Panel, Modal, Seg, Dropdown, Sparkline,
 *   CellBar, ComboChart, SmallMultiples, Donut, StackBar, Funnel, Heatmap, Legend.
 * Grafikler el yazimi SVG'dir (grafik kutuphanesi yok). Renkler CSS
 * degiskenleridir (acik / koyu kip, Filament'in html.dark sinifi). Tek eksen
 * kurali: birlesik grafikte butun seriler ayni olcudedir (adet).
 */
(function () {
    'use strict';

    if (!window.React || !window.ReactDOM) {
        console.error('KonelsisDash: React yok');
        return;
    }

    if (window.KonelsisDash) {
        return;
    }

    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const h = React.createElement;
    const Fragment = React.Fragment;
    const { useState, useEffect, useRef, useMemo, useCallback, useContext, createContext } = React;

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    function cx() {
        const out = [];

        for (let i = 0; i < arguments.length; i++) {
            const part = arguments[i];

            if (!part) {
                continue;
            }

            if (typeof part === 'string') {
                out.push(part);
            } else if (typeof part === 'object') {
                Object.keys(part).forEach((key) => { if (part[key]) { out.push(key); } });
            }
        }

        return out.join(' ');
    }

    function readConfig(el) {
        try {
            return JSON.parse(el.getAttribute('data-config') || '{}') || {};
        } catch (error) {
            console.error('KonelsisDash: data-config okunamadi', error);
            return {};
        }
    }

    function makeT(labels) {
        return function t(key, params) {
            let text = labels && typeof labels[key] === 'string' ? labels[key] : key;

            if (params) {
                Object.keys(params)
                    .sort((a, b) => b.length - a.length)
                    .forEach((name) => { text = text.split(':' + name).join(params[name] === null || params[name] === undefined ? '' : String(params[name])); });
            }

            return text;
        };
    }

    function fillUrl(template, id) {
        return template && id !== null && id !== undefined ? String(template).split('__ID__').join(encodeURIComponent(String(id))) : null;
    }

    /** Tarayici deposu yalniz kisisel kolaylik (sutun secimi); okunamazsa varsayilan. */
    const store = {
        get(key, fallback) {
            try {
                const raw = window.localStorage.getItem('kd:' + key);
                return raw === null ? fallback : JSON.parse(raw);
            } catch (error) {
                return fallback;
            }
        },
        set(key, value) {
            try { window.localStorage.setItem('kd:' + key, JSON.stringify(value)); } catch (error) { /* kisisel kolaylik; yoksay */ }
        },
    };

    /* ------------------------------------------------------------------ */
    /* Bicim                                                                */
    /* ------------------------------------------------------------------ */

    function makeFmt(locale, symbols) {
        const tag = locale === 'en' ? 'en-GB' : 'tr-TR';
        const cache = {};
        const nf = (digits) => (cache[digits] = cache[digits] || new Intl.NumberFormat(tag, { maximumFractionDigits: digits, minimumFractionDigits: 0 }));
        let compactNf = null;
        let moneyCompactNf = null;
        let cents = null;

        try {
            compactNf = new Intl.NumberFormat(tag, { notation: 'compact', maximumFractionDigits: 1 });
            // D-180: tutarda kisaltma okunur kelimeyle ("225,8 bin", "1,2 milyon").
            moneyCompactNf = new Intl.NumberFormat(tag, { notation: 'compact', compactDisplay: 'long', maximumFractionDigits: 1 });
            cents = new Intl.NumberFormat(tag, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (error) {
            compactNf = null;
            moneyCompactNf = null;
            cents = null;
        }

        // D-180 (App\Support\Money ile ayni kural): kurus yoksa "1.000", varsa
        // "1.000,50"; arkada para biriminin simgesi (ISO kodu degil).
        const symbol = (currency) => (currency ? ((symbols || {})[currency] || currency) : '');
        const amount = (n, compact) => {
            if (compact && moneyCompactNf && Math.abs(n) >= 1000) { return moneyCompactNf.format(n); }
            const rounded = Math.round(n * 100) / 100;
            return (Number.isInteger(rounded) || !cents) ? nf(0).format(rounded) : cents.format(rounded);
        };

        const date = (iso) => {
            if (!iso) { return '–'; }
            const p = String(iso).slice(0, 10).split('-');
            return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : String(iso);
        };

        return {
            num: (n, digits) => (n === null || n === undefined || isNaN(n) ? '–' : nf(digits || 0).format(n)),
            compact: (n) => (n === null || n === undefined ? '–' : (compactNf ? compactNf.format(n) : nf(0).format(n))),
            money: (n, currency, compact) => (n === null || n === undefined ? '–' : (amount(n, compact) + (symbol(currency) ? ' ' + symbol(currency) : ''))),
            date,
            dshort: (iso) => { const d = date(iso); return d.length === 10 ? d.slice(0, 6) + d.slice(8) : d; },
            dm: (iso) => date(iso).slice(0, 5),
            pct: (n) => (n === null || n === undefined ? '–' : '%' + nf(0).format(n)),
        };
    }

    /** Para birimine gore toplamlar: "225.791 [TL simgesi] · 1.200 $" ya da null (D-180). */
    function sumsText(sums, fmt, compact) {
        const keys = Object.keys(sums || {});

        if (!keys.length) {
            return null;
        }

        return keys.map((cur) => fmt.money(sums[cur], cur, compact)).join(' · ');
    }

    /** Grafik ekseni icin yuvarlak tepe degeri. */
    function niceMax(value) {
        if (!value || value <= 0) { return 4; }
        const exp = Math.pow(10, Math.floor(Math.log10(value)));
        const steps = [1, 2, 2.5, 5, 10];

        for (let i = 0; i < steps.length; i++) {
            if (value <= steps[i] * exp) { return steps[i] * exp; }
        }

        return 10 * exp;
    }

    /* ------------------------------------------------------------------ */
    /* Uygulama baglami                                                     */
    /* ------------------------------------------------------------------ */

    const AppContext = createContext(null);

    function useApp() {
        return useContext(AppContext);
    }

    let toastSeq = 0;

    function AppShell(props) {
        const { config, children } = props;
        const [toasts, setToasts] = useState([]);
        const value = useMemo(() => {
            const t = makeT(config.labels || {});
            const fmt = makeFmt(config.locale, config.currency_symbols);
            const toast = (text, tone) => {
                const id = ++toastSeq;
                setToasts((list) => list.concat([{ id, text, tone: tone || null }]).slice(-3));
                window.setTimeout(() => setToasts((list) => list.filter((item) => item.id !== id)), 2600);
            };
            const url = (name, id) => fillUrl((config.urls || {})[name], id);

            return { config, t, fmt, toast, url, icons: config.icons || {} };
        }, [config]);

        return h(AppContext.Provider, { value },
            children,
            h('div', { className: 'kd-toasts', role: 'status', 'aria-live': 'polite' },
                toasts.map((item) => h('div', { key: item.id, className: cx('kd-toast', item.tone) }, item.text)),
            ),
        );
    }

    function mount(name, Component) {
        const start = () => {
            document.querySelectorAll('[data-kd-root="' + name + '"]').forEach((el) => {
                if (el.getAttribute('data-kd-mounted') === '1') { return; }

                el.setAttribute('data-kd-mounted', '1');
                el.classList.add('kd');

                try {
                    const config = readConfig(el);
                    ReactDOM.createRoot(el).render(h(AppShell, { config }, h(Component, null)));
                } catch (error) {
                    el.removeAttribute('data-kd-mounted');
                    console.error('KonelsisDash: ' + name + ' baglanamadi', error);
                }
            });
        };

        if (document.readyState !== 'loading') {
            start();
        } else {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        }

        document.addEventListener('livewire:navigated', start);
    }

    /** Ogenin genisligini izler (grafikler kutuya gore cizilir). */
    function useWidth(fallback) {
        const ref = useRef(null);
        const [width, setWidth] = useState(fallback || 480);

        useEffect(() => {
            const el = ref.current;

            if (!el) { return undefined; }

            const measure = () => setWidth(Math.max(120, Math.floor(el.getBoundingClientRect().width)));
            measure();

            if (typeof ResizeObserver === 'undefined') {
                window.addEventListener('resize', measure);
                return () => window.removeEventListener('resize', measure);
            }

            const observer = new ResizeObserver(measure);
            observer.observe(el);
            return () => observer.disconnect();
        }, []);

        return [ref, width];
    }

    /* ------------------------------------------------------------------ */
    /* Kucuk bilesenler                                                     */
    /* ------------------------------------------------------------------ */

    /** Sunucuda uretilen Heroicon SVG'si (ad ya da dogrudan svg). */
    function Icon(props) {
        const { name, svg, className, title } = props;
        const { icons } = useApp();
        const markup = svg || icons[name] || '';

        return h('span', {
            className: cx('kd-ic', className),
            title: title || undefined,
            'aria-hidden': title ? undefined : 'true',
            dangerouslySetInnerHTML: { __html: markup },
        });
    }

    /** Proje tipi: tek simge kaynagi ProjectScopeType::getIcon (D-163). */
    function TypeChip(props) {
        const { type, compact } = props;
        const { config } = useApp();
        const def = (config.types || {})[type];

        if (!def) { return null; }

        return h('span', { className: cx('kd-type', 'kd-c-' + def.color, { compact }), title: def.label },
            h(Icon, { svg: def.icon }),
            compact ? null : h('b', null, def.label),
        );
    }

    function statusDef(config, value) {
        return (config.statuses || []).find((row) => row.value === value) || { value, label: value, color: 'gray', icon: 'flag' };
    }

    function StatusPill(props) {
        const { status, short } = props;
        const { config } = useApp();
        const def = statusDef(config, status);

        return h('span', { className: cx('kd-st', 'kd-st-' + def.value), title: def.label },
            h(Icon, { name: def.icon }),
            short ? null : h('span', null, def.label),
        );
    }

    function Panel(props) {
        const { num, title, sub, right, children, className, flush, id } = props;

        return h('section', { className: cx('kd-panel', className), id: id || undefined },
            title || right || num ? h('header', { className: 'kd-ph' },
                num ? h('span', { className: 'kd-num', title: '#' + num }, num) : null,
                h('div', { className: 'kd-pt' },
                    title ? h('h3', null, title) : null,
                    sub ? h('span', { className: 'kd-sub' }, sub) : null,
                ),
                right ? h('div', { className: 'kd-pr' }, right) : null,
            ) : null,
            h('div', { className: cx('kd-pb', { flush }) }, children),
        );
    }

    function Empty(props) {
        const { t } = useApp();
        return h('div', { className: 'kd-empty' }, props.text || t('no_data'));
    }

    function Modal(props) {
        const { title, onClose, children, footer } = props;

        useEffect(() => {
            const onKey = (event) => { if (event.key === 'Escape') { onClose(); } };
            document.addEventListener('keydown', onKey);
            return () => document.removeEventListener('keydown', onKey);
        }, [onClose]);

        return ReactDOM.createPortal(
            h('div', { className: 'kd kd-modal-wrap', onMouseDown: (event) => { if (event.target === event.currentTarget) { onClose(); } } },
                h('div', { className: 'kd-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
                    h('header', null,
                        h('h3', null, title),
                        h('button', { type: 'button', className: 'kd-x', onClick: onClose, 'aria-label': 'x' }, h(Icon, { name: 'close' })),
                    ),
                    h('div', { className: 'kd-modal-body' }, children),
                    footer ? h('footer', null, footer) : null,
                ),
            ),
            document.body,
        );
    }

    /** Bolmeli secici (kip, durum sekmeleri). */
    function Seg(props) {
        const { options, value, onChange, size, label } = props;

        return h('div', { className: cx('kd-seg', size), role: 'tablist', 'aria-label': label || undefined },
            options.map((opt) => h('button', {
                key: String(opt.value),
                type: 'button',
                role: 'tab',
                'aria-selected': opt.value === value ? 'true' : 'false',
                className: cx(opt.className, { on: opt.value === value }),
                title: opt.title || undefined,
                onClick: () => onChange(opt.value),
            }, opt.icon ? h(Icon, { name: opt.icon }) : null, h('span', null, opt.label), opt.count !== undefined ? h('em', null, opt.count) : null)),
        );
    }

    /** Acilir kutu (sutun secici). */
    function Dropdown(props) {
        const { trigger, children, align } = props;
        const [open, setOpen] = useState(false);
        const ref = useRef(null);

        useEffect(() => {
            if (!open) { return undefined; }
            const onDoc = (event) => { if (ref.current && !ref.current.contains(event.target)) { setOpen(false); } };
            const onKey = (event) => { if (event.key === 'Escape') { setOpen(false); } };
            document.addEventListener('mousedown', onDoc);
            document.addEventListener('keydown', onKey);
            return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey); };
        }, [open]);

        return h('div', { className: 'kd-dd', ref },
            trigger(open, () => setOpen(!open)),
            open ? h('div', { className: cx('kd-dd-panel', align) }, children(() => setOpen(false))) : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Grafikler (el yazimi SVG)                                            */
    /* ------------------------------------------------------------------ */

    /** Hucre ici kucuk cizgi grafik. */
    function Sparkline(props) {
        const { values, width, height, color, area, label } = props;
        const w = width || 64;
        const hh = height || 16;
        const list = values || [];
        const max = Math.max(1, ...list);
        const step = list.length > 1 ? (w - 4) / (list.length - 1) : 0;
        const pts = list.map((v, i) => [2 + i * step, hh - 2 - (v / max) * (hh - 4)]);
        const line = pts.map((p) => p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
        const last = pts[pts.length - 1];
        const total = list.reduce((a, b) => a + b, 0);

        return h('svg', { className: 'kd-spark', width: w, height: hh, viewBox: '0 0 ' + w + ' ' + hh, role: 'img', 'aria-label': (label || '') + ' ' + list.join(', ') },
            h('title', null, (label ? label + ': ' : '') + list.join(' · ') + ' (Σ ' + total + ')'),
            area !== false && pts.length ? h('polygon', { points: line + ' ' + last[0].toFixed(1) + ',' + (hh - 1) + ' 2,' + (hh - 1), fill: color || 'var(--kd-s1)', opacity: 0.14 }) : null,
            pts.length ? h('polyline', { points: line, fill: 'none', stroke: color || 'var(--kd-s1)', strokeWidth: 1.5, strokeLinejoin: 'round', strokeLinecap: 'round' }) : null,
            last && total > 0 ? h('circle', { cx: last[0], cy: last[1], r: 2, fill: color || 'var(--kd-s1)' }) : null,
        );
    }

    /** Hucrenin zemininde cubuk (tablonun bos zemini). */
    function CellBar(props) {
        const { value, max, tone, children, align } = props;
        const pct = max > 0 && value !== null && value !== undefined ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;

        return h('span', { className: cx('kd-cellbar', tone, align) },
            h('i', { style: { width: pct + '%' }, 'aria-hidden': 'true' }),
            h('span', null, children),
        );
    }

    function Legend(props) {
        const { series, hidden, onToggle } = props;

        return h('div', { className: 'kd-legend' }, series.map((s) => h('button', {
            key: s.key,
            type: 'button',
            className: cx('kd-lg', s.kind, { off: hidden && hidden[s.key] }),
            onClick: onToggle ? () => onToggle(s.key) : undefined,
            'aria-pressed': hidden ? (hidden[s.key] ? 'false' : 'true') : undefined,
        }, h('i', { style: { '--c': s.color } }), s.label)));
    }

    /**
     * Birlesik grafik: cubuk + cizgi + alan + nokta, TEK eksen (hepsi adet).
     * series: [{ key, label, kind: 'bar'|'line'|'area'|'dot', color }]
     */
    function ComboChart(props) {
        const { rows, series, height, xKey, initialHidden, unit } = props;
        const { fmt } = useApp();
        const [ref, width] = useWidth(520);
        const [hidden, setHidden] = useState(initialHidden || {});
        const [hover, setHover] = useState(null);
        const H = height || 170;
        const pad = { l: 30, r: 8, t: 10, b: 20 };
        const innerW = Math.max(40, width - pad.l - pad.r);
        const innerH = H - pad.t - pad.b;
        const visible = series.filter((s) => !hidden[s.key]);
        const rawMax = Math.max(0, ...rows.map((row) => Math.max(0, ...visible.map((s) => row[s.key] || 0))));
        const top = niceMax(rawMax);
        const n = rows.length;
        const band = innerW / Math.max(1, n);
        const x = (i) => pad.l + band * i + band / 2;
        const y = (v) => pad.t + innerH - (v / top) * innerH;
        const bars = visible.filter((s) => s.kind === 'bar');
        const barW = Math.max(2, Math.min(18, (band * 0.7) / Math.max(1, bars.length)) - 2);
        const ticks = [0, top / 2, top];
        const labelEvery = Math.ceil(n / Math.max(1, Math.floor(innerW / 42)));

        const onMove = (event) => {
            const rect = event.currentTarget.getBoundingClientRect();
            const px = event.clientX - rect.left - pad.l;
            const i = Math.max(0, Math.min(n - 1, Math.floor(px / band)));
            setHover(i);
        };

        const toggle = (key) => setHidden(Object.assign({}, hidden, { [key]: !hidden[key] }));
        const pathFor = (s) => rows.map((row, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(row[s.key] || 0).toFixed(1)).join(' ');

        return h('div', { className: 'kd-chart', ref },
            h(Legend, { series, hidden, onToggle: toggle }),
            h('svg', { width, height: H, viewBox: '0 0 ' + width + ' ' + H, role: 'img', 'aria-label': series.map((s) => s.label).join(', '), onMouseMove: onMove, onMouseLeave: () => setHover(null) },
                ticks.map((tick) => h('g', { key: 't' + tick },
                    h('line', { x1: pad.l, x2: width - pad.r, y1: y(tick), y2: y(tick), className: tick === 0 ? 'kd-axis' : 'kd-gl' }),
                    h('text', { x: pad.l - 4, y: y(tick) + 3, textAnchor: 'end', className: 'kd-tick' }, fmt.compact(tick)),
                )),
                rows.map((row, i) => (i % labelEvery === 0 || i === n - 1) ? h('text', { key: 'x' + i, x: x(i), y: H - 6, textAnchor: 'middle', className: 'kd-tick' }, row[xKey || 'label']) : null),
                hover !== null ? h('rect', { x: pad.l + band * hover, y: pad.t, width: band, height: innerH, className: 'kd-hoverband' }) : null,
                // Alanlar en altta, sonra cubuklar, cizgiler, noktalar.
                visible.filter((s) => s.kind === 'area').map((s) => h('path', {
                    key: s.key,
                    d: pathFor(s) + ' L' + x(n - 1).toFixed(1) + ' ' + y(0) + ' L' + x(0).toFixed(1) + ' ' + y(0) + ' Z',
                    fill: s.color, opacity: 0.16, stroke: s.color, strokeWidth: 1.5,
                })),
                bars.map((s, bi) => h('g', { key: s.key }, rows.map((row, i) => {
                    const v = row[s.key] || 0;
                    if (!v) { return null; }
                    const bx = x(i) - (bars.length * (barW + 2)) / 2 + bi * (barW + 2) + 1;
                    return h('rect', { key: i, x: bx, y: y(v), width: barW, height: Math.max(1, y(0) - y(v)), rx: 2, fill: s.color });
                }))),
                visible.filter((s) => s.kind === 'line').map((s) => h('path', { key: s.key, d: pathFor(s), fill: 'none', stroke: s.color, strokeWidth: 2, strokeLinejoin: 'round', strokeLinecap: 'round' })),
                visible.filter((s) => s.kind === 'dot').map((s) => h('g', { key: s.key }, rows.map((row, i) => (row[s.key] ? h('circle', { key: i, cx: x(i), cy: y(row[s.key]), r: 4, fill: s.color, className: 'kd-dot' }) : null)))),
                hover !== null ? h('line', { x1: x(hover), x2: x(hover), y1: pad.t, y2: pad.t + innerH, className: 'kd-cross' }) : null,
            ),
            hover !== null && rows[hover] ? h('div', {
                className: 'kd-tip',
                style: { left: Math.min(width - 150, Math.max(0, x(hover) + 8)) + 'px', top: '22px' },
            },
                h('b', null, rows[hover][xKey || 'label']),
                series.filter((s) => !hidden[s.key]).map((s) => h('span', { key: s.key }, h('i', { style: { background: s.color } }), s.label, h('em', null, fmt.num(rows[hover][s.key] || 0) + (unit ? ' ' + unit : '')))),
            ) : null,
        );
    }

    /** Kucuk coklu grafik: her seri kendi kutusunda, ayni zaman ekseniyle. */
    function SmallMultiples(props) {
        const { rows, series, xKey } = props;
        const { fmt } = useApp();

        return h('div', { className: 'kd-multiples' }, series.map((s) => {
            const values = rows.map((row) => row[s.key] || 0);
            const total = values.reduce((a, b) => a + b, 0);
            const last = values[values.length - 1];

            return h('div', { key: s.key, className: 'kd-mult' },
                h('div', { className: 'kd-mult-h' }, h('i', { style: { background: s.color } }), h('span', null, s.label), h('b', null, fmt.num(total))),
                h(Sparkline, { values, width: 150, height: 34, color: s.color, label: s.label }),
                h('div', { className: 'kd-mult-f' }, h('span', null, rows.length ? rows[0][xKey || 'label'] : ''), h('span', null, (rows.length ? rows[rows.length - 1][xKey || 'label'] : '') + ': ' + fmt.num(last))),
            );
        }));
    }

    /** Mini halka (parca-butun, en fazla 6 dilim). */
    function Donut(props) {
        const { parts, size, centerLabel, centerValue } = props;
        const S = size || 88;
        const r = S / 2 - 8;
        const c = 2 * Math.PI * r;
        const total = parts.reduce((a, p) => a + p.value, 0);
        let offset = 0;

        return h('svg', { width: S, height: S, viewBox: '0 0 ' + S + ' ' + S, role: 'img', 'aria-label': parts.map((p) => p.label + ' ' + p.value).join(', ') },
            h('circle', { cx: S / 2, cy: S / 2, r, fill: 'none', stroke: 'var(--kd-surface-2)', strokeWidth: 12 }),
            total > 0 ? parts.filter((p) => p.value > 0).map((p) => {
                const len = (p.value / total) * c;
                const el = h('circle', {
                    key: p.key, cx: S / 2, cy: S / 2, r, fill: 'none', stroke: p.color, strokeWidth: 12,
                    strokeDasharray: Math.max(0, len - 2) + ' ' + c, strokeDashoffset: -offset, transform: 'rotate(-90 ' + S / 2 + ' ' + S / 2 + ')',
                }, h('title', null, p.label + ': ' + p.value));
                offset += len;
                return el;
            }) : null,
            h('text', { x: S / 2, y: S / 2 + 1, textAnchor: 'middle', className: 'kd-donut-v' }, centerValue !== undefined ? centerValue : total),
            centerLabel ? h('text', { x: S / 2, y: S / 2 + 12, textAnchor: 'middle', className: 'kd-tick' }, centerLabel) : null,
        );
    }

    /** Yuzde yigilmis yatay cubuk. */
    function StackBar(props) {
        const { parts, height } = props;
        const total = parts.reduce((a, p) => a + p.value, 0);

        return h('div', { className: 'kd-stack', style: { height: (height || 14) + 'px' }, role: 'img', 'aria-label': parts.map((p) => p.label + ' ' + p.value).join(', ') },
            total > 0 ? parts.filter((p) => p.value > 0).map((p) => h('span', {
                key: p.key, className: p.className, style: { width: (p.value / total) * 100 + '%', background: p.color || undefined }, title: p.label + ': ' + p.value + ' (%' + Math.round((p.value / total) * 100) + ')',
            }, (p.value / total) > 0.08 ? String(p.value) : '')) : null,
        );
    }

    /** Donusum hunisi: her adim bir oncekinden gecis yuzdesiyle. */
    function Funnel(props) {
        const { steps } = props;
        const { t, fmt } = useApp();
        const max = Math.max(1, ...steps.map((s) => s.value));

        return h('div', { className: 'kd-funnel' }, steps.map((step, i) => {
            const prev = i > 0 ? steps[step.from !== undefined ? step.from : i - 1].value : null;
            const conv = prev ? Math.round((step.value / prev) * 100) : null;

            return h('div', { key: step.key, className: cx('kd-fstep', step.tone) },
                h('span', { className: 'kd-fl' }, step.label),
                h('span', { className: 'kd-fbar' }, h('i', { style: { width: Math.max(2, (step.value / max) * 100) + '%' } })),
                h('b', null, fmt.num(step.value)),
                h('em', null, conv !== null ? t('conv', { p: conv }) : ''),
            );
        }));
    }

    /** Sirali mavi rampa (dataviz varsayilani): 0 bos, sonra 6 kademe. */
    const RAMP = ['var(--kd-q1)', 'var(--kd-q2)', 'var(--kd-q3)', 'var(--kd-q4)', 'var(--kd-q5)', 'var(--kd-q6)'];

    function rampColor(value, max) {
        if (!value) { return 'var(--kd-surface-2)'; }
        const i = Math.min(RAMP.length - 1, Math.floor((value / Math.max(1, max)) * RAMP.length - 0.0001));
        return RAMP[Math.max(0, i)];
    }

    /** Isi haritasi: satir x sutun, sirali tek renk. */
    function Heatmap(props) {
        const { rows, cols, onRow } = props;
        const { t } = useApp();
        const max = Math.max(1, ...rows.map((row) => Math.max(0, ...row.values)));

        return h('div', { className: 'kd-heat' },
            h('table', null,
                h('thead', null, h('tr', null, h('th', null), cols.map((col, i) => h('th', { key: i }, col)), h('th', null, 'Σ'))),
                h('tbody', null, rows.map((row) => h('tr', { key: row.key, onClick: onRow ? () => onRow(row) : undefined, className: onRow ? 'kd-click' : null },
                    h('th', { title: row.title || row.label }, row.icon || null, row.label),
                    row.values.map((v, i) => h('td', { key: i, style: { background: rampColor(v, max) }, className: v / max > 0.5 ? 'dark' : null, title: row.label + ' · ' + cols[i] + ': ' + v }, v || '')),
                    h('td', { className: 'kd-sum' }, row.values.reduce((a, b) => a + b, 0)),
                ))),
            ),
            h('div', { className: 'kd-ramp' }, h('span', null, t('heat_scale')), RAMP.map((c, i) => h('i', { key: i, style: { background: c } }))),
        );
    }

    window.KonelsisDash = {
        h, Fragment, cx, store, sumsText, niceMax, rampColor, statusDef, fillUrl,
        hooks: { useState, useEffect, useRef, useMemo, useCallback },
        mount, useApp, useWidth,
        Icon, TypeChip, StatusPill, Panel, Empty, Modal, Seg, Dropdown,
        Sparkline, CellBar, Legend, ComboChart, SmallMultiples, Donut, StackBar, Funnel, Heatmap,
    };
}());
