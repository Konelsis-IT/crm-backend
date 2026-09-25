/*
 * Konelsis - KONTROL MATRISI (B36, D-115, 22 Eylul 2026; gunluk doldurma ve
 * salt okunur haftalik gorunum D-116, 23 Eylul 2026 kullanici karari:
 * "Haftalik kontrol matrisi gunluk doldurulabilir olmali. Sadece IK
 * tarafindan doldurulabilecek. Yonetici ise sadece salt okunur sekilde, hem
 * haftalik hem gunluk raporu gorebilecek... Tik'ler yani olumlular
 * yazilmayacak. Aciklama kismi ise gunluk yazilan tum aciklamalarin
 * toplamini gostermelidir.")
 *
 * Gunluk gorunum: gun secilir, bolum sekmesi, satir personel, sutun bolumun
 * kriterleri. Hucre tiklandikca isaretsiz -> uygun -> uygun degil. Kaydet
 * kisi basina bir gunluk kontrol raporu yazar.
 *
 * Haftalik gorunum: ayni haftanin gunluk isaretlerinin toplami; yalniz uygun
 * olmayanlar sayisiyla yazilir ("x4"), aciklama sutunu gunluk aciklamalarin
 * toplamidir. Salt okunurdur.
 *
 * "Haftalik rapor" sutunu her iki gorunumde de sistemden gelir.
 *
 * Kok: [data-kw-root="work-matrix"]; data-config: WorkAppConfig::matrix().
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;

    if (!KW) {
        return;
    }

    const { h, Fragment, cx, fmt, useApp, Btn, Tabs, Seg, State } = KW;
    const { useState, useEffect, useMemo, useCallback } = KW.hooks;

    // Varsayilan ✓ (25 Eylul 2026 kullanici istegi): tiklama sirasi ✓ -> ✗ -> – -> ✓.
    const NEXT = { ok: 'bad', bad: 'none', none: 'ok' };

    /**
     * O gun icin kaydi olmayan satirda elle isaretlenen kriterler ✓ baslar.
     * Kayitli satirda "–" bilerek secilmistir (yalniz ✓ ve ✗ saklanir);
     * dokunulmaz. Otomatik kriter (haftalik rapor) sistemden gelir.
     */
    function withDefaultMarks(row, criteria) {
        if (row.report) { return row; }

        const marks = { ...row.marks };
        criteria.forEach((criterion) => {
            if (!criterion.auto && (!marks[criterion.code] || marks[criterion.code] === 'none')) {
                marks[criterion.code] = 'ok';
            }
        });

        return { ...row, marks };
    }
    const SYMBOL = { ok: '✓', bad: '✗', none: '–' };

    function Mark(props) {
        const { value, auto, locked, onClick, label } = props;
        const { t } = useApp();
        const text = value === 'ok' ? t('mark_ok') : (value === 'bad' ? t('mark_bad') : t('mark_none'));
        const fixed = auto || locked;

        return h('button', {
            type: 'button',
            className: cx('kw-mark', value, fixed && 'auto'),
            onClick: fixed ? undefined : onClick,
            disabled: fixed,
            title: label + ': ' + text + (auto ? ' (' + t('auto') + ')' : ''),
            'aria-label': label + ': ' + text,
        }, SYMBOL[value] || '–');
    }

    /** Haftalik gorunum hucresi: yalniz uygun olmayanlar yazilir. */
    function Count(props) {
        const { count, checked, label } = props;
        const { t } = useApp();

        if (!count) {
            return h('span', {
                className: 'kw-tally none',
                title: label + ': ' + (checked ? t('week_all_ok', { n: checked }) : t('week_unchecked')),
                'aria-label': label + ': ' + (checked ? t('week_all_ok', { n: checked }) : t('week_unchecked')),
            }, checked ? '' : '–');
        }

        return h('span', {
            className: 'kw-tally bad',
            title: label + ': ' + t('week_bad_days', { n: count, of: checked }),
            'aria-label': label + ': ' + t('week_bad_days', { n: count, of: checked }),
        }, '✗', h('b', null, count));
    }

    function score(row, criteria) {
        let ok = 0;
        let total = 0;

        criteria.forEach((criterion) => {
            const mark = criterion.auto ? row.auto : (row.marks[criterion.code] || 'none');

            if (mark !== 'none') {
                total++;
                ok += mark === 'ok' ? 1 : 0;
            }
        });

        return { ok, total };
    }

    function Matrix() {
        const app = useApp();
        const { t, config, api } = app;
        const canFill = !!(config.can && config.can.fill);
        const [mode, setMode] = useState(config.mode === 'week' ? 'week' : 'day');
        const [day, setDay] = useState(config.day || config.today || null);
        const [week, setWeek] = useState(config.week || null);
        const [section, setSection] = useState(null);
        const [state, setState] = useState({ loading: true, error: null, data: null });
        const [edits, setEdits] = useState({});
        const [selected, setSelected] = useState(null);
        const [saving, setSaving] = useState(false);
        const dirty = Object.keys(edits).length > 0;

        const load = useCallback((next) => {
            setState((previous) => ({ ...previous, loading: true, error: null }));
            api.get(config.endpoints.matrix, { mode: next.mode, day: next.day, week: next.week, section: next.section }).then((json) => {
                setState({ loading: false, error: null, data: json });
                setMode(json.mode);
                setDay(json.day);
                setWeek(json.week);
                setSection(json.section);
                setEdits({});
                setSelected((current) => (json.sheet && json.sheet.rows.some((row) => row.personnel.id === current) ? current : (json.sheet && json.sheet.rows[0] ? json.sheet.rows[0].personnel.id : null)));
            }).catch((error) => setState((previous) => ({ ...previous, loading: false, error })));
        }, [api, config]);

        useEffect(() => { load({ mode, day, week, section: null }); }, []);

        useEffect(() => {
            if (!dirty) { return undefined; }

            const guard = (event) => {
                event.preventDefault();
                event.returnValue = '';
            };

            window.addEventListener('beforeunload', guard);

            return () => window.removeEventListener('beforeunload', guard);
        }, [dirty]);

        const guarded = (action) => {
            if (!dirty) {
                action();
                return;
            }

            app.confirm({ title: t('matrix_title'), text: t('unsaved_confirm'), confirmLabel: t('yes') }).then((yes) => { if (yes) { action(); } });
        };

        const data = state.data;
        const sections = (data && data.sections) || [];
        const current = sections.find((row) => row.code === section) || null;
        const criteria = current ? current.criteria : [];
        const sheet = data && data.sheet;
        const weekly = sheet && sheet.mode === 'week';
        const editable = canFill && !weekly;

        // Varsayilan ✓ yalniz dolduran kiside (IK, gunluk gorunum) ve bugun ya
        // da gecmis bir gunde; salt okunur gorunumde ve ileri tarihli gunde
        // doldurulmamis satir isaretsiz gorunur (yanlislikla toplu ✓ yazilmasin).
        const defaultsOn = editable && !!(data && data.today && day) && day <= data.today;
        const rows = useMemo(() => (sheet ? sheet.rows.map((source) => {
            const row = defaultsOn ? withDefaultMarks(source, criteria) : source;
            const edit = edits[row.personnel.id];
            return edit ? { ...row, marks: { ...row.marks, ...edit.marks }, note: edit.note !== undefined ? edit.note : row.note } : row;
        }) : []), [sheet, edits, defaultsOn, criteria]);
        const unsaved = defaultsOn && rows.some((row) => !row.report);

        const setMark = (row, code) => {
            // row, varsayilanlar ve duzenlemeler islenmis satirdir.
            const currentMark = row.marks[code] || 'none';
            setEdits((previous) => {
                const existing = previous[row.personnel.id] || { marks: {} };
                return { ...previous, [row.personnel.id]: { ...existing, marks: { ...existing.marks, [code]: NEXT[currentMark] } } };
            });
            setSelected(row.personnel.id);
        };

        const setNote = (row, note) => setEdits((previous) => {
            const existing = previous[row.personnel.id] || { marks: {} };
            return { ...previous, [row.personnel.id]: { ...existing, note } };
        });

        const save = () => {
            // Kaydi olmayan satirlarin varsayilan ✓'leri de kaydedilir.
            if (!dirty && !unsaved) {
                app.toast({ text: t('nothing_to_save') });
                return;
            }

            setSaving(true);
            api.post(config.endpoints.save, {
                day,
                section,
                rows: rows.map((row) => ({ personnel_id: row.personnel.id, marks: row.marks, note: row.note || null })),
            }).then((json) => {
                setState((previous) => ({ ...previous, data: { ...previous.data, sheet: json.sheet || previous.data.sheet } }));
                setEdits({});
                app.toast({ text: t('matrix_saved', { c: json.created, u: json.updated }), tone: 'ok' });
            }).catch(app.fail).finally(() => setSaving(false));
        };

        const shift = (steps) => guarded(() => (weekly
            ? load({ mode, day, week: fmt.addDays(week, steps * 7), section })
            : load({ mode, day: fmt.addDays(day, steps), week, section })));

        const jump = (value) => guarded(() => load({ mode, day: value, week: value, section }));
        const switchMode = (value) => guarded(() => load({ mode: value, day, week: value === 'week' ? day : week, section }));
        const chosen = rows.find((row) => row.personnel.id === selected) || null;
        const reportsUrl = config.urls && config.urls.reports;
        const today = weekly ? (data && data.today_week) : (data && data.today);
        const currentValue = weekly ? week : day;

        return h('div', { className: 'kw-app' }, h('div', { className: 'kw-panel' },
            h('div', { className: 'kw-topbar' },
                h('div', null,
                    h('div', { className: 'kw-crumbs' }, reportsUrl ? h('a', { href: reportsUrl }, t('reports')) : t('reports'), ' › ', t('matrix_crumb')),
                    h('h2', null, t('matrix_title')),
                ),
                h('div', { className: 'kw-actions' },
                    h(Seg, {
                        label: t('view_label'),
                        value: weekly ? 'week' : 'day',
                        items: [{ value: 'day', label: t('view_day') }, { value: 'week', label: t('view_week') }],
                        onChange: switchMode,
                    }),
                    h('span', { className: 'kw-weeknav' },
                        h('button', { type: 'button', 'aria-label': weekly ? t('prev_week') : t('prev_day'), onClick: () => shift(-1) }, '◀'),
                        sheet ? sheet.label : (currentValue ? fmt.date(currentValue) : ''),
                        h('button', { type: 'button', 'aria-label': weekly ? t('next_week') : t('next_day'), onClick: () => shift(1) }, '▶'),
                    ),
                    h('input', {
                        type: 'date',
                        className: 'kw-datejump',
                        value: currentValue || '',
                        'aria-label': weekly ? t('pick_week') : t('pick_day'),
                        onChange: (event) => { if (event.target.value) { jump(event.target.value); } },
                    }),
                    today && currentValue !== today ? h(Btn, { onClick: () => jump(today) }, weekly ? t('this_week') : t('today')) : null,
                    editable ? h(Btn, { variant: 'primary', disabled: saving || !sheet, onClick: save }, t('save')) : null,
                ),
            ),
            sections.length ? h(Tabs, {
                items: sections.map((row) => ({ value: row.code, label: row.label })),
                value: section,
                label: t('sections_label'),
                onChange: (code) => guarded(() => load({ mode, day, week, section: code })),
            }) : null,
            state.error ? h(State, { error: state.error, onRetry: () => load({ mode, day, week, section }) }) : null,
            state.loading && !data ? h(State, { loading: true }) : null,
            data && !sections.length ? h(State, { text: t('no_sections') }) : null,
            sheet && unsaved ? h('p', { className: 'kw-info' }, t('default_ok_info')) : null,
            sheet ? h('div', { className: 'kw-matrix-layout' },
                h('div', { className: 'kw-matrix-wrap' },
                    rows.length ? h('table', { className: 'kw-matrix' },
                        h('thead', null, h('tr', null,
                            h('th', { scope: 'col' }, t('personnel')),
                            criteria.map((criterion) => h('th', { key: criterion.code, scope: 'col' }, criterion.label, criterion.auto ? h('span', { className: 'kw-auto' }, t('auto')) : null)),
                            h('th', { scope: 'col' }, t('note_col')),
                        )),
                        h('tbody', null, rows.map((row) => h('tr', { key: row.personnel.id, className: cx({ sel: row.personnel.id === selected }) },
                            h('td', { className: 'kw-name' }, h('button', { type: 'button', onClick: () => setSelected(row.personnel.id) },
                                row.personnel.name,
                                h('small', null, weekly ? t('week_days', { n: row.days || 0 }) : (row.personnel.unit || '')),
                            )),
                            criteria.map((criterion) => h('td', { key: criterion.code }, weekly
                                ? (criterion.auto
                                    ? h(Count, { count: row.auto === 'bad' ? 1 : 0, checked: row.auto === 'none' ? 0 : 1, label: criterion.label })
                                    : h(Count, { count: (row.bad || {})[criterion.code] || 0, checked: (row.checked || {})[criterion.code] || 0, label: criterion.label }))
                                : (criterion.auto
                                    ? h(Mark, { value: row.auto, auto: true, label: criterion.label })
                                    : h(Mark, { value: row.marks[criterion.code] || 'none', locked: !editable, label: criterion.label, onClick: () => setMark(row, criterion.code) })))),
                            h('td', { className: 'kw-note' }, weekly
                                ? ((row.notes || []).length
                                    ? h('div', { className: 'kw-notes' }, row.notes.map((note, index) => h('span', { key: index }, h('b', null, note.label), ' ', note.text)))
                                    : h('span', { className: 'kw-muted' }, '–'))
                                : (editable
                                    ? h('input', {
                                        value: row.note || '',
                                        placeholder: t('note_placeholder'),
                                        'aria-label': row.personnel.name + ' · ' + t('note_col'),
                                        onFocus: () => setSelected(row.personnel.id),
                                        onChange: (event) => setNote(row, event.target.value),
                                    })
                                    : h('span', null, row.note || '–'))),
                        ))),
                    ) : h(State, { text: t('empty_section') }),
                ),
                h(Summary, { row: chosen, criteria, sheet, weekly }),
            ) : null,
        ));
    }

    function Summary(props) {
        const { row, criteria, sheet, weekly } = props;
        const { t } = useApp();

        if (!row) {
            return h('aside', { className: 'kw-summary' }, h('h3', null, t('aside_title')), h('p', { className: 'kw-muted', style: { fontSize: '12.5px' } }, t('pick_row')));
        }

        const live = weekly ? { ok: row.summary.ok, total: row.summary.total } : score(row, criteria);
        const weeks = (row.summary.weeks || []).map((week, index, list) => (index === list.length - 1 && !weekly ? { ...week, ok: live.ok, total: live.total } : week));
        const repeating = row.summary.repeating;

        return h('aside', { className: 'kw-summary', 'aria-label': t('aside_title') },
            h('h3', null, t('aside_title')),
            h('div', null,
                h('div', { className: 'kw-kpi' }, live.ok + ' / ' + live.total, h('small', null, weekly ? t('aside_kpi_week') : t('aside_kpi'))),
                h('p', { style: { color: 'var(--kw-muted)', fontSize: '12.5px' } }, row.personnel.name + (row.personnel.unit ? ' · ' + row.personnel.unit : '')),
            ),
            h('div', null,
                h('p', { style: { fontSize: '12px', fontWeight: 600, marginBottom: '6px' } }, t('last_four')),
                h('div', { className: 'kw-bars' }, weeks.map((week) => {
                    const ratio = week.total ? week.ok / week.total : 0;
                    return h('div', { key: week.label, title: week.label },
                        h('b', { className: cx({ warn: week.total && ratio < 1, empty: !week.total }), style: { height: (week.total ? Math.max(8, Math.round(ratio * 100)) : 4) + '%' } }),
                        h('span', null, week.total ? week.ok + '/' + week.total : '–'),
                    );
                })),
            ),
            h('p', { style: { fontSize: '12.5px' } }, repeating ? h(Fragment, null, t('repeating_label'), ' ', h('b', null, repeating.label), ' · ', t('weeks_value', { n: repeating.weeks })) : t('repeating_none')),
            h('p', { style: { fontSize: '12.5px', color: 'var(--kw-muted)' } }, sheet.controller ? t('controller_info', { name: sheet.controller }) : t('controller_none')),
            h('span', { className: 'kw-lock' }, t('matrix_lock')),
        );
    }

    KW.mount('work-matrix', Matrix);
}());
