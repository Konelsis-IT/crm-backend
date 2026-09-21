/*
 * Konelsis - ORTAK AY TAKVIMI (B34, D-109, 21 Eylul 2026).
 *
 * Sosyal medya Plan gorunumunun ay takvimi (B31, D-106) buraya tasindi; ayni
 * izgara gorusme planinda da kullanilir (kullanici karari: "kod tekrari
 * yapmayalim"). Cekirdekten (social-core.js) SONRA, gorunum dosyalarindan ONCE
 * yuklenir ve KS.calendar ad alanini kurar. Stiller konelsis-social.css PLANNER
 * bolumundedir (ks-planner- on eki); iki ekran ayni siniflari kullanir.
 *
 * KS.calendar:
 *   pad2, isYmd, isMonth, monthOf, shiftMonth, shiftDay, weekdayIndex
 *   useCompact(ref, esik, ilk)          kap genisligi esigin altinda mi
 *   buildMonth(ay, gunler, { items(gun), ribbons(gun) })
 *       -> Pazartesi ile baslayan hucreler: { date, number, outside, weekend, items, ribbons }
 *   MonthCalendar(props)                baslikli takvim kabi (izgara / dar kapta dikey liste)
 *       state: 'loading'|'ready'|'error', error, month, cells, today, focusDate, isMobile,
 *       summary (metin), legend (eleman), chipOf(item) -> { key, color, icon, time, title, check, label },
 *       renderRibbon(ribbon, cell, today) -> eleman, dayLabel(cell, today) -> metin,
 *       onFocusDate, onMonth, onToday, onRetry, onSelect(date), onOpen(item), className
 *
 * Takvim ogesi (chip) ve serit (ribbon) icerigini cagiran belirler; bu dosya
 * yalniz izgarayi, klavye gezintisini, "+N daha" ozetini ve dar kap listesini cizer.
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, t, fmt, cx } = KS;
    const { useState, useEffect, useRef, useMemo } = KS;
    const { Icon, Button, IconButton, ErrorState, Skeleton } = KS;

    const GRID_MAX_CHIPS = 3;
    const GRID_MAX_RIBBONS = 2;
    const LIST_MAX_CHIPS = 8;
    const COMPACT_WIDTH = 560;      // takvim kabi bundan darsa dikey ay listesi

    /* ================================================================== */
    /* 1. Tarih yardimcilari                                               */
    /* ================================================================== */

    function pad2(value) {
        return (value < 10 ? '0' : '') + value;
    }

    function isYmd(value) {
        return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
    }

    function isMonth(value) {
        return typeof value === 'string' && /^\d{4}-(0[1-9]|1[0-2])$/.test(value);
    }

    function monthOf(ymd) {
        return String(ymd || '').slice(0, 7);
    }

    /** '2026-09' + 1 -> '2026-10'. Sunucu 1970 oncesini kabul etmez; o sinirda ay degismez. */
    function shiftMonth(month, delta) {
        const first = fmt.parse(month);

        if (!first) {
            return month;
        }

        const next = new Date(first.getFullYear(), first.getMonth() + delta, 1);

        if (next.getFullYear() < 1970 || next.getFullYear() > 9999) {
            return month;
        }

        return next.getFullYear() + '-' + pad2(next.getMonth() + 1);
    }

    function shiftDay(ymd, delta) {
        const date = fmt.parse(ymd);

        return date ? fmt.toYmd(new Date(date.getFullYear(), date.getMonth(), date.getDate() + delta)) : ymd;
    }

    /** Pazartesi = 0 ... Pazar = 6 */
    function weekdayIndex(ymd) {
        const date = fmt.parse(ymd);

        return date ? (date.getDay() + 6) % 7 : 0;
    }

    function listOf(value) {
        return Array.isArray(value) ? value : [];
    }

    /**
     * Ay izgarasinin hucreleri: onceki / sonraki aydan tasan gunler `outside` olarak
     * gelir (veri tasimaz), ayin gunleri sunucudan gelen gun kaydiyla eslenir.
     * pick.items / pick.ribbons gun kaydindan listeleri secer.
     */
    function buildMonth(month, days, pick) {
        const pickItems = pick && typeof pick.items === 'function' ? pick.items : (day) => day.items;
        const pickRibbons = pick && typeof pick.ribbons === 'function' ? pick.ribbons : () => [];
        const first = fmt.parse(month) || fmt.parse(monthOf(fmt.todayYmd()));
        const year = first.getFullYear();
        const index = first.getMonth();
        const total = new Date(year, index + 1, 0).getDate();
        const offset = (first.getDay() + 6) % 7;
        const byDate = {};
        const cells = [];

        (days || []).forEach((day) => {
            if (day && day.date) {
                byDate[day.date] = day;
            }
        });

        const push = (dateObject, outside) => {
            const date = fmt.toYmd(dateObject);
            const source = outside ? null : byDate[date];

            cells.push({
                date,
                number: dateObject.getDate(),
                outside,
                weekend: cells.length % 7 >= 5,
                items: source ? listOf(pickItems(source)) : [],
                ribbons: source ? listOf(pickRibbons(source)) : [],
            });
        };

        for (let before = offset; before > 0; before -= 1) {
            push(new Date(year, index, 1 - before), true);
        }

        for (let day = 1; day <= total; day += 1) {
            push(new Date(year, index, day), false);
        }

        let after = 1;

        while (cells.length % 7 !== 0) {
            push(new Date(year, index + 1, after), true);
            after += 1;
        }

        return cells;
    }

    /** Kabin genisligi esigin altinda mi? (kenar cubugu acik / kapali fark etmesin diye ekran degil kap olculur) */
    function useCompact(ref, threshold, initial) {
        const [compact, setCompact] = useState(!!initial);

        useEffect(() => {
            const node = ref.current;

            if (!node) {
                return undefined;
            }

            const measure = () => {
                const width = node.getBoundingClientRect().width;

                if (width > 0) {
                    setCompact(width < threshold);
                }
            };

            measure();

            if (typeof window.ResizeObserver === 'function') {
                const observer = new window.ResizeObserver(measure);

                observer.observe(node);

                return () => observer.disconnect();
            }

            window.addEventListener('resize', measure);

            return () => window.removeEventListener('resize', measure);
        }, [ref, threshold]);

        return compact;
    }

    /* ================================================================== */
    /* 2. Izgara parcalari                                                 */
    /* ================================================================== */

    /** Takvim cipi: palet renginde, simge + saat + baslik + onay isareti. Tiklayinca ayrinti acilir. */
    function Chip(props) {
        const chip = props.chip;

        return h('button', {
            type: 'button',
            className: cx('ks-planner-chip', 'ks-c-' + KS.paletteColor(chip.color), chip.check && 'is-published', props.large && 'ks-planner-chip--lg'),
            tabIndex: props.tabbable ? 0 : -1,
            title: chip.label || chip.title,
            'aria-label': chip.label || chip.title,
            onClick: (event) => {
                event.stopPropagation();
                props.onOpen(props.item);
            },
        },
            chip.icon ? h(Icon, { name: chip.icon, className: 'ks-planner-chip__icon' }) : null,
            chip.time ? h('span', { className: 'ks-planner-chip__time' }, chip.time) : null,
            h('span', { className: 'ks-planner-chip__title' }, chip.title),
            chip.check ? h(Icon, { name: 'check-circle', className: 'ks-planner-chip__check' }) : null,
        );
    }

    /** Bir gunun seritleri + cipleri + "+N daha". Izgara ve liste ayni bileseni kullanir. */
    function DayEntries(props) {
        const cell = props.cell;
        const ribbons = cell.ribbons.slice(0, props.maxRibbons);
        const items = cell.items.slice(0, props.maxChips);
        const hidden = (cell.ribbons.length - ribbons.length) + (cell.items.length - items.length);

        if (!ribbons.length && !items.length) {
            return null;
        }

        return h('div', { className: 'ks-planner-entries' },
            props.renderRibbon ? ribbons.map((ribbon, index) => h(KS.Fragment, { key: 's' + (ribbon.id || index) }, props.renderRibbon(ribbon, cell, props.today))) : null,
            items.map((item, index) => {
                const chip = props.chipOf(item);

                return h(Chip, { key: 'c' + (chip.key || item.id || index), item, chip, large: props.large, tabbable: props.tabbable, onOpen: props.onOpen });
            }),
            hidden > 0 ? h('span', { className: 'ks-planner-more' }, t('planner_more', { count: fmt.number(hidden) })) : null,
        );
    }

    function GridCell(props) {
        const cell = props.cell;

        if (cell.outside) {
            return h('div', { className: cx('ks-planner-cell', 'is-outside', cell.weekend && 'is-weekend'), 'aria-hidden': 'true' },
                h('span', { className: 'ks-planner-cell__num' }, cell.number),
            );
        }

        const isToday = cell.date === props.today;
        const filled = cell.items.length > 0 || cell.ribbons.length > 0;

        return h('div', {
            className: cx('ks-planner-cell', isToday && 'is-today', cell.date < props.today && 'is-past', cell.weekend && 'is-weekend', filled && 'has-entries'),
        },
            h('button', {
                type: 'button',
                className: 'ks-planner-cell__day',
                'data-date': cell.date,
                tabIndex: props.tabbable ? 0 : -1,
                'aria-label': props.dayLabel(cell, props.today),
                'aria-current': isToday ? 'date' : undefined,
                onFocus: () => props.onFocusDate(cell.date),
                onClick: () => props.onSelect(cell.date),
            },
                h('span', { className: 'ks-planner-cell__num' }, cell.number),
                h(Icon, { name: 'plus', className: 'ks-planner-cell__add' }),
            ),
            h(DayEntries, {
                cell,
                today: props.today,
                maxChips: GRID_MAX_CHIPS,
                maxRibbons: GRID_MAX_RIBBONS,
                tabbable: false,
                chipOf: props.chipOf,
                renderRibbon: props.renderRibbon,
                onOpen: props.onOpen,
            }),
        );
    }

    /** Ay izgarasi: gun dugmeleri arasinda ok tuslari, Home / End (hafta basi / sonu) ile gezilir. */
    function MonthGrid(props) {
        const gridRef = useRef(null);
        const weekdays = useMemo(() => fmt.weekdays('short'), []);
        const month = props.month;

        const onKeyDown = (event) => {
            const target = event.target;
            const date = target && typeof target.getAttribute === 'function' ? target.getAttribute('data-date') : null;

            if (!date) {
                return;
            }

            let next = null;

            if (event.key === 'ArrowLeft') {
                next = shiftDay(date, -1);
            } else if (event.key === 'ArrowRight') {
                next = shiftDay(date, 1);
            } else if (event.key === 'ArrowUp') {
                next = shiftDay(date, -7);
            } else if (event.key === 'ArrowDown') {
                next = shiftDay(date, 7);
            } else if (event.key === 'Home' || event.key === 'End') {
                next = shiftDay(date, event.key === 'Home' ? -weekdayIndex(date) : 6 - weekdayIndex(date));

                // Hafta baska aya tasiyorsa ayin ilk / son gununde durulur.
                if (monthOf(next) !== month) {
                    const inMonth = props.cells.filter((cell) => !cell.outside);

                    next = inMonth.length ? inMonth[event.key === 'Home' ? 0 : inMonth.length - 1].date : null;
                }
            }

            if (!next || monthOf(next) !== month) {
                return;
            }

            event.preventDefault();
            props.onFocusDate(next);

            const node = gridRef.current ? gridRef.current.querySelector('[data-date="' + next + '"]') : null;

            if (node && typeof node.focus === 'function') {
                node.focus();
            }
        };

        return h('div', { className: 'ks-planner-month' },
            h('div', { className: 'ks-planner-weekdays', 'aria-hidden': 'true' },
                weekdays.map((name, index) => h('span', { key: index, className: cx('ks-planner-weekday', index >= 5 && 'is-weekend') }, name)),
            ),
            h('div', { ref: gridRef, className: 'ks-planner-grid', role: 'group', 'aria-label': fmt.monthLabel(month), onKeyDown },
                props.cells.map((cell) => h(GridCell, {
                    key: cell.date,
                    cell,
                    today: props.today,
                    tabbable: cell.date === props.focusDate,
                    dayLabel: props.dayLabel,
                    chipOf: props.chipOf,
                    renderRibbon: props.renderRibbon,
                    onFocusDate: props.onFocusDate,
                    onSelect: props.onSelect,
                    onOpen: props.onOpen,
                })),
            ),
        );
    }

    /** Dar kap / telefon: dikey ay listesi. Gecmisteki bos gunler istege bagli gizlenir. */
    function MonthList(props) {
        const [showPast, setShowPast] = useState(false);
        const days = props.cells.filter((cell) => !cell.outside);
        const isEmptyPast = (cell) => cell.date < props.today && !cell.items.length && !cell.ribbons.length;
        const pastEmpty = days.filter(isEmptyPast).length;
        const visible = showPast ? days : days.filter((cell) => !isEmptyPast(cell));

        return h('div', { className: 'ks-planner-monthlist' },
            pastEmpty > 0 ? h('div', { className: 'ks-planner-monthlist__toggle' },
                h(Button, {
                    variant: 'link',
                    size: 'sm',
                    icon: showPast ? 'eye-off' : 'eye',
                    pressed: showPast,
                    onClick: () => setShowPast(!showPast),
                }, showPast ? t('planner_hide_past_days') : t('planner_show_past_days', { count: fmt.number(pastEmpty) })),
            ) : null,
            h('ol', { className: 'ks-planner-list', 'aria-label': fmt.monthLabel(props.month) },
                visible.map((cell) => {
                    const isToday = cell.date === props.today;
                    const filled = cell.items.length > 0 || cell.ribbons.length > 0;
                    const date = fmt.parse(cell.date);

                    return h('li', {
                        key: cell.date,
                        className: cx('ks-planner-listday', isToday && 'is-today', cell.date < props.today && 'is-past', cell.weekend && 'is-weekend', !filled && 'is-empty'),
                    },
                        h('button', {
                            type: 'button',
                            className: 'ks-planner-listday__day',
                            'aria-label': props.dayLabel(cell, props.today),
                            'aria-current': isToday ? 'date' : undefined,
                            onClick: () => props.onSelect(cell.date),
                        },
                            h('span', { className: 'ks-planner-listday__num' }, cell.number),
                            h('span', { className: 'ks-planner-listday__weekday' }, date ? date.toLocaleDateString(KS.locale, { weekday: 'short' }) : ''),
                        ),
                        h('div', { className: 'ks-planner-listday__body' },
                            filled
                                ? h(DayEntries, {
                                    cell,
                                    today: props.today,
                                    maxChips: LIST_MAX_CHIPS,
                                    maxRibbons: LIST_MAX_CHIPS,
                                    large: true,
                                    tabbable: true,
                                    chipOf: props.chipOf,
                                    renderRibbon: props.renderRibbon,
                                    onOpen: props.onOpen,
                                })
                                : h('span', { className: 'ks-planner-listday__empty', 'aria-hidden': 'true' }, h(Icon, { name: 'plus' })),
                        ),
                    );
                }),
            ),
        );
    }

    function CalendarSkeleton(props) {
        if (props.compact) {
            return h('div', { className: 'ks-planner-list ks-planner-list--skeleton', 'aria-hidden': 'true' },
                [0, 1, 2, 3, 4, 5].map((index) => h(Skeleton, { key: index, variant: 'rect', height: index % 2 ? 56 : 84, radius: 14 })),
            );
        }

        const cells = [];

        for (let index = 0; index < 35; index += 1) {
            cells.push(h('div', { key: index, className: 'ks-planner-cell is-skeleton' },
                h(Skeleton, { variant: 'text', width: 22 }),
                index % 4 === 1 ? h(Skeleton, { variant: 'text', width: '85%' }) : null,
                index % 6 === 2 ? h(Skeleton, { variant: 'text', width: '60%' }) : null,
            ));
        }

        return h('div', { className: 'ks-planner-month', 'aria-hidden': 'true' },
            h('div', { className: 'ks-planner-grid' }, cells),
        );
    }

    /* ================================================================== */
    /* 3. Takvim kabi                                                      */
    /* ================================================================== */

    function defaultDayLabel(cell, today) {
        const text = fmt.dateLong(cell.date);

        return cell.date === today ? t('today') + ', ' + text : text;
    }

    function MonthCalendar(props) {
        const paneRef = useRef(null);
        const compact = useCompact(paneRef, COMPACT_WIDTH, props.isMobile);
        const month = props.month;
        const cells = props.cells;
        const dayLabel = typeof props.dayLabel === 'function' ? props.dayLabel : defaultDayLabel;
        const shared = {
            cells,
            month,
            today: props.today,
            dayLabel,
            chipOf: props.chipOf,
            renderRibbon: props.renderRibbon,
            onSelect: props.onSelect,
            onOpen: props.onOpen,
        };
        let body = null;

        if (props.state === 'ready') {
            body = compact
                ? h(MonthList, shared)
                : h(MonthGrid, Object.assign({}, shared, { focusDate: props.focusDate, onFocusDate: props.onFocusDate }));
        } else if (props.state === 'error') {
            body = h(ErrorState, { error: props.error, onRetry: props.onRetry });
        } else {
            body = h(CalendarSkeleton, { compact });
        }

        return h('section', { ref: paneRef, className: cx('ks-planner-pane', 'ks-planner-cal', compact && 'is-compact', props.className), 'aria-label': t('planner_calendar') },
            h('header', { className: 'ks-planner-pane__head ks-planner-cal__head' },
                h('span', { className: 'ks-planner-pane__icon', 'aria-hidden': 'true' }, h(Icon, { name: 'calendar' })),
                h('div', { className: 'ks-planner-pane__titles' },
                    h('h2', { className: 'ks-planner-pane__title ks-planner-cal__month', 'aria-live': 'polite' }, fmt.monthLabel(month)),
                    h('p', { className: 'ks-planner-pane__hint' }, props.state === 'ready' && props.summary ? props.summary : t('loading')),
                ),
                props.tools ? h('div', { className: 'ks-planner-cal__tools' }, props.tools) : null,
                h('div', { className: 'ks-planner-cal__nav' },
                    h(IconButton, { icon: 'chevron-left', label: t('planner_prev_month'), onClick: () => props.onMonth(shiftMonth(month, -1)) }),
                    h(Button, { variant: 'ghost', onClick: props.onToday }, t('today')),
                    h(IconButton, { icon: 'chevron-right', label: t('planner_next_month'), onClick: () => props.onMonth(shiftMonth(month, 1)) }),
                ),
            ),
            h('div', { className: 'ks-planner-cal__body', 'aria-busy': props.state === 'loading' ? 'true' : undefined }, body),
            props.legend ? h('footer', { className: 'ks-planner-cal__foot' }, props.legend) : null,
        );
    }

    KS.calendar = {
        pad2,
        isYmd,
        isMonth,
        monthOf,
        shiftMonth,
        shiftDay,
        weekdayIndex,
        useCompact,
        buildMonth,
        MonthCalendar,
        COMPACT_WIDTH,
    };
}());
