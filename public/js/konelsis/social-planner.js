/*
 * Konelsis Sosyal Medya modulu - PLAN gorunumu (B31, D-106, 18 Eylul 2026).
 *
 * KS.views.Planner: solda ajanda (Bugun, Yarin, Yaklasan, Geciken, Onay bekleyen
 * yaklasanlar), sagda Pazartesi ile baslayan ay takvimi. Gune tiklaninca gun sayfasi
 * (Drawer) acilir; oradan "Bu gune icerik ekle" olusturucuyu planned_on ile acar.
 *
 * Uclar: GET agenda?profile, GET calendar?month=YYYY-MM&profile, POST contents/{id}/urgent.
 * Ajanda ve takvim ogeleri ayni bicimdedir (SocialContentPresenter::cardLite).
 * Veri; hesap, ay, feedVersion ya da contentVersion degisince yeniden cekilir
 * (ayni anda gelen degisiklikler tek istege indirgenir).
 *
 * Baska gorunum dosyalarindan yalniz KS.parts.ContentCard (variant 'row') kullanilir;
 * cizim aninda aranir, yoksa ya da hata verirse bu dosyanin kendi satiri cizilir.
 * Stiller: resources/css/filament/konelsis-social.css, bolum PLANNER (on ek ks-planner-).
 * Dar kapta (< 560px) takvim izgarasi dikey ay listesine doner.
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const React = window.React;
    const { h, t, api, url, fmt, cx } = KS;
    const { useState, useEffect, useRef, useMemo, useCallback } = KS;
    const { Icon, PlatformIcon, Button, IconButton, Badge, StatusBadge, StageBadge, Drawer, Empty, ErrorState, Notice, Skeleton } = KS;

    /* ================================================================== */
    /* 1. Sabitler ve kucuk yardimcilar                                    */
    /* ================================================================== */

    const TYPE_ICONS = { photo: 'image', video: 'video', short_text: 'text', long_text: 'document', blog: 'blog' };

    // Ajanda bolumleri (sira kullanicinin istedigi sira). Renkler cekirdekteki asama renkleridir.
    const SECTIONS = [
        { key: 'today', label: 'planner_section_today', color: 'red' },
        { key: 'tomorrow', label: 'planner_section_tomorrow', color: 'amber' },
        { key: 'approaching', label: 'planner_section_approaching', color: 'sky' },
        { key: 'missed', label: 'planner_section_missed', color: 'rose' },
        { key: 'awaiting_approval', label: 'planner_section_awaiting', color: 'teal', showStage: true },
    ];

    const AGENDA_CAP = 50;          // sunucu her listeyi en cok 50 oge ile dondurur (F6)
    const SECTION_PREVIEW = 5;      // bolumde ilk gosterilen oge sayisi
    const GRID_MAX_CHIPS = 3;
    const GRID_MAX_RIBBONS = 2;
    const LIST_MAX_CHIPS = 8;
    const COMPACT_WIDTH = 560;      // takvim kabi bundan darsa dikey ay listesi
    const REMINDER_STALE_MS = 48 * 60 * 60 * 1000;

    // Gorunumden cikilip donuldugunde ayni ay acilsin (oturum ici, sayfa yenilenince sifirlanir).
    let rememberedMonth = null;

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

    function typeIcon(type) {
        return TYPE_ICONS[type] || 'document';
    }

    function colorOf(item) {
        return KS.paletteColor(item.status_color || KS.statusColor(item.status));
    }

    function isAwaiting(item) {
        return item.status === 'pending' || item.status === 'revision_requested';
    }

    /**
     * Ay izgarasinin hucreleri: onceki / sonraki aydan tasan gunler `outside` olarak
     * gelir (veri tasimaz), ayin gunleri sunucudan gelen gun kaydiyla eslenir.
     */
    function buildMonth(month, days) {
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
                contents: (source && Array.isArray(source.contents)) ? source.contents : [],
                special_days: (source && Array.isArray(source.special_days)) ? source.special_days : [],
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

    /**
     * Ozel gunun durumu. Sunucu `covered` verir: gecmis gunde paylasilmis icerik,
     * bugun / gelecekte paylasilmis ya da gecerli planli icerik varsa true.
     */
    function specialMeta(special, date, today) {
        if (special && special.covered) {
            return { state: 'covered', color: 'emerald', icon: 'check-circle', label: t(date < today ? 'planner_special_published' : 'planner_special_planned') };
        }

        if (date < today) {
            return { state: 'missed', color: 'rose', icon: 'alert', label: t('planner_special_missed') };
        }

        return { state: 'open', color: 'amber', icon: 'star', label: t('planner_special_open') };
    }

    function dayLabel(cell, today) {
        const empty = !cell.contents.length && !cell.special_days.length;
        const text = empty
            ? t('planner_day_label_empty', { date: fmt.dateLong(cell.date) })
            : t('planner_day_label', {
                date: fmt.dateLong(cell.date),
                contents: fmt.number(cell.contents.length),
                days: fmt.number(cell.special_days.length),
            });

        return cell.date === today ? t('today') + ', ' + text : text;
    }

    /** cardLite -> ContentCard'in bekledigi kart bicimi (eksik alanlar yansiz varsayilanlarla). */
    function toCard(item) {
        const cover = item.cover || null;

        return Object.assign({
            excerpt: '',
            category: null,
            published_at: null,
            created_at: null,
            media_count: cover ? 1 : 0,
            thumbs: cover ? [cover] : [],
            has_video: !!(cover && cover.kind === 'video'),
            video_url: null,
            // Sayaclar (begeni, yorum, isaret) cardLite icinde YOKTUR; alanlar bilerek tanimsiz
            // birakilir ki KS.parts.ContentCard yaniltici sifirlar yerine sayac seridini hic cizmesin.
            my_reaction: 'none',
        }, item);
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
    /* 2. Icerik satiri (ajanda ve gun sayfasi)                            */
    /* ================================================================== */

    /** Baska dosyadan gelen ContentCard hata verirse sayfa cokmesin: kendi satirimiza donulur. */
    class CardBoundary extends React.Component {
        constructor(props) {
            super(props);
            this.state = { failed: false };
        }

        static getDerivedStateFromError() {
            return { failed: true };
        }

        componentDidCatch(error) {
            console.warn('KonelsisSocial: ContentCard (row) cizilemedi; plan kendi satirini kullaniyor', error);

            if (typeof this.props.onFail === 'function') {
                this.props.onFail();
            }
        }

        render() {
            return this.state.failed ? null : this.props.children;
        }
    }

    function RowThumb(props) {
        const item = props.item;
        const cover = item.cover || null;
        const source = cover ? KS.safeUrl(cover.thumbnail_url || (cover.kind === 'image' ? cover.preview_url : null)) : null;
        const [broken, setBroken] = useState(false);

        useEffect(() => setBroken(false), [source]);

        if (source && !broken) {
            return h('span', { className: 'ks-planner-row__thumb', 'aria-hidden': 'true' },
                h('img', { src: source, alt: '', loading: 'lazy', decoding: 'async', onError: () => setBroken(true) }),
                h('span', { className: 'ks-planner-row__type' }, h(Icon, { name: typeIcon(item.content_type) })),
            );
        }

        return h('span', { className: 'ks-planner-row__thumb ks-planner-row__thumb--icon', 'aria-hidden': 'true' },
            h(Icon, { name: typeIcon(item.content_type) }),
        );
    }

    /** Kendi kompakt satirimiz: tur simgesi / kapak, baslik, tarih-saat, durum, platformlar. */
    function RowMain(props) {
        const item = props.item;
        const planned = fmt.planned(item.planned_on, item.planned_time);
        const platforms = Array.isArray(item.platforms) ? item.platforms : [];

        return h('button', { type: 'button', className: 'ks-planner-row', onClick: props.onOpen },
            h(RowThumb, { item }),
            h('span', { className: 'ks-planner-row__text' },
                h('span', { className: 'ks-sr-only' }, (item.content_type_label || '') + ': '),
                h('span', { className: 'ks-planner-row__title ks-clamp-2' }, item.title),
                h('span', { className: 'ks-planner-row__meta' },
                    planned ? h('span', { className: 'ks-planner-row__date' },
                        h(Icon, { name: item.planned_time ? 'clock' : 'calendar' }),
                        h('span', null, planned),
                    ) : null,
                    props.showStage && item.planned_stage ? h(StageBadge, { stage: item.planned_stage, size: 'sm' }) : null,
                    h(StatusBadge, { status: item.status, label: item.status_label, color: colorOf(item), size: 'sm' }),
                    item.is_published ? h(Badge, { color: 'emerald', icon: 'check', size: 'sm' }, t('planner_published')) : null,
                    platforms.length ? h('span', { className: 'ks-planner-row__platforms' },
                        platforms.map((platform) => h(PlatformIcon, {
                            key: platform.platform,
                            platform: platform.platform,
                            variant: 'badge',
                            size: 'sm',
                            label: platform.platform_label,
                        })),
                    ) : null,
                ),
            ),
        );
    }

    /**
     * AgendaItem({ item, showStage, onOpen(id), onUrgent(item), urgentBusy })
     * Govde: KS.parts.ContentCard (row) varsa o, yoksa RowMain + her zaman bir "ac" dugmesi.
     * Alt serit: acil onay istenebiliyorsa dugme, istenmisse ne zaman istendigi.
     */
    function AgendaItem(props) {
        const item = props.item;
        const [cardFailed, setCardFailed] = useState(false);
        const Card = (!cardFailed && KS.parts && KS.parts.ContentCard) || null;
        const open = () => props.onOpen(item.id);
        const requested = !!item.urgent_requested_at && isAwaiting(item);

        const main = Card
            ? h(CardBoundary, { onFail: () => setCardFailed(true) },
                h('div', { className: 'ks-planner-item__card' },
                    h(Card, { card: toCard(item), variant: 'row', onOpen: open }),
                ),
            )
            : h(RowMain, { item, showStage: props.showStage, onOpen: open });

        return h('li', { className: cx('ks-planner-item', Card && 'ks-planner-item--card') },
            h('div', { className: 'ks-planner-item__body' },
                main,
                h(IconButton, {
                    icon: 'chevron-right',
                    variant: 'plain',
                    className: 'ks-planner-item__open',
                    label: t('planner_open_named', { title: item.title }),
                    onClick: open,
                }),
            ),
            item.urgent_allowed || requested ? h('div', { className: 'ks-planner-item__foot' },
                requested
                    ? h(Badge, { color: 'rose', icon: 'bolt', size: 'sm' }, t('planner_urgent_requested', { when: fmt.relative(item.urgent_requested_at) }))
                    : h('span', { className: 'ks-planner-item__hint' }, t('planner_urgent_hint')),
                item.urgent_allowed ? h(Button, {
                    variant: 'danger',
                    size: 'sm',
                    icon: 'bolt',
                    loading: props.urgentBusy === item.id,
                    disabled: !!props.urgentBusy && props.urgentBusy !== item.id,
                    onClick: () => props.onUrgent(item),
                }, t('planner_request_urgent')) : null,
            ) : null,
        );
    }

    /* ================================================================== */
    /* 3. Ajanda                                                           */
    /* ================================================================== */

    function AgendaSection(props) {
        const section = props.section;
        const items = props.items;
        const [open, setOpen] = useState(true);
        const [expanded, setExpanded] = useState(false);
        const bodyId = useMemo(() => KS.uid('ks-planner-section'), []);
        const count = items.length;
        const shown = expanded ? items : items.slice(0, SECTION_PREVIEW);

        const head = [
            h('span', { key: 'dot', className: 'ks-planner-section__dot', 'aria-hidden': 'true' }),
            h('span', { key: 'title', className: 'ks-planner-section__title' }, t(section.label)),
        ];

        if (!count) {
            return h('section', { className: cx('ks-planner-section', 'is-empty', 'ks-c-' + section.color) },
                h('div', { className: 'ks-planner-section__head' },
                    head,
                    h('span', { className: 'ks-planner-section__none' }, t('planner_section_empty')),
                ),
            );
        }

        return h('section', { className: cx('ks-planner-section', 'ks-c-' + section.color) },
            h('button', {
                type: 'button',
                className: 'ks-planner-section__head',
                'aria-expanded': open ? 'true' : 'false',
                'aria-controls': bodyId,
                onClick: () => setOpen(!open),
            },
                head,
                h(Badge, { color: section.color, size: 'sm' }, count >= AGENDA_CAP ? AGENDA_CAP + '+' : fmt.number(count)),
                h(Icon, { name: 'chevron-down', className: 'ks-planner-section__chevron' }),
            ),
            open ? h('div', { id: bodyId, className: 'ks-planner-section__body' },
                h('ul', { className: 'ks-planner-items' },
                    shown.map((item) => h(AgendaItem, {
                        key: item.id,
                        item,
                        showStage: !!section.showStage,
                        onOpen: props.onOpen,
                        onUrgent: props.onUrgent,
                        urgentBusy: props.urgentBusy,
                    })),
                ),
                count > SECTION_PREVIEW ? h('div', { className: 'ks-planner-section__more' },
                    h(Button, {
                        variant: 'link',
                        size: 'sm',
                        iconRight: expanded ? 'chevron-up' : 'chevron-down',
                        onClick: () => setExpanded(!expanded),
                    }, expanded ? t('show_less') : t('planner_show_all', { count: fmt.number(count) })),
                ) : null,
                expanded && count >= AGENDA_CAP ? h('p', { className: 'ks-planner-section__capped' }, t('planner_list_capped', { count: AGENDA_CAP })) : null,
            ) : null,
        );
    }

    function AgendaSkeleton() {
        return h('div', { className: 'ks-planner-agenda__skeleton', 'aria-hidden': 'true' },
            [0, 1, 2].map((index) => h('div', { key: index, className: 'ks-planner-agenda__skeleton-block' },
                h(Skeleton, { variant: 'text', width: '45%' }),
                h(Skeleton, { variant: 'rect', height: 68 }),
                index === 0 ? h(Skeleton, { variant: 'rect', height: 68 }) : null,
            )),
        );
    }

    function AgendaPane(props) {
        const lists = props.lists || {};
        const total = SECTIONS.reduce((sum, section) => sum + ((lists[section.key] || []).length), 0);
        let body = null;

        if (props.state === 'loading') {
            body = h(AgendaSkeleton);
        } else if (props.state === 'error') {
            body = h(ErrorState, { error: props.error, onRetry: props.onRetry, compact: true });
        } else if (!total) {
            body = h(Empty, {
                icon: 'calendar',
                title: t('planner_agenda_empty_title'),
                text: t('planner_agenda_empty_text'),
                action: props.canCreate ? h(Button, { variant: 'primary', icon: 'plus', onClick: props.onAdd }, t('planner_add_content')) : null,
            });
        } else {
            body = SECTIONS.map((section) => h(AgendaSection, {
                key: section.key,
                section,
                items: Array.isArray(lists[section.key]) ? lists[section.key] : [],
                onOpen: props.onOpen,
                onUrgent: props.onUrgent,
                urgentBusy: props.urgentBusy,
            }));
        }

        return h('section', { className: 'ks-planner-pane ks-planner-agenda', 'aria-label': t('planner_agenda') },
            h('header', { className: 'ks-planner-pane__head' },
                h('span', { className: 'ks-planner-pane__icon', 'aria-hidden': 'true' }, h(Icon, { name: 'list' })),
                h('div', { className: 'ks-planner-pane__titles' },
                    h('h2', { className: 'ks-planner-pane__title' }, t('planner_agenda')),
                    h('p', { className: 'ks-planner-pane__hint' }, t('planner_agenda_hint')),
                ),
                h(IconButton, { icon: 'refresh', variant: 'plain', label: t('refresh'), loading: props.refreshing, onClick: props.onRefresh }),
            ),
            h('div', { className: 'ks-planner-agenda__body ks-scroll', 'aria-busy': props.state === 'loading' ? 'true' : undefined }, body),
        );
    }

    /* ================================================================== */
    /* 4. Takvim                                                           */
    /* ================================================================== */

    /** Takvim cipi: durum renginde, tur simgesi + saat + baslik + paylasildi isareti. Tiklayinca ayrinti acilir. */
    function CalendarChip(props) {
        const item = props.item;
        const time = fmt.time(item.planned_time);
        const label = [
            item.title,
            item.content_type_label,
            item.status_label,
            item.is_published ? t('planner_published') : null,
            time || null,
        ].filter(Boolean).join(', ');

        return h('button', {
            type: 'button',
            className: cx('ks-planner-chip', 'ks-c-' + colorOf(item), item.is_published && 'is-published', props.large && 'ks-planner-chip--lg'),
            tabIndex: props.tabbable ? 0 : -1,
            title: label,
            'aria-label': label,
            onClick: (event) => {
                event.stopPropagation();
                props.onOpen(item.id);
            },
        },
            h(Icon, { name: typeIcon(item.content_type), className: 'ks-planner-chip__icon' }),
            time ? h('span', { className: 'ks-planner-chip__time' }, time) : null,
            h('span', { className: 'ks-planner-chip__title' }, item.title),
            item.is_published ? h(Icon, { name: 'check-circle', className: 'ks-planner-chip__check' }) : null,
        );
    }

    function SpecialRibbon(props) {
        const meta = specialMeta(props.special, props.date, props.today);

        return h('span', {
            className: cx('ks-planner-ribbon', 'ks-planner-ribbon--' + meta.state, 'ks-c-' + meta.color),
            title: props.special.name + ' · ' + meta.label,
        },
            h(Icon, { name: meta.icon }),
            h('span', { className: 'ks-planner-ribbon__name' }, props.special.name),
            h('span', { className: 'ks-sr-only' }, ' (' + t('planner_special_day') + ', ' + meta.label + ')'),
        );
    }

    /** Bir gunun seritleri + cipleri + "+N daha". Izgara ve liste ayni bileseni kullanir. */
    function DayEntries(props) {
        const cell = props.cell;
        const ribbons = cell.special_days.slice(0, props.maxRibbons);
        const chips = cell.contents.slice(0, props.maxChips);
        const hidden = (cell.special_days.length - ribbons.length) + (cell.contents.length - chips.length);

        if (!ribbons.length && !chips.length) {
            return null;
        }

        return h('div', { className: 'ks-planner-entries' },
            ribbons.map((special, index) => h(SpecialRibbon, { key: 's' + (special.id || index), special, date: cell.date, today: props.today })),
            chips.map((item) => h(CalendarChip, { key: 'c' + item.id, item, large: props.large, tabbable: props.tabbable, onOpen: props.onOpen })),
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
        const filled = cell.contents.length > 0 || cell.special_days.length > 0;

        return h('div', {
            className: cx('ks-planner-cell', isToday && 'is-today', cell.date < props.today && 'is-past', cell.weekend && 'is-weekend', filled && 'has-entries'),
        },
            h('button', {
                type: 'button',
                className: 'ks-planner-cell__day',
                'data-date': cell.date,
                tabIndex: props.tabbable ? 0 : -1,
                'aria-label': dayLabel(cell, props.today),
                'aria-current': isToday ? 'date' : undefined,
                onFocus: () => props.onFocusDate(cell.date),
                onClick: () => props.onSelect(cell.date),
            },
                h('span', { className: 'ks-planner-cell__num' }, cell.number),
                h(Icon, { name: 'plus', className: 'ks-planner-cell__add' }),
            ),
            h(DayEntries, { cell, today: props.today, maxChips: GRID_MAX_CHIPS, maxRibbons: GRID_MAX_RIBBONS, tabbable: false, onOpen: props.onOpen }),
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
        const isEmptyPast = (cell) => cell.date < props.today && !cell.contents.length && !cell.special_days.length;
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
                    const filled = cell.contents.length > 0 || cell.special_days.length > 0;
                    const date = fmt.parse(cell.date);

                    return h('li', {
                        key: cell.date,
                        className: cx('ks-planner-listday', isToday && 'is-today', cell.date < props.today && 'is-past', cell.weekend && 'is-weekend', !filled && 'is-empty'),
                    },
                        h('button', {
                            type: 'button',
                            className: 'ks-planner-listday__day',
                            'aria-label': dayLabel(cell, props.today),
                            'aria-current': isToday ? 'date' : undefined,
                            onClick: () => props.onSelect(cell.date),
                        },
                            h('span', { className: 'ks-planner-listday__num' }, cell.number),
                            h('span', { className: 'ks-planner-listday__weekday' }, date ? date.toLocaleDateString(KS.locale, { weekday: 'short' }) : ''),
                        ),
                        h('div', { className: 'ks-planner-listday__body' },
                            filled
                                ? h(DayEntries, { cell, today: props.today, maxChips: LIST_MAX_CHIPS, maxRibbons: LIST_MAX_CHIPS, large: true, tabbable: true, onOpen: props.onOpen })
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

    function Legend() {
        const statuses = KS.options('statuses').filter((status) => status && status.value !== 'archived');

        return h('div', { className: 'ks-planner-legend', role: 'group', 'aria-label': t('planner_legend') },
            statuses.map((status) => h('span', { key: status.value, className: cx('ks-planner-legend__item', 'ks-c-' + KS.paletteColor(status.color || KS.statusColor(status.value))) },
                h('span', { className: 'ks-planner-legend__swatch', 'aria-hidden': 'true' }),
                status.label,
            )),
            h('span', { className: 'ks-planner-legend__item' },
                h(Icon, { name: 'check-circle', className: 'ks-planner-legend__check' }),
                t('planner_published'),
            ),
            h('span', { className: 'ks-planner-legend__sep', 'aria-hidden': 'true' }),
            h('span', { className: 'ks-planner-legend__item ks-c-emerald' },
                h('span', { className: 'ks-planner-legend__swatch ks-planner-legend__swatch--ribbon', 'aria-hidden': 'true' }),
                t('planner_legend_special_covered'),
            ),
            h('span', { className: 'ks-planner-legend__item ks-c-amber' },
                h('span', { className: 'ks-planner-legend__swatch ks-planner-legend__swatch--ribbon', 'aria-hidden': 'true' }),
                t('planner_legend_special_open'),
            ),
            h('span', { className: 'ks-planner-legend__item ks-c-rose' },
                h('span', { className: 'ks-planner-legend__swatch ks-planner-legend__swatch--ribbon', 'aria-hidden': 'true' }),
                t('planner_legend_special_missed'),
            ),
        );
    }

    function CalendarPane(props) {
        const paneRef = useRef(null);
        const compact = useCompact(paneRef, COMPACT_WIDTH, props.isMobile);
        const month = props.month;
        const cells = props.cells;
        let body = null;
        let summary = null;

        if (props.state === 'ready') {
            let contents = 0;
            let specials = 0;

            cells.forEach((cell) => {
                contents += cell.contents.length;
                specials += cell.special_days.length;
            });

            summary = t('planner_summary', { contents: fmt.number(contents), days: fmt.number(specials) });
            body = compact
                ? h(MonthList, { cells, month, today: props.today, onSelect: props.onSelect, onOpen: props.onOpen })
                : h(MonthGrid, { cells, month, today: props.today, focusDate: props.focusDate, onFocusDate: props.onFocusDate, onSelect: props.onSelect, onOpen: props.onOpen });
        } else if (props.state === 'error') {
            body = h(ErrorState, { error: props.error, onRetry: props.onRetry });
        } else {
            body = h(CalendarSkeleton, { compact });
        }

        return h('section', { ref: paneRef, className: cx('ks-planner-pane', 'ks-planner-cal', compact && 'is-compact'), 'aria-label': t('planner_calendar') },
            h('header', { className: 'ks-planner-pane__head ks-planner-cal__head' },
                h('span', { className: 'ks-planner-pane__icon', 'aria-hidden': 'true' }, h(Icon, { name: 'calendar' })),
                h('div', { className: 'ks-planner-pane__titles' },
                    h('h2', { className: 'ks-planner-pane__title ks-planner-cal__month', 'aria-live': 'polite' }, fmt.monthLabel(month)),
                    h('p', { className: 'ks-planner-pane__hint' }, summary || t('loading')),
                ),
                h('div', { className: 'ks-planner-cal__nav' },
                    h(IconButton, { icon: 'chevron-left', label: t('planner_prev_month'), onClick: () => props.onMonth(shiftMonth(month, -1)) }),
                    h(Button, { variant: 'ghost', onClick: props.onToday }, t('today')),
                    h(IconButton, { icon: 'chevron-right', label: t('planner_next_month'), onClick: () => props.onMonth(shiftMonth(month, 1)) }),
                ),
            ),
            h('div', { className: 'ks-planner-cal__body', 'aria-busy': props.state === 'loading' ? 'true' : undefined }, body),
            h('footer', { className: 'ks-planner-cal__foot' }, h(Legend)),
        );
    }

    /* ================================================================== */
    /* 5. Gun sayfasi                                                      */
    /* ================================================================== */

    function DaySheet(props) {
        const cell = props.cell;
        const today = props.today;
        const relative = cell.date === today ? t('today') : (cell.date === shiftDay(today, 1) ? t('tomorrow') : null);
        const summary = t('planner_summary', { contents: fmt.number(cell.contents.length), days: fmt.number(cell.special_days.length) });

        return h(Drawer, {
            title: fmt.dateLong(cell.date),
            subtitle: relative ? relative + ' · ' + summary : summary,
            icon: 'calendar',
            side: 'right',
            size: 'md',
            className: 'ks-planner-sheet',
            onClose: props.onClose,
        },
            h('div', { className: 'ks-planner-sheet__body' },
                cell.special_days.length ? h('section', { className: 'ks-planner-sheet__block' },
                    h('h3', { className: 'ks-planner-sheet__heading' }, t('planner_special_days')),
                    h('ul', { className: 'ks-planner-specials' },
                        cell.special_days.map((special, index) => {
                            const meta = specialMeta(special, cell.date, today);

                            return h('li', { key: special.id || index, className: cx('ks-planner-special', 'ks-c-' + meta.color) },
                                h('span', { className: 'ks-planner-special__icon', 'aria-hidden': 'true' }, h(Icon, { name: 'star' })),
                                h('span', { className: 'ks-planner-special__text' },
                                    h('span', { className: 'ks-planner-special__name' }, special.name),
                                    special.note ? h('span', { className: 'ks-planner-special__note' }, special.note) : null,
                                ),
                                h(Badge, { color: meta.color, icon: meta.icon, size: 'sm' }, meta.label),
                            );
                        }),
                    ),
                ) : null,
                h('section', { className: 'ks-planner-sheet__block' },
                    h('h3', { className: 'ks-planner-sheet__heading' }, t('planner_day_contents')),
                    cell.contents.length
                        ? h('ul', { className: 'ks-planner-items' },
                            cell.contents.map((item) => h(AgendaItem, {
                                key: item.id,
                                item,
                                showStage: false,
                                onOpen: props.onOpen,
                                onUrgent: props.onUrgent,
                                urgentBusy: props.urgentBusy,
                            })),
                        )
                        : h(Empty, {
                            compact: true,
                            icon: 'calendar',
                            title: t('planner_day_empty_title'),
                            text: props.canCreate ? t('planner_day_empty_text') : null,
                        }),
                ),
                props.canCreate ? h(Button, {
                    variant: 'primary',
                    icon: 'plus',
                    block: true,
                    className: 'ks-planner-sheet__add',
                    onClick: () => props.onAdd(cell.date),
                }, t('planner_add_to_day')) : null,
            ),
        );
    }

    /* ================================================================== */
    /* 6. Gorunum                                                          */
    /* ================================================================== */

    function Planner() {
        const boot = KS.useBoot();
        const profile = KS.useProfile();
        const abilities = KS.useAbilities();
        const feedVersion = KS.useStore((state) => state.feedVersion);
        const contentVersion = KS.useStore((state) => state.contentVersion);
        const isMobile = KS.useIsMobile();

        const profileId = profile ? profile.id : null;
        const canCreate = !!abilities.create;
        const bootToday = isYmd(KS.config.today) ? KS.config.today : fmt.todayYmd();

        const [month, setMonthState] = useState(() => (isMonth(rememberedMonth) ? rememberedMonth : monthOf(bootToday)));
        const [focusDate, setFocusDate] = useState(null);
        const [selectedDate, setSelectedDate] = useState(null);
        const [urgentBusy, setUrgentBusy] = useState(null);
        const mounted = useRef(true);

        useEffect(() => {
            mounted.current = true;

            return () => {
                mounted.current = false;
            };
        }, []);

        const setMonth = useCallback((next) => {
            if (isMonth(next)) {
                rememberedMonth = next;
                setMonthState(next);
                // Gun sayfasi yalniz gosterilen aya aittir.
                setSelectedDate(null);
            }
        }, []);

        /*
         * Istek anahtari: hesap | ay | surum. Hesap degisimi ayni anda feedVersion'i da artirir;
         * gecikmeli anahtar bu tur es zamanli degisiklikleri tek istege indirir ve hizli ay
         * gezintisinde ara aylar icin istek atilmaz.
         */
        const liveKey = [profileId || 0, month, feedVersion + contentVersion].join('|');
        const settledKey = KS.useDebounced(liveKey, 200);
        const settled = useMemo(() => {
            const parts = settledKey.split('|');

            return { profileId: Number(parts[0]) || null, month: parts[1], version: parts[2] };
        }, [settledKey]);
        const pending = liveKey !== settledKey;

        const agenda = KS.useResource(() => {
            const id = settled.profileId;

            if (!id) {
                return Promise.resolve(null);
            }

            return api.get(url('agenda'), { profile: id }).then((payload) => ({ profileId: id, lists: payload || {} }));
        }, [settled.profileId, settled.version]);

        const calendar = KS.useResource(() => {
            const id = settled.profileId;
            const wanted = settled.month;

            if (!id) {
                return Promise.resolve(null);
            }

            return api.get(url('calendar'), { profile: id, month: wanted }).then((payload) => ({ profileId: id, month: wanted, payload: payload || {} }));
        }, [settled.profileId, settled.month, settled.version]);

        const agendaReady = !!agenda.data && agenda.data.profileId === profileId;
        const calendarReady = !!calendar.data && calendar.data.profileId === profileId && calendar.data.month === month;
        const agendaState = agendaReady ? 'ready' : (agenda.error && !agenda.loading && !pending ? 'error' : 'loading');
        const calendarState = calendarReady ? 'ready' : (calendar.error && !calendar.loading && !pending ? 'error' : 'loading');

        // Arka plan yenilemesi basarisiz olursa eldeki veri ekranda kalir, hata bildirim olarak gosterilir.
        // Iki istek ayni anda duserse (ornegin baglanti koptu) tek bildirim yeter.
        const readyRef = useRef({ agenda: false, calendar: false });
        const lastErrorToast = useRef(0);

        readyRef.current = { agenda: agendaReady, calendar: calendarReady };

        const reportBackgroundError = useCallback((error) => {
            if (Date.now() - lastErrorToast.current < 1500) {
                return;
            }

            lastErrorToast.current = Date.now();
            KS.handleError(error);
        }, []);

        useEffect(() => {
            if (agenda.error && readyRef.current.agenda) {
                reportBackgroundError(agenda.error);
            }
        }, [agenda.error, reportBackgroundError]);

        useEffect(() => {
            if (calendar.error && readyRef.current.calendar) {
                reportBackgroundError(calendar.error);
            }
        }, [calendar.error, reportBackgroundError]);

        const serverToday = calendarReady && isYmd(calendar.data.payload.today) ? calendar.data.payload.today : null;
        const today = serverToday || bootToday;

        const cells = useMemo(
            () => buildMonth(month, calendarReady ? calendar.data.payload.days : null),
            [month, calendarReady, calendar.data],
        );

        // Klavye odagi: secili gun bu aydaysa o, degilse bugun, o da degilse ayin ilk gunu.
        const effectiveFocus = focusDate && monthOf(focusDate) === month
            ? focusDate
            : (monthOf(today) === month ? today : month + '-01');

        const selectedCell = selectedDate ? (cells.find((cell) => !cell.outside && cell.date === selectedDate) || null) : null;

        const reloadAll = useCallback(() => {
            agenda.reload();
            calendar.reload();
        }, [agenda.reload, calendar.reload]);

        const openContent = useCallback((id) => {
            // Gun sayfasi (modal katman) ayrintinin ustunde kalmasin.
            setSelectedDate(null);
            KS.actions.openDetail(id);
        }, []);

        const addContent = useCallback((date) => {
            const defaults = { profile_id: profileId };

            if (isYmd(date)) {
                defaults.planned_on = date;
            }

            setSelectedDate(null);
            KS.actions.openComposer({ defaults });
        }, [profileId]);

        /** Acil onaydan sonra satirlar sunucu yaniti beklenmeden guncellenir (dugme ikinci kez basilmasin). */
        const patchItem = useCallback((id, patch) => {
            const apply = (item) => (item && Number(item.id) === Number(id) ? Object.assign({}, item, patch) : item);

            agenda.setData((current) => {
                if (!current || !current.lists) {
                    return current;
                }

                const lists = {};

                Object.keys(current.lists).forEach((name) => {
                    lists[name] = Array.isArray(current.lists[name]) ? current.lists[name].map(apply) : current.lists[name];
                });

                return Object.assign({}, current, { lists });
            });

            calendar.setData((current) => {
                if (!current || !current.payload || !Array.isArray(current.payload.days)) {
                    return current;
                }

                const days = current.payload.days.map((day) => Object.assign({}, day, {
                    contents: Array.isArray(day.contents) ? day.contents.map(apply) : day.contents,
                }));

                return Object.assign({}, current, { payload: Object.assign({}, current.payload, { days }) });
            });
        }, [agenda.setData, calendar.setData]);

        const requestUrgent = useCallback((item) => {
            if (urgentBusy) {
                return;
            }

            KS.confirm({
                title: t('planner_urgent_confirm_title'),
                text: t('planner_urgent_confirm_text', { title: item.title }),
                confirmLabel: t('planner_request_urgent'),
                icon: 'bolt',
            }).then((confirmed) => {
                if (!confirmed) {
                    return null;
                }

                setUrgentBusy(item.id);

                return api.post(url('contents.urgent', item.id), {}).then((payload) => {
                    const detail = payload && payload.content ? payload.content : null;

                    patchItem(item.id, {
                        urgent_allowed: false,
                        urgent_requested_at: (detail && detail.urgent_requested_at) || new Date().toISOString(),
                    });

                    if (detail) {
                        KS.actions.applyContent(detail);
                    }

                    KS.actions.refreshFeed();
                    KS.toast.success(t('planner_urgent_sent', { count: fmt.number(Number(payload && payload.notified) || 0) }));
                }).catch((error) => {
                    KS.handleError(error);
                    // Bekleme suresi / pencere gibi engellerde `urgent_allowed` degismis olabilir.
                    reloadAll();
                }).then(() => {
                    if (mounted.current) {
                        setUrgentBusy(null);
                    }
                });
            });
        }, [urgentBusy, patchItem, reloadAll]);

        if (!profile) {
            return h('div', { className: 'ks-planner' }, h(Empty, { icon: 'calendar', title: t('empty') }));
        }

        // H9: hatirlatma gorevi hic calismadiysa ya da 48 saatten eskiyse ayar yetkilisine sessiz ipucu.
        const lastRun = boot && boot.reminders ? fmt.parse(boot.reminders.last_run_at) : null;
        const remindersStale = !lastRun || (Date.now() - lastRun.getTime()) > REMINDER_STALE_MS;
        const showReminderHint = !!abilities.manage_settings && !!boot && remindersStale;

        return h('div', { className: 'ks-planner' },
            showReminderHint ? h(Notice, { tone: 'neutral', icon: 'bell', compact: true, className: 'ks-planner__hint' }, t('reminders_not_running')) : null,
            h('div', { className: 'ks-planner__layout' },
                h(AgendaPane, {
                    state: agendaState,
                    error: agenda.error,
                    lists: agendaReady ? agenda.data.lists : null,
                    refreshing: agenda.loading || calendar.loading,
                    canCreate,
                    urgentBusy,
                    onRetry: agenda.reload,
                    onRefresh: reloadAll,
                    onOpen: openContent,
                    onUrgent: requestUrgent,
                    onAdd: () => addContent(null),
                }),
                h(CalendarPane, {
                    state: calendarState,
                    error: calendar.error,
                    month,
                    cells,
                    today,
                    focusDate: effectiveFocus,
                    isMobile,
                    onFocusDate: setFocusDate,
                    onMonth: setMonth,
                    onToday: () => {
                        setMonth(monthOf(today));
                        setFocusDate(today);
                    },
                    onRetry: calendar.reload,
                    onSelect: setSelectedDate,
                    onOpen: openContent,
                }),
            ),
            selectedCell ? h(DaySheet, {
                cell: selectedCell,
                today,
                canCreate,
                urgentBusy,
                onClose: () => setSelectedDate(null),
                onOpen: openContent,
                onUrgent: requestUrgent,
                onAdd: addContent,
            }) : null,
        );
    }

    KS.views.Planner = Planner;
}());
