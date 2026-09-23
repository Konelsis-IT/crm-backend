/*
 * Konelsis - IS ANALIZI PANOSU (B36, D-115, 22 Eylul 2026; kullanici karari:
 * madde 10 React ile, taslak arayuzle birebir).
 *
 * Is raporlari kumesinin ozet sayfasi: alti gosterge (onceki donemle
 * karsilastirmali), departmana gore harcanan saat, zaman dagilimi halkasi,
 * sekiz haftalik tamamlanan is cizgisi, en cok bekleten taraflar, en uzun
 * suredir acik isler. Her kutu ilgili rapora (Sure raporu / Isler) acilir.
 *
 * Kok: [data-kw-root="work-analysis"]; data-config: WorkAppConfig::analysis().
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;

    if (!KW) {
        return;
    }

    const { h, Fragment, cx, fmt, useApp, Chip, Dropdown, Opt, State, StatusBadge } = KW;
    const { useState } = KW.hooks;

    function Picker(props) {
        const { label, value, options, onPick, allLabel, searchable } = props;
        const { t } = useApp();
        const current = options.find((row) => row.value === value);

        return h(Dropdown, {
            width: '260px',
            trigger: (open, toggle) => h(Chip, { label, value: current ? current.label : allLabel, open, onClick: toggle }),
        }, (close) => h(PickerPanel, { options, value, allLabel, searchable, t, onPick: (next) => { onPick(next); close(); } }));
    }

    function PickerPanel(props) {
        const { options, value, allLabel, searchable, onPick, t } = props;
        const [query, setQuery] = useState('');
        const q = query.toLocaleLowerCase('tr-TR');

        return h(Fragment, null,
            searchable ? h('div', { className: 'kw-search' }, '🔍 ', h('input', { value: query, autoFocus: true, placeholder: t('search'), onChange: (event) => setQuery(event.target.value) })) : null,
            allLabel ? h(Opt, { radio: true, on: value === null, onClick: () => onPick(null) }, allLabel) : null,
            options.filter((row) => !q || String(row.label).toLocaleLowerCase('tr-TR').indexOf(q) !== -1).slice(0, 80)
                .map((row) => h(Opt, { key: row.value, radio: true, on: row.value === value, onClick: () => onPick(row.value) }, row.label)),
        );
    }

    /** Onceki doneme gore degisim metni ve rengi. */
    function delta(kind, current, previous, t) {
        if (previous === null || previous === undefined || current === null || current === undefined) {
            return null;
        }

        const diff = current - previous;

        if (kind === 'hours') {
            if (!previous) { return null; }
            const pct = Math.round((diff / previous) * 100);
            return { text: (pct >= 0 ? '▲ ' : '▼ ') + Math.abs(pct) + '% ' + t('vs_prev', { d: '' }).trim(), tone: pct >= 0 ? 'up' : 'down' };
        }

        if (kind === 'days') {
            if (diff === 0) { return { text: '= ' + t('vs_prev', { d: '' }).trim(), tone: 'flat' }; }
            const value = fmt.num(Math.abs(diff), 1) + ' ' + t('days_value', { n: '' }).trim();
            return diff < 0 ? { text: '▼ ' + t('faster', { d: value }), tone: 'up' } : { text: '▲ ' + t('slower', { d: value }), tone: 'down' };
        }

        if (kind === 'count') {
            if (diff === 0) { return { text: '= ' + fmt.num(current), tone: 'flat' }; }
            return { text: (diff > 0 ? '▲ ' : '▼ ') + Math.abs(diff), tone: diff > 0 ? 'down' : 'up' };
        }

        // Puan (yuzde) gostergeleri: kind = 'points-up' (artmasi iyi) | 'points-down' (azalmasi iyi).
        if (diff === 0) { return { text: '= ' + t('points', { n: 0 }), tone: 'flat' }; }
        const good = kind === 'points-up' ? diff > 0 : diff < 0;
        return { text: (diff > 0 ? '▲ ' : '▼ ') + t('points', { n: Math.abs(diff) }), tone: good ? 'up' : 'down' };
    }

    function Stat(props) {
        const { value, label, change, href } = props;

        return h(href ? 'a' : 'div', { className: 'kw-stat', href: href || undefined },
            h('span', { className: 'kw-v' }, value),
            h('span', { className: 'kw-l' }, label),
            change ? h('span', { className: cx('kw-d', change.tone) }, change.text) : null,
        );
    }

    function UnitBars(props) {
        const { units } = props;
        const max = units.reduce((acc, row) => Math.max(acc, row.hours), 0) || 1;

        return h('div', { className: 'kw-vbars' }, units.map((row) => h('div', { key: row.name, title: row.name + ': ' + fmt.num(row.hours, 1) },
            h('em', null, fmt.num(row.hours)),
            h('b', { style: { height: Math.max(2, Math.round((row.hours / max) * 100)) + '%' } }),
            h('span', null, row.name),
        )));
    }

    function Donut(props) {
        const { distribution } = props;
        const { t } = useApp();
        const c = 251.3;
        const progress = (distribution.progress || 0) / 100 * c;
        const waiting = (distribution.waiting || 0) / 100 * c;
        const blocked = (distribution.blocked || 0) / 100 * c;
        const label = t('work') + ' ' + distribution.progress + '%, ' + t('waiting') + ' ' + distribution.waiting + '%, ' + t('blocked') + ' ' + distribution.blocked + '%';

        return h('div', { className: 'kw-donut' },
            h('svg', { className: 'kw-chart', viewBox: '0 0 120 120', role: 'img', 'aria-label': label },
                h('circle', { cx: 60, cy: 60, r: 40, fill: 'none', stroke: 'var(--kw-surface-2)', strokeWidth: 16 }),
                h('circle', { cx: 60, cy: 60, r: 40, fill: 'none', stroke: 'var(--kw-done)', strokeWidth: 16, strokeDasharray: progress + ' ' + c, transform: 'rotate(-90 60 60)' }),
                h('circle', { cx: 60, cy: 60, r: 40, fill: 'none', stroke: 'var(--kw-waiting)', strokeWidth: 16, strokeDasharray: waiting + ' ' + c, strokeDashoffset: -progress, transform: 'rotate(-90 60 60)' }),
                h('circle', { cx: 60, cy: 60, r: 40, fill: 'none', stroke: 'var(--kw-blocked)', strokeWidth: 16, strokeDasharray: blocked + ' ' + c, strokeDashoffset: -(progress + waiting), transform: 'rotate(-90 60 60)' }),
                h('text', { x: 60, y: 57, textAnchor: 'middle', style: { fontSize: '13px', fontWeight: 700, fill: 'var(--kw-ink)' } }, '%' + (distribution.progress || 0)),
                h('text', { x: 60, y: 71, textAnchor: 'middle' }, t('work').toLocaleLowerCase('tr-TR')),
            ),
            h('div', { className: 'kw-legend', style: { flexDirection: 'column', gap: '6px' } },
                h('span', null, h('i', { style: { background: 'var(--kw-done)' } }), t('work') + ' ' + (distribution.progress || 0) + '%'),
                h('span', null, h('i', { style: { background: 'var(--kw-waiting)' } }), t('waiting') + ' ' + (distribution.waiting || 0) + '%'),
                h('span', null, h('i', { style: { background: 'var(--kw-blocked)' } }), t('blocked') + ' ' + (distribution.blocked || 0) + '%'),
            ),
        );
    }

    function WeeklyLine(props) {
        const { weeks } = props;
        const { t } = useApp();
        const max = Math.max(4, weeks.reduce((acc, row) => Math.max(acc, row.count), 0));
        const top = Math.ceil(max / 2) * 2;
        const x = (i) => 30 + i * (280 / Math.max(1, weeks.length - 1));
        const y = (value) => 100 - (value / top) * 90;
        const points = weeks.map((row, i) => x(i) + ',' + y(row.count)).join(' ');
        const last = weeks[weeks.length - 1];
        const label = t('widget_weekly') + ': ' + weeks.map((row) => row.week + '. ' + row.count).join(', ');

        return h('svg', { className: 'kw-chart', viewBox: '0 0 320 130', role: 'img', 'aria-label': label },
            h('line', { x1: 30, y1: 100, x2: 310, y2: 100, stroke: 'var(--kw-line)' }),
            h('line', { x1: 30, y1: 55, x2: 310, y2: 55, stroke: 'var(--kw-line)', strokeDasharray: '3 3' }),
            h('line', { x1: 30, y1: 10, x2: 310, y2: 10, stroke: 'var(--kw-line)', strokeDasharray: '3 3' }),
            h('text', { x: 24, y: 103, textAnchor: 'end' }, '0'),
            h('text', { x: 24, y: 58, textAnchor: 'end' }, String(top / 2)),
            h('text', { x: 24, y: 13, textAnchor: 'end' }, String(top)),
            weeks.length ? h('polygon', { points: points + ' ' + x(weeks.length - 1) + ',100 30,100', fill: 'var(--kw-accent)', opacity: 0.12 }) : null,
            weeks.length ? h('polyline', { points, fill: 'none', stroke: 'var(--kw-accent)', strokeWidth: 2, strokeLinejoin: 'round' }) : null,
            last ? h('circle', { cx: x(weeks.length - 1), cy: y(last.count), r: 3.5, fill: 'var(--kw-accent)' }) : null,
            last ? h('text', { x: x(weeks.length - 1) - 6, y: y(last.count) - 4.5, textAnchor: 'end', style: { fontWeight: 600, fill: 'var(--kw-ink)' } }, String(last.count)) : null,
            weeks.map((row, i) => h('text', { key: row.start, x: x(i), y: 118, textAnchor: 'middle' }, String(row.week))),
            h('text', { x: 170, y: 129, textAnchor: 'middle' }, t('week_axis')),
        );
    }

    function WaitingBars(props) {
        const { parties } = props;
        const { t } = useApp();
        const max = parties.reduce((acc, row) => Math.max(acc, row.days), 0) || 1;

        return h('div', { className: 'kw-hbars' }, parties.map((row) => h('div', { key: row.name, className: 'kw-hbar' },
            h('span', { title: row.name }, row.name),
            h('span', { className: 'kw-bar' }, h('b', { style: { width: Math.max(4, Math.round((row.days / max) * 100)) + '%' } })),
            h('span', { className: 'kw-v' }, t('days_value', { n: fmt.num(row.days, 1) })),
        )));
    }

    function relative(iso, t) {
        if (!iso) { return '–'; }
        const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
        return days <= 0 ? t('today_word') : t('days_ago', { n: days });
    }

    function Analysis() {
        const app = useApp();
        const { t, config, api } = app;
        const [month, setMonth] = useState(config.month);
        const [unit, setUnit] = useState(null);
        const [project, setProject] = useState(null);
        const [compare, setCompare] = useState(true);
        const resource = KW.useResource(() => api.get(config.endpoints.analysis, { month, unit, project, compare: compare ? 1 : 0 }), [month, unit, project, compare]);
        const data = resource.data;
        const urls = config.urls || {};
        const itemUrl = (id) => (urls.item ? app.url(urls.item, id) : null);
        const previous = data && data.previous;
        const stats = data && data.stats;
        const totalHours = data ? data.units.reduce((acc, row) => acc + row.hours, 0) : 0;
        const unitLabel = unit ? ((config.options.units || []).find((row) => row.value === unit) || {}).label : null;

        return h('div', { className: 'kw-app' }, h('div', { className: 'kw-panel' },
            h('div', { className: 'kw-topbar' },
                h('div', null,
                    h('div', { className: 'kw-crumbs' }, t('analysis_crumb'), ' › ', t('analysis_title')),
                    h('h2', null, t('analysis_title')),
                ),
                h('div', { className: 'kw-filters' },
                    h(Picker, { label: t('period'), value: month, options: config.months || [], onPick: (value) => setMonth(value || config.month) }),
                    h(Picker, { label: t('filter_unit'), value: unit, options: config.options.units || [], allLabel: t('all'), onPick: setUnit }),
                    h(Picker, { label: t('filter_project'), value: project, options: config.options.projects || [], allLabel: t('all'), searchable: true, onPick: setProject }),
                    h(Chip, { label: t('compare'), check: true, on: compare, onClick: () => setCompare(!compare) }),
                ),
            ),
            !data ? h(State, { loading: resource.loading, error: resource.error, onRetry: resource.reload }) : h(Fragment, null,
                h('div', { className: 'kw-stats' },
                    h(Stat, { value: fmt.num(stats.hours), label: t('stat_hours'), href: urls.duration, change: previous ? delta('hours', stats.hours, previous.hours, t) : null }),
                    h(Stat, { value: stats.avg_days !== null ? fmt.num(stats.avg_days, 1) + ' ' + t('days_value', { n: '' }).trim() : '–', label: t('stat_avg_days'), href: urls.duration, change: previous ? delta('days', stats.avg_days, previous.avg_days, t) : null }),
                    h(Stat, { value: stats.waiting_share !== null ? stats.waiting_share + '%' : '–', label: t('stat_waiting'), href: urls.duration, change: previous ? delta('points-down', stats.waiting_share, previous.waiting_share, t) : null }),
                    h(Stat, { value: stats.due_compliance !== null ? stats.due_compliance + '%' : '–', label: t('stat_due'), href: urls.duration, change: previous ? delta('points-up', stats.due_compliance, previous.due_compliance, t) : null }),
                    h(Stat, { value: stats.day_close !== null ? stats.day_close + '%' : '–', label: t('stat_day_close'), change: previous ? delta('points-up', stats.day_close, previous.day_close, t) : null }),
                    h(Stat, { value: fmt.num(stats.open_critical), label: t('stat_critical'), href: urls.items, change: previous ? delta('count', stats.open_critical, previous.open_critical, t) : null }),
                ),
                h('div', { className: 'kw-widgets' },
                    h('a', { className: 'kw-widget wide', href: urls.duration || undefined },
                        h('h4', null, t('widget_units')),
                        h('p', { className: 'kw-sub' }, t('widget_units_sub', { label: data.label, n: fmt.num(totalHours) })),
                        data.units.length ? h(UnitBars, { units: data.units }) : h(State, { text: t('no_data_widget') }),
                    ),
                    h('a', { className: 'kw-widget', href: urls.duration || undefined },
                        h('h4', null, t('widget_distribution')),
                        h('p', { className: 'kw-sub' }, t('widget_distribution_sub')),
                        h(Donut, { distribution: data.distribution }),
                    ),
                    h('a', { className: 'kw-widget wide', href: urls.items || undefined },
                        h('h4', null, t('widget_weekly')),
                        h('p', { className: 'kw-sub' }, t('widget_weekly_sub', { scope: unitLabel || t('all_units') })),
                        h(WeeklyLine, { weeks: data.weekly_done }),
                    ),
                    h('a', { className: 'kw-widget', href: urls.duration || undefined },
                        h('h4', null, t('widget_waiting')),
                        h('p', { className: 'kw-sub' }, t('widget_waiting_sub')),
                        data.waiting_parties.length ? h(WaitingBars, { parties: data.waiting_parties }) : h(State, { text: t('no_data_widget') }),
                    ),
                    h('div', { className: 'kw-widget wide' },
                        h('h4', null, t('widget_oldest')),
                        h('p', { className: 'kw-sub' }, t('widget_oldest_sub')),
                        data.oldest.length ? h('div', { className: 'kw-table-wrap' }, h('table', { className: 'kw-tbl' },
                            h('thead', null, h('tr', null,
                                h('th', null, t('col_item')), h('th', null, t('col_project')), h('th', null, t('col_status')),
                                h('th', null, t('col_owner')), h('th', null, t('col_open_days')), h('th', null, t('col_updated')),
                            )),
                            h('tbody', null, data.oldest.map((row) => h('tr', { key: row.id },
                                h('td', { className: 'wrap' }, itemUrl(row.id) ? h('a', { href: itemUrl(row.id) }, row.title) : row.title),
                                h('td', null, row.project || '–'),
                                h('td', null, h(StatusBadge, { status: row.status, label: row.status_label })),
                                h('td', null, row.owner || '–'),
                                h('td', { className: row.open_days > 14 ? 'neg' : null }, String(row.open_days)),
                                h('td', null, relative(row.updated_at, t)),
                            ))),
                        )) : h(State, { text: t('no_data_widget') }),
                    ),
                ),
            ),
        ));
    }

    KW.mount('work-analysis', Analysis);
}());
