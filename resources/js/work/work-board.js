/*
 * Konelsis - IS PANOSU (B36, D-115, 22 Eylul 2026; kullanici karari: madde 1, 3,
 * 6 ve 7 React ile, taslak arayuzle birebir).
 *
 * Tek sayfa, dort tip: Panom, Ekip panosu, Proje panosu, Yonetim panosu. Suzgecler
 * tipe gore gelir, sayi gosterir, coklu secilir ve tip ile birlikte hatirlanir
 * (localStorage, yalniz tercih). Sistemden gelen oneriler (Kart yap / Yoksay),
 * hizli satir (is tarihi secici ile), bes durum sutunu, surukle-birak (durum +
 * sira; Bekleniyor'da kimden, Tamamlandi'da saat sorulur; Geri al), kart menusu
 * (klavye ve dokunmatik icin Durum), Yeni kalem / duzenleme penceresi, Gunu
 * kapat, Haftayi kapat ve Yonetim panosunu dondur pencereleri.
 *
 * Kok: [data-kw-root="work-board"]; data-config: WorkAppConfig::board().
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;

    if (!KW) {
        return;
    }

    const { h, Fragment, cx, fmt, storage, useApp, Btn, Chip, Dropdown, Opt, Modal, Seg, Toggle, Field, Input, TextArea, Select, SearchSelect, Tag, Avatar, State } = KW;
    const { useState, useEffect, useRef, useCallback, useMemo } = KW.hooks;

    const STATUSES = ['planned', 'in_progress', 'waiting', 'done', 'blocked'];
    const SCOPES = ['mine', 'team', 'all'];
    const RANGES = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'range'];
    const GROUP_DEFAULT = { mine: 'none', team: 'personnel', all: 'project' };
    const NONE = '__none__';

    function defaultFilters(scope) {
        return {
            projects: [],
            categories: [],
            range: 'this_week',
            from: '',
            to: '',
            personnel: [],
            units: [],
            critical: false,
            longWait: false,
            group: GROUP_DEFAULT[scope] || 'none',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Suzgec ve gruplama                                                   */
    /* ------------------------------------------------------------------ */

    function matches(card, f, skip) {
        if (skip !== 'projects' && f.projects.length && f.projects.indexOf(card.project ? card.project.id : NONE) === -1) { return false; }
        if (skip !== 'categories' && f.categories.length && f.categories.indexOf(card.category || NONE) === -1) { return false; }
        if (skip !== 'personnel' && f.personnel.length && f.personnel.indexOf(card.personnel ? card.personnel.id : NONE) === -1) { return false; }
        if (skip !== 'units' && f.units.length && f.units.indexOf(card.unit ? card.unit.id : NONE) === -1) { return false; }
        if (skip !== 'critical' && f.critical && !card.is_critical) { return false; }
        if (skip !== 'longWait' && f.longWait && !(card.status === 'waiting' && card.waiting && card.waiting.late)) { return false; }

        return true;
    }

    function countBy(cards, f, skip, keyOf) {
        const counts = {};

        cards.forEach((card) => {
            if (!matches(card, f, skip)) { return; }
            const key = keyOf(card);
            counts[key] = (counts[key] || 0) + 1;
        });

        return counts;
    }

    function laneKey(card, group, t) {
        switch (group) {
            case 'personnel': return card.personnel ? card.personnel.name : t('none');
            case 'project': return card.project ? card.project.name : t('no_project');
            case 'unit': return card.unit ? card.unit.name : t('none');
            case 'category': return card.category_label || t('no_category');
            default: return '';
        }
    }

    function sortCards(cards, scope) {
        const list = cards.slice();

        if (scope === 'all') {
            list.sort((a, b) => {
                if (a.is_critical !== b.is_critical) { return a.is_critical ? -1 : 1; }
                const wa = a.status === 'waiting' && a.waiting ? a.waiting.days || 0 : -1;
                const wb = b.status === 'waiting' && b.waiting ? b.waiting.days || 0 : -1;
                if (wa !== wb) { return wb - wa; }
                return (a.sort_order || 0) - (b.sort_order || 0);
            });
        } else {
            list.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
        }

        return list;
    }

    /* ------------------------------------------------------------------ */
    /* Suzgec kutulari                                                      */
    /* ------------------------------------------------------------------ */

    function MultiFilter(props) {
        const { label, options, selected, onApply, searchable, searchPlaceholder, hint, valueLabel } = props;
        const { t } = useApp();

        return h(Dropdown, {
            width: '260px',
            trigger: (open, toggle) => h(Chip, { label, value: valueLabel, open, onClick: toggle }),
        }, (close) => h(MultiPanel, { options, selected, searchable, searchPlaceholder, hint, t, onApply: (next) => { onApply(next); close(); } }));
    }

    function MultiPanel(props) {
        const { options, selected, searchable, searchPlaceholder, hint, onApply, t } = props;
        const [local, setLocal] = useState(selected.slice());
        const [query, setQuery] = useState('');
        const q = query.toLocaleLowerCase('tr-TR');

        const toggle = (value) => setLocal((list) => (list.indexOf(value) === -1 ? list.concat([value]) : list.filter((item) => item !== value)));

        return h(Fragment, null,
            searchable ? h('div', { className: 'kw-search' }, '🔍 ', h('input', { value: query, autoFocus: true, placeholder: searchPlaceholder || t('search'), onChange: (event) => setQuery(event.target.value) })) : null,
            options.map((option) => {
                if (option.sub) {
                    return q ? null : h(Opt, { key: 'sub-' + option.sub, sub: true }, option.sub);
                }

                if (q && String(option.label).toLocaleLowerCase('tr-TR').indexOf(q) === -1) {
                    return null;
                }

                return h(Opt, { key: option.key || option.value, on: local.indexOf(option.value) !== -1, dot: option.dot, count: option.count, onClick: () => toggle(option.value) }, option.label);
            }),
            h('div', { className: 'kw-dd-foot' },
                hint
                    ? h('span', null, hint)
                    : h('button', { type: 'button', className: 'kw-btn link', onClick: () => setLocal([]) }, t('clear')),
                h(Fragment, null,
                    hint ? h('button', { type: 'button', className: 'kw-btn link', style: { marginLeft: 'auto', marginRight: '8px' }, onClick: () => setLocal([]) }, t('clear')) : null,
                    h(Btn, { variant: 'primary', size: 'sm', onClick: () => onApply(local) }, t('apply')),
                ),
            ),
        );
    }

    function RadioFilter(props) {
        const { label, options, value, onApply, valueLabel, hint, extra } = props;
        const { t } = useApp();

        return h(Dropdown, {
            width: '240px',
            trigger: (open, toggle) => h(Chip, { label, value: valueLabel, open, onClick: toggle }),
        }, (close) => h(RadioPanel, { options, value, hint, extra, t, onApply: (next, more) => { onApply(next, more); close(); } }));
    }

    function RadioPanel(props) {
        const { options, value, hint, extra, onApply, t } = props;
        const [local, setLocal] = useState(value);
        const [more, setMore] = useState(extra ? extra.value : null);

        return h(Fragment, null,
            options.map((option) => h(Opt, { key: option.value, radio: true, on: local === option.value, count: option.count, onClick: () => setLocal(option.value) }, option.label)),
            extra && local === extra.when ? extra.render(more, setMore) : null,
            h('div', { className: 'kw-dd-foot' },
                h('span', null, hint || ''),
                h(Btn, { variant: 'primary', size: 'sm', onClick: () => onApply(local, more) }, t('apply')),
            ),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Is tarihi secici (hizli satir ve pencere)                             */
    /* ------------------------------------------------------------------ */

    function whenLabel(when, t, nowTime) {
        if (!when || when.mode === 'now') { return t('date_chip_now', { time: nowTime }); }
        if (when.mode === 'yesterday') { return t('date_chip_yesterday', { date: fmt.dm(when.date), time: when.time }); }
        return t('date_chip_other', { date: fmt.date(when.date), time: when.time });
    }

    function useClock() {
        const now = () => {
            const d = new Date();
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        };
        const [time, setTime] = useState(now);

        useEffect(() => {
            const timer = window.setInterval(() => setTime(now()), 30000);
            return () => window.clearInterval(timer);
        }, []);

        return time;
    }

    function WhenPicker(props) {
        const { when, onChange, today, variant } = props;
        const { t } = useApp();
        const nowTime = useClock();

        return h(Dropdown, {
            width: '300px',
            align: variant === 'field' ? 'end' : undefined,
            trigger: (open, toggle) => h('button', {
                type: 'button',
                className: variant === 'input' ? 'kw-input' : cx('kw-field', open && 'open'),
                onClick: toggle,
                'aria-expanded': String(open),
            }, h('span', null, whenLabel(when, t, nowTime)), h('span', { className: 'kw-caret', style: { marginLeft: 'auto', color: 'var(--kw-muted)', fontSize: '10px' }, 'aria-hidden': 'true' }, '▼')),
        }, (close) => h(WhenPanel, { when, today, nowTime, t, onCancel: close, onApply: (next) => { onChange(next); close(); } }));
    }

    function WhenPanel(props) {
        const { when, today, nowTime, t, onApply, onCancel } = props;
        const yesterday = fmt.addDays(today, -1);
        const [mode, setMode] = useState(when ? when.mode : 'now');
        const [date, setDate] = useState(when && when.date ? when.date : yesterday);
        const [time, setTime] = useState(when && when.time ? when.time : nowTime);

        return h('div', { className: 'kw-date-opts' },
            h(Opt, { radio: true, on: mode === 'now', onClick: () => setMode('now') }, t('date_now') + ' · ' + fmt.date(today) + ' ' + nowTime),
            h(Opt, { radio: true, on: mode === 'yesterday', onClick: () => { setMode('yesterday'); setDate(yesterday); } }, t('date_yesterday') + ' · ' + fmt.date(yesterday)),
            h(Opt, { radio: true, on: mode === 'custom', onClick: () => setMode('custom') }, t('date_pick')),
            mode !== 'now' ? h('div', { className: 'kw-range' },
                h('input', { type: 'date', className: 'kw-input', value: mode === 'yesterday' ? yesterday : date, disabled: mode === 'yesterday', onChange: (event) => setDate(event.target.value) }),
                h('input', { type: 'time', className: 'kw-input', value: time, onChange: (event) => setTime(event.target.value) }),
            ) : null,
            h('div', { className: 'kw-pop-foot', style: { paddingTop: '6px' } },
                h(Btn, { size: 'sm', onClick: onCancel }, t('cancel')),
                h(Btn, { size: 'sm', variant: 'primary', onClick: () => onApply(mode === 'now' ? { mode: 'now' } : { mode, date: mode === 'yesterday' ? yesterday : (date || today), time: time || nowTime }) }, t('apply')),
            ),
        );
    }

    function whenPayload(when) {
        return !when || when.mode === 'now' ? {} : { work_on: when.date, work_time: when.time };
    }

    /* ------------------------------------------------------------------ */
    /* Kimden bekleniyor ve bagli kayit secicileri                           */
    /* ------------------------------------------------------------------ */

    function WaitingPicker(props) {
        const { value, onChange, invalid } = props;
        const { t, api, config } = useApp();
        const kinds = config.options.waiting_kinds || [];
        const people = (config.options.people || []).map((person) => ({ value: person.value, label: person.label }));
        const kind = value.kind || '';
        const loadParties = useCallback((q) => api.get(config.endpoints.parties, { q }).then((json) => (json.items || []).map((row) => ({ value: row.id, label: row.label }))), [api, config]);

        return h('div', { className: 'kw-pair' },
            h(Select, {
                value: kind,
                options: kinds,
                placeholder: t('select'),
                className: invalid && !kind ? 'invalid' : null,
                'aria-label': t('field_waiting'),
                onChange: (next) => onChange({ kind: next || '', personnel_id: null, party_id: null, party_label: null, text: kind === 'text' || next === 'text' ? value.text : '' }),
            }),
            kind === 'personnel'
                ? h(SearchSelect, { value: value.personnel_id, options: people, placeholder: t('waiting_value'), onChange: (id) => onChange({ ...value, personnel_id: id }) })
                : kind === 'party'
                    ? h(SearchSelect, { value: value.party_id, label: value.party_label, load: loadParties, placeholder: t('waiting_value'), onChange: (id, option) => onChange({ ...value, party_id: id, party_label: option ? option.label : null }) })
                    : h(Input, { value: value.text || '', placeholder: t('waiting_value'), disabled: !kind, invalid: invalid && kind === 'text' && !value.text, onChange: (text) => onChange({ ...value, kind: kind || 'text', text }) }),
        );
    }

    function waitingValid(waiting) {
        if (!waiting || !waiting.kind) { return false; }
        if (waiting.kind === 'personnel') { return !!waiting.personnel_id; }
        if (waiting.kind === 'party') { return !!waiting.party_id || !!(waiting.text && waiting.text.trim()); }
        return !!(waiting.text && waiting.text.trim());
    }

    function waitingPayload(waiting) {
        if (!waiting || !waiting.kind) { return { waiting_kind: null, waiting_personnel_id: null, waiting_party_id: null, waiting_text: null }; }

        return {
            waiting_kind: waiting.kind,
            waiting_personnel_id: waiting.kind === 'personnel' ? waiting.personnel_id : null,
            waiting_party_id: waiting.kind === 'party' ? waiting.party_id : null,
            waiting_text: waiting.kind !== 'personnel' ? (waiting.text || null) : null,
        };
    }

    function waitingFromCard(card) {
        const w = card && card.waiting;

        if (!w) { return { kind: '', personnel_id: null, party_id: null, party_label: null, text: '' }; }

        return { kind: w.kind, personnel_id: w.personnel_id, party_id: w.party_id, party_label: w.kind === 'party' ? w.label : null, text: w.text || '' };
    }

    /**
     * Talep eden (B37, D-118): isi kim istedi. Tek kutu; personel ve taraf
     * birlikte aranir. Serbest metin yalniz Isler formundadir.
     */
    function RequesterPicker(props) {
        const { value, onChange, variant } = props;
        const { t, api, config } = useApp();
        const people = (config.options.people || []).map((person) => ({ value: 'person:' + person.value, label: person.label }));
        const load = useCallback((q) => {
            const needle = String(q || '').toLocaleLowerCase('tr');
            const matched = needle ? people.filter((row) => row.label.toLocaleLowerCase('tr').indexOf(needle) !== -1) : people;

            if (!needle) { return Promise.resolve(matched); }

            return api.get(config.endpoints.parties, { q })
                .then((json) => matched.concat((json.items || []).map((row) => ({ value: 'party:' + row.id, label: row.label }))))
                .catch(() => matched);
        }, [api, config]);

        return h(SearchSelect, {
            variant,
            value: value && value.value,
            label: value && value.label,
            options: people,
            load,
            clearable: true,
            placeholder: t('requester_none'),
            searchPlaceholder: t('requester_value'),
            onChange: (next, option) => onChange(next ? { value: next, label: option ? option.label : null } : null),
        });
    }

    function requesterPayload(requester) {
        if (!requester || !requester.value) {
            return { requester_kind: null, requester_personnel_id: null, requester_party_id: null, requester_text: null };
        }

        const parts = String(requester.value).split(':');
        const id = Number(parts[1]);

        return parts[0] === 'party'
            ? { requester_kind: 'party', requester_personnel_id: null, requester_party_id: id, requester_text: requester.label || null }
            : { requester_kind: 'personnel', requester_personnel_id: id, requester_party_id: null, requester_text: null };
    }

    function requesterFromCard(card) {
        const r = card && card.requester;

        if (!r) { return null; }

        if (r.kind === 'personnel' && r.personnel_id) { return { value: 'person:' + r.personnel_id, label: r.label }; }
        if (r.kind === 'party' && r.party_id) { return { value: 'party:' + r.party_id, label: r.label }; }

        return null;
    }

    function LinkPicker(props) {
        const { value, onChange, locked } = props;
        const { t, api, config } = useApp();
        const kinds = config.options.link_kinds || [];
        const loadLinks = useCallback((q) => api.get(config.endpoints.links, { kind: value.kind, q }).then((json) => (json.items || []).map((row) => ({ value: row.id, label: (row.no ? row.no + ' · ' : '') + row.label }))), [api, config, value.kind]);

        if (locked) {
            return h('div', { className: 'kw-input lock' }, value.label || '–', ' · ', t('locked'));
        }

        return h('div', { className: 'kw-pair' },
            h(Select, {
                value: value.kind || '',
                options: kinds,
                placeholder: t('link_kind'),
                'aria-label': t('link_kind'),
                onChange: (next) => onChange({ kind: next || '', id: null, label: null }),
            }),
            h(SearchSelect, {
                key: value.kind || 'none',
                value: value.id,
                label: value.label,
                load: value.kind ? loadLinks : null,
                options: value.kind ? null : [],
                disabled: !value.kind,
                placeholder: t('link_search'),
                onChange: (id, option) => onChange({ ...value, id, label: option ? option.label : null }),
            }),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Kart                                                                 */
    /* ------------------------------------------------------------------ */

    function cardRight(card, t) {
        if (card.status === 'waiting' && card.waiting && card.waiting.label) {
            return h(Tag, { kind: cx('wait', card.waiting.late && 'late') }, card.waiting.label + ' · ' + t('days_value', { n: card.waiting.days || 0 }));
        }

        if (card.status === 'blocked' && card.note) {
            return h('span', null, card.note.length > 40 ? card.note.slice(0, 40) + '…' : card.note);
        }

        if (card.source === 'automatic' && card.source_tag) {
            return h(Tag, { kind: 'auto' }, card.source_tag);
        }

        if ((card.status === 'in_progress' || card.status === 'done') && card.hours) {
            return h('span', null, t('hours_value', { h: fmt.hours(card.hours) }));
        }

        if (card.due_on && card.status !== 'done') {
            return h('span', null, t('due_label', { date: fmt.dm(card.due_on) }));
        }

        if (card.link) {
            return h(Tag, { kind: 'link', href: card.link.url || undefined, title: card.link.label }, card.link.no || card.link.kind_label);
        }

        if (card.hours) {
            return h('span', null, t('hours_value', { h: fmt.hours(card.hours) }));
        }

        return h('span', null);
    }

    function CardMenu(props) {
        const { card, onEdit, onStatus, onCritical, onDelete } = props;
        const { t, config } = useApp();

        return h(Dropdown, {
            align: 'end',
            width: '210px',
            trigger: (open, toggle) => h('button', {
                type: 'button',
                className: 'kw-card-menu',
                'aria-label': t('card_menu'),
                'aria-expanded': String(open),
                onClick: (event) => { event.stopPropagation(); toggle(); },
                onMouseDown: (event) => event.stopPropagation(),
                draggable: false,
            }, '···'),
        }, (close) => h('div', { onClick: (event) => event.stopPropagation() },
            card.can && card.can.update ? h(Opt, { plain: true, onClick: () => { close(); onEdit(card); } }, t('menu_edit')) : null,
            card.can && card.can.update ? h(Opt, { sub: true }, t('menu_status')) : null,
            card.can && card.can.update ? (config.options.statuses || []).map((status) => h(Opt, {
                key: status.value,
                radio: true,
                dot: status.value,
                on: card.status === status.value,
                onClick: () => { close(); if (status.value !== card.status) { onStatus(card, status.value); } },
            }, status.label)) : null,
            card.can && card.can.update ? h('div', { className: 'kw-divider' }) : null,
            card.can && card.can.update ? h(Opt, { plain: true, onClick: () => { close(); onCritical(card); } }, card.is_critical ? t('menu_critical_off') : t('menu_critical_on')) : null,
            card.link && card.link.url ? h(Opt, { plain: true, onClick: () => { close(); window.location.assign(card.link.url); } }, t('menu_open_record')) : null,
            card.url ? h(Opt, { plain: true, onClick: () => { close(); window.location.assign(card.url); } }, t('menu_detail')) : null,
            card.can && card.can.delete ? h(Opt, { plain: true, danger: true, onClick: () => { close(); onDelete(card); } }, t('menu_delete')) : null,
        ));
    }

    function CardView(props) {
        const { card, type, isNew, ghost, handlers, dragStart, dragEnd } = props;
        const { t } = useApp();
        const draggable = !!(card.can && card.can.update);

        return h('article', {
            className: cx('kw-card', { critical: card.is_critical, new: isNew, ghost }),
            'data-card-id': card.id,
            draggable,
            tabIndex: 0,
            'aria-label': card.title,
            onClick: () => handlers.onOpen(card),
            onKeyDown: (event) => { if (event.key === 'Enter' && event.target === event.currentTarget) { handlers.onOpen(card); } },
            onDragStart: draggable ? (event) => dragStart(event, card) : undefined,
            onDragEnd: draggable ? dragEnd : undefined,
        },
        h(CardMenu, { card, onEdit: handlers.onOpen, onStatus: handlers.onStatus, onCritical: handlers.onCritical, onDelete: handlers.onDelete }),
        h('div', { className: 'kw-title' }, card.title),
        h('div', { className: 'kw-meta' },
            card.unit ? h(Tag, { kind: 'dept' }, card.unit.name) : null,
            card.project ? h('span', null, card.project.name) : null,
            card.category_label ? h(Tag, null, card.category_label) : null,
            card.is_critical ? h(Tag, { kind: 'critical' }, t('critical_tag')) : null,
            card.children ? h(Tag, null, t('children_n', { n: card.children })) : null,
            isNew ? h(Tag, { kind: 'newc' }, t('new_tag')) : null,
        ),
        card.parent ? h('div', { className: 'kw-sub' }, '↳ ' + t('parent_of', { title: card.parent.title })) : null,
        h('div', { className: 'kw-foot' },
            type !== 'panom' && card.personnel
                ? h('span', { className: 'kw-who' }, h(Avatar, { initials: card.personnel.initials }), h('span', null, card.personnel.name))
                : (card.link && card.source === 'automatic' && card.source_tag
                    ? h(Tag, { kind: 'link', href: card.link.url || undefined, title: card.link.label }, card.link.no || card.link.kind_label)
                    : h('span', null)),
            cardRight(card, t),
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Sutunlar ve surukle-birak                                            */
    /* ------------------------------------------------------------------ */

    function Column(props) {
        const { status, label, cards, lane, type, drag, dropAt, setDropAt, onDrop, newIds, handlers, dragStart, dragEnd } = props;
        const { t } = useApp();
        const ref = useRef(null);
        const isTarget = drag && dropAt && dropAt.status === status && dropAt.lane === lane;
        const others = drag ? cards.filter((card) => card.id !== drag.id) : cards;
        const n = cards.length;
        const countLabel = isTarget && drag.from !== status ? n + ' → ' + (n + 1) : String(n);

        const indexAt = (clientY) => {
            const nodes = ref.current ? Array.prototype.slice.call(ref.current.querySelectorAll('[data-card-id]')) : [];
            let index = 0;

            nodes.forEach((node) => {
                if (drag && String(node.getAttribute('data-card-id')) === String(drag.id)) { return; }
                const rect = node.getBoundingClientRect();
                if (clientY > rect.top + rect.height / 2) { index++; }
            });

            return index;
        };

        const children = [];
        let placed = false;

        others.forEach((card, index) => {
            if (isTarget && !placed && dropAt.index === index) {
                children.push(h('div', { key: 'drop', className: 'kw-drop' }, t('drop_here')));
                placed = true;
            }

            children.push(h(CardView, { key: card.id, card, type, isNew: newIds.indexOf(card.id) !== -1, handlers, dragStart, dragEnd }));
        });

        if (drag) {
            const own = cards.find((card) => card.id === drag.id);

            if (own && !isTarget) {
                children.splice(cards.indexOf(own), 0, h(CardView, { key: 'ghost-' + own.id, card: own, type, ghost: true, handlers, dragStart, dragEnd }));
            }
        }

        if (isTarget && !placed) {
            children.push(h('div', { key: 'drop', className: 'kw-drop' }, t('drop_here')));
        }

        return h('div', {
            className: cx('kw-col', { target: isTarget }),
            'data-status': status,
            ref,
            onDragOver: (event) => {
                if (!drag) { return; }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                const index = indexAt(event.clientY);

                if (!dropAt || dropAt.status !== status || dropAt.index !== index || dropAt.lane !== lane) {
                    setDropAt({ status, index, lane });
                }
            },
            onDrop: (event) => {
                if (!drag) { return; }
                event.preventDefault();
                onDrop(status, indexAt(event.clientY), others.map((card) => card.id));
            },
        },
        h('div', { className: 'kw-col-head' }, h('i', null), label, h('span', { className: 'kw-n' }, countLabel)),
        children.length ? children : h('div', { className: 'kw-col-empty' }, '–'),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Soru acilir kutusu (Bekleniyor / Tamamlandi)                          */
    /* ------------------------------------------------------------------ */

    function PromptPop(props) {
        const { kind, card, onCancel, onConfirm } = props;
        const { t } = useApp();
        const [waiting, setWaiting] = useState(waitingFromCard(card));
        const [hours, setHours] = useState(card.hours !== null && card.hours !== undefined ? String(card.hours) : '');
        const [note, setNote] = useState('');
        const [invalid, setInvalid] = useState(false);

        KW.useEscape(onCancel, true);

        const submit = () => {
            if (kind === 'waiting') {
                if (!waitingValid(waiting)) {
                    setInvalid(true);
                    return;
                }

                onConfirm({ ...waitingPayload(waiting), note: note || null });
                return;
            }

            onConfirm({ work_hours: hours === '' ? null : Number(String(hours).replace(',', '.')), note: note || null });
        };

        return h(KW.Portal, null, h('div', { className: 'kw-overlay light', onMouseDown: (event) => { if (event.target === event.currentTarget) { onCancel(); } } },
            h('div', { className: 'kw-pop', role: 'dialog', 'aria-modal': 'true', 'aria-label': kind === 'waiting' ? t('waiting_prompt') : t('hours_prompt') },
                h('h5', null, kind === 'waiting' ? t('waiting_prompt') : t('hours_prompt')),
                h('div', { className: 'kw-muted', style: { fontSize: '12px' } }, card.title),
                kind === 'waiting'
                    ? h(WaitingPicker, { value: waiting, onChange: setWaiting, invalid })
                    : h(Input, { type: 'number', min: 0, max: 999, step: 0.25, value: hours, placeholder: '0,0', autoFocus: true, onChange: setHours, onKeyDown: (event) => { if (event.key === 'Enter') { submit(); } } }),
                kind === 'waiting' && invalid ? h('p', { className: 'kw-error', style: { fontSize: '11.5px', color: 'var(--kw-bad)' } }, t('waiting_required')) : null,
                kind === 'done' ? h('p', { className: 'kw-muted', style: { fontSize: '11.5px' } }, t('hours_prompt_help')) : null,
                h(Input, { value: note, placeholder: t('note_optional'), onChange: setNote }),
                h('div', { className: 'kw-pop-foot' },
                    h(Btn, { onClick: onCancel }, t('cancel')),
                    h(Btn, { variant: 'primary', onClick: submit }, t('save')),
                ),
            ),
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Yeni kalem / duzenleme penceresi                                      */
    /* ------------------------------------------------------------------ */

    function categoryOptionsFor(config, unitId, t) {
        const units = config.options.units || [];
        const unit = units.find((row) => row.value === unitId);
        const sets = config.options.category_sets || {};
        const codes = (unit && sets[String(unit.code || '').toUpperCase()]) || sets['*'] || [];
        const labels = config.options.category_labels || {};

        return codes.map((code) => ({ value: code, label: labels[code] || code }));
    }

    function unitOfPersonnel(config, personnelId) {
        const person = (config.options.people || []).find((row) => row.value === personnelId);
        return person ? person.unit : null;
    }

    function ItemModal(props) {
        const { card, scope, cards, onClose, onSaved } = props;
        const app = useApp();
        const { t, config, api } = app;
        const me = config.me || {};
        const editing = !!card;
        const [saving, setSaving] = useState(false);
        const [errors, setErrors] = useState({});
        const [form, setForm] = useState(() => ({
            title: card ? card.title : '',
            when: card ? { mode: 'custom', date: card.work_on, time: card.time || '09:00' } : { mode: 'now' },
            hours: card && card.hours !== null && card.hours !== undefined ? String(card.hours) : '',
            project_id: card && card.project ? card.project.id : (props.defaultProject || null),
            category: card ? card.category : null,
            status: card ? card.status : 'planned',
            critical: card ? !!card.is_critical : false,
            due: card ? card.due_on || '' : '',
            waiting: waitingFromCard(card),
            requester: requesterFromCard(card),
            link: card && card.link ? { kind: card.link.kind, id: card.link.id, label: (card.link.no ? card.link.no + ' · ' : '') + card.link.label } : { kind: '', id: null, label: null },
            note: card ? card.note || '' : '',
            parent_id: card && card.parent ? card.parent.id : null,
            personnel_id: card && card.personnel ? card.personnel.id : me.id,
            unit_id: card && card.unit ? card.unit.id : null,
        }));
        const set = (patch) => setForm((previous) => ({ ...previous, ...patch }));
        const personnelUnit = form.unit_id || unitOfPersonnel(config, form.personnel_id) || me.unit;
        const categories = categoryOptionsFor(config, personnelUnit, t);
        const unitName = ((config.options.units || []).find((row) => row.value === personnelUnit) || {}).label || me.unit_name || '';
        const projects = config.options.projects || [];
        const parents = (cards || [])
            .filter((row) => !row.parent && (!card || row.id !== card.id) && (!row.personnel || row.personnel.id === form.personnel_id))
            .map((row) => ({ value: row.id, label: row.title }));
        const linkLocked = !!(card && card.source === 'automatic' && card.link);
        const scopeLabel = t('scope_' + scope);
        const subDate = form.when.mode === 'now' ? fmt.dayName(config.today) + ' ' + fmt.date(config.today) : fmt.dayName(form.when.date) + ' ' + fmt.date(form.when.date);

        const save = () => {
            const next = {};

            if (!form.title.trim()) { next.title = t('title_required'); }
            if (form.status === 'waiting' && !waitingValid(form.waiting)) { next.waiting = t('waiting_required'); }

            setErrors(next);

            if (Object.keys(next).length) { return; }

            const payload = {
                title: form.title.trim(),
                ...whenPayload(form.when),
                work_hours: form.hours === '' ? null : Number(String(form.hours).replace(',', '.')),
                project_id: form.project_id || null,
                category_code: form.category || null,
                status: form.status,
                is_critical: form.critical,
                due_on: form.due || null,
                ...waitingPayload(form.waiting),
                ...requesterPayload(form.requester),
                note: form.note || null,
                parent_id: form.parent_id || null,
            };

            // Otomatik kartin bagli kaydi kilitlidir (kaynak hareketin kaydi).
            if (!linkLocked) {
                payload.link_kind = form.link.kind && form.link.id ? form.link.kind : null;
                payload.link_id = form.link.kind && form.link.id ? form.link.id : null;
            }

            if (scope !== 'mine') {
                payload.personnel_id = form.personnel_id || me.id;
                payload.org_unit_id = form.unit_id || null;
            }

            if (editing) {
                payload.row_version = card.row_version;
            }

            setSaving(true);
            const request = editing ? api.post(app.url(config.endpoints.update, card.id), payload) : api.post(config.endpoints.store, payload);

            request.then((json) => {
                onSaved(json.card, !editing);
                onClose();
            }).catch((error) => {
                if (error && error.payload && error.payload.errors) {
                    const fieldErrors = {};
                    Object.keys(error.payload.errors).forEach((key) => { fieldErrors[key] = error.payload.errors[key][0]; });
                    setErrors(fieldErrors);
                }

                app.fail(error);
            }).finally(() => setSaving(false));
        };

        return h(Modal, {
            title: editing ? t('edit_item') : t('new_item'),
            sub: t('modal_sub', { type: scopeLabel, date: subDate }),
            onClose,
            footer: [
                h(Btn, { key: 'c', onClick: onClose }, t('cancel')),
                h(Btn, { key: 's', variant: 'primary', disabled: saving, onClick: save }, t('save')),
            ],
        }, h('div', { className: 'kw-form' },
            h(Field, { label: t('field_title'), full: true, error: errors.title },
                h(Input, { value: form.title, 'data-autofocus': true, invalid: !!errors.title, onChange: (value) => set({ title: value }), onKeyDown: (event) => { if (event.key === 'Enter') { save(); } } })),
            scope !== 'mine' ? h(Field, { label: t('field_personnel') },
                h(SearchSelect, {
                    value: form.personnel_id,
                    options: (config.options.people || []).map((person) => ({ value: person.value, label: person.label })),
                    onChange: (id) => set({ personnel_id: id, category: null, parent_id: null }),
                })) : null,
            scope !== 'mine' ? h(Field, { label: t('field_unit'), help: t('field_unit_help') },
                h(Select, { value: form.unit_id, options: config.options.units || [], placeholder: '–', onChange: (value) => set({ unit_id: value || null, category: null }) })) : null,
            h(Field, { label: t('field_date'), size: 'md', help: t('field_date_help') },
                h(WhenPicker, { when: form.when, today: config.today, variant: 'input', onChange: (when) => set({ when }) })),
            h(Field, { label: t('field_due'), size: 'md' },
                h('input', { type: 'date', className: 'kw-input', value: form.due || '', onChange: (event) => set({ due: event.target.value }) })),
            h(Field, { label: t('field_hours'), size: 'md' },
                h(Input, { type: 'number', min: 0, max: 999, step: 0.25, placeholder: '0,0', value: form.hours, onChange: (value) => set({ hours: value }) })),
            h(Field, { label: t('field_project') },
                h(SearchSelect, { value: form.project_id, options: projects, placeholder: '–', clearable: true, searchPlaceholder: t('search_project'), onChange: (id) => set({ project_id: id }) })),
            h(Field, { label: t('field_category'), help: unitName ? t('field_category_help', { unit: unitName }) : null },
                h(Select, { value: form.category, options: categories, placeholder: '–', onChange: (value) => set({ category: value || null }) })),
            h(Field, { label: t('field_parent'), help: t('field_parent_help') },
                h(SearchSelect, { value: form.parent_id, options: parents, placeholder: '–', clearable: true, onChange: (id) => set({ parent_id: id }) })),
            h(Field, { label: t('field_requester'), help: t('field_requester_help') },
                h(RequesterPicker, { value: form.requester, onChange: (requester) => set({ requester }) })),
            h(Field, { label: t('field_status'), size: 'lg' },
                h(Seg, { items: config.options.statuses || [], value: form.status, label: t('field_status'), onChange: (value) => set({ status: value }) })),
            h(Field, { label: t('field_critical'), size: 'sm' },
                h(Toggle, { checked: form.critical, label: form.critical ? t('yes') : t('no'), onChange: (value) => set({ critical: value }) })),
            h(Field, { label: t('field_waiting'), full: true, help: t('field_waiting_help'), error: errors.waiting },
                h(WaitingPicker, { value: form.waiting, invalid: !!errors.waiting, onChange: (waiting) => set({ waiting }) })),
            h(Field, { label: t('field_link'), full: true },
                h(LinkPicker, { value: form.link, locked: linkLocked, onChange: (link) => set({ link }) })),
            h(Field, { label: t('field_note'), full: true },
                h(Input, { value: form.note, placeholder: t('optional'), onChange: (value) => set({ note: value }) })),
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Gunu kapat / Haftayi kapat                                           */
    /* ------------------------------------------------------------------ */

    function rowMeta(card, t, withDue, hideProject) {
        if (card.status === 'waiting' && card.waiting && card.waiting.label) {
            return card.waiting.label + ' · ' + t('days_value', { n: card.waiting.days || 0 });
        }

        if (card.status === 'blocked' && card.note) {
            return card.note;
        }

        const parts = [];

        if (card.project && !hideProject) { parts.push(card.project.name); }
        if (card.source === 'automatic') { parts.push(t('row_auto')); }
        if (card.hours) { parts.push(t('hours_value', { h: fmt.hours(card.hours) })); }
        if (withDue && card.status === 'planned' && card.due_on) { parts.push(fmt.dm(card.due_on)); }
        if (card.is_critical) { parts.push(t('critical_tag')); }

        if (!parts.length && card.link) {
            return card.link.no || card.link.label;
        }

        return parts.join(' · ');
    }

    function CardRows(props) {
        const { cards, withDue, hideProject } = props;
        const { t } = useApp();

        return h('div', { className: 'kw-rows' }, cards.map((card) => h('div', { key: card.id, className: 'kw-row' },
            h('i', { className: 'kw-dot-' + card.status }),
            h('span', null, card.title),
            h('span', { className: 'kw-m' }, rowMeta(card, t, withDue, hideProject)),
        )));
    }

    function PlanList(props) {
        const { planned, excluded, setExcluded, extra, setExtra, placeholder } = props;
        const { t } = useApp();

        return h('ul', { className: 'kw-plan' },
            planned.filter((card) => excluded.indexOf(card.id) === -1).map((card) => h('li', { key: card.id },
                h('i', { className: 'kw-dot-planned' }),
                h('span', null, card.title),
                h('span', { className: 'kw-m' }, [card.project ? card.project.name : null, card.is_critical ? t('critical_tag') : null].filter(Boolean).join(' · ')),
                h('button', { type: 'button', className: 'kw-remove', 'aria-label': t('clear'), onClick: () => setExcluded(excluded.concat([card.id])) }, '×'),
            )),
            extra.map((line, index) => h('li', { key: 'x' + index },
                h('i', { className: 'kw-dot-planned' }),
                h('input', { value: line, placeholder, autoFocus: line === '', onChange: (event) => setExtra(extra.map((value, i) => (i === index ? event.target.value : value))) }),
                h('button', { type: 'button', className: 'kw-remove', 'aria-label': t('clear'), onClick: () => setExtra(extra.filter((_, i) => i !== index)) }, '×'),
            )),
            h('li', { className: 'add', role: 'button', tabIndex: 0, onClick: () => setExtra(extra.concat([''])), onKeyDown: (event) => { if (event.key === 'Enter') { setExtra(extra.concat([''])); } } }, t('add_row')),
        );
    }

    function planText(planned, excluded) {
        return planned.filter((card) => excluded.indexOf(card.id) === -1).map((card) => card.title + (card.project ? ' — ' + card.project.name : ''));
    }

    function DayModal(props) {
        const { onClose, onDone } = props;
        const app = useApp();
        const { t, config, api } = app;
        const preview = KW.useResource(() => api.get(config.endpoints.day, { date: config.today }), []);
        const [summary, setSummary] = useState(null);
        const [blockers, setBlockers] = useState(null);
        const [excluded, setExcluded] = useState([]);
        const [extra, setExtra] = useState([]);
        const [busy, setBusy] = useState(false);
        const data = preview.data;
        const closed = data && data.report && !data.report.editable;

        const send = (submit) => {
            setBusy(true);
            api.post(config.endpoints.day_close, {
                date: data.date,
                summary: summary !== null ? summary : ((data.draft && data.draft.summary) || ''),
                blockers: blockers !== null ? blockers : ((data.draft && data.draft.blockers) || data.blockers || ''),
                plan: planText(data.plan, excluded),
                extra_plan: extra.filter((line) => line.trim() !== ''),
                submit,
            }).then((json) => {
                onDone(json.report, submit ? t('day_sent', { no: json.report.no }) : t('draft_saved', { no: json.report.no }));
                onClose();
            }).catch(app.fail).finally(() => setBusy(false));
        };

        return h(Modal, {
            title: t('day_title'),
            sub: data ? data.label : '',
            size: 'wide',
            onClose,
            footer: data ? [
                h(Btn, { key: 'c', onClick: onClose }, t('cancel')),
                h(Btn, { key: 'd', disabled: busy || closed, onClick: () => send(false) }, t('save_draft')),
                h(Btn, { key: 's', variant: 'primary', disabled: busy || closed, onClick: () => send(true) }, t('close_day_submit')),
            ] : null,
        }, !data ? h(State, { loading: preview.loading, error: preview.error, onRetry: preview.reload }) : h(Fragment, null,
            h('div', { className: 'kw-kpis' },
                h('span', null, h('b', null, data.kpis.done), t('kpi_done')),
                h('span', null, h('b', null, data.kpis.in_progress), t('kpi_progress')),
                h('span', null, h('b', null, data.kpis.waiting), t('kpi_waiting')),
                h('span', null, h('b', null, data.kpis.blocked), t('kpi_blocked')),
                h('span', null, h('b', null, fmt.hours(data.kpis.hours) || '0,0'), t('kpi_hours')),
            ),
            closed ? h('p', { className: 'kw-info' }, t('day_closed_info', { no: data.report.no, status: data.report.status_label })) : null,
            h('div', { className: 'kw-group' },
                h('h5', null, t('day_cards'), ' ', h('span', null, t('cards_n', { n: data.cards.length }))),
                h(CardRows, { cards: data.cards }),
            ),
            h('div', { className: 'kw-form' },
                h(Field, { label: t('summary'), full: true },
                    h(TextArea, { value: summary !== null ? summary : ((data.draft && data.draft.summary) || ''), placeholder: t('summary_help'), onChange: setSummary })),
                h(Field, { label: t('blockers'), full: true, help: t('blockers_help') },
                    h(TextArea, { value: blockers !== null ? blockers : ((data.draft && data.draft.blockers) || data.blockers || ''), onChange: setBlockers })),
                h(Field, { label: t('tomorrow_plan'), full: true, help: t('plan_help') },
                    h(PlanList, { planned: data.plan, excluded, setExcluded, extra, setExtra, placeholder: t('plan_line') })),
            ),
            h('p', { className: 'kw-info' }, t('carry_info', { n: data.carry })),
        ));
    }

    function WeekModal(props) {
        const { onClose, onDone } = props;
        const app = useApp();
        const { t, config, api } = app;
        const preview = KW.useResource(() => api.get(config.endpoints.week, { date: config.today }), []);
        const [groupBy, setGroupBy] = useState('project');
        const [summary, setSummary] = useState(null);
        const [achievements, setAchievements] = useState(null);
        const [blockers, setBlockers] = useState(null);
        const [excluded, setExcluded] = useState([]);
        const [extra, setExtra] = useState([]);
        const [busy, setBusy] = useState(false);
        const [invalid, setInvalid] = useState(false);
        const data = preview.data;
        const closed = data && data.report && !data.report.editable;
        const value = (local, key, fallback) => (local !== null ? local : ((data && data.draft && data.draft[key]) || fallback || ''));

        const groups = useMemo(() => {
            if (!data) { return []; }
            const map = new Map();

            data.cards.forEach((card) => {
                const key = groupBy === 'project' ? (card.project ? card.project.name : t('no_project')) : (card.category_label || t('no_category'));
                if (!map.has(key)) { map.set(key, []); }
                map.get(key).push(card);
            });

            const noneKey = groupBy === 'project' ? t('no_project') : t('no_category');

            return Array.from(map.entries()).sort((a, b) => {
                if (a[0] === noneKey) { return 1; }
                if (b[0] === noneKey) { return -1; }
                return b[1].length - a[1].length;
            });
        }, [data, groupBy]);

        const send = (submit) => {
            const text = value(summary, 'summary', '');

            if (submit && !text.trim()) {
                setInvalid(true);
                return;
            }

            setBusy(true);
            api.post(config.endpoints.week_close, {
                date: data.start,
                summary: text,
                achievements: value(achievements, 'achievements', ''),
                blockers: value(blockers, 'blockers', data.blockers),
                plan: planText(data.plan, excluded),
                extra_plan: extra.filter((line) => line.trim() !== ''),
                submit,
            }).then((json) => {
                onDone(json.report, submit ? t('week_sent', { no: json.report.no }) : t('draft_saved', { no: json.report.no }));
                onClose();
            }).catch(app.fail).finally(() => setBusy(false));
        };

        return h(Modal, {
            title: t('week_title'),
            sub: data ? t('week_sub', { label: data.label, week: data.week }) : '',
            size: 'wide',
            onClose,
            footer: data ? [
                h(Btn, { key: 'c', onClick: onClose }, t('cancel')),
                h(Btn, { key: 'd', disabled: busy || closed, onClick: () => send(false) }, t('save_draft')),
                h(Btn, { key: 's', variant: 'primary', disabled: busy || closed, onClick: () => send(true) }, t('close_week_submit')),
            ] : null,
        }, !data ? h(State, { loading: preview.loading, error: preview.error, onRetry: preview.reload }) : h(Fragment, null,
            h('div', { className: 'kw-kpis' },
                h('span', null, h('b', null, data.kpis.done), t('kpi_week_done')),
                h('span', null, h('b', null, data.kpis.open), t('kpi_week_open')),
                h('span', null, h('b', null, data.kpis.waiting), t('kpi_week_waiting')),
                h('span', null, h('b', null, fmt.hours(data.kpis.hours) || '0,0'), t('kpi_hours')),
                h('span', null, h('b', null, data.kpis.closed_days + ' / ' + data.kpis.workdays), t('kpi_days_closed')),
            ),
            closed ? h('p', { className: 'kw-info' }, t('week_closed_info', { no: data.report.no, status: data.report.status_label })) : null,
            h(Seg, { items: [{ value: 'project', label: t('by_project') }, { value: 'category', label: t('by_category') }], value: groupBy, onChange: setGroupBy }),
            h('div', { className: 'kw-groups' }, groups.map((entry) => h('div', { key: entry[0], className: 'kw-group' },
                h('h5', null, entry[0], ' ', h('span', null, t('cards_n', { n: entry[1].length }))),
                h(CardRows, { cards: entry[1], withDue: true, hideProject: groupBy === 'project' }),
            ))),
            h('div', { className: 'kw-form' },
                h(Field, { label: t('week_summary'), full: true, error: invalid ? t('summary_required') : null },
                    h(TextArea, { value: value(summary, 'summary', ''), onChange: (text) => { setSummary(text); setInvalid(false); } })),
                h(Field, { label: t('achievements') },
                    h(TextArea, { value: value(achievements, 'achievements', ''), placeholder: t('optional'), onChange: setAchievements })),
                h(Field, { label: t('blockers') },
                    h(TextArea, { value: value(blockers, 'blockers', data.blockers), onChange: setBlockers })),
                h(Field, { label: t('next_week_plan'), full: true },
                    h(PlanList, { planned: data.plan, excluded, setExcluded, extra, setExtra, placeholder: t('next_week_line') })),
            ),
            h('p', { className: 'kw-info' }, data.reviewer ? t('reviewer_info', { name: data.reviewer }) : t('reviewer_none')),
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Kart yap / Panoyu dondur                                             */
    /* ------------------------------------------------------------------ */

    function MakeCardModal(props) {
        const { suggestion, onClose, onCreated } = props;
        const app = useApp();
        const { t, config, api } = app;
        const d = suggestion.defaults || {};
        const me = config.me || {};
        const [title, setTitle] = useState(d.title || '');
        const [project, setProject] = useState(d.project_id || null);
        const [category, setCategory] = useState(d.category || null);
        const [status, setStatus] = useState(d.status || 'done');
        const [hours, setHours] = useState('');
        const [busy, setBusy] = useState(false);
        const statuses = (config.options.statuses || []).filter((row) => ['planned', 'in_progress', 'done'].indexOf(row.value) !== -1);

        const create = () => {
            setBusy(true);
            api.post(app.url(config.endpoints.card, suggestion.id), {
                title,
                project_id: project || null,
                category_code: category || null,
                status,
                work_hours: hours === '' ? null : Number(String(hours).replace(',', '.')),
            }).then((json) => {
                onCreated(json.card, suggestion);
                onClose();
            }).catch(app.fail).finally(() => setBusy(false));
        };

        return h(Modal, {
            title: t('make_card_title'),
            sub: t('make_card_sub', { label: suggestion.label, subject: suggestion.subject, time: suggestion.time || '' }),
            size: 'small',
            onClose,
            footer: [
                h(Btn, { key: 'c', onClick: onClose }, t('cancel')),
                h(Btn, { key: 's', variant: 'primary', disabled: busy || !title.trim(), onClick: create }, t('create_card')),
            ],
        }, h('div', { className: 'kw-form' },
            h(Field, { label: t('field_title'), full: true }, h(Input, { value: title, 'data-autofocus': true, onChange: setTitle })),
            h(Field, { label: t('field_project'), full: true },
                h(SearchSelect, { value: project, options: config.options.projects || [], clearable: true, placeholder: t('project_none_link'), searchPlaceholder: t('search_project'), onChange: setProject })),
            h(Field, { label: t('field_category'), full: true },
                h(Select, { value: category, options: categoryOptionsFor(config, me.unit, t), placeholder: '–', onChange: (value) => setCategory(value || null) })),
            h(Field, { label: t('field_status'), full: true },
                h(Seg, { items: statuses, value: status, onChange: setStatus })),
            h(Field, { label: t('field_link'), full: true },
                h('div', { className: 'kw-input lock' }, d.link ? h(Fragment, null, h('span', { className: 'kw-mono' }, d.link.no || ''), ' ', d.link.label || d.link.kind_label, ' · ', t('locked')) : '–')),
            h(Field, { label: t('field_source') }, h('div', { className: 'kw-input lock' }, d.source_label || '')),
            h(Field, { label: t('field_hours') }, h(Input, { type: 'number', min: 0, max: 999, step: 0.25, placeholder: '0,0', value: hours, onChange: setHours })),
        ));
    }

    function FreezeModal(props) {
        const { cards, onClose, onDone } = props;
        const app = useApp();
        const { t, config, api } = app;
        const [summary, setSummary] = useState('');
        const [busy, setBusy] = useState(false);

        const freeze = () => {
            setBusy(true);
            api.post(config.endpoints.freeze, { ids: cards.map((card) => card.id), summary }).then((json) => {
                onDone(json.report, t('frozen', { no: json.report.no }));
                onClose();
            }).catch(app.fail).finally(() => setBusy(false));
        };

        return h(Modal, {
            title: t('freeze_title'),
            size: 'small',
            onClose,
            footer: [
                h(Btn, { key: 'c', onClick: onClose }, t('cancel')),
                h(Btn, { key: 's', variant: 'primary', disabled: busy || !cards.length, onClick: freeze }, t('freeze_submit')),
            ],
        }, h('p', { className: 'kw-info' }, t('freeze_text', { n: cards.length })),
        h(Field, { label: t('summary'), help: t('summary_help') }, h(TextArea, { value: summary, onChange: setSummary })));
    }

    /* ------------------------------------------------------------------ */
    /* Oneriler                                                             */
    /* ------------------------------------------------------------------ */

    function Tray(props) {
        const { suggestions, gone, onMake, onDismiss, dismissedMode, dismissed, onRestore, open, onToggle } = props;
        const { t } = useApp();
        const list = dismissedMode ? dismissed : suggestions;
        const visible = list.filter((row) => dismissedMode || !gone[row.id] || gone[row.id] === 'fading');
        const count = dismissedMode ? visible.length : visible.filter((row) => !gone[row.id]).length;

        if (!dismissedMode && !visible.length) { return null; }

        return h('div', { className: cx('kw-tray', { closed: !open }), 'aria-label': t('tray_title') },
            h('h3', null, h('button', {
                type: 'button',
                className: 'kw-tray-toggle',
                'aria-expanded': String(!!open),
                onClick: onToggle,
            }, h('span', { className: 'kw-caret', 'aria-hidden': 'true' }, open ? '▾' : '▸'), dismissedMode ? t('dismissed_title') : t('tray_title'), ' ', h('span', { className: 'kw-count' }, count))),
            open && dismissedMode ? h('p', { className: 'kw-tray-help' }, t('dismissed_help')) : null,
            open && dismissedMode && !visible.length ? h('p', { className: 'kw-tray-help' }, t('dismissed_empty')) : null,
            open ? visible.map((row) => {
                const isGone = !dismissedMode && gone[row.id];

                return h('div', { key: row.id, className: cx('kw-suggest', { gone: isGone }) },
                    h('span', null,
                        row.label, ' · ',
                        h('b', { className: row.mono ? 'kw-mono' : null }, row.subject),
                        ' ', h('span', { className: 'kw-src' }, '· ' + [row.time, row.module].filter(Boolean).join(' · ')),
                    ),
                    isGone
                        ? h(Tag, null, t('dismissed_tag'))
                        : h('span', { className: 'kw-btns' },
                            dismissedMode
                                ? h(Btn, { onClick: () => onRestore(row) }, t('undo'))
                                : h(Fragment, null,
                                    h(Btn, { variant: 'primary', onClick: () => onMake(row) }, t('make_card')),
                                    h(Btn, { onClick: () => onDismiss(row) }, t('dismiss')),
                                ),
                        ),
                );
            }) : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Hizli satir                                                          */
    /* ------------------------------------------------------------------ */

    function QuickRow(props) {
        const { onAdd, defaultProject, busy } = props;
        const app = useApp();
        const { t, config } = app;
        const me = config.me || {};
        const [title, setTitle] = useState('');
        const [project, setProject] = useState(defaultProject || null);
        const [category, setCategory] = useState(null);
        const [when, setWhen] = useState({ mode: 'now' });
        const [requester, setRequester] = useState(null);
        const categories = categoryOptionsFor(config, me.unit, t);

        useEffect(() => { setProject(defaultProject || null); }, [defaultProject]);

        const submit = (event) => {
            if (event) { event.preventDefault(); }

            if (!title.trim()) {
                app.toast({ text: t('title_required'), tone: 'bad' });
                return;
            }

            onAdd({ title: title.trim(), project_id: project || null, category_code: category || null, status: 'planned', ...whenPayload(when), ...requesterPayload(requester) }).then((ok) => {
                if (ok) {
                    setTitle('');
                    setWhen({ mode: 'now' });
                }
            });
        };

        return h('form', { className: 'kw-quick', 'aria-label': t('quick_label'), onSubmit: submit },
            h('input', { className: 'kw-field', value: title, placeholder: t('quick_title'), 'aria-label': t('field_title'), onChange: (event) => setTitle(event.target.value) }),
            h(SearchSelect, { variant: 'field', value: project, options: config.options.projects || [], clearable: true, placeholder: t('quick_project'), searchPlaceholder: t('search_project'), onChange: setProject }),
            h(SearchSelect, { variant: 'field', value: category, options: categories, clearable: true, placeholder: t('quick_category'), onChange: setCategory }),
            h(RequesterPicker, { value: requester, variant: 'field', onChange: setRequester }),
            h(WhenPicker, { when, today: config.today, variant: 'field', onChange: setWhen }),
            h(Btn, { variant: 'primary', type: 'submit', disabled: busy }, t('add')),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Pano                                                                 */
    /* ------------------------------------------------------------------ */

    function Board() {
        const app = useApp();
        const { config, t, api } = app;
        const can = config.can || {};
        const me = config.me || {};
        const allowed = SCOPES.filter((scope) => scope === 'mine' || (scope === 'team' && can.team) || (scope === 'all' && can.all));
        const urlScope = new URLSearchParams(window.location.search).get('kapsam');
        const [scope, setScopeState] = useState(() => {
            const initial = urlScope ? config.scope : (storage.get('kw_board_scope', null) || config.scope);
            return allowed.indexOf(initial) !== -1 ? initial : 'mine';
        });
        const [filters, setFiltersState] = useState(() => ({
            ...defaultFilters(scope),
            ...(storage.get('kw_board_filters', null) || {}),
            ...(config.project ? { projects: [config.project] } : {}),
        }));
        const [data, setData] = useState({ cards: [], suggestions: [], limited: false });
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);
        const [newIds, setNewIds] = useState([]);
        const [modal, setModal] = useState(null);
        const [gone, setGone] = useState({});
        const [dismissed, setDismissed] = useState([]);
        const [dismissedMode, setDismissedMode] = useState(false);
        const [trayOpen, setTrayOpenState] = useState(() => storage.get('kw_tray_open', true) !== false);
        const [drag, setDrag] = useState(null);
        const [dropAt, setDropAt] = useState(null);
        const [busy, setBusy] = useState(false);
        const loadTicket = useRef(0);

        const setScope = (next) => {
            setScopeState(next);
            storage.set('kw_board_scope', next);
            setFilters({ group: GROUP_DEFAULT[next] || 'none', ...(next === 'mine' ? { personnel: [] } : {}) });

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('kapsam', next);
                window.history.replaceState(window.history.state, '', url.toString());
            } catch (ignored) {
                // Adres guncellenemezse kapsam yine de degisir.
            }
        };

        const setTrayOpen = (next) => {
            setTrayOpenState(next);
            storage.set('kw_tray_open', next);
        };

        const setFilters = (patch) => setFiltersState((previous) => {
            const next = { ...previous, ...patch };
            storage.set('kw_board_filters', next);
            return next;
        });

        const load = useCallback(() => {
            loadTicket.current += 1;
            const ticket = loadTicket.current;
            setLoading(true);
            setError(null);

            api.get(config.endpoints.board, {
                scope,
                range: filters.range,
                from: filters.range === 'range' ? filters.from : null,
                to: filters.range === 'range' ? filters.to : null,
                projects: filters.projects.filter((id) => id !== NONE).join(','),
                units: filters.units.filter((id) => id !== NONE).join(','),
            }).then((json) => {
                if (ticket !== loadTicket.current) { return; }
                setData({ cards: json.cards || [], suggestions: json.suggestions || [], limited: !!json.limited });
            }).catch((err) => {
                if (ticket !== loadTicket.current) { return; }
                setError(err);
            }).finally(() => {
                if (ticket === loadTicket.current) { setLoading(false); }
            });
        }, [scope, filters.range, filters.from, filters.to, filters.projects, filters.units]);

        useEffect(() => { load(); }, [load]);

        useEffect(() => {
            if (scope !== 'mine' || !dismissedMode) { return; }
            api.get(config.endpoints.dismissed).then((json) => setDismissed(json.suggestions || [])).catch(app.fail);
        }, [scope, dismissedMode]);

        const upsert = useCallback((card, isNew) => {
            setData((previous) => {
                const exists = previous.cards.some((row) => row.id === card.id);
                const cards = exists ? previous.cards.map((row) => (row.id === card.id ? card : row)) : [card].concat(previous.cards);
                return { ...previous, cards };
            });

            if (isNew) {
                setNewIds((list) => list.concat([card.id]));
                window.setTimeout(() => setNewIds((list) => list.filter((id) => id !== card.id)), 8000);
            }
        }, []);

        const removeCard = (id) => setData((previous) => ({ ...previous, cards: previous.cards.filter((row) => row.id !== id) }));

        const visibleCards = useMemo(() => data.cards.filter((card) => matches(card, filters, null)), [data.cards, filters]);

        /* ---- islemler ---- */

        const addQuick = (payload) => {
            setBusy(true);

            return api.post(config.endpoints.store, payload).then((json) => {
                upsert(json.card, true);
                app.toast({ text: t('created'), tone: 'ok', action: { label: t('open'), onClick: () => setModal({ kind: 'item', card: json.card }) } });
                return true;
            }).catch((err) => {
                app.fail(err);
                return false;
            }).finally(() => setBusy(false));
        };

        const reorder = (status, ids, moved) => {
            setData((previous) => ({
                ...previous,
                cards: previous.cards.map((card) => {
                    const index = ids.indexOf(card.id);
                    return index === -1 ? card : { ...card, sort_order: index + 1 };
                }),
            }));

            return api.post(config.endpoints.reorder, { status, ids, moved }).catch(app.fail);
        };

        const applyStatus = (card, status, extra, placement) => {
            const previous = card;
            setData((state) => ({ ...state, cards: state.cards.map((row) => (row.id === card.id ? { ...row, status, sort_order: 0 } : row)) }));

            return api.post(app.url(config.endpoints.status, card.id), { status, ...(extra || {}) }).then((json) => {
                upsert(json.card, false);

                if (placement) {
                    const ids = placement.ids.slice();
                    ids.splice(placement.index, 0, card.id);
                    reorder(status, ids, card.id);
                }

                const label = ((config.options.statuses || []).find((row) => row.value === status) || {}).label || status;
                app.toast({
                    text: t('status_changed', { status: label }),
                    tone: 'ok',
                    action: {
                        label: t('undo'),
                        onClick: () => {
                            const back = json.previous || { status: previous.status };
                            api.post(app.url(config.endpoints.status, card.id), back).then((reverted) => {
                                upsert(reverted.card, false);
                                app.toast({ text: t('undone') });
                            }).catch(app.fail);
                        },
                    },
                });
            }).catch((err) => {
                upsert(previous, false);
                app.fail(err);
            });
        };

        const requestStatus = (card, status, placement) => {
            if (status === 'waiting' || status === 'done') {
                setModal({ kind: 'prompt', prompt: status, card, placement });
                return;
            }

            applyStatus(card, status, null, placement);
        };

        const toggleCritical = (card) => api.post(app.url(config.endpoints.critical, card.id), { critical: !card.is_critical })
            .then((json) => upsert(json.card, false))
            .catch(app.fail);

        const deleteCard = (card) => app.confirm({ title: t('menu_delete'), text: t('delete_confirm'), confirmLabel: t('menu_delete') }).then((yes) => {
            if (!yes) { return; }

            api.post(app.url(config.endpoints.delete, card.id), {}).then(() => {
                removeCard(card.id);
                app.toast({ text: t('deleted'), tone: 'ok' });
            }).catch(app.fail);
        });

        const dismiss = (row) => {
            api.post(app.url(config.endpoints.dismiss, row.id), {}).then(() => {
                setGone((state) => ({ ...state, [row.id]: 'fading' }));
                app.toast({
                    text: t('dismissed_toast'),
                    countdown: true,
                    duration: 10000,
                    action: {
                        label: t('undo'),
                        onClick: () => api.post(app.url(config.endpoints.restore, row.id), {}).then(() => {
                            setGone((state) => { const next = { ...state }; delete next[row.id]; return next; });
                            app.toast({ text: t('restored'), tone: 'ok' });
                        }).catch(app.fail),
                    },
                    onExpire: () => setGone((state) => (state[row.id] === 'fading' ? { ...state, [row.id]: 'gone' } : state)),
                });
            }).catch(app.fail);
        };

        const restore = (row) => api.post(app.url(config.endpoints.restore, row.id), {}).then(() => {
            setDismissed((list) => list.filter((item) => item.id !== row.id));
            setGone((state) => { const next = { ...state }; delete next[row.id]; return next; });
            app.toast({ text: t('restored'), tone: 'ok' });
            load();
        }).catch(app.fail);

        const onCreatedFromSuggestion = (card, suggestion) => {
            upsert(card, true);
            setData((previous) => ({ ...previous, suggestions: previous.suggestions.filter((row) => row.id !== suggestion.id) }));
            app.toast({ text: t('created'), tone: 'ok', action: { label: t('open'), onClick: () => setModal({ kind: 'item', card }) } });
        };

        const onReport = (report, text) => {
            app.toast({ text, tone: 'ok', duration: 8000, action: report && report.url ? { label: t('view'), onClick: () => window.location.assign(report.url) } : null });
            load();
        };

        const handlers = {
            onOpen: (card) => { if (card.can && card.can.update) { setModal({ kind: 'item', card }); } else if (card.url) { window.location.assign(card.url); } },
            onStatus: (card, status) => requestStatus(card, status, null),
            onCritical: toggleCritical,
            onDelete: deleteCard,
        };

        /* ---- surukle-birak ---- */

        const dragStart = (event, card) => {
            setDrag({ id: card.id, from: card.status });
            event.dataTransfer.effectAllowed = 'move';

            try {
                event.dataTransfer.setData('text/plain', String(card.id));
                const node = event.currentTarget;
                const rect = node.getBoundingClientRect();
                const clone = node.cloneNode(true);
                clone.classList.add('dragging');
                clone.style.position = 'absolute';
                clone.style.top = '-2000px';
                clone.style.left = '-2000px';
                clone.style.width = rect.width + 'px';
                KW.portalRoot().appendChild(clone);
                event.dataTransfer.setDragImage(clone, Math.min(40, rect.width / 2), 20);
                window.setTimeout(() => clone.remove(), 0);
            } catch (ignored) {
                // Tarayici surukleme gorselini desteklemiyorsa varsayilan kullanilir.
            }
        };

        const dragEnd = () => {
            setDrag(null);
            setDropAt(null);
        };

        const onDrop = (status, index, otherIds) => {
            const card = data.cards.find((row) => row.id === (drag && drag.id));
            setDrag(null);
            setDropAt(null);

            if (!card) { return; }

            if (card.status === status) {
                const ids = otherIds.slice();
                ids.splice(index, 0, card.id);
                reorder(status, ids, card.id);
                return;
            }

            requestStatus(card, status, { index, ids: otherIds });
        };

        /* ---- suzgec secenekleri ---- */

        const statusOptions = config.options.statuses || [];
        const projectCounts = countBy(data.cards, filters, 'projects', (card) => (card.project ? card.project.id : NONE));
        const projectNames = {};
        data.cards.forEach((card) => { if (card.project) { projectNames[card.project.id] = card.project.name; } });
        // Secenekler butun projelerden gelir (kartsiz proje de secilebilir);
        // sayilar yuklenen kartlardan okunur.
        const projectOptions = (config.options.projects || [])
            .map((row) => ({ value: row.value, label: row.label, count: projectCounts[row.value] || 0 }))
            .sort((a, b) => b.count - a.count || String(a.label).localeCompare(String(b.label), 'tr'));
        Object.keys(projectCounts)
            .filter((key) => key !== NONE && !projectOptions.some((row) => String(row.value) === String(key)))
            .forEach((key) => projectOptions.push({ value: Number(key), label: projectNames[key] || key, count: projectCounts[key] }));
        projectOptions.push({ value: NONE, label: t('no_project'), count: projectCounts[NONE] || 0 });
        // Tek proje secildiyse yeni kartin projesi hazir gelir (eski Proje panosu).
        const soleProject = filters.projects.length === 1 && filters.projects[0] !== NONE ? filters.projects[0] : null;

        const categoryCounts = countBy(data.cards, filters, 'categories', (card) => card.category || NONE);
        const unitsInCards = {};
        data.cards.forEach((card) => { if (card.unit) { unitsInCards[card.unit.id] = card.unit; } });
        if (me.unit && !unitsInCards[me.unit]) {
            const own = (config.options.units || []).find((row) => row.value === me.unit);
            if (own) { unitsInCards[me.unit] = { id: own.value, name: own.label, code: own.code }; }
        }
        // Kategori seti: departman secildiyse o birimler, yoksa kendi birimin
        // (kendi kartlarim) ya da kartlardaki butun birimler.
        const unitScope = filters.units.length
            ? filters.units.map((id) => unitsInCards[id] || (config.options.units || []).filter((row) => row.value === id).map((row) => ({ id: row.value, name: row.label, code: row.code }))[0]).filter(Boolean)
            : (scope === 'mine' ? [unitsInCards[me.unit]].filter(Boolean) : Object.keys(unitsInCards).map((id) => unitsInCards[id]));
        const sets = config.options.category_sets || {};
        const catLabels = config.options.category_labels || {};
        const categoryOptions = [];
        const seenCategory = {};
        unitScope.forEach((unitRow) => {
            const codes = sets[String(unitRow.code || '').toUpperCase()] || sets['*'] || [];
            categoryOptions.push({ sub: unitRow.name });
            codes.forEach((code) => {
                seenCategory[code] = true;
                categoryOptions.push({ key: unitRow.id + '-' + code, value: code, label: catLabels[code] || code, count: categoryCounts[code] || 0 });
            });
        });
        Object.keys(categoryCounts).filter((code) => code !== NONE && !seenCategory[code]).forEach((code) => categoryOptions.push({ value: code, label: catLabels[code] || code, count: categoryCounts[code] }));
        categoryOptions.push({ value: NONE, label: t('no_category'), count: categoryCounts[NONE] || 0 });

        const personCounts = countBy(data.cards, filters, 'personnel', (card) => (card.personnel ? card.personnel.id : NONE));
        const personNames = {};
        data.cards.forEach((card) => { if (card.personnel) { personNames[card.personnel.id] = card.personnel.name; } });
        const personOptions = Object.keys(personCounts).filter((key) => key !== NONE).map((key) => ({ value: Number(key), label: personNames[key] || key, count: personCounts[key] })).sort((a, b) => String(a.label).localeCompare(String(b.label), 'tr'));

        const unitCounts = countBy(data.cards, filters, 'units', (card) => (card.unit ? card.unit.id : NONE));
        const unitOptions = (config.options.units || [])
            .map((row) => ({ value: row.value, label: row.label, count: unitCounts[row.value] || 0 }))
            .sort((a, b) => String(a.label).localeCompare(String(b.label), 'tr'));

        const chipValue = (selected, options) => {
            if (!selected.length) { return t('all'); }
            if (selected.length === 1) { return (options.find((row) => row.value === selected[0]) || {}).label || t('selected', { n: 1 }); }
            return t('selected', { n: selected.length });
        };
        const rangeLabel = filters.range === 'range' && filters.from ? fmt.dm(filters.from) + ' – ' + fmt.date(filters.to || filters.from) : t('range_' + filters.range);
        const groupLabels = { none: t('group_none'), personnel: t('group_personnel'), project: t('group_project'), unit: t('group_unit'), category: t('group_category') };

        /* ---- cizim ---- */

        const lanes = useMemo(() => {
            if (!filters.group || filters.group === 'none') {
                return [{ key: '', cards: visibleCards }];
            }

            const map = new Map();
            visibleCards.forEach((card) => {
                const key = laneKey(card, filters.group, t);
                if (!map.has(key)) { map.set(key, []); }
                map.get(key).push(card);
            });

            return Array.from(map.entries()).sort((a, b) => String(a[0]).localeCompare(String(b[0]), 'tr')).map((entry) => ({ key: entry[0], cards: entry[1] }));
        }, [visibleCards, filters.group]);

        const renderBoard = (lane) => h('div', { key: 'board-' + lane.key, className: 'kw-board', 'aria-label': t('columns_label') },
            STATUSES.map((status) => h(Column, {
                key: status,
                status,
                lane: lane.key,
                label: ((statusOptions.find((row) => row.value === status)) || {}).label || status,
                cards: sortCards(lane.cards.filter((card) => card.status === status), scope),
                scope,
                drag,
                dropAt,
                setDropAt,
                onDrop,
                newIds,
                handlers,
                dragStart,
                dragEnd,
            })),
        );

        const actions = [h(Btn, { key: 'q', onClick: () => setModal({ kind: 'item', card: null }) }, t('quick_item'))];

        if (scope === 'mine') {
            actions.push(h(Btn, { key: 'x', onClick: () => setDismissedMode(!dismissedMode) }, dismissedMode ? t('back_to_suggestions') : t('source_dismissed')));
        }

        // Gunluk ve haftalik rapor kisinin kendi kartlarindan uretilir; kapsamdan bagimsizdir.
        actions.push(h(Btn, { key: 'd', onClick: () => setModal({ kind: 'day' }) }, t('close_day')));
        actions.push(h(Btn, { key: 'w', variant: 'primary', onClick: () => setModal({ kind: 'week' }) }, t('close_week')));

        if (scope === 'all') {
            actions.push(h(Btn, { key: 'f', onClick: () => setModal({ kind: 'freeze' }) }, t('freeze')));
        }

        const chips = [];

        // Kapsam: panonun kimin kartlarini gosterdigi (eski sekmelerin
        // karsiligi). Acilir kutu degil, secenekleri gorunen dugme grubu
        // (23 Eylul 2026 kullanici istegi).
        if (allowed.length > 1) {
            chips.push(h(Seg, {
                key: 'scope',
                label: t('filter_scope'),
                value: scope,
                items: allowed.map((value) => ({ value, label: t('scope_' + value) })),
                onChange: setScope,
            }));
        }

        chips.push(h(MultiFilter, { key: 'projects', label: t('filter_project'), options: projectOptions, selected: filters.projects, valueLabel: chipValue(filters.projects, projectOptions), searchable: true, searchPlaceholder: t('search_project'), hint: t('project_hint'), onApply: (next) => setFilters({ projects: next }) }));
        chips.push(h(MultiFilter, { key: 'units', label: t('filter_unit'), options: unitOptions, selected: filters.units, valueLabel: chipValue(filters.units, unitOptions), hint: t('unit_hint'), onApply: (next) => setFilters({ units: next, categories: [] }) }));

        chips.push(h(MultiFilter, { key: 'categories', label: t('filter_category'), options: categoryOptions, selected: filters.categories, valueLabel: chipValue(filters.categories, categoryOptions.filter((row) => !row.sub)), hint: t('category_hint'), onApply: (next) => setFilters({ categories: next }) }));
        chips.push(h(RadioFilter, {
            key: 'range',
            label: t('filter_date'),
            value: filters.range,
            valueLabel: rangeLabel,
            hint: t('date_hint'),
            options: RANGES.map((range) => ({ value: range, label: t('range_' + range) })),
            extra: {
                when: 'range',
                value: { from: filters.from || config.today, to: filters.to || config.today },
                render: (more, setMore) => h('div', { className: 'kw-range' },
                    h('input', { type: 'date', className: 'kw-input', value: more.from, onChange: (event) => setMore({ ...more, from: event.target.value }) }),
                    h('input', { type: 'date', className: 'kw-input', value: more.to, onChange: (event) => setMore({ ...more, to: event.target.value }) }),
                ),
            },
            onApply: (next, more) => setFilters(next === 'range' ? { range: next, from: more.from, to: more.to } : { range: next }),
        }));

        if (scope !== 'mine') {
            chips.push(h(MultiFilter, { key: 'personnel', label: t('filter_personnel'), options: personOptions, selected: filters.personnel, valueLabel: filters.personnel.length ? chipValue(filters.personnel, personOptions) : t('everyone'), searchable: true, searchPlaceholder: t('search_personnel'), onApply: (next) => setFilters({ personnel: next }) }));
        }

        chips.push(h(Chip, { key: 'critical', label: t('filter_critical'), check: true, on: filters.critical, onClick: () => setFilters({ critical: !filters.critical }) }));
        chips.push(h(Chip, { key: 'long', label: t('filter_long_wait'), check: true, on: filters.longWait, onClick: () => setFilters({ longWait: !filters.longWait }) }));
        chips.push(h(RadioFilter, {
            key: 'group',
            label: t('filter_group'),
            value: filters.group,
            valueLabel: groupLabels[filters.group] || groupLabels.none,
            options: ['none', 'personnel', 'project', 'unit', 'category'].map((value) => ({ value, label: groupLabels[value] })),
            onApply: (next) => setFilters({ group: next }),
        }));

        const reportsUrl = config.urls && config.urls.reports;

        return h('div', { className: 'kw-app' }, h('div', { className: 'kw-panel' },
            h('div', { className: 'kw-topbar' },
                h('div', null,
                    h('div', { className: 'kw-crumbs' }, reportsUrl ? h('a', { href: reportsUrl }, t('reports')) : t('reports'), ' › ', t('board_title')),
                    h('h2', null, t('board_title')),
                ),
                h('div', { className: 'kw-actions' }, actions),
            ),
            h('div', { className: 'kw-filters', 'aria-label': t('filters_label') }, chips),
            scope === 'mine' ? h(Tray, {
                suggestions: data.suggestions,
                gone,
                open: trayOpen,
                onToggle: () => setTrayOpen(!trayOpen),
                dismissedMode,
                dismissed,
                onMake: (row) => setModal({ kind: 'make', suggestion: row }),
                onDismiss: dismiss,
                onRestore: restore,
            }) : null,
            h(QuickRow, { onAdd: addQuick, busy, defaultProject: soleProject }),
            data.limited ? h('p', { className: 'kw-notice' }, t('limited', { n: 600 })) : null,
            error ? h(State, { error, onRetry: load }) : null,
            loading && !data.cards.length ? h(State, { loading: true }) : null,
            !error ? (lanes.length === 1 && !lanes[0].key
                ? renderBoard(lanes[0])
                : lanes.map((lane) => h('div', { key: 'lane-' + lane.key, className: 'kw-lane' },
                    h('div', { className: 'kw-lane-head' }, lane.key, h('span', null, t('cards_n', { n: lane.cards.length }))),
                    renderBoard(lane),
                ))) : null,
            modal && modal.kind === 'item' ? h(ItemModal, { card: modal.card, scope, cards: data.cards, defaultProject: soleProject, onClose: () => setModal(null), onSaved: (card, isNew) => { upsert(card, isNew); app.toast({ text: isNew ? t('created') : t('saved'), tone: 'ok' }); } }) : null,
            modal && modal.kind === 'day' ? h(DayModal, { onClose: () => setModal(null), onDone: onReport }) : null,
            modal && modal.kind === 'week' ? h(WeekModal, { onClose: () => setModal(null), onDone: onReport }) : null,
            modal && modal.kind === 'make' ? h(MakeCardModal, { suggestion: modal.suggestion, onClose: () => setModal(null), onCreated: onCreatedFromSuggestion }) : null,
            modal && modal.kind === 'freeze' ? h(FreezeModal, { cards: visibleCards, onClose: () => setModal(null), onDone: onReport }) : null,
            modal && modal.kind === 'prompt' ? h(PromptPop, {
                kind: modal.prompt,
                card: modal.card,
                onCancel: () => setModal(null),
                onConfirm: (extra) => {
                    const current = modal;
                    setModal(null);
                    applyStatus(current.card, current.prompt, extra, current.placement);
                },
            }) : null,
        ));
    }

    KW.mount('work-board', Board);
}());
