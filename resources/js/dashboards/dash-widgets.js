/*
 * Konelsis - DEPARTMAN PANOLARI ALAN BILESENLERI (D-173, 8 Ekim 2026).
 *
 * dash-core.js'in (window.KonelsisDash) uzerine: gosterge bandi, gosterge
 * kutulari, birlesik grafikler, teklif masasi (yogun tablo; hucre ici cubuk,
 * satir arkasi cubuk, ustte sutun dagilimi), durum dugmeleri, huni, isi
 * haritasi, firma liderlik tablosu, proje tipi halkasi, uyari seridi, hareket
 * akisi, durum dagilimi, para birimi kutulari, teklif derinligi, bekleyen
 * teklifler, ekip tablosu. Panolar (dash-app.js) ve katalog (dash-catalog.js)
 * bunlari dizer: KD.W.<Bilesen>.
 *
 * DENEME (D-173): durum dugmeleri KAYIT DEGISTIRMEZ. Tiklama yalniz ekrandaki
 * satiri degistirir ve "Deneme ekranı: kaydedilmedi" der. Kullanici onaylayinca
 * pickStatus() icindeki yere gercek uc baglanacak (asagidaki not).
 */
(function () {
    'use strict';

    const KD = window.KonelsisDash;

    if (!KD) {
        return;
    }

    const { h, Fragment, cx, useApp, store, sumsText, statusDef, Icon, TypeChip, StatusPill, Panel, Empty, Modal, Seg, Dropdown,
        Sparkline, CellBar, ComboChart, SmallMultiples, Donut, StackBar, Funnel, Heatmap } = KD;
    const { useState, useMemo, useCallback } = KD.hooks;

    const STATUS_ORDER = ['to_be_submitted', 'submitted', 'approved', 'lost'];

    /* ------------------------------------------------------------------ */
    /* Ortak                                                                */
    /* ------------------------------------------------------------------ */

    function sumObjects(a, b) {
        const out = Object.assign({}, a || {});
        Object.keys(b || {}).forEach((key) => { out[key] = (out[key] || 0) + b[key]; });
        return out;
    }

    /** Son iki haftanin farki: { text, tone } ya da null. */
    function weekDelta(weeks, key, goodWhenUp) {
        if (!weeks || weeks.length < 2) { return null; }
        const cur = weeks[weeks.length - 1][key] || 0;
        const prev = weeks[weeks.length - 2][key] || 0;
        const diff = cur - prev;

        if (diff === 0) { return { text: '= ' + prev, tone: 'flat' }; }

        const up = diff > 0;
        return { text: (up ? '▲' : '▼') + Math.abs(diff), tone: (up === goodWhenUp) ? 'up' : 'down' };
    }

    function series(t) {
        return {
            submitted: { key: 'submitted', label: t('s_submitted'), color: 'var(--kd-s1)' },
            notes: { key: 'notes', label: t('s_notes'), color: 'var(--kd-s2)' },
            new_cases: { key: 'new_cases', label: t('s_new_cases'), color: 'var(--kd-s3)' },
            won: { key: 'won', label: t('s_won'), color: 'var(--kd-good)' },
            lost: { key: 'lost', label: t('s_lost'), color: 'var(--kd-bad)' },
        };
    }

    /* ------------------------------------------------------------------ */
    /* 1-2. Gosterge bandi ve kutulari                                      */
    /* ------------------------------------------------------------------ */

    function tickerItems(data, t, fmt) {
        const k = data.kpi;
        const offers = k.offers;
        const open = { count: offers.to_be_submitted.count + offers.submitted.count, valued: offers.to_be_submitted.valued + offers.submitted.valued, sums: sumObjects(offers.to_be_submitted.sums, offers.submitted.sums) };
        const cov = (block) => t('coverage', { v: block.valued, n: block.count });
        const weeks = data.weeks || [];

        return [
            { key: 'pipeline', label: t('tk_pipeline'), value: fmt.num(k.pipeline.count), sub: sumsText(k.pipeline.sums, fmt, true) || cov(k.pipeline), spark: weeks.map((w) => w.new_cases), icon: 'case' },
            { key: 'winnable', label: t('tk_pipeline_value'), value: sumsText(open.sums, fmt, true) || '—', sub: cov(open), tone: open.valued ? null : 'muted', icon: 'chart' },
            { key: 'lost', label: t('tk_lost'), value: fmt.num(offers.lost.count), sub: sumsText(offers.lost.sums, fmt, true) || cov(offers.lost), spark: weeks.map((w) => w.lost), sparkColor: 'var(--kd-bad)', delta: weekDelta(weeks, 'lost', false), tone: 'bad', icon: 'lost' },
            { key: 'submitted', label: t('tk_submitted'), value: fmt.num(offers.submitted.count), sub: sumsText(offers.submitted.sums, fmt, true) || cov(offers.submitted), spark: weeks.map((w) => w.submitted), delta: weekDelta(weeks, 'submitted', true), icon: 'send' },
            { key: 'to_submit', label: t('tk_to_submit'), value: fmt.num(offers.to_be_submitted.count), sub: sumsText(offers.to_be_submitted.sums, fmt, true) || cov(offers.to_be_submitted), icon: 'clock' },
            { key: 'approved', label: t('tk_approved'), value: fmt.num(offers.approved.count), sub: sumsText(offers.approved.sums, fmt, true) || cov(offers.approved), tone: 'good', icon: 'check' },
            { key: 'win', label: t('tk_win_rate'), value: k.win_rate === null ? '–' : fmt.pct(k.win_rate), sub: t('win_rate_sub', { w: k.outcomes.won, l: k.outcomes.lost }), icon: 'up' },
            { key: 'week', label: t('tk_week'), value: fmt.num(k.week.submitted), sub: t('tk_week_sub', { s: k.week.submitted, c: k.week.new_cases, n: k.week.notes }), spark: weeks.map((w) => w.notes), sparkColor: 'var(--kd-s2)', icon: 'calendar' },
        ];
    }

    /** 1. Borsa bandi: ince, yapiskan, her zaman gorunur. */
    function Ticker(props) {
        const { data, sticky } = props;
        const { t, fmt } = useApp();
        const items = tickerItems(data, t, fmt);

        return h('div', { className: cx('kd-ticker', { sticky }), role: 'list' }, items.map((item) => h('div', { key: item.key, className: cx('kd-tk', item.tone), role: 'listitem', title: item.label + ': ' + item.value + ' — ' + item.sub },
            h('span', { className: 'kd-tk-l' }, h(Icon, { name: item.icon }), item.label),
            h('span', { className: 'kd-tk-v' }, item.value, item.delta ? h('em', { className: item.delta.tone }, item.delta.text) : null),
            h('span', { className: 'kd-tk-s' }, item.sub),
        )));
    }

    /** 2. Gosterge kutulari + kucuk cizgi grafik. */
    function KpiTiles(props) {
        const { data } = props;
        const { t, fmt } = useApp();
        const items = tickerItems(data, t, fmt);

        return h('div', { className: 'kd-tiles' }, items.map((item) => h('div', { key: item.key, className: cx('kd-tile', item.tone) },
            h('div', null,
                h('span', { className: 'kd-tile-l' }, item.label),
                h('span', { className: 'kd-tile-v' }, item.value, item.delta ? h('em', { className: item.delta.tone }, item.delta.text) : null),
                h('span', { className: 'kd-tile-s' }, item.sub),
            ),
            item.spark ? h(Sparkline, { values: item.spark, width: 70, height: 26, color: item.sparkColor, label: item.label }) : h(Icon, { name: item.icon, className: 'kd-tile-ic' }),
        )));
    }

    /* ------------------------------------------------------------------ */
    /* 3-5. Birlesik grafikler                                              */
    /* ------------------------------------------------------------------ */

    function MonthlyCombo(props) {
        const { data, height, focus } = props;
        const { t } = useApp();
        const s = series(t);
        const list = focus === 'outcome'
            ? [Object.assign({ kind: 'bar' }, s.won), Object.assign({ kind: 'bar' }, s.lost), Object.assign({ kind: 'line' }, s.submitted)]
            : [Object.assign({ kind: 'bar' }, s.submitted), Object.assign({ kind: 'line' }, s.notes), Object.assign({ kind: 'dot' }, s.won), Object.assign({ kind: 'dot' }, s.lost), Object.assign({ kind: 'line' }, s.new_cases)];

        return h(ComboChart, { rows: data.months || [], series: list, height: height || 170, unit: t('chart_unit'), initialHidden: focus === 'outcome' ? {} : { new_cases: true } });
    }

    function WeeklyCombo(props) {
        const { data, height } = props;
        const { t } = useApp();
        const s = series(t);

        return h(ComboChart, {
            rows: data.weeks || [],
            series: [Object.assign({ kind: 'area' }, s.notes), Object.assign({ kind: 'bar' }, s.submitted), Object.assign({ kind: 'dot' }, s.lost), Object.assign({ kind: 'line' }, s.new_cases)],
            height: height || 170,
            unit: t('chart_unit'),
            initialHidden: { new_cases: true },
        });
    }

    function Multiples(props) {
        const { data } = props;
        const { t } = useApp();
        const s = series(t);

        return h(SmallMultiples, { rows: data.months || [], series: [s.submitted, s.notes, s.new_cases, s.won, s.lost] });
    }

    /* ------------------------------------------------------------------ */
    /* 9-10. Durum dugmeleri                                                */
    /* ------------------------------------------------------------------ */

    /** Tek simgeli durum dugmeleri (borsa al / sat gibi). variant: 'icons' | 'cycle'. */
    function StatusButtons(props) {
        const { value, onPick, variant } = props;
        const { config, t } = useApp();
        const statuses = config.statuses || [];

        if (variant === 'cycle') {
            const def = statusDef(config, value);
            const next = STATUS_ORDER[(STATUS_ORDER.indexOf(value) + 1) % STATUS_ORDER.length];

            return h('button', {
                type: 'button',
                className: cx('kd-sbtn', 'cycle', 'kd-st-' + def.value, 'on'),
                title: def.label + ' → ' + statusDef(config, next).label + ' (' + t('cycle_hint') + ')',
                onClick: (event) => { event.stopPropagation(); onPick(next); },
            }, h(Icon, { name: def.icon }), h('span', null, def.label));
        }

        return h('span', { className: 'kd-sbtns', role: 'group' }, statuses.map((st) => h('button', {
            key: st.value,
            type: 'button',
            className: cx('kd-sbtn', 'kd-st-' + st.value, { on: st.value === value }),
            title: st.label,
            'aria-label': st.label,
            'aria-pressed': st.value === value ? 'true' : 'false',
            onClick: (event) => { event.stopPropagation(); if (st.value !== value) { onPick(st.value); } },
        }, h(Icon, { name: st.icon }))));
    }

    /**
     * Deneme durum degisikligi: yalniz ekrandaki satir degisir.
     *
     * Canliya alinirken (kullanici onayi gerekir): burada bir POST ucu
     * cagrilacak; uc, teklifin guncel surumunun durumunu StatusButton::proposal
     * ile ayni yoldan (ProposalVersionService::changeStatus) degistirir ve
     * Teklif durumu (offer_status) ProposalService::syncOfferStatus ile izler;
     * "Kacan firsat" icin potansiyel isin durumu BusinessCaseService::changeStage.
     */
    function useDemoStatus() {
        const { t, toast } = useApp();
        const [overrides, setOverrides] = useState({});

        const pick = useCallback((row, status) => {
            setOverrides((current) => Object.assign({}, current, { [row.id]: status }));
            toast(t('demo_not_saved'), 'demo');
        }, [t, toast]);

        const statusOf = useCallback((row) => overrides[row.id] || row.status, [overrides]);

        return { pick, statusOf, overrides };
    }

    /** 10 (ust). Sayili bolmeli durum secici (ayni zamanda suzgec). */
    function StatusSeg(props) {
        const { rows, value, onChange, statusOf } = props;
        const { config, t } = useApp();
        const counts = {};
        rows.forEach((row) => { const s = statusOf ? statusOf(row) : row.status; counts[s] = (counts[s] || 0) + 1; });

        return h(Seg, {
            value,
            onChange,
            size: 'sm',
            options: [{ value: 'all', label: t('all'), count: rows.length }].concat((config.statuses || []).map((st) => ({
                value: st.value, label: st.label, icon: st.icon, count: counts[st.value] || 0, className: 'kd-st-' + st.value,
            }))),
        });
    }

    /* ------------------------------------------------------------------ */
    /* 6-8. Teklif masasi (yogun tablo)                                     */
    /* ------------------------------------------------------------------ */

    function exportUrl(config, params) {
        const base = config.export && config.export.url;

        if (!base) { return null; }

        const query = new URLSearchParams();
        Object.keys(params).forEach((key) => {
            const value = params[key];
            if (value === null || value === undefined || value === '' || value === false) { return; }
            if (Array.isArray(value)) { value.forEach((item) => query.append(key + '[]', item)); } else { query.append(key, value === true ? '1' : String(value)); }
        });

        return base + (base.indexOf('?') === -1 ? '?' : '&') + query.toString();
    }

    function ColumnPicker(props) {
        const { columns, visible, onChange, defaults } = props;
        const { t } = useApp();

        return h(Dropdown, {
            align: 'end',
            trigger: (open, toggle) => h('button', { type: 'button', className: cx('kd-btn', { on: open }), onClick: toggle, title: t('columns_btn') }, h(Icon, { name: 'columns' }), h('span', null, t('columns_btn'))),
        }, () => h('div', { className: 'kd-menu' },
            columns.map((col) => h('label', { key: col.key, className: 'kd-check' },
                h('input', {
                    type: 'checkbox',
                    checked: visible.indexOf(col.key) !== -1,
                    onChange: () => onChange(visible.indexOf(col.key) !== -1 ? visible.filter((key) => key !== col.key) : columns.map((c) => c.key).filter((key) => key === col.key || visible.indexOf(key) !== -1)),
                }),
                col.label,
            )),
            h('button', { type: 'button', className: 'kd-link', onClick: () => onChange(defaults) }, t('columns_reset')),
        ));
    }

    function ExportButtons(props) {
        const { params } = props;
        const { config, t } = useApp();
        const flags = config.export || {};

        if (!flags.url || (!flags.excel && !flags.pdf)) { return null; }

        return h('span', { className: 'kd-export', title: t('export_hint') },
            flags.excel ? h('a', { className: 'kd-btn', href: exportUrl(config, Object.assign({ format: 'xlsx' }, params)) }, h(Icon, { name: 'excel' }), h('span', null, t('export_excel'))) : null,
            flags.pdf ? h('a', { className: 'kd-btn', href: exportUrl(config, Object.assign({ format: 'pdf' }, params)) }, h(Icon, { name: 'pdf' }), h('span', null, t('export_pdf'))) : null,
        );
    }

    function sortValue(row, key, statusOf) {
        switch (key) {
            case 'status': return STATUS_ORDER.indexOf(statusOf(row));
            case 'amount': return row.amount === null ? -1 : row.amount;
            case 'activity': return row.spark.reduce((a, b) => a + b, 0);
            case 'types': return row.types.join(',');
            case 'offer_date': return row.offer_date || '';
            default: return row[key] === null || row[key] === undefined ? '' : row[key];
        }
    }

    /** Ust seritte sutun dagilimi (8): her sutunun kendi kucuk grafigi. */
    function DockCell(props) {
        const { col, rows, statusOf } = props;
        const { config, fmt } = useApp();

        switch (col) {
            case 'status': {
                const counts = {};
                rows.forEach((row) => { const s = statusOf(row); counts[s] = (counts[s] || 0) + 1; });
                return h(StackBar, { height: 12, parts: STATUS_ORDER.map((s) => ({ key: s, label: statusDef(config, s).label, value: counts[s] || 0, className: 'kd-st-bg-' + s })) });
            }
            case 'offer_date':
            case 'age':
            case 'heat': {
                const bins = new Array(8).fill(0);
                const vals = rows.map((row) => (col === 'heat' ? row.heat : (col === 'age' ? row.age : (row.offer_date ? Date.parse(row.offer_date) : null)))).filter((v) => v !== null);
                if (!vals.length) { return null; }
                const min = col === 'heat' ? 0 : Math.min(...vals);
                const max = col === 'heat' ? 100 : Math.max(...vals);
                vals.forEach((v) => { bins[Math.min(7, Math.floor(((v - min) / Math.max(1, max - min)) * 8))] += 1; });
                const top = Math.max(1, ...bins);
                return h('span', { className: 'kd-hist', title: col }, bins.map((b, i) => h('i', { key: i, style: { height: Math.max(b ? 2 : 0, (b / top) * 100) + '%' }, title: String(b) })));
            }
            case 'amount': {
                const valued = rows.filter((row) => row.amount !== null).length;
                return h('span', { className: 'kd-meter', title: valued + ' / ' + rows.length }, h('i', { style: { width: (rows.length ? (valued / rows.length) * 100 : 0) + '%' } }), h('em', null, valued + '/' + rows.length));
            }
            case 'types': {
                const counts = {};
                rows.forEach((row) => row.types.forEach((type) => { counts[type] = (counts[type] || 0) + 1; }));
                return h('span', { className: 'kd-dock-types' }, Object.keys(counts).map((type) => h('span', { key: type }, h(TypeChip, { type, compact: true }), counts[type])));
            }
            case 'owner': {
                const counts = {};
                rows.forEach((row) => { if (row.owner) { counts[row.owner] = (counts[row.owner] || 0) + 1; } });
                const top = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0];
                return top ? h('span', { className: 'kd-dock-txt', title: top }, top.split(' ')[0] + ' ' + counts[top]) : null;
            }
            case 'activity': {
                const sum = new Array((rows[0] && rows[0].spark.length) || 8).fill(0);
                rows.forEach((row) => row.spark.forEach((v, i) => { sum[i] += v; }));
                return h(Sparkline, { values: sum, width: 64, height: 16 });
            }
            case 'party': {
                return h('span', { className: 'kd-dock-txt' }, fmt.num(new Set(rows.map((row) => row.party_id)).size));
            }
            default:
                return null;
        }
    }

    function ProposalModal(props) {
        const { row, status, onPick, onClose } = props;
        const { config, t, fmt, url } = useApp();
        const fields = [
            [t('f_case'), (row.potis ? row.potis + ' · ' : '') + row.case_title],
            [t('f_party_full'), row.party_full],
            [t('f_stage'), (config.stages || {})[row.stage] || '–'],
            [(config.columns.find((c) => c.key === 'offer_date') || {}).label, fmt.date(row.offer_date)],
            [t('f_created'), fmt.date(row.created)],
            [t('f_version'), row.version !== null ? 'v' + row.version : '–'],
            [(config.columns.find((c) => c.key === 'amount') || {}).label, row.amount !== null ? fmt.money(row.amount, row.currency) : t('no_amount')],
            [(config.columns.find((c) => c.key === 'heat') || {}).label, fmt.pct(row.heat)],
            [t('f_waiting'), t('waiting_days', { n: row.age })],
        ];
        const links = [
            ['proposal', url('proposal', row.id), t('open_proposal')],
            ['case', url('case', row.case_id), t('open_case')],
            ['party', url('party', row.party_id), t('open_party')],
        ].filter((link) => link[1]);

        return h(Modal, {
            title: row.no + ' · ' + row.title,
            onClose,
            footer: h(Fragment, null,
                links.map((link) => h('a', { key: link[0], className: 'kd-btn primary', href: link[1] }, h(Icon, { name: link[0] }), h('span', null, link[2]), h(Icon, { name: 'external' }))),
                h('button', { type: 'button', className: 'kd-btn cancel', onClick: onClose }, t('close')),
            ),
        },
            h('div', { className: 'kd-detail-top' },
                h(StatusPill, { status }),
                row.types.map((type) => h(TypeChip, { key: type, type })),
                row.owner ? h('span', { className: 'kd-person' }, h(Icon, { name: 'person' }), row.owner) : null,
            ),
            h('dl', { className: 'kd-dl' }, fields.map((f, i) => h(Fragment, { key: i }, h('dt', null, f[0]), h('dd', null, f[1] || '–')))),
            h('div', { className: 'kd-detail-act' },
                h('span', null, t('status_change_title')),
                h(StatusButtons, { value: status, onPick }),
                h('em', null, t('demo_wired_later')),
            ),
            h('div', { className: 'kd-detail-spark' }, h('span', null, (config.columns.find((c) => c.key === 'activity') || {}).label), h(Sparkline, { values: row.spark, width: 180, height: 30, label: row.no })),
        );
    }

    /**
     * Teklif masasi. variant: 'cells' (6) | 'rowbg' (7) | 'docked' (8).
     * statusVariant: 'icons' (9) | 'cycle' (10).
     */
    function ProposalDesk(props) {
        const { rows, variant, statusVariant, tableKey, maxHeight, title, num, sub } = props;
        const app = useApp();
        const { config, t, fmt } = app;
        const defaults = config.columns.filter((c) => c.default).map((c) => c.key);
        const [visible, setVisibleState] = useState(() => {
            const saved = store.get('cols:' + (tableKey || 'desk'), null);
            return Array.isArray(saved) && saved.length ? saved.filter((key) => config.columns.some((c) => c.key === key)) : defaults;
        });
        const [status, setStatus] = useState('all');
        const [mine, setMine] = useState(false);
        const [query, setQuery] = useState('');
        const [sort, setSort] = useState({ key: null, dir: -1 });
        const [selected, setSelected] = useState(null);
        const [bgBy, setBgBy] = useState('age');
        const demo = useDemoStatus();
        const me = config.viewer && config.viewer.id;

        const setVisible = (next) => { setVisibleState(next); store.set('cols:' + (tableKey || 'desk'), next); };

        const base = useMemo(() => (rows || []).filter((row) => (!mine || row.owner_id === me)), [rows, mine, me]);
        const filtered = useMemo(() => {
            const q = query.trim().toLocaleLowerCase('tr-TR');
            let list = base.filter((row) => (status === 'all' || demo.statusOf(row) === status)
                && (!q || [row.no, row.title, row.party, row.party_full, row.potis || ''].join(' ').toLocaleLowerCase('tr-TR').indexOf(q) !== -1));

            if (sort.key) {
                list = list.slice().sort((a, b) => {
                    const va = sortValue(a, sort.key, demo.statusOf);
                    const vb = sortValue(b, sort.key, demo.statusOf);
                    return (va > vb ? 1 : va < vb ? -1 : 0) * sort.dir;
                });
            }

            return list;
        }, [base, status, query, sort, demo.statusOf]);

        const cols = config.columns.filter((c) => visible.indexOf(c.key) !== -1);
        const maxAmount = Math.max(0, ...filtered.map((row) => row.amount || 0));
        const maxAge = Math.max(1, ...filtered.map((row) => row.age));
        const staleTo = (config.thresholds || {}).stale_to_submit || 14;
        const staleSub = (config.thresholds || {}).stale_submitted || 30;

        const rowBg = (row) => {
            if (variant !== 'rowbg') { return null; }
            const pct = bgBy === 'heat' ? row.heat : (bgBy === 'amount' ? (maxAmount ? ((row.amount || 0) / maxAmount) * 100 : 0) : (row.age / maxAge) * 100);
            return { '--kd-rowbar': Math.round(pct) + '%' };
        };

        const cell = (row, key) => {
            const st = demo.statusOf(row);
            const late = (st === 'to_be_submitted' && row.age > staleTo) || (st === 'submitted' && row.age > staleSub);

            switch (key) {
                case 'no': return h('td', { key, className: 'kd-mono' }, row.no);
                case 'potis': return h('td', { key, className: 'kd-mono kd-muted' }, row.potis || '–');
                case 'party': return h('td', { key, title: row.party_full, className: 'kd-strong' }, row.party);
                case 'title': return h('td', { key, className: 'kd-ell', title: row.title }, row.title);
                case 'types': return h('td', { key, className: 'kd-types' }, row.types.map((type) => h(TypeChip, { key: type, type, compact: row.types.length > 1 })));
                case 'status': return h('td', { key }, h(StatusPill, { status: st }));
                case 'offer_date': return h('td', { key, className: 'kd-num-c' }, fmt.dshort(row.offer_date));
                case 'amount': return h('td', { key, className: 'kd-num-c' }, variant === 'cells'
                    ? h(CellBar, { value: row.amount, max: maxAmount, tone: 's1', align: 'right' }, row.amount !== null ? fmt.money(row.amount, row.currency, true) : h('span', { className: 'kd-muted', title: t('no_amount') }, '—'))
                    : (row.amount !== null ? fmt.money(row.amount, row.currency, true) : h('span', { className: 'kd-muted', title: t('no_amount') }, '—')));
                case 'heat': return h('td', { key, className: 'kd-num-c' }, variant === 'cells'
                    ? h(CellBar, { value: row.heat, max: 100, tone: row.heat >= 60 ? 'good' : (row.heat >= 30 ? 'warn' : 'cold'), align: 'right' }, fmt.pct(row.heat))
                    : fmt.pct(row.heat));
                case 'owner': return h('td', { key, className: 'kd-ell' }, row.owner ? h('span', { className: 'kd-person' }, h(Icon, { name: 'person' }), row.owner) : '–');
                case 'age': return h('td', { key, className: cx('kd-num-c', { 'kd-late': late }) }, variant === 'cells'
                    ? h(CellBar, { value: row.age, max: maxAge, tone: late ? 'bad' : 'muted', align: 'right' }, t('days_short', { n: row.age }))
                    : t('days_short', { n: row.age }));
                case 'activity': return h('td', { key }, h(Sparkline, { values: row.spark, label: row.no }));
                case 'actions': return h('td', { key, className: 'kd-act' }, h(StatusButtons, { value: st, variant: statusVariant, onPick: (next) => demo.pick(row, next) }));
                default: return h('td', { key });
            }
        };

        const exportParams = {
            table: 'proposals',
            columns: cols.filter((c) => c.export).map((c) => c.key),
            status: status === 'all' ? null : status,
            mine,
            q: query.trim(),
        };

        const toolbar = h('div', { className: 'kd-toolbar' },
            h(StatusSeg, { rows: base, value: status, onChange: setStatus, statusOf: demo.statusOf }),
            h(Seg, { size: 'sm', value: mine ? 'mine' : 'team', onChange: (v) => setMine(v === 'mine'), options: [{ value: 'team', label: t('team') }, { value: 'mine', label: t('mine'), icon: 'person' }] }),
            h('label', { className: 'kd-search' }, h(Icon, { name: 'search' }), h('input', { value: query, placeholder: t('search'), onChange: (event) => setQuery(event.target.value) })),
            variant === 'rowbg' ? h(Seg, {
                size: 'sm', value: bgBy, onChange: setBgBy, label: t('tv_bg_by'),
                options: [{ value: 'age', label: t('bg_age') }, { value: 'heat', label: t('bg_heat') }, { value: 'amount', label: t('bg_amount') }],
            }) : null,
            h('span', { className: 'kd-spacer' }),
            h('span', { className: 'kd-count' }, t('rows_n', { n: filtered.length })),
            h(ColumnPicker, { columns: config.columns, visible, defaults, onChange: setVisible }),
            h(ExportButtons, { params: exportParams }),
        );

        const head = h('thead', null,
            variant === 'docked' ? h('tr', { className: 'kd-dock', title: t('dock_hint') }, cols.map((c) => h('td', { key: c.key }, h(DockCell, { col: c.key, rows: filtered, statusOf: demo.statusOf })))) : null,
            h('tr', null, cols.map((c) => h('th', {
                key: c.key,
                className: cx({ sortable: c.key !== 'actions', sorted: sort.key === c.key, 'kd-num-c': ['amount', 'heat', 'age', 'offer_date'].indexOf(c.key) !== -1 }),
                onClick: c.key === 'actions' ? undefined : () => setSort({ key: c.key, dir: sort.key === c.key ? -sort.dir : -1 }),
                title: c.key === 'actions' ? undefined : t('sort_hint'),
            }, c.label, sort.key === c.key ? h('i', null, sort.dir > 0 ? ' ▲' : ' ▼') : null))),
        );

        const table = !config.viewer || !config.viewer.can_rows
            ? h(Empty, { text: t('no_rows_permission') })
            : (filtered.length
                ? h('div', { className: 'kd-tablewrap', style: maxHeight ? { maxHeight: maxHeight + 'px' } : undefined },
                    h('table', { className: cx('kd-table', { 'kd-rowbg-on': variant === 'rowbg' }) },
                        head,
                        h('tbody', null, filtered.map((row) => h('tr', {
                            key: row.id,
                            className: cx('kd-click', { 'kd-demo': demo.overrides[row.id] }),
                            style: rowBg(row) || undefined,
                            onClick: () => setSelected(row),
                            tabIndex: 0,
                            onKeyDown: (event) => { if (event.key === 'Enter') { setSelected(row); } },
                        }, cols.map((c) => cell(row, c.key))))),
                    ))
                : h(Empty, null));

        return h(Panel, { num, title: title || t('desk'), sub: sub || t('desk_sub'), className: 'kd-desk', flush: true },
            toolbar,
            table,
            selected ? h(ProposalModal, { row: selected, status: demo.statusOf(selected), onPick: (next) => demo.pick(selected, next), onClose: () => setSelected(null) }) : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* 11-20. Diger bilesenler                                              */
    /* ------------------------------------------------------------------ */

    function FunnelBox(props) {
        const { data } = props;
        const { t } = useApp();
        const f = data.funnel;

        return h(Funnel, {
            steps: [
                { key: 'development', label: t('f_development'), value: f.development },
                { key: 'offer', label: t('f_offer'), value: f.offer },
                { key: 'submitted', label: t('f_submitted'), value: f.submitted },
                { key: 'approved', label: t('f_approved'), value: f.approved, tone: 'good', from: 2 },
                { key: 'lost', label: t('f_lost'), value: f.lost, tone: 'bad', from: 2 },
            ],
        });
    }

    function TeamHeat(props) {
        const { data, limit } = props;
        const { url } = useApp();
        const weeks = (data.weeks || []).slice(-8).map((w) => w.label);
        const rows = (data.people || []).filter((p) => p.notes > 0).slice(0, limit || 12).map((p) => ({ key: p.id, label: p.name, title: p.name + (p.unit ? ' · ' + p.unit : ''), values: p.weeks, icon: h(Icon, { name: 'person' }), id: p.id }));

        return rows.length ? h(Heatmap, { rows, cols: weeks, onRow: (row) => { const target = url('personnel', row.id); if (target) { window.location.href = target; } } }) : h(Empty, null);
    }

    function PartyBoard(props) {
        const { data, limit, exportable } = props;
        const { config, fmt, url } = useApp();
        const rows = (data.parties || []).slice(0, limit || 12);
        const maxP = Math.max(1, ...rows.map((r) => r.proposals));
        const maxC = Math.max(1, ...rows.map((r) => r.cases));
        const cols = config.party_columns || [];
        const label = (key) => (cols.find((c) => c.key === key) || {}).label || key;

        if (!rows.length) { return h(Empty, null); }

        return h(Fragment, null,
            exportable ? h('div', { className: 'kd-toolbar' }, h('span', { className: 'kd-spacer' }), h(ExportButtons, { params: { table: 'parties', columns: cols.filter((c) => c.export).map((c) => c.key) } })) : null,
            h('div', { className: 'kd-tablewrap' }, h('table', { className: 'kd-table' },
                h('thead', null, h('tr', null,
                    h('th', null, '#'), h('th', null, label('party')), h('th', { className: 'kd-num-c' }, label('proposals')), h('th', { className: 'kd-num-c' }, label('cases')),
                    h('th', { className: 'kd-num-c' }, label('submitted')), h('th', { className: 'kd-num-c' }, label('lost')), h('th', { className: 'kd-num-c' }, label('notes_90')), h('th', null, label('spark')),
                )),
                h('tbody', null, rows.map((r, i) => h('tr', { key: r.id, className: 'kd-click', onClick: () => { const target = url('party', r.id); if (target) { window.location.href = target; } } },
                    h('td', { className: 'kd-muted kd-mono' }, i + 1),
                    h('td', { className: 'kd-strong', title: r.party_full }, h(Icon, { name: 'party', className: 'kd-ic-sm' }), r.party),
                    h('td', { className: 'kd-num-c' }, h(CellBar, { value: r.proposals, max: maxP, tone: 's1', align: 'right' }, fmt.num(r.proposals))),
                    h('td', { className: 'kd-num-c' }, h(CellBar, { value: r.cases, max: maxC, tone: 'muted', align: 'right' }, fmt.num(r.cases))),
                    h('td', { className: 'kd-num-c' }, fmt.num(r.submitted)),
                    h('td', { className: cx('kd-num-c', { 'kd-bad-t': r.lost > 0 }) }, fmt.num(r.lost)),
                    h('td', { className: 'kd-num-c' }, fmt.num(r.notes_90)),
                    h('td', null, h(Sparkline, { values: r.spark, label: r.party_full, color: 'var(--kd-s2)' })),
                ))),
            )),
        );
    }

    function TypeDonut(props) {
        const { data } = props;
        const { config, t, fmt } = useApp();
        const types = data.types || [];
        const parts = types.map((row) => ({ key: row.key, label: ((config.types || {})[row.key] || {}).label || row.key, value: row.proposals, color: 'var(--kd-c-' + (((config.types || {})[row.key] || {}).color || 'gray') + ')' }));
        const total = parts.reduce((a, p) => a + p.value, 0);

        return h('div', { className: 'kd-donutbox' },
            h(Donut, { parts, centerValue: fmt.num(total), centerLabel: t('type_props') }),
            h('ul', { className: 'kd-typelist' }, types.map((row) => h('li', { key: row.key },
                h(TypeChip, { type: row.key }),
                h('b', null, fmt.num(row.proposals)),
                h('span', null, fmt.num(row.open_cases) + ' ' + t('type_cases')),
            ))),
        );
    }

    function AlertStrip(props) {
        const { data, only } = props;
        const { config, t } = useApp();
        const th = config.thresholds || {};
        const urls = config.urls || {};
        const target = { stale_to_submit: urls.proposals, stale_submitted: urls.proposals, no_amount: urls.proposals, past_meetings: urls.meetings, overdue_actions: urls.meetings };
        const items = (data.alerts || []).filter((a) => a.count > 0 && (!only || only.indexOf(a.key) !== -1));

        if (!items.length) { return h(Empty, null); }

        return h('div', { className: 'kd-alerts', role: 'list' }, items.map((a) => h(target[a.key] ? 'a' : 'span', {
            key: a.key,
            role: 'listitem',
            href: target[a.key] || undefined,
            className: cx('kd-alert', a.tone),
        }, h(Icon, { name: a.tone === 'critical' ? 'critical' : (a.tone === 'warning' ? 'warning' : 'info') }), t('al_' + a.key, { n: a.count, d: a.key === 'stale_to_submit' ? th.stale_to_submit : th.stale_submitted }))));
    }

    function ActivityFeed(props) {
        const { data, limit } = props;
        const { t, fmt, url } = useApp();
        const notes = (data.notes || []).slice(0, limit || 12);

        if (!notes.length) { return h(Empty, null); }

        return h('ol', { className: 'kd-feed' }, notes.map((n) => {
            const href = n.case_id ? url('case', n.case_id) : url('party', n.party_id);

            return h('li', { key: n.id },
                h('time', null, fmt.dm(n.date)),
                h('a', { href: href || undefined, className: 'kd-feed-main' },
                    h('span', { className: 'kd-strong' }, h(Icon, { name: 'party', className: 'kd-ic-sm' }), n.party),
                    n.potis ? h('span', { className: 'kd-mono kd-muted' }, n.potis) : h('span', { className: 'kd-muted' }, t('no_case')),
                    h('span', { className: 'kd-ell' }, n.subject),
                ),
                n.person ? h('span', { className: 'kd-person kd-muted' }, h(Icon, { name: 'person' }), n.person) : null,
                n.next_action ? h('span', { className: cx('kd-next', { 'kd-late': n.overdue }) }, t('note_next') + ': ' + n.next_action + (n.next_action_on ? ' (' + fmt.dm(n.next_action_on) + ')' : '')) : null,
            );
        }));
    }

    function StatusMix(props) {
        const { data } = props;
        const { config, fmt } = useApp();
        const offers = data.kpi.offers;
        const total = STATUS_ORDER.reduce((a, s) => a + offers[s].count, 0);

        return h('div', { className: 'kd-mix' },
            h(StackBar, { height: 18, parts: STATUS_ORDER.map((s) => ({ key: s, label: statusDef(config, s).label, value: offers[s].count, className: 'kd-st-bg-' + s })) }),
            h('div', { className: 'kd-mix-l' }, STATUS_ORDER.map((s) => h('span', { key: s }, h(StatusPill, { status: s }), h('b', null, fmt.num(offers[s].count)), h('em', null, total ? '%' + Math.round((offers[s].count / total) * 100) : '')))),
        );
    }

    function MoneyBoxes(props) {
        const { data } = props;
        const { config, t, fmt } = useApp();
        const k = data.kpi;
        const blocks = [{ key: 'pipeline', label: t('tk_pipeline'), block: k.pipeline }].concat(STATUS_ORDER.map((s) => ({ key: s, label: statusDef(config, s).label, block: k.offers[s], status: s })));

        return h('div', { className: 'kd-money' },
            blocks.map((b) => h('div', { key: b.key, className: 'kd-money-row' },
                b.status ? h(StatusPill, { status: b.status, short: true }) : h(Icon, { name: 'case' }),
                h('span', { className: 'kd-money-l' }, b.label),
                h('b', null, sumsText(b.block.sums, fmt, false) || '—'),
                h('span', { className: 'kd-meter', title: t('coverage', { v: b.block.valued, n: b.block.count }) },
                    h('i', { style: { width: (b.block.count ? (b.block.valued / b.block.count) * 100 : 0) + '%' } }),
                    h('em', null, b.block.valued + '/' + b.block.count),
                ),
            )),
            h('p', { className: 'kd-note' }, t('money_note')),
        );
    }

    function OrderBook(props) {
        const { data } = props;
        const { t, fmt } = useApp();
        const types = data.types || [];
        const max = Math.max(1, ...types.map((r) => Math.max(r.to_be_submitted, r.submitted)));

        return h('div', { className: 'kd-book' },
            h('div', { className: 'kd-book-h' }, h('span', null, t('ob_to_submit')), h('span', null), h('span', null, t('ob_submitted'))),
            types.map((r) => h('div', { key: r.key, className: 'kd-book-r' },
                h('span', { className: 'kd-book-l' }, h('em', null, fmt.num(r.to_be_submitted)), h('i', { style: { width: (r.to_be_submitted / max) * 100 + '%' } })),
                h('span', { className: 'kd-book-c' }, h(TypeChip, { type: r.key })),
                h('span', { className: 'kd-book-rt' }, h('i', { style: { width: (r.submitted / max) * 100 + '%' } }), h('em', null, fmt.num(r.submitted))),
            )),
        );
    }

    function CriticalList(props) {
        const { data, limit } = props;
        const { config, t, url } = useApp();
        const rows = (data.critical || []).slice(0, limit || 8);
        const th = config.thresholds || {};

        if (!rows.length) { return h(Empty, null); }

        return h('ul', { className: 'kd-crit' }, rows.map((r) => h('li', { key: r.id },
            h('a', { href: url('proposal', r.id) || undefined },
                h('span', { className: cx('kd-days', { 'kd-late': r.days > (r.status === 'submitted' ? th.stale_submitted : th.stale_to_submit) }) }, r.days, h('small', null, 'g')),
                h('span', { className: 'kd-crit-m' }, h('b', { className: 'kd-mono' }, r.no), h('span', { className: 'kd-ell' }, r.party + ' · ' + r.title)),
                h(StatusPill, { status: r.status, short: true }),
            ),
        )));
    }

    function PeopleTable(props) {
        const { data, limit } = props;
        const { t, fmt, url } = useApp();
        const rows = (data.people || []).slice(0, limit || 12);
        const maxN = Math.max(1, ...rows.map((r) => r.notes));
        const maxP = Math.max(1, ...rows.map((r) => r.proposals));

        if (!rows.length) { return h(Empty, null); }

        return h('div', { className: 'kd-tablewrap' }, h('table', { className: 'kd-table' },
            h('thead', null, h('tr', null,
                h('th', null, t('pp_person')), h('th', { className: 'kd-num-c' }, t('pp_notes')), h('th', null, t('pp_weeks')), h('th', { className: 'kd-num-c' }, t('pp_props')),
                h('th', { className: 'kd-num-c' }, t('pp_to_submit')), h('th', { className: 'kd-num-c' }, t('pp_work')), h('th', { className: 'kd-num-c' }, t('pp_critical')),
            )),
            h('tbody', null, rows.map((r) => h('tr', { key: r.id, className: 'kd-click', onClick: () => { const target = url('personnel', r.id); if (target) { window.location.href = target; } } },
                h('td', { className: 'kd-strong', title: r.unit || '' }, h('span', { className: 'kd-person' }, h(Icon, { name: 'person' }), r.name)),
                h('td', { className: 'kd-num-c' }, h(CellBar, { value: r.notes, max: maxN, tone: 's2', align: 'right' }, fmt.num(r.notes))),
                h('td', null, h(Sparkline, { values: r.weeks, color: 'var(--kd-s2)', label: r.name })),
                h('td', { className: 'kd-num-c' }, h(CellBar, { value: r.proposals, max: maxP, tone: 's1', align: 'right' }, fmt.num(r.proposals))),
                h('td', { className: 'kd-num-c' }, fmt.num(r.to_submit)),
                h('td', { className: 'kd-num-c' }, fmt.num(r.open_work)),
                h('td', { className: cx('kd-num-c', { 'kd-bad-t': r.critical_work > 0 }) }, fmt.num(r.critical_work)),
            ))),
        ));
    }

    function KindsBar(props) {
        const { data } = props;
        const { t, fmt } = useApp();
        const k = data.kpi.kinds;
        const parts = [
            { key: 'investor', label: t('kind_investor'), value: k.investor, color: 'var(--kd-s3)' },
            { key: 'potential', label: t('kind_potential'), value: k.potential, color: 'var(--kd-s4)' },
            { key: 'offer', label: t('kind_offer'), value: k.offer, color: 'var(--kd-s1)' },
            { key: 'drafts', label: t('kind_drafts'), value: k.drafts, color: 'var(--kd-muted)' },
        ];

        return h('div', { className: 'kd-mix' },
            h(StackBar, { height: 14, parts }),
            h('div', { className: 'kd-mix-l' }, parts.map((p) => h('span', { key: p.key }, h('i', { className: 'kd-sw', style: { background: p.color } }), p.label, h('b', null, fmt.num(p.value))))),
        );
    }

    function WeekMoves(props) {
        const { data } = props;
        const { t, fmt } = useApp();
        const w = data.kpi.week;
        const items = [['wk_submitted', w.submitted, 'send'], ['wk_new', w.new_cases, 'case'], ['wk_notes', w.notes, 'chat'], ['wk_won', w.won, 'check'], ['wk_lost', w.lost, 'lost']];

        return h('div', { className: 'kd-week' }, items.map((it) => h('div', { key: it[0] }, h(Icon, { name: it[2] }), h('b', null, fmt.num(it[1])), h('span', null, t(it[0])))));
    }

    function ReportsBox(props) {
        const { data } = props;
        const { t, fmt } = useApp();
        const find = (key) => (data.alerts || []).find((a) => a.key === key);
        const rows = [['rep_waiting', find('reports_waiting'), 'pdf'], ['appr_waiting', find('approvals_waiting'), 'check'], ['crit_work', find('critical_work'), 'critical']].filter((r) => r[1]);

        return h('div', { className: 'kd-week col' }, rows.map((r) => h('div', { key: r[0], className: r[1].count > 0 && r[0] === 'crit_work' ? 'bad' : null }, h(Icon, { name: r[2] }), h('b', null, fmt.num(r[1].count)), h('span', null, t(r[0])))));
    }

    KD.W = {
        Ticker, KpiTiles, MonthlyCombo, WeeklyCombo, Multiples, StatusButtons, StatusSeg, useDemoStatus,
        ProposalDesk, FunnelBox, TeamHeat, PartyBoard, TypeDonut, AlertStrip, ActivityFeed, StatusMix, MoneyBoxes,
        OrderBook, CriticalList, PeopleTable, KindsBar, WeekMoves, ReportsBox,
    };
}());
