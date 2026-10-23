/*
 * Konelsis - ISLER LISTESI (D-240, D-241; 23 Ekim 2026).
 *
 * Kullanici: "Isler kisminin arayuzunu react tablosu ile yapalim. Filament bunun
 * icin uygun degil. Filtreleme islemini ise ayni sekilde Akilli is ekle'de elle
 * eklenen alanlardaki gibi secilebilir yapicaz"; "Listelemede baslik felan yok.
 * Direk tek text alani olusturuldugu gibi olacak"; "isi arsivleme ozelligi eksik".
 * D-241: "ilgili satir istenirse duz yazi veya badge'li hale gelebilecek. Altinda
 * alan acilmasin ... kopyalama islemi her zaman sabit buton olarak kalabilir. Tum
 * liste duz yazi formatina donusturulebilir. Hangi formattaysa o formatta
 * kalabilir"; "Satira direk tikladigimizda duzenlenmek icin butona basilmali";
 * "isler sayfasinda pagination olmasi lazim ... 1 2 3 diye siralanmasi lazim";
 * "Filtre kismi tum Alanlar ve Baglantilar kismini kapsamalidir".
 *
 * - Her satir isin okunusudur (rozetli ya da duz yazi, tek metin), basinda sira
 *   numarasi. Satira tiklamak isi okuma penceresinde acar (cevaplar, belgeler);
 *   Duzenle yalniz yetkiliye. Kopyala her zaman duz yaziyi alir.
 * - Suzgecler Akilli is ekle'nin dugmeleri, Alanlar ve Baglantilar menuleri ve
 *   secim pencereleriyle secilir; secilenler rozet olarak durur.
 * - Sayfalar (1 2 3 ...) ve sayfa basina is; Arsiv: Aktif / Arsivlenenler / Tumu.
 *
 * Kok [data-kw-root="work-list"]; yapilandirma WorkAppConfig::workList().
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;
    const Q = window.KonelsisWorkQuick;

    if (!KW || !Q || window.KonelsisWorkList) {
        return;
    }

    const { h, cx, useApp, Btn, Seg, State, Portal } = KW;
    const { useState, useEffect, useRef, useCallback, useMemo } = KW.hooks;
    const { Icon } = Q;

    const ENTITY_FILTERS = ['person', 'unit', 'project', 'proposal', 'case', 'tender'];
    const RANGES = ['today', 'yesterday', 'this_week', 'last_week', 'this_month'];
    const PER_PAGE_KEY = 'konelsis.work-list.per-page';

    /** Suzgecin sunucuya giden degeri: kayit kimligi, alan degeri ya da "tur:kimlik" (talep eden / bekletici). */
    function filterValue(filter) {
        if (filter.type === 'requester' || filter.type === 'waiting') {
            return filter.kind + ':' + (filter.kind === 'text' ? (filter.value || filter.label) : filter.id);
        }

        return filter.id || filter.value;
    }

    const keyOf = (filter) => filter.type + ':' + filterValue(filter);

    function paramsOf(filters, archive, range, q, page, perPage) {
        const params = { archive, range: range || null, q: q.trim() || null, page, per_page: perPage };

        filters.forEach((filter) => {
            if (filter.type === 'team') {
                params.team = 1;

                return;
            }

            params[filter.type] = (params[filter.type] || []).concat([filterValue(filter)]);
        });

        return params;
    }

    function FilterBadge(props) {
        const { filter, onRemove } = props;
        const { t } = useApp();

        return h('span', {
            className: cx('kq-badge', 'kq-t-' + filter.type, filter.type === 'status' && 'kq-s-' + filter.value, filter.type === 'priority' && 'kq-p-' + filter.value),
        },
        h(Icon, { name: filter.type }),
        h('span', { className: 'kq-badge-text' }, Q.badgeText(filter, t)),
        h('button', { type: 'button', className: 'kq-badge-x', 'aria-label': t('qa_badge_remove'), onClick: onRemove }, '×'));
    }

    /** Tarih menusu (Bugun, Dun, Bu hafta ...); menu sayfanin ustunde acilir (portal). */
    function RangeMenu(props) {
        const { value, onChange } = props;
        const { t } = useApp();
        const [open, setOpen] = useState(null);
        const ref = useRef(null);
        const boxRef = useRef(null);
        const close = useCallback((event) => {
            if (event && event.target && boxRef.current && boxRef.current.contains(event.target)) { return; }
            setOpen(null);
        }, []);

        KW.useClickOutside(ref, close, !!open);
        KW.useEscape(() => setOpen(null), !!open);

        const pick = (range) => {
            setOpen(null);
            onChange(range);
        };

        return h('div', { className: 'kq-menu', ref },
            h('button', {
                type: 'button',
                className: cx('kq-tool', 'kq-tool-menu', 'kq-t-start', (open || value) && 'open'),
                'aria-expanded': String(!!open),
                'aria-haspopup': 'menu',
                onClick: (event) => setOpen(open ? null : event.currentTarget.getBoundingClientRect()),
            }, h(Icon, { name: 'start' }), value ? t('range_' + value) : t('wl_date'), h(Icon, { name: 'chevron', className: 'kq-caret' })),
            open ? h(Portal, null, h('div', {
                className: 'kq-menu-box',
                role: 'menu',
                ref: boxRef,
                style: { left: Math.max(8, Math.min(open.left, window.innerWidth - 228)) + 'px', top: (open.bottom + 6) + 'px' },
            }, [''].concat(RANGES).map((range) => h('button', {
                key: range || 'all',
                type: 'button',
                role: 'menuitemradio',
                'aria-checked': String(range === value),
                className: cx('kq-menu-item', 'kq-t-start', range === value && 'on'),
                onClick: () => pick(range),
            }, h('span', { className: 'kq-opt-ic' }, h(Icon, { name: 'start' })), range ? t('range_' + range) : t('wl_date_any'))))) : null,
        );
    }

    /** Sayfalar: ilk, son, bulunulanin iki yani; araliklar "…". */
    function Pager(props) {
        const { page, pages, onPage } = props;
        const { t } = useApp();

        if (pages <= 1) { return null; }

        const shown = [];

        for (let n = 1; n <= pages; n++) {
            if (n === 1 || n === pages || Math.abs(n - page) <= 1 || (page <= 3 && n <= 4) || (page >= pages - 2 && n >= pages - 3)) {
                shown.push(n);
            }
        }

        const items = [];
        let previous = 0;

        shown.forEach((n) => {
            if (n - previous > 1) { items.push('gap-' + n); }
            items.push(n);
            previous = n;
        });

        return h('nav', { className: 'kwl-pager', 'aria-label': t('wl_pages') },
            h('button', { type: 'button', className: 'kwl-page', disabled: page <= 1, 'aria-label': t('wl_prev'), title: t('wl_prev'), onClick: () => onPage(page - 1) }, h(Icon, { name: 'left' })),
            items.map((n) => (typeof n === 'string'
                ? h('span', { key: n, className: 'kwl-gap', 'aria-hidden': 'true' }, '…')
                : h('button', {
                    key: n,
                    type: 'button',
                    className: cx('kwl-page', n === page && 'on'),
                    'aria-current': n === page ? 'page' : undefined,
                    onClick: () => onPage(n),
                }, n))),
            h('button', { type: 'button', className: 'kwl-page', disabled: page >= pages, 'aria-label': t('wl_next'), title: t('wl_next'), onClick: () => onPage(page + 1) }, h(Icon, { name: 'right' })),
        );
    }

    function Row(props) {
        const { item, number, format, onFormat, onArchive, busy, extended } = props;
        const { t } = useApp();
        const plain = format === 'plain';
        // D-246: okuma penceresi satirin o anki biciminde acilir; Duzenle her zaman rozetli duzenler.
        const open = (mode) => window.dispatchEvent(new CustomEvent('kq-edit', { detail: { id: item.id, mode, format } }));

        return h('li', { className: cx('kwl-row', item.archived && 'archived', plain && 'plain') },
            h('span', { className: 'kwl-no', 'aria-hidden': 'true' }, number),
            h('div', {
                className: 'kwl-main',
                role: 'button',
                tabIndex: 0,
                title: t('wl_open'),
                onClick: () => open('view'),
                onKeyDown: (event) => { if (event.key === 'Enter' && event.target === event.currentTarget) { open('view'); } },
            },
            h(Q.WorkText, { segments: item.sentence, format }),
            item.archived ? h('span', { className: 'kwl-archived' }, h(Icon, { name: 'archive' }), t('wl_archived_badge')) : null),
            h('div', { className: 'kwl-side' },
                extended && (item.notes_count || item.files_count) ? h('button', {
                    type: 'button',
                    className: 'kwl-counts',
                    title: t('wl_open'),
                    onClick: () => open('view'),
                },
                item.notes_count ? h('span', { title: t('wl_replies_n', { n: item.notes_count }) }, h(Icon, { name: 'chat' }), item.notes_count) : null,
                item.files_count ? h('span', { title: t('wl_files_n', { n: item.files_count }) }, h(Icon, { name: 'document' }), item.files_count) : null) : null,
                h('div', { className: 'kwl-actions' },
                    h(Q.FormatToggle, { plain, onToggle: onFormat }),
                    h(Q.CopyButton, { text: Q.readingText(item.sentence, t) }),
                    item.can.update ? h('button', { type: 'button', className: 'kq-row-btn edit', title: t('wl_edit'), 'aria-label': t('wl_edit'), onClick: () => open('edit') }, h(Icon, { name: 'edit' })) : null,
                    item.can.archive ? h('button', {
                        type: 'button',
                        className: 'kq-row-btn',
                        title: item.archived ? t('wl_unarchive') : t('wl_archive'),
                        'aria-label': item.archived ? t('wl_unarchive') : t('wl_archive'),
                        disabled: busy,
                        onClick: onArchive,
                    }, h(Icon, { name: item.archived ? 'undo' : 'archive' })) : null,
                )),
        );
    }

    function WorkList() {
        const app = useApp();
        const { t, config, api } = app;
        const { ctx, data, loadError, load } = Q.useQuickContext();
        const perPageOptions = (config.options && config.options.per_page) || [25, 50, 100];
        const [filters, setFilters] = useState([]);
        const [archive, setArchive] = useState('active');
        const [range, setRange] = useState('');
        const [query, setQuery] = useState('');
        const [q, setQ] = useState('');
        const [page, setPage] = useState(1);
        const [perPage, setPerPage] = useState(() => {
            const saved = Number(KW.storage.get(PER_PAGE_KEY, perPageOptions[0]));

            return perPageOptions.indexOf(saved) !== -1 ? saved : perPageOptions[0];
        });
        const [items, setItems] = useState([]);
        const [total, setTotal] = useState(0);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);
        const [picker, setPicker] = useState(null);
        const [format, setFormat] = Q.useFormat();
        const [rowFormat, setRowFormat] = useState({});
        const [busy, setBusy] = useState(null);
        const ticket = useRef(0);
        const topRef = useRef(null);
        const firstPage = useRef(true);
        const features = config.features || {};

        // Arama yazildiktan kisa sure sonra; sayfa basa doner.
        useEffect(() => {
            const timer = window.setTimeout(() => {
                setQ(query);
                setPage(1);
            }, 300);

            return () => window.clearTimeout(timer);
        }, [query]);

        const params = useMemo(() => paramsOf(filters, archive, range, q, page, perPage), [filters, archive, range, q, page, perPage]);

        const fetchPage = useCallback(() => {
            ticket.current += 1;
            const mine = ticket.current;
            setLoading(true);
            setError(null);

            api.get(config.endpoints.list, params).then((json) => {
                if (mine !== ticket.current) { return; }

                setItems(json.items || []);
                setTotal(json.total || 0);
            }).catch((err) => {
                if (mine === ticket.current) { setError(err); }
            }).finally(() => {
                if (mine === ticket.current) { setLoading(false); }
            });
        }, [api, config, params]);

        useEffect(() => { fetchPage(); }, [fetchPage]);

        // Sayfa degisince listenin basina.
        useEffect(() => {
            if (firstPage.current) {
                firstPage.current = false;

                return;
            }

            if (topRef.current) { topRef.current.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
        }, [page]);

        // Akilli is penceresi (bu sayfada ya da ust cubukta) kaydedince liste tazelenir.
        useEffect(() => {
            const refresh = () => fetchPage();
            const counts = (event) => {
                const detail = (event && event.detail) || {};
                setItems((list) => list.map((row) => (row.id === detail.id ? { ...row, notes_count: detail.notes, files_count: detail.files } : row)));
            };
            window.addEventListener('kq-saved', refresh);
            window.addEventListener('kq-thread', counts);

            return () => {
                window.removeEventListener('kq-saved', refresh);
                window.removeEventListener('kq-thread', counts);
            };
        }, [fetchPage]);

        // Bildirimden gelen baglanti (?item=): is okuma penceresinde acilir.
        useEffect(() => {
            const id = Number(new URLSearchParams(window.location.search).get('item'));

            if (id > 0) {
                window.setTimeout(() => window.dispatchEvent(new CustomEvent('kq-edit', { detail: { id, mode: 'view' } })), 300);
            }
        }, []);

        const changeFilters = (next) => {
            setFilters(next);
            setPage(1);
        };

        const addFilter = (item) => {
            const filter = { type: item.type, id: item.id || null, value: item.value === undefined || item.value === null ? null : item.value, label: item.label, text: item.text || null, kind: item.kind || null };

            if (filters.some((other) => keyOf(other) === keyOf(filter))) { return; }

            changeFilters(filters.concat([filter]));
        };

        const choose = (type, rect) => {
            if (type === 'team') {
                addFilter({ type: 'team', value: '1', label: t('qa_type_team') });

                return;
            }

            setPicker(picker && picker.type === type ? null : { type, rect });
        };

        const changeFormat = (next) => {
            setFormat(next);
            setRowFormat({});
        };

        const toggleArchive = (item) => {
            setBusy(item.id);
            const url = app.url(item.archived ? config.endpoints.unarchive : config.endpoints.archive, item.id);

            api.post(url, {}).then(() => {
                const archived = !item.archived;

                if (archive === 'all') {
                    setItems((list) => list.map((row) => (row.id === item.id ? { ...row, archived } : row)));
                } else {
                    fetchPage();
                }

                app.toast({
                    text: archived ? t('wl_archived_toast') : t('wl_unarchived_toast'),
                    tone: 'ok',
                    action: archived ? { label: t('wl_undo'), onClick: () => api.post(app.url(config.endpoints.unarchive, item.id), {}).then(fetchPage).catch(app.fail) } : null,
                });
            }).catch(app.fail).finally(() => setBusy(null));
        };

        // D-245: is grubu yalniz secenekleri varsa (kapali: kategoriyle birlesti).
        const fieldTypes = ['status', 'start', 'due', 'hours', 'category', 'requester', 'waiting'].concat(ctx.extended ? ['priority', 'team'] : []).concat(ctx.extended && (ctx.disciplines || []).length ? ['discipline'] : []);
        const pages = Math.max(1, Math.ceil(total / perPage));
        const from = total ? (page - 1) * perPage + 1 : 0;

        const toolbar = h('div', { className: 'kq-toolbar compact kwl-tools', role: 'toolbar', 'aria-label': t('wl_filters') },
            Q.ENTITY_TYPES.filter((type) => ENTITY_FILTERS.indexOf(type) !== -1).map((type) => h('button', {
                key: type,
                type: 'button',
                className: cx('kq-tool', 'kq-t-' + type, picker && picker.type === type && 'open'),
                disabled: !data,
                onClick: (event) => choose(type, event.currentTarget.getBoundingClientRect()),
            }, h(Icon, { name: type }), Q.typeLabel(t, type))),
            h('span', { className: 'kq-sep', 'aria-hidden': 'true' }),
            h(Q.ToolMenu, { label: t('qa_menu_fields'), icon: 'fields', types: fieldTypes, onChoose: choose }),
            h(Q.ToolMenu, { label: t('qa_menu_links'), icon: 'link', types: ctx.linkTypes || [], onChoose: choose }),
            h(RangeMenu, { value: range, onChange: (next) => { setRange(next); setPage(1); } }),
        );

        return h('section', { className: 'kq kwl', 'aria-label': t('wl_title') },
            h('div', { className: 'kwl-head', ref: topRef },
                h('label', { className: 'kq-pop-search kwl-search' },
                    h(Icon, { name: 'search' }),
                    h('input', { value: query, placeholder: t('wl_search'), onChange: (event) => setQuery(event.target.value) })),
                h(Q.FormatSwitch, { value: format, onChange: changeFormat }),
                features.archive ? h(Seg, {
                    label: t('wl_archive_label'),
                    value: archive,
                    onChange: (next) => { setArchive(next); setPage(1); },
                    items: ['active', 'archived', 'all'].map((value) => ({ value, label: t('wl_archive_' + value) })),
                }) : null,
            ),
            loadError ? h(State, { error: loadError, onRetry: load }) : toolbar,
            filters.length ? h('div', { className: 'kwl-filters' },
                filters.map((filter) => h(FilterBadge, { key: keyOf(filter), filter, onRemove: () => changeFilters(filters.filter((other) => keyOf(other) !== keyOf(filter))) })),
                h('button', { type: 'button', className: 'kwl-clear', onClick: () => { changeFilters([]); setRange(''); setQuery(''); } }, t('wl_clear')),
            ) : null,
            h('p', { className: 'kwl-count' }, total ? t('wl_count_range', { from, to: Math.min(total, page * perPage), total }) : t('wl_count', { n: 0 })),
            error ? h(State, { error, onRetry: fetchPage }) : null,
            !error && !loading && !items.length ? h('div', { className: 'kwl-empty' }, h(Icon, { name: 'search' }), t('wl_empty')) : null,
            h('ol', { className: cx('kwl-rows', loading && 'loading') }, items.map((item, index) => h(Row, {
                key: item.id,
                item,
                number: from + index,
                extended: !!features.extended && !!features.thread,
                format: rowFormat[item.id] || format,
                busy: busy === item.id,
                onFormat: () => setRowFormat((map) => ({ ...map, [item.id]: (map[item.id] || format) === 'plain' ? 'badge' : 'plain' })),
                onArchive: () => toggleArchive(item),
            }))),
            loading && !items.length ? h(State, { loading: true }) : null,
            total ? h('div', { className: 'kwl-foot' },
                h('label', { className: 'kwl-per-page' },
                    h('span', null, t('wl_per_page')),
                    h('select', {
                        className: 'kw-input',
                        value: perPage,
                        onChange: (event) => {
                            const next = Number(event.target.value);
                            setPerPage(next);
                            setPage(1);
                            KW.storage.set(PER_PAGE_KEY, next);
                        },
                    }, perPageOptions.map((n) => h('option', { key: n, value: n }, n)))),
                h(Pager, { page, pages, onPage: setPage }),
            ) : null,
            picker ? h(Q.PickerPop, {
                type: picker.type,
                rect: picker.rect,
                ctx,
                onClose: (event) => {
                    if (event && event.target && event.target.closest && event.target.closest('.kq-tool, .kq-menu')) { return; }
                    setPicker(null);
                },
                onPick: (item) => { setPicker(null); addFilter(item); },
            }) : null,
        );
    }

    window.KonelsisWorkList = { WorkList };

    KW.mount('work-list', WorkList);
}());
