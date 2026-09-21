/*
 * Konelsis - GORUSME PLANI TAKVIMI (B34, D-109, 21 Eylul 2026).
 *
 * Sosyal medya cekirdegi (social-core.js, KS) ve ortak ay takvimi
 * (social-calendar.js, KS.calendar) uzerine kuruludur; izgara, cip, klavye
 * gezintisi, dar kap listesi ve stiller sosyal medya Plan gorunumuyle AYNIDIR
 * (kullanici karari: "kod tekrari yapmayalim"). Bu dosya yalniz gorusme
 * verisini cipe cevirir, personel suzgecini, lejanti ve gun sayfasini cizer.
 *
 * Kok: [data-ks-root="meetings"] (resources/views/filament/meetings/calendar.blade.php).
 * data-config: MeetingPlanAppConfig::make() -> endpoints['meetings.calendar'], labels,
 * today, month, create_url (__DATE__), can_create, can_filter, me, personnel.
 * Uc: GET meetings.calendar?month=YYYY-MM&personnel=ID -> { today, days: [{ date, items }], total }.
 * Cipe tiklaninca gorusme ayrintisi (Filament sayfasi) acilir; gune tiklaninca gun
 * sayfasi (Drawer) acilir, oradan o gune gorusme planlanir.
 */
(function () {
    'use strict';

    const KS = window.KonelsisSocial;

    if (!KS || !KS.calendar || !KS.root || KS.root.getAttribute('data-ks-root') !== 'meetings') {
        return;
    }

    const { h, t, api, url, fmt, cx } = KS;
    const { useState, useCallback, useMemo } = KS;
    const { Icon, Button, Badge, Drawer, Empty, Select } = KS;
    const { isYmd, isMonth, monthOf } = KS.calendar;

    const CHANNEL_ICONS = { visit: 'building', phone: 'phone', email: 'mail', message: 'comment', other: 'calendar' };
    const STATUS_LEGEND = [
        { key: 'planned', color: 'sky' },
        { key: 'overdue', color: 'rose' },
        { key: 'done', color: 'emerald' },
        { key: 'cancelled', color: 'stone' },
    ];

    const config = KS.config || {};
    const ALL = 'all';

    function openUrl(value) {
        const safe = KS.safeUrl(value);

        if (safe) {
            window.location.assign(safe);
        }
    }

    function personName(person) {
        return person && person.name ? person.name : t('meeting_no_personnel');
    }

    /** Takvim cipi: durum renginde, kanal simgesi + taraf adi; sonraki adim ok simgesiyle baslar. */
    function chipOf(item) {
        const people = [personName(item.personnel)].concat((item.participants || []).map(personName));

        return {
            key: item.id,
            color: item.color,
            icon: item.follow_up ? 'arrow-right' : (CHANNEL_ICONS[item.channel] || 'calendar'),
            title: item.party,
            check: item.status === 'done',
            label: [
                item.party,
                item.contact,
                people.join(', '),
                item.status_label,
                item.follow_up ? t('meeting_follow_up') : item.channel_label,
                item.subject,
            ].filter(Boolean).join(' · '),
        };
    }

    function dayLabel(cell, today) {
        const text = cell.items.length
            ? t('meeting_day_label', { date: fmt.dateLong(cell.date), count: fmt.number(cell.items.length) })
            : fmt.dateLong(cell.date);

        return cell.date === today ? t('today') + ', ' + text : text;
    }

    function Legend() {
        return h('div', { className: 'ks-planner-legend', role: 'group', 'aria-label': t('planner_legend') },
            STATUS_LEGEND.map((status) => h('span', { key: status.key, className: cx('ks-planner-legend__item', 'ks-c-' + status.color) },
                h('span', { className: 'ks-planner-legend__swatch', 'aria-hidden': 'true' }),
                t('meeting_status_' + status.key),
            )),
            h('span', { className: 'ks-planner-legend__sep', 'aria-hidden': 'true' }),
            h('span', { className: 'ks-planner-legend__item' },
                h(Icon, { name: 'arrow-right', className: 'ks-planner-legend__check' }),
                t('meeting_follow_up'),
            ),
        );
    }

    /** Gun sayfasi: o gunun gorusmeleri ve "Bu gune gorusme planla". */
    function DaySheet(props) {
        const cell = props.cell;
        const createUrl = config.create_url ? String(config.create_url).replace('__DATE__', cell.date) : null;

        return h(Drawer, {
            title: fmt.dateLong(cell.date),
            subtitle: t('meeting_day_count', { count: fmt.number(cell.items.length) }),
            icon: 'calendar',
            side: 'right',
            size: 'md',
            className: 'ks-planner-sheet',
            onClose: props.onClose,
        },
            h('div', { className: 'ks-planner-sheet__body' },
                h('section', { className: 'ks-planner-sheet__block' },
                    cell.items.length
                        ? h('ul', { className: 'ks-planner-items' },
                            cell.items.map((item) => h('li', { key: item.id },
                                h('button', { type: 'button', className: 'ks-planner-row', onClick: () => openUrl(item.url) },
                                    h('span', { className: 'ks-planner-row__thumb ks-planner-row__thumb--icon', 'aria-hidden': 'true' },
                                        h(Icon, { name: item.follow_up ? 'arrow-right' : (CHANNEL_ICONS[item.channel] || 'calendar') }),
                                    ),
                                    h('span', { className: 'ks-planner-row__text' },
                                        h('span', { className: 'ks-planner-row__title ks-clamp-2' }, item.party + (item.contact ? ' · ' + item.contact : '')),
                                        h('span', { className: 'ks-planner-row__meta' },
                                            h(Badge, { color: KS.paletteColor(item.color), size: 'sm' }, item.status_label),
                                            item.follow_up ? h(Badge, { color: 'amber', icon: 'arrow-right', size: 'sm' }, t('meeting_follow_up')) : h('span', null, item.channel_label),
                                            h('span', { className: 'ks-planner-row__date' },
                                                h(Icon, { name: 'user' }),
                                                h('span', null, [personName(item.personnel)].concat((item.participants || []).map(personName)).join(', ')),
                                            ),
                                        ),
                                        item.subject ? h('span', { className: 'ks-planner-row__meta ks-clamp-2' }, item.subject) : null,
                                    ),
                                ),
                            )),
                        )
                        : h(Empty, { compact: true, icon: 'calendar', title: t('meeting_day_empty') }),
                ),
                config.can_create && createUrl ? h(Button, {
                    variant: 'primary',
                    icon: 'plus',
                    block: true,
                    className: 'ks-planner-sheet__add',
                    href: createUrl,
                }, t('meeting_add_to_day')) : null,
            ),
        );
    }

    function MeetingCalendar() {
        const bootToday = isYmd(config.today) ? config.today : fmt.todayYmd();
        const isMobile = KS.useIsMobile();
        const [month, setMonthState] = useState(() => (isMonth(config.month) ? config.month : monthOf(bootToday)));
        const [personnel, setPersonnel] = useState(ALL);
        const [focusDate, setFocusDate] = useState(null);
        const [selectedDate, setSelectedDate] = useState(null);

        const setMonth = useCallback((next) => {
            if (isMonth(next)) {
                setMonthState(next);
                setSelectedDate(null);
            }
        }, []);

        const calendar = KS.useResource(() => api.get(url('meetings.calendar'), Object.assign(
            { month },
            personnel !== ALL ? { personnel } : {},
        )).then((payload) => ({ month, personnel, payload: payload || {} })), [month, personnel]);

        const ready = !!calendar.data && calendar.data.month === month && calendar.data.personnel === personnel;
        const state = ready ? 'ready' : (calendar.error && !calendar.loading ? 'error' : 'loading');
        const today = ready && isYmd(calendar.data.payload.today) ? calendar.data.payload.today : bootToday;
        const cells = useMemo(
            () => KS.calendar.buildMonth(month, ready ? calendar.data.payload.days : null, { items: (day) => day.items }),
            [month, ready, calendar.data],
        );
        const effectiveFocus = focusDate && monthOf(focusDate) === month
            ? focusDate
            : (monthOf(today) === month ? today : month + '-01');
        const selectedCell = selectedDate ? (cells.find((cell) => !cell.outside && cell.date === selectedDate) || null) : null;
        const total = ready ? Number(calendar.data.payload.total) || 0 : 0;

        const options = [{ value: ALL, label: t('meeting_all_personnel') }].concat(
            (config.personnel || []).map((person) => ({ value: String(person.value), label: person.label })),
        );

        const tools = config.can_filter ? h(Select, {
            size: 'sm',
            icon: 'user',
            value: personnel,
            options,
            placeholder: false,
            'aria-label': t('meeting_personnel_filter'),
            onChange: (value) => {
                setPersonnel(value ? String(value) : ALL);
                setSelectedDate(null);
            },
        }) : null;

        return h('div', { className: 'ks-root ks-planner ks-meetings' },
            h(KS.calendar.MonthCalendar, {
                state,
                error: calendar.error,
                month,
                cells,
                today,
                focusDate: effectiveFocus,
                isMobile,
                summary: t('meeting_summary', { count: fmt.number(total) }),
                legend: h(Legend),
                tools,
                chipOf,
                dayLabel,
                onFocusDate: setFocusDate,
                onMonth: setMonth,
                onToday: () => {
                    setMonth(monthOf(today));
                    setFocusDate(today);
                },
                onRetry: calendar.reload,
                onSelect: setSelectedDate,
                onOpen: (item) => openUrl(item.url),
            }),
            selectedCell ? h(DaySheet, { cell: selectedCell, onClose: () => setSelectedDate(null) }) : null,
            h(KS.ToastHost, null),
        );
    }

    function mount() {
        const rootEl = KS.root;

        if (!rootEl || rootEl.getAttribute('data-ks-mounted') === '1') {
            return;
        }

        rootEl.setAttribute('data-ks-mounted', '1');

        try {
            window.ReactDOM.createRoot(rootEl).render(h(MeetingCalendar, null));
        } catch (error) {
            rootEl.removeAttribute('data-ks-mounted');
            console.error('Konelsis: gorusme takvimi baglanamadi', error);
        }
    }

    if (document.readyState !== 'loading') {
        mount();
    } else {
        document.addEventListener('DOMContentLoaded', mount, { once: true });
    }
}());
