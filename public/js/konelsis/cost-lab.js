/*
 * Konelsis - UI DENEME > MALIYET KALEMLERI (D-187, 9 Ekim 2026).
 *
 * Kullanici istegi: Excel maliyet listesinin Birim_Fiyat_GES_KESIF sayfasi
 * Urun/Hizmet, "Idari Kadro ve Genel Giderler" sayfasi Idari Kadro ve Genel
 * Giderler sekmelerine bolunur; kalemler kategorileri bozulmadan sik bir onay
 * listesinde (D-157 kontrol listesi tahtasi gibi) gorunur; her kalemde hangi
 * departmanlarin onay verdigi bilinir; genel toplamlar ve Icmal ozeti farkli
 * sunumlarla gosterilir. Numarali tasarimlar yan yana karsilastirilir
 * ("3'u sectim"). En onemli kural: Excel yuklenince kalemler var olan
 * katalogla eslestirilir, her seferinde yeni kayit acilmaz (6. tasarim ve
 * her satirdaki katalog isareti).
 *
 * Veri: CostLabConfig::make() -> data (Excel'den bir kez ayristirilmis gercek
 * kalemler). Onay durumlari ve katalog eslestirmesi DENEME verisidir
 * (belirlenimci); tiklamalar yalniz ekrani degistirir, hicbir sey kaydedilmez.
 * Cekirdek: dash-core.js (KD); stil: konelsis-dash.css (kd-cl- on eki).
 */
(function () {
    'use strict';

    const KD = window.KonelsisDash;

    if (!KD) {
        return;
    }

    const { h, Fragment, cx, useApp, Icon, Seg, Modal, Dropdown, store } = KD;
    const { useState, useMemo, useEffect, useCallback, useRef } = KD.hooks;
    const CHECK = '✓';
    const CROSS = '✗';
    const DOT = '•';

    const Ctx = window.React.createContext(null);
    const useLab = () => window.React.useContext(Ctx);

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    function hash(text) {
        let value = 2166136261;

        for (let i = 0; i < text.length; i++) {
            value ^= text.charCodeAt(i);
            value = Math.imul(value, 16777619);
        }

        return value >>> 0;
    }

    function lower(text) {
        return String(text || '').toLocaleLowerCase('tr-TR');
    }

    /** Excel'in tek kur tablosu: 1 birim kac TL (TRY 1, USD, EUR). */
    function makeConvert(rates) {
        const table = { TRY: 1, USD: rates.USD, EUR: rates.EUR };

        return (amount, from, to) => {
            if (amount === null || amount === undefined) { return null; }
            if (!from || from === to) { return amount; }
            return (amount * (table[from] || 1)) / (table[to] || 1);
        };
    }

    /* ------------------------------------------------------------------ */
    /* Model: sekme > bolum > grup > satir                                  */
    /* ------------------------------------------------------------------ */

    function buildModel(data) {
        const tabs = {
            products: { key: 'products', icon: 'product', sections: [], lines: [] },
            staff: { key: 'staff', icon: 'staff', sections: [], lines: [] },
            expenses: { key: 'expenses', icon: 'expense', sections: [], lines: [] },
        };

        (data.products || []).forEach((cat) => {
            const sec = { id: 'p-' + cat.no, tab: 'products', no: cat.no, name: cat.title || cat.name, depts: cat.departments, groups: [] };

            cat.groups.forEach((g, gi) => {
                const grp = {
                    id: sec.id + '-' + gi, no: g.no, name: g.summary_name || g.name, qty: g.qty, unit: g.unit, lines: [],
                    flat: cat.groups.length === 1 && g.no === cat.no,
                };

                g.items.forEach((it) => {
                    const line = {
                        id: 'p' + it.row, row: it.row, tab: 'products', sec, grp, no: it.no, name: it.name, note: it.note,
                        ref: it.ref, page: it.page, qty: it.qty, unit: it.unit, currency: it.currency, price: it.price,
                        discount: it.discount, markup: it.markup, unitBase: it.unit_cost, base: 'USD',
                        cost: it.total_cost, sale: it.total_sale, mark: it.mark, match: it.match, depts: cat.departments,
                    };
                    line.inScope = line.cost > 0;
                    grp.lines.push(line);
                    tabs.products.lines.push(line);
                });

                sec.groups.push(grp);
            });

            tabs.products.sections.push(sec);
        });

        const usd = (data.rates || {}).USD || 1;
        const pushFlat = (tab, prefix, group, depts) => {
            const sec = { id: prefix + '-' + group.row, tab: tab.key, no: group.no, name: group.name, depts, groups: [] };
            const grp = { id: sec.id + '-0', no: group.no, name: group.name, lines: [], flat: true };

            group.items.forEach((it) => {
                const line = {
                    id: prefix + it.row, row: it.row, tab: tab.key, sec, grp, no: it.no, name: it.name, note: it.note,
                    qty: it.count, duration: it.duration, unit: null, currency: 'TRY', price: it.unit_price, sub: it.subtotal,
                    unitBase: it.unit_price !== null ? it.unit_price / usd : null, base: 'USD',
                    cost: it.total_usd, costTry: it.total_try, sale: null, mark: it.mark, match: it.match, depts,
                };
                line.inScope = line.cost > 0;
                grp.lines.push(line);
                tab.lines.push(line);
            });

            sec.groups.push(grp);
            tab.sections.push(sec);
        };

        if (data.staff) {
            pushFlat(tabs.staff, 's', data.staff, data.staff.departments);
        }

        (data.expenses || []).forEach((group) => pushFlat(tabs.expenses, 'e', group, group.departments));

        const all = tabs.products.lines.concat(tabs.staff.lines, tabs.expenses.lines);
        const mwp = ((data.icmal || {}).inputs || []).reduce((found, row) => (row.name === 'DC' ? row.value : found), null);

        return { tabs, all, mwp };
    }

    /* ------------------------------------------------------------------ */
    /* Onay (deneme): belirlenimci baslangic + ekrandaki degisiklikler       */
    /* ------------------------------------------------------------------ */

    const SEED_RATES = [[84, 3], [61, 4], [44, 5]];

    function seedState(line, dept, index) {
        if (!line.inScope) { return 'wait'; }
        const value = hash(line.id + '|' + dept) % 100;
        const rate = SEED_RATES[Math.min(index, SEED_RATES.length - 1)];

        if (value < rate[1]) { return 'no'; }
        return value < rate[1] + rate[0] ? 'ok' : 'wait';
    }

    function seedWhen(line, dept) {
        const value = hash(dept + '#' + line.id);
        const pad = (n) => (n < 10 ? '0' + n : String(n));

        return pad(1 + (value % 9)) + '.10.2026 ' + pad(8 + ((value >> 4) % 10)) + ':' + pad((value >> 8) % 60);
    }

    function lineCounts(lines, statusOf) {
        const out = {};

        lines.forEach((line) => {
            if (!line.inScope) { return; }
            line.depts.forEach((dept) => {
                const row = out[dept] || (out[dept] = { ok: 0, no: 0, wait: 0, total: 0 });
                row[statusOf(line, dept)]++;
                row.total++;
            });
        });

        return out;
    }

    function sumLines(lines) {
        return lines.reduce((acc, line) => {
            acc.cost += line.cost || 0;
            acc.sale += line.sale || 0;
            acc.n++;
            if (line.inScope) { acc.scope++; }
            return acc;
        }, { cost: 0, sale: 0, n: 0, scope: 0 });
    }

    function matchCounts(lines, matchOf) {
        return lines.reduce((acc, line) => { acc[matchOf(line).status]++; acc.total++; return acc; }, { matched: 0, new: 0, similar: 0, total: 0 });
    }

    /*
     * Fiyat durumu (D-188, kullanici): Excel'de yesil satir = fiyatindan emin olunan
     * (kesin), sari satir = henuz net karar verilmemis (netlesmedi). Renksiz satirlar
     * bu projede tutari olmayanlardir.
     */
    function priceOf(line) {
        return line.mark === 'yellow' ? 'open' : (line.mark === 'green' ? 'firm' : null);
    }

    function priceSplit(lines) {
        return lines.reduce((acc, line) => {
            if (!line.inScope) { return acc; }
            const state = priceOf(line);
            if (state === 'open') { acc.open += line.cost || 0; acc.nOpen++; }
            if (state === 'firm') { acc.firm += line.cost || 0; acc.nFirm++; }
            return acc;
        }, { firm: 0, open: 0, nFirm: 0, nOpen: 0 });
    }

    /* ------------------------------------------------------------------ */
    /* Kucuk parcalar                                                       */
    /* ------------------------------------------------------------------ */

    function Money(props) {
        const { fmt } = useApp();
        const lab = useLab();
        const { usd, compact, strong, muted } = props;

        if (usd === null || usd === undefined) { return h('span', { className: 'kd-muted' }, '–'); }

        return h('span', { className: cx('kd-cl-amt', { 'kd-strong': strong, 'kd-muted': muted }) }, fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur, compact));
    }

    const unitFormats = {};

    /**
     * Birim fiyat (D-180 kurali; birim fiyatta 1'in altindaki tutarlar 4 basamaga kadar,
     * MoneyInput ->decimals(4) gibi): "334,82 $", "0,0563 $", "60.000 [TL]".
     */
    function unitPrice(amount, currency, symbols) {
        if (amount === null || amount === undefined) { return '–'; }
        const small = Math.abs(amount) > 0 && Math.abs(amount) < 1;
        const whole = !small && Number.isInteger(Math.round(amount * 100) / 100);
        const key = (small ? 's' : 'n') + (whole ? 'w' : 'f');
        const nf = unitFormats[key] || (unitFormats[key] = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: small ? 4 : 2 }));

        return nf.format(amount) + (currency ? ' ' + ((symbols || {})[currency] || currency) : '');
    }

    /** Excel'deki birim metninde ISO kodu yerine simge (D-180): "USD/Ton" -> "$/Ton". */
    function unitText(unit) {
        return String(unit || '').replace(/\bUSD\b/g, '$').replace(/\bEURO?\b/g, '€').replace(/\bTL\b/g, '₺');
    }

    function pct(fmt, value, digits) {
        return value === null || value === undefined || isNaN(value) ? '–' : '%' + fmt.num(value * 100, digits === undefined ? 1 : digits);
    }

    function Bar(props) {
        const { value, max, tone } = props;
        const width = max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;

        return h('span', { className: cx('kd-cl-bar', tone) }, h('i', { style: { width: width + '%' } }));
    }

    /** Departman isaretleri: onaylandi / bekliyor / reddedildi; tiklayinca siradaki duruma gecer. */
    function ApprovalChips(props) {
        const { line } = props;
        const { t } = useApp();
        const lab = useLab();

        if (!line.inScope) {
            return h('span', { className: 'kd-cl-out', title: t('out_of_scope_hint') }, t('out_of_scope'));
        }

        return h('span', { className: 'kd-cl-chips' }, line.depts.map((dept) => {
            const def = lab.departments[dept] || { name: dept, short: dept, title: '' };
            const state = lab.statusOf(line, dept);
            const who = lab.whoOf(line, dept);
            const title = def.name + ': ' + t('state_' + state) + (state !== 'wait' ? ' · ' + who : '') + ' · ' + t('click_cycles');

            return h('button', {
                key: dept,
                type: 'button',
                className: cx('kd-cl-chip', state, { full: lab.fullChips }),
                title,
                'aria-label': title,
                onClick: (event) => { event.stopPropagation(); lab.cycle(line, dept); },
            }, h('b', null, state === 'ok' ? CHECK : (state === 'no' ? CROSS : DOT)), lab.fullChips ? def.name : def.short);
        }));
    }

    /** Departman onay ilerlemesi: "Satin Alma 34/52". */
    function DeptProgress(props) {
        const { counts, layout } = props;
        const { t } = useApp();
        const lab = useLab();
        const keys = Object.keys(counts);

        if (!keys.length) { return null; }

        return h('span', { className: cx('kd-cl-prog', layout) }, keys.map((dept) => {
            const row = counts[dept];
            const def = lab.departments[dept] || { name: dept, short: dept };
            const done = row.ok === row.total;

            return h('span', { key: dept, className: cx('kd-cl-pr', { done, bad: row.no > 0 }), title: t('progress_title', { dept: def.name, ok: row.ok, total: row.total, no: row.no, wait: row.wait }) },
                h('em', null, layout === 'wide' ? def.name : def.short),
                h('span', { className: 'kd-cl-pr-bar' }, h('i', { className: 'ok', style: { width: (row.total ? (row.ok / row.total) * 100 : 0) + '%' } }), h('i', { className: 'no', style: { width: (row.total ? (row.no / row.total) * 100 : 0) + '%' } })),
                h('b', null, row.ok + '/' + row.total),
            );
        }));
    }

    /** Grup / bolum icin toplu onay (deneme). */
    function BulkApprove(props) {
        const { lines, depts } = props;
        const { t } = useApp();
        const lab = useLab();

        return h(Dropdown, {
            align: 'end',
            trigger: (open, toggle) => h('button', { type: 'button', className: cx('kd-btn', 'kd-cl-mini', { on: open }), onClick: (event) => { event.stopPropagation(); toggle(); } }, h(Icon, { name: 'shield' }), t('bulk')),
        }, (close) => h('div', { className: 'kd-menu', onClick: (event) => event.stopPropagation() },
            depts.map((dept) => {
                const pending = lines.filter((line) => line.inScope && lab.statusOf(line, dept) !== 'ok').length;
                const def = lab.departments[dept] || { name: dept };

                return h('button', { key: dept, type: 'button', className: 'kd-cl-menu-btn', disabled: pending === 0, onClick: () => { lab.bulk(lines, [dept]); close(); } },
                    h('span', null, t('bulk_as', { dept: def.name })), h('em', null, t('pending_n', { n: pending })));
            }),
            h('button', { type: 'button', className: 'kd-cl-menu-btn strong', onClick: () => { lab.bulk(lines, depts); close(); } }, t('bulk_all')),
        ));
    }

    /** Katalog eslesmesi: Katalogda / Yeni / Benzer? (secim penceresini acar). */
    function MatchChip(props) {
        const { line, withName } = props;
        const { t } = useApp();
        const lab = useLab();
        const match = lab.matchOf(line);
        const icon = match.status === 'matched' ? 'link' : (match.status === 'new' ? 'new' : 'similar');
        const title = match.status === 'matched'
            ? t('match_matched_title', { name: match.catalog, path: match.path, uses: match.uses })
            : (match.status === 'new' ? t('match_new_title') : t('match_similar_title', { n: (match.suggestions || []).length }));

        return h('span', { className: 'kd-cl-match-wrap' },
            h('button', {
                type: 'button',
                className: cx('kd-cl-match', match.status),
                title,
                onClick: (event) => { event.stopPropagation(); lab.openMatch(line); },
            }, h(Icon, { name: icon }), withName === false ? null : h('span', null, t('match_' + match.status))),
            // Katalog sutunu: ad farkliysa katalogdaki ad, ayniysa katalogdaki yeri (kategori > grup).
            withName && match.status === 'matched' ? h('span', { className: 'kd-cl-cat-name', title: match.catalog + ' · ' + match.path }, match.catalog !== line.name ? match.catalog : match.path) : null,
            withName && match.status === 'similar' && match.suggestions && match.suggestions[0] ? h('span', { className: 'kd-cl-cat-name', title: match.suggestions[0].path }, match.suggestions[0].name + '?') : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Kalem tablosu                                                        */
    /* ------------------------------------------------------------------ */

    const COLS = {
        products: ['no', 'name', 'catalog', 'qty', 'unit_cost', 'cost', 'sale', 'markup', 'approvals'],
        staff: ['no', 'position', 'catalog', 'people', 'months', 'salary', 'monthly', 'cost', 'approvals'],
        expenses: ['no', 'item', 'catalog', 'count', 'duration', 'unit_price', 'subtotal', 'cost', 'approvals'],
    };

    const NUMERIC = { qty: 1, unit_cost: 1, cost: 1, sale: 1, markup: 1, people: 1, months: 1, salary: 1, monthly: 1, count: 1, duration: 1, unit_price: 1, subtotal: 1 };

    function visibleLines(lab, group) {
        return group.lines.filter((line) => lab.passes(line));
    }

    function LineRow(props) {
        const { line, tab, narrow } = props;
        const { fmt, t } = useApp();
        const lab = useLab();
        const cells = [];
        const disp = (amount, currency) => (amount === null || amount === undefined ? '–' : fmt.money(lab.convert(amount, currency, lab.cur), lab.cur));
        const orig = (amount, currency) => (currency && currency !== lab.cur && amount !== null && amount !== undefined ? unitPrice(amount, currency, lab.symbols) : null);

        const parentNo = line.grp.flat ? line.sec.no : line.grp.no;
        cells.push(h('td', { key: 'no', className: 'kd-mono kd-cl-no' }, line.no === parentNo ? line.no : parentNo + '.' + line.no));
        const price = line.inScope ? priceOf(line) : null;
        cells.push(h('td', { key: 'name', className: 'kd-cl-name' }, h('span', { className: 'kd-cl-namebox' },
            line.mark ? h('i', { className: cx('kd-cl-mark', line.mark), title: t('mark_' + line.mark) }) : null,
            h('span', { className: 'kd-cl-label', title: line.name }, line.name),
            price === 'open' ? h('span', { className: 'kd-cl-price open', title: t('mark_yellow') }, t('price_open_short')) : null,
            line.note ? h('span', { className: 'kd-cl-note', title: line.note }, h(Icon, { name: 'note' })) : null,
        )));
        cells.push(h('td', { key: 'catalog', className: 'kd-cl-catcell' }, h(MatchChip, { line, withName: !narrow })));

        if (tab === 'products') {
            cells.push(h('td', { key: 'qty', className: 'kd-num-c' }, line.qty ? fmt.num(line.qty, 2) : '–', line.unit ? h('small', null, ' ' + line.unit) : null));
            if (!narrow) {
                cells.push(h('td', { key: 'uc', className: 'kd-num-c', title: line.price !== null && line.currency ? t('orig_price', { price: unitPrice(line.price, line.currency, lab.symbols) }) : undefined },
                    line.unitBase ? unitPrice(lab.convert(line.unitBase, 'USD', lab.cur), lab.cur, lab.symbols) : '–',
                    line.unitBase && orig(line.price, line.currency) ? h('small', { className: 'kd-cl-orig' }, orig(line.price, line.currency)) : null,
                ));
            }
            cells.push(h('td', { key: 'cost', className: 'kd-num-c kd-strong' }, line.cost ? disp(line.cost, 'USD') : h('span', { className: 'kd-muted' }, '0')));
            if (!narrow) {
                cells.push(h('td', { key: 'sale', className: 'kd-num-c' }, line.sale ? disp(line.sale, 'USD') : h('span', { className: 'kd-muted' }, '0')));
                cells.push(h('td', { key: 'mk', className: 'kd-num-c kd-muted' }, pct(fmt, line.markup)));
            }
        } else {
            cells.push(h('td', { key: 'q', className: 'kd-num-c' }, line.qty ? fmt.num(line.qty, 2) : '–'));
            cells.push(h('td', { key: 'd', className: 'kd-num-c' }, line.duration ? fmt.num(line.duration, 2) : '–'));
            cells.push(h('td', { key: 'p', className: 'kd-num-c' }, line.price !== null ? disp(line.price, 'TRY') : '–', line.price !== null && orig(line.price, 'TRY') ? h('small', { className: 'kd-cl-orig' }, orig(line.price, 'TRY')) : null));
            cells.push(h('td', { key: 's', className: 'kd-num-c' }, line.sub !== null && line.sub !== undefined ? disp(line.sub, 'TRY') : '–'));
            cells.push(h('td', { key: 'cost', className: 'kd-num-c kd-strong' }, line.cost ? disp(line.cost, 'USD') : h('span', { className: 'kd-muted' }, '0')));
        }

        cells.push(h('td', { key: 'ap', className: 'kd-cl-apcell' }, h(ApprovalChips, { line })));

        return h('tr', { className: cx('kd-cl-line', { out: !line.inScope, 'price-open': price === 'open', hit: lab.query && lower(line.name).indexOf(lower(lab.query)) !== -1 }) }, cells);
    }

    function SubtotalCells(props) {
        const { lines, tab, label, narrow } = props;
        const { fmt } = useApp();
        const sum = sumLines(lines);
        const markup = sum.cost > 0 && sum.sale ? (sum.sale - sum.cost) / sum.cost : null;

        if (tab === 'products' && narrow) {
            return [
                h('td', { key: 'l', className: 'kd-num-c kd-muted' }, label || null),
                h('td', { key: 'c', className: 'kd-num-c kd-strong' }, h(Money, { usd: sum.cost })),
            ];
        }

        if (tab === 'products') {
            return [
                h('td', { key: 'l', className: 'kd-num-c kd-muted', colSpan: 2 }, label || null),
                h('td', { key: 'c', className: 'kd-num-c kd-strong' }, h(Money, { usd: sum.cost })),
                h('td', { key: 's', className: 'kd-num-c kd-strong' }, h(Money, { usd: sum.sale })),
                h('td', { key: 'm', className: 'kd-num-c' }, pct(fmt, markup)),
            ];
        }

        return [
            h('td', { key: 'l', className: 'kd-num-c kd-muted', colSpan: 4 }, label || null),
            h('td', { key: 'c', className: 'kd-num-c kd-strong' }, h(Money, { usd: sum.cost })),
        ];
    }

    function HeadRow(props) {
        const { kind, item, tab, lines, depts, open, onToggle, allLines, narrow } = props;
        const { t, fmt } = useApp();
        const lab = useLab();
        const counts = lineCounts(allLines || lines, lab.statusOf);
        const hidden = (allLines || lines).length - lines.length;
        const price = priceSplit(allLines || lines);

        return h('tr', { className: cx('kd-cl-head', kind), id: 'kd-cl-' + item.id, onClick: onToggle },
            h('td', { className: 'kd-mono kd-cl-no' }, h('span', { className: cx('kd-cl-chev', { open }) }, h(Icon, { name: 'chevron' })), item.no),
            h('td', { className: 'kd-cl-name' },
                h('b', null, item.name),
                h('span', { className: 'kd-cl-count' }, t('lines_n', { n: lines.length }) + (hidden > 0 ? ' · ' + t('hidden_n', { n: hidden }) : '')),
                item.qty ? h('span', { className: 'kd-cl-count' }, '· ' + fmt.num(item.qty, 2) + (item.unit ? ' ' + item.unit : '')) : null,
                price.nOpen ? h('span', { className: 'kd-cl-price open', title: t('price_open_title', { n: price.nOpen, amount: fmt.money(lab.convert(price.open, 'USD', lab.cur), lab.cur) }) }, t('price_open_n', { n: price.nOpen })) : null,
            ),
            h('td', { className: 'kd-cl-catcell' }, h(DeptProgress, { counts })),
            h(SubtotalCells, { lines: allLines || lines, tab, narrow }),
            h('td', { className: 'kd-cl-apcell' }, h(BulkApprove, { lines: allLines || lines, depts })),
        );
    }

    function CostTable(props) {
        const { tab, narrow } = props;
        const { t } = useApp();
        const lab = useLab();
        const model = lab.model.tabs[tab];
        const rows = [];
        // Dar listede (7. tasarim, sag panel acik) birim maliyet, satis ve kar sutunlari gizli; satis ve kar seritte / sag panelde.
        const cols = narrow && tab === 'products' ? COLS[tab].filter((col) => col !== 'unit_cost' && col !== 'sale' && col !== 'markup') : COLS[tab];
        let shown = 0;

        model.sections.forEach((sec) => {
            const secAll = [].concat.apply([], sec.groups.map((g) => g.lines));
            const secLines = secAll.filter((line) => lab.passes(line));

            if (!secLines.length && (lab.filtering || lab.query)) { return; }

            const secOpen = !lab.collapsed[sec.id];
            rows.push(h(HeadRow, { key: sec.id, kind: 'sec', item: sec, tab, narrow, lines: secLines, allLines: secAll, depts: sec.depts, open: secOpen, onToggle: () => lab.toggle(sec.id) }));

            if (!secOpen) { return; }

            sec.groups.forEach((grp) => {
                const lines = visibleLines(lab, grp);

                if (!lines.length && (lab.filtering || lab.query || !lab.showZero)) {
                    if (!grp.flat && !lines.length && grp.lines.length) {
                        rows.push(h('tr', { key: grp.id, className: 'kd-cl-head grp empty' },
                            h('td', { className: 'kd-mono kd-cl-no' }, grp.no),
                            h('td', { className: 'kd-cl-name', colSpan: cols.length - 1 }, h('span', null, grp.name), h('span', { className: 'kd-cl-count' }, t('group_zero', { n: grp.lines.length }))),
                        ));
                    }
                    return;
                }

                const grpOpen = !lab.collapsed[grp.id];

                if (!grp.flat) {
                    rows.push(h(HeadRow, { key: grp.id, kind: 'grp', item: grp, tab, narrow, lines, allLines: grp.lines, depts: sec.depts, open: grpOpen, onToggle: () => lab.toggle(grp.id) }));
                }

                if (grp.flat || grpOpen) {
                    lines.forEach((line) => { shown++; rows.push(h(LineRow, { key: line.id, line, tab, narrow })); });
                }
            });
        });

        return h('div', { className: 'kd-tablewrap kd-cl-wrap' },
            h('table', { className: cx('kd-table', 'kd-cl-table', lab.density, { narrow }) },
                h('thead', null, h('tr', null, cols.map((col) => h('th', { key: col, className: NUMERIC[col] ? 'kd-num-c' : null }, col === 'cost' || col === 'sale' || col === 'unit_cost' || col === 'salary' || col === 'monthly' || col === 'unit_price' || col === 'subtotal' ? t('col_' + col) + ' (' + lab.symbol + ')' : t('col_' + col))))),
                h('tbody', null, rows.length ? rows : h('tr', null, h('td', { colSpan: cols.length, className: 'kd-empty' }, t('no_match')))),
            ),
            h('div', { className: 'kd-cl-foot' }, t('shown_n', { n: shown })),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Arac cubugu, sekmeler                                                */
    /* ------------------------------------------------------------------ */

    function Toolbar(props) {
        const { tab, extra } = props;
        const { t } = useApp();
        const lab = useLab();
        const model = lab.model.tabs[tab];
        const zero = model.lines.filter((line) => !line.inScope).length;
        const deptKeys = Array.from(new Set([].concat.apply([], model.sections.map((sec) => sec.depts))));
        const price = priceSplit(model.lines);

        return h('div', { className: 'kd-toolbar kd-cl-toolbar' },
            h('label', { className: 'kd-search' }, h(Icon, { name: 'search' }), h('input', { type: 'search', value: lab.query, placeholder: t('search'), onChange: (event) => lab.setQuery(event.target.value) })),
            h('label', { className: 'kd-cl-select' }, h(Icon, { name: 'filter' }),
                h('select', { value: lab.pending, onChange: (event) => lab.setPending(event.target.value) },
                    h('option', { value: '' }, t('pending_off')),
                    h('option', { value: 'any' }, t('pending_any')),
                    deptKeys.map((dept) => h('option', { key: dept, value: dept }, t('pending_dept', { dept: (lab.departments[dept] || {}).name || dept }))),
                ),
            ),
            h(Seg, {
                size: 'sm',
                value: lab.matchFilter,
                onChange: lab.setMatchFilter,
                label: t('match_filter'),
                options: [
                    { value: 'all', label: t('match_all') },
                    { value: 'new', label: t('match_new') },
                    { value: 'similar', label: t('match_similar') },
                ],
            }),
            h(Seg, {
                size: 'sm',
                value: lab.priceFilter,
                onChange: lab.setPriceFilter,
                label: t('price_filter'),
                options: [
                    { value: 'all', label: t('price_all') },
                    { value: 'firm', label: t('price_firm'), count: price.nFirm },
                    { value: 'open', label: t('price_open'), count: price.nOpen },
                ],
            }),
            h('label', { className: 'kd-check kd-cl-toggle', title: t('show_zero_hint') }, h('input', { type: 'checkbox', checked: lab.showZero, onChange: (event) => lab.setShowZero(event.target.checked) }), t('show_zero', { n: zero })),
            h('span', { className: 'kd-spacer' }),
            extra || null,
            h('button', { type: 'button', className: 'kd-btn kd-cl-mini', title: t('expand_all'), onClick: () => lab.expandAll(true) }, h(Icon, { name: 'expand' })),
            h('button', { type: 'button', className: 'kd-btn kd-cl-mini', title: t('collapse_all'), onClick: () => lab.expandAll(false, tab) }, h(Icon, { name: 'collapse' })),
            h(Seg, {
                size: 'sm',
                value: lab.density,
                onChange: lab.setDensity,
                label: t('density'),
                options: [{ value: 'tight', label: t('density_tight') }, { value: 'comfort', label: t('density_comfort') }],
            }),
            h('label', { className: 'kd-check kd-cl-toggle' }, h('input', { type: 'checkbox', checked: lab.fullChips, onChange: (event) => lab.setFullChips(event.target.checked) }), t('full_chips')),
        );
    }

    function TabBar(props) {
        const { tabs, value, onChange, mini, progress } = props;
        const { t, fmt } = useApp();
        const lab = useLab();

        return h('div', { className: 'kd-cl-tabs', role: 'tablist' }, tabs.map((key) => {
            const model = lab.model.tabs[key];
            const sum = model ? sumLines(model.lines) : null;
            const counts = model ? lineCounts(model.lines, lab.statusOf) : {};
            const all = Object.keys(counts).reduce((acc, dept) => { acc.ok += counts[dept].ok; acc.total += counts[dept].total; return acc; }, { ok: 0, total: 0 });

            return h('button', { key, type: 'button', role: 'tab', 'aria-selected': value === key ? 'true' : 'false', className: cx('kd-cl-tab', { on: value === key }), onClick: () => onChange(key) },
                h(Icon, { name: key === 'icmal' ? 'summary' : (key === 'upload' ? 'upload' : model.icon) }),
                h('span', null, t('tab_' + key)),
                model ? h('em', null, model.lines.filter((line) => line.inScope).length) : null,
                mini && sum ? h('small', { className: 'kd-cl-tab-sum' }, fmt.money(lab.convert(sum.cost, 'USD', lab.cur), lab.cur, true)) : null,
                progress && all.total ? h('small', { className: 'kd-cl-tab-prog', title: t('approved_pairs', { ok: all.ok, total: all.total }) }, '%' + Math.round((all.ok / all.total) * 100) + ' ' + t('approved_short')) : null,
            );
        }));
    }

    /* ------------------------------------------------------------------ */
    /* Toplamlar                                                            */
    /* ------------------------------------------------------------------ */

    function useGrand() {
        const lab = useLab();
        const icmal = lab.data.icmal || {};
        const result = icmal.result || {};
        const products = sumLines(lab.model.tabs.products.lines);
        const staff = sumLines(lab.model.tabs.staff.lines);
        const expenses = sumLines(lab.model.tabs.expenses.lines);
        const cost = products.cost + staff.cost + expenses.cost;
        const sale = products.sale;
        const profit = sale - cost;

        return {
            products, staff, expenses, cost, sale, profit,
            netRate: sale > 0 ? profit / sale : null,
            markup: cost > 0 ? profit / cost : null,
            perMwp: result['GES Maliyet / MWp'],
            salePerMwp: result['GES Satış / MWp'],
            contract: result['Sözleşme Tutarı'],
        };
    }

    function Tile(props) {
        const { label, value, sub, tone, title } = props;

        return h('div', { className: cx('kd-cl-tile', tone), title: title || undefined },
            h('span', { className: 'kd-tk-l' }, label),
            h('span', { className: 'kd-cl-tile-v' }, value),
            sub ? h('span', { className: 'kd-tk-s' }, sub) : null,
        );
    }

    /** (b) Sekmelerin ustunde yapisik genel toplam seridi. */
    function SummaryStrip() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const g = useGrand();
        const counts = lineCounts(lab.model.all, lab.statusOf);
        const all = Object.keys(counts).reduce((acc, dept) => { acc.ok += counts[dept].ok; acc.no += counts[dept].no; acc.total += counts[dept].total; return acc; }, { ok: 0, no: 0, total: 0 });
        const mc = matchCounts(lab.model.all, lab.matchOf);
        const price = priceSplit(lab.model.all);
        const m = (usd) => fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur, true);

        return h('div', { className: 'kd-ticker sticky kd-cl-strip' },
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('total_cost')), h('span', { className: 'kd-tk-v' }, m(g.cost)), h('span', { className: 'kd-tk-s' }, t('cost_split', { p: m(g.products.cost), s: m(g.staff.cost), e: m(g.expenses.cost) }))),
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('total_sale')), h('span', { className: 'kd-tk-v' }, m(g.sale)), h('span', { className: 'kd-tk-s' }, t('sale_hint'))),
            h('div', { className: 'kd-tk good' }, h('span', { className: 'kd-tk-l' }, t('net_profit')), h('span', { className: 'kd-tk-v' }, m(g.profit)), h('span', { className: 'kd-tk-s' }, t('net_rate') + ' ' + pct(fmt, g.netRate))),
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('markup_on_cost')), h('span', { className: 'kd-tk-v' }, pct(fmt, g.markup)), h('span', { className: 'kd-tk-s' }, t('markup_hint'))),
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('cost_per_mwp')), h('span', { className: 'kd-tk-v' }, m(g.perMwp)), h('span', { className: 'kd-tk-s' }, t('sale_per_mwp') + ' ' + m(g.salePerMwp))),
            h('div', { className: 'kd-tk kd-cl-tk-open', title: t('price_open_click'), onClick: () => lab.showOpenPrices() }, h('span', { className: 'kd-tk-l' }, t('price_open_total')), h('span', { className: 'kd-tk-v' }, m(price.open)), h('span', { className: 'kd-tk-s' }, t('price_open_sub', { n: price.nOpen, p: pct(fmt, g.cost ? price.open / g.cost : null) }))),
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('approvals')), h('span', { className: 'kd-tk-v' }, '%' + (all.total ? Math.round((all.ok / all.total) * 100) : 0)), h('span', { className: 'kd-tk-s' }, t('approved_pairs', { ok: all.ok, total: all.total }) + (all.no ? ' · ' + t('rejected_n', { n: all.no }) : ''))),
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('catalog_match')), h('span', { className: 'kd-tk-v' }, mc.matched + '/' + mc.total), h('span', { className: 'kd-tk-s' }, t('match_short', { n: mc.new, s: mc.similar }))),
            h('div', { className: 'kd-tk' }, h('span', { className: 'kd-tk-l' }, t('contract')), h('span', { className: 'kd-tk-v' }, m(g.contract)), h('span', { className: 'kd-tk-s' }, t('contract_hint'))),
        );
    }

    /** (c) Sekmenin kendi ic toplamlari + departman ilerlemesi. */
    function InnerTotals(props) {
        const { tab } = props;
        const { t, fmt } = useApp();
        const lab = useLab();
        const model = lab.model.tabs[tab];
        const sum = sumLines(model.lines);
        const counts = lineCounts(model.lines, lab.statusOf);
        const mc = matchCounts(model.lines, lab.matchOf);
        const m = (usd) => fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur);
        const tiles = [
            h(Tile, { key: 'c', label: t('tab_cost', { tab: t('tab_' + tab) }), value: m(sum.cost), sub: t('lines_scope', { scope: sum.scope, n: sum.n }) }),
        ];

        if (tab === 'products') {
            tiles.push(h(Tile, { key: 's', label: t('total_sale'), value: m(sum.sale), sub: t('markup_on_cost') + ' ' + pct(fmt, sum.cost ? (sum.sale - sum.cost) / sum.cost : null) }));
            tiles.push(h(Tile, { key: 'p', tone: 'good', label: t('gross_margin'), value: m(sum.sale - sum.cost), sub: t('of_sale') + ' ' + pct(fmt, sum.sale ? (sum.sale - sum.cost) / sum.sale : null) }));
        } else {
            tiles.push(h(Tile, { key: 'tl', label: t('in_try'), value: fmt.money(lab.convert(sum.cost, 'USD', 'TRY'), 'TRY'), sub: t('excel_currency_try') }));
            tiles.push(h(Tile, { key: 'sh', label: t('share_of_cost'), value: pct(fmt, sum.cost / Math.max(1, grandCost(lab))), sub: t('share_hint') }));
        }

        const price = priceSplit(model.lines);
        tiles.push(h(Tile, { key: 'po', tone: price.nOpen ? 'warn' : null, label: t('price_open_total'), value: m(price.open), sub: t('price_open_sub', { n: price.nOpen, p: pct(fmt, sum.cost ? price.open / sum.cost : null) }) }));
        tiles.push(h(Tile, { key: 'm', label: t('catalog_match'), value: mc.matched + '/' + mc.total, sub: t('match_short', { n: mc.new, s: mc.similar }) }));

        return h('div', { className: 'kd-cl-inner' },
            h('div', { className: 'kd-cl-tiles' }, tiles),
            h('div', { className: 'kd-cl-inner-prog' }, h('span', { className: 'kd-tk-l' }, t('dept_progress')), h(DeptProgress, { counts, layout: 'wide' }), h(ManagementApproval, { tab, counts })),
        );
    }

    function grandCost(lab) {
        return ['products', 'staff', 'expenses'].reduce((acc, key) => acc + sumLines(lab.model.tabs[key].lines).cost, 0);
    }

    /** Yonetim onayi: butun departmanlar onaylayinca acilir (deneme). */
    function ManagementApproval(props) {
        const { tab, counts } = props;
        const { t, toast } = useApp();
        const lab = useLab();
        const ready = Object.keys(counts).every((dept) => counts[dept].ok === counts[dept].total);
        const done = lab.managed[tab];

        return h('button', {
            type: 'button',
            className: cx('kd-btn', 'kd-cl-mgmt', { primary: ready && !done, done }),
            disabled: !ready && !done,
            title: ready ? t('mgmt_ready') : t('mgmt_wait'),
            onClick: () => { lab.setManaged(Object.assign({}, lab.managed, { [tab]: !done })); toast(t(done ? 'mgmt_undone' : 'mgmt_done') + ' · ' + t('demo_toast'), 'demo'); },
        }, h(Icon, { name: 'shield' }), done ? t('mgmt_given') : t('mgmt_button'));
    }

    /* ------------------------------------------------------------------ */
    /* Icmal                                                                */
    /* ------------------------------------------------------------------ */

    function RatesBox() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const r = lab.data.rates;

        return h('div', { className: 'kd-cl-box' },
            h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'rates' }), h('b', null, t('rates_title')), h('span', { className: 'kd-muted' }, fmt.date(r.date))),
            h('dl', { className: 'kd-cl-dl' },
                h('dt', null, '1 $'), h('dd', null, fmt.money(r.USD, 'TRY')),
                h('dt', null, '1 €'), h('dd', null, fmt.money(r.EUR, 'TRY')),
                h('dt', null, t('parity')), h('dd', null, fmt.num(r.parity, 4)),
                h('dt', null, t('offer_currency')), h('dd', null, '$'),
                h('dt', null, t('petrol')), h('dd', null, fmt.money(r.petrol, 'TRY')),
                h('dt', null, t('diesel')), h('dd', null, fmt.money(r.diesel, 'TRY')),
            ),
        );
    }

    function SplitBox() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const split = (lab.data.icmal || {}).split || {};
        const row = (key) => split[key] || {};

        return h('div', { className: 'kd-cl-box' },
            h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'excel' }), h('b', null, t('split_title'))),
            h('table', { className: 'kd-cl-mini-table' },
                h('thead', null, h('tr', null, h('th', null), h('th', { className: 'kd-num-c' }, '₺'), h('th', { className: 'kd-num-c' }, '$'), h('th', { className: 'kd-num-c' }, '€'))),
                h('tbody', null, [['Malzeme', 'split_material'], ['Genel Gider', 'split_overhead'], ['Toplam', 'split_total']].map((pair) => h('tr', { key: pair[0] },
                    h('td', null, t(pair[1])),
                    h('td', { className: 'kd-num-c' }, fmt.money(row(pair[0]).TRY || 0, 'TRY', true)),
                    h('td', { className: 'kd-num-c' }, fmt.money(row(pair[0]).USD || 0, 'USD', true)),
                    h('td', { className: 'kd-num-c' }, fmt.money(row(pair[0]).EUR || 0, 'EUR', true)),
                ))),
                h('tfoot', null, h('tr', null,
                    h('td', null, t('split_offer')),
                    h('td', { className: 'kd-num-c' }, fmt.money(row('Teklif Kuru').TRY || 0, 'USD', true)),
                    h('td', { className: 'kd-num-c' }, fmt.money(row('Teklif Kuru').USD || 0, 'USD', true)),
                    h('td', { className: 'kd-num-c' }, fmt.money(row('Teklif Kuru').EUR || 0, 'USD', true)),
                )),
            ),
            h('p', { className: 'kd-note' }, t('split_total_line', { total: fmt.money(row('Teklif Kuru').total || 0, 'USD') })),
        );
    }

    function ProjectBox() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const icmal = lab.data.icmal || {};
        const dates = icmal.dates || {};

        return h('div', { className: 'kd-cl-box' },
            h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'info' }), h('b', null, t('project_title'))),
            h('dl', { className: 'kd-cl-dl' },
                (icmal.inputs || []).map((row) => [h('dt', { key: 'k' + row.name }, row.name), h('dd', { key: 'v' + row.name }, fmt.num(row.value, 3) + ' ' + unitText(row.unit))]),
                h('dt', null, t('contract_date')), h('dd', null, fmt.date(dates.contract) + ' ' + t('estimated')),
                h('dt', null, t('duration')), h('dd', null, t('days_n', { n: fmt.num(dates.duration_days) })),
                h('dt', null, t('acceptance')), h('dd', null, fmt.date(dates.provisional_acceptance)),
            ),
            icmal.project_note ? h('pre', { className: 'kd-cl-pre' }, icmal.project_note) : null,
        );
    }

    /** Fiyat durumu kutusu: kesin / netlesmedi tutarlari, sekme kirilimi, netlesmeyenlere git. */
    function PriceBox() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const all = priceSplit(lab.model.all);
        const total = all.firm + all.open || 1;
        const m = (usd) => fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur, true);

        return h('div', { className: 'kd-cl-box kd-cl-pricebox' },
            h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'rates' }), h('b', null, t('price_title'))),
            h('span', { className: 'kd-cl-pricebar', title: t('price_bar_title', { p: pct(fmt, all.open / total) }) },
                h('i', { className: 'firm', style: { width: (all.firm / total) * 100 + '%' } }),
                h('i', { className: 'open', style: { width: (all.open / total) * 100 + '%' } }),
            ),
            h('dl', { className: 'kd-cl-dl' },
                h('dt', null, h('i', { className: 'kd-cl-mark green' }), ' ' + t('price_firm_long', { n: all.nFirm })), h('dd', null, m(all.firm)),
                h('dt', null, h('i', { className: 'kd-cl-mark yellow' }), ' ' + t('price_open_long', { n: all.nOpen })), h('dd', null, m(all.open)),
            ),
            h('table', { className: 'kd-cl-mini-table' },
                h('thead', null, h('tr', null, h('th', null), h('th', { className: 'kd-num-c' }, t('price_firm')), h('th', { className: 'kd-num-c' }, t('price_open')))),
                h('tbody', null, DATA_TABS.map((key) => {
                    const s = priceSplit(lab.model.tabs[key].lines);
                    return h('tr', { key }, h('td', null, t('tab_' + key)), h('td', { className: 'kd-num-c' }, s.nFirm), h('td', { className: 'kd-num-c' }, s.nOpen ? h('b', { className: 'kd-cl-open-n' }, s.nOpen) : '0'));
                })),
            ),
            all.nOpen ? h('button', { type: 'button', className: 'kd-btn kd-cl-mini', onClick: () => lab.showOpenPrices() }, h(Icon, { name: 'filter' }), t('price_show_open')) : null,
        );
    }

    /** Fiyati netlesmeyen kalemler: bolume gore kisa liste (9. tasarim). */
    function OpenPriceList() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const groups = [];

        DATA_TABS.forEach((key) => {
            lab.model.tabs[key].sections.forEach((sec) => {
                const lines = [].concat.apply([], sec.groups.map((g) => g.lines)).filter((line) => line.inScope && priceOf(line) === 'open');
                if (lines.length) { groups.push({ key, sec, lines, sum: priceSplit(lines).open }); }
            });
        });

        return h('div', { className: 'kd-cl-openlist' }, groups.length ? groups.map((g) => h('div', { key: g.sec.id, className: 'kd-cl-og' },
            h('button', { type: 'button', className: 'kd-cl-og-h', title: t('go_section'), onClick: () => { lab.setPriceFilter('open'); lab.focusSection(g.key, g.sec.id); } },
                h('span', { className: 'kd-mono' }, g.sec.no), h('b', null, g.sec.name), h('em', null, t('lines_n', { n: g.lines.length })), h('span', { className: 'kd-spacer' }), h('b', null, fmt.money(lab.convert(g.sum, 'USD', lab.cur), lab.cur, true)),
            ),
            h('ul', null, g.lines.map((line) => h('li', { key: line.id },
                h('i', { className: 'kd-cl-mark yellow' }),
                h('span', { className: 'kd-cl-label', title: line.name + (line.note ? ' · ' + line.note : '') }, line.name),
                h('span', { className: 'kd-spacer' }),
                h('span', { className: 'kd-num-c' }, fmt.money(lab.convert(line.cost, 'USD', lab.cur), lab.cur)),
            ))),
        )) : h('div', { className: 'kd-empty' }, t('price_none_open')));
    }

    /** (a) Icmal: Excel'deki kategori ozeti; satirlar Icmal sayfasinin degerleri. */
    function IcmalTable(props) {
        const { compact } = props;
        const { t, fmt } = useApp();
        const lab = useLab();
        const icmal = lab.data.icmal || {};
        const result = icmal.result || {};
        const total = result['Toplam Maliyet'] || 1;
        const rows = [];
        const m = (usd, c) => fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur, c);
        const openOf = (lines) => priceSplit(lines);
        // Fiyati netlesmeyen tutar ayri sutun degil, satir adinin yaninda etiket (tablo tasmasin).
        const openPill = (s) => (s.nOpen ? h('span', { className: 'kd-cl-price open', title: t('price_open_title', { n: s.nOpen, amount: m(s.open) }) }, t('price_open_pill', { n: s.nOpen, amount: m(s.open, true) })) : null);
        const secOpen = {};
        lab.model.tabs.products.sections.forEach((sec) => { secOpen[sec.no] = openOf([].concat.apply([], sec.groups.map((g) => g.lines))); });
        const allOpen = openOf(lab.model.all);

        (icmal.categories || []).forEach((cat) => {
            const tot = cat.total || { cost: 0, sale: 0 };
            rows.push(h('tr', { key: cat.no, className: 'kd-cl-head sec', onClick: () => lab.focusSection('products', 'p-' + cat.no) },
                h('td', { className: 'kd-mono' }, cat.no),
                h('td', null, h('b', null, cat.name), h('span', { className: 'kd-cl-count' }, t('markup_short') + ' ' + pct(fmt, cat.markup, 2)), openPill(secOpen[cat.no] || { open: 0, nOpen: 0 })),
                compact ? null : h('td', null),
                h('td', { className: 'kd-num-c kd-strong' }, m(tot.cost)),
                h('td', { className: 'kd-num-c kd-strong' }, m(tot.sale)),
                h('td', { className: 'kd-cl-sharecell' }, h(Bar, { value: tot.cost, max: total, tone: 's1' }), h('small', null, pct(fmt, tot.cost / total))),
            ));

            if (compact) { return; }

            cat.rows.forEach((row) => {
                rows.push(h('tr', { key: cat.no + row.no + row.name, className: cx('kd-cl-line', { out: !row.cost }) },
                    h('td', { className: 'kd-mono kd-cl-no' }, row.no),
                    h('td', null, row.name),
                    h('td', { className: 'kd-num-c kd-muted', title: cat.h_label || '' }, (row.qty !== null ? fmt.num(row.qty, 2) + ' ' + (row.unit || '') : '') + (row.per ? ' · ' + unitPrice(lab.convert(row.per, 'USD', lab.cur), lab.cur, lab.symbols) + (cat.h_label === 'Maliyet / MWp' ? ' / MWp' : '') : '')),
                    h('td', { className: 'kd-num-c' }, row.cost ? m(row.cost) : '0'),
                    h('td', { className: 'kd-num-c' }, row.sale ? m(row.sale) : '0'),
                    h('td', { className: 'kd-cl-sharecell' }, row.cost ? h(Bar, { value: row.cost, max: total }) : null),
                ));
            });
        });

        const oh = icmal.overhead || { cost: 0 };
        rows.push(h('tr', { key: 'oh', className: 'kd-cl-head sec', onClick: () => lab.setTab('staff') },
            h('td', { className: 'kd-mono' }, '+'),
            h('td', null, h('b', null, t('overhead_row')), h('span', { className: 'kd-cl-count' }, t('overhead_hint')), openPill(openOf(lab.model.tabs.staff.lines.concat(lab.model.tabs.expenses.lines)))),
            compact ? null : h('td', null),
            h('td', { className: 'kd-num-c kd-strong' }, m(oh.cost)),
            h('td', { className: 'kd-num-c kd-muted' }, '0'),
            h('td', { className: 'kd-cl-sharecell' }, h(Bar, { value: oh.cost, max: total, tone: 's2' }), h('small', null, pct(fmt, oh.cost / total))),
        ));

        return h('div', { className: 'kd-tablewrap' },
            h('table', { className: cx('kd-table', 'kd-cl-table', 'kd-cl-icmal') },
                h('thead', null, h('tr', null,
                    h('th', null, t('col_no')), h('th', null, t('col_scope')), compact ? null : h('th', { className: 'kd-num-c' }, t('col_qty_per')),
                    h('th', { className: 'kd-num-c' }, t('col_cost') + ' (' + lab.symbol + ')'), h('th', { className: 'kd-num-c' }, t('col_sale') + ' (' + lab.symbol + ')'), h('th', null, t('col_share')),
                )),
                h('tbody', null, rows),
                h('tfoot', null,
                    h('tr', null, h('td', null), h('td', null, h('b', null, t('total_cost'))), compact ? null : h('td', null), h('td', { className: 'kd-num-c kd-strong' }, m(result['Toplam Maliyet'])), h('td', { className: 'kd-num-c kd-strong' }, m(result['Keşif Satış Kontrol'])), h('td', null)),
                    allOpen.nOpen ? h('tr', null, h('td', null), h('td', null, h('i', { className: 'kd-cl-mark yellow' }), ' ' + t('price_open_long', { n: allOpen.nOpen }) + ' · ' + t('price_open_share', { p: pct(fmt, allOpen.open / (result['Toplam Maliyet'] || 1)) })), compact ? null : h('td', null), h('td', { className: 'kd-num-c kd-strong kd-cl-open-amt' }, m(allOpen.open)), h('td', null), h('td', null)) : null,
                    h('tr', null, h('td', null), h('td', null, t('contract') + ' · ' + t('markup_on_cost') + ' ' + pct(fmt, result['Kâr Oranı'])), compact ? null : h('td', null), h('td', null), h('td', { className: 'kd-num-c kd-strong' }, m(result['Sözleşme Tutarı'])), h('td', null)),
                    h('tr', { className: 'good' }, h('td', null), h('td', null, t('net_profit') + ' · ' + t('net_rate') + ' ' + pct(fmt, result['Net Kâr Oranı'])), compact ? null : h('td', null), h('td', null), h('td', { className: 'kd-num-c kd-strong' }, m(result['Net Kâr'])), h('td', null)),
                ),
            ),
        );
    }

    /** İcmal sekmesi; `full` ile selale ve onay matrisi de bu sekmede (7-9. tasarimlar). */
    function IcmalView(props) {
        const { full } = props || {};
        const { t } = useApp();
        const view = h('div', { className: 'kd-cl-icmal-wrap' },
            h('div', { className: 'kd-panel' }, h('header', { className: 'kd-ph' }, h('div', { className: 'kd-pt' }, h('h3', null, t('icmal_title')), h('span', { className: 'kd-sub' }, t('icmal_sub')))), h(IcmalTable, {})),
            h('div', { className: 'kd-cl-side-stack' }, h(PriceBox), h(RatesBox), h(SplitBox), h(ProjectBox)),
        );

        if (!full) { return view; }

        return h('div', { className: 'kd-cl-stack' },
            view,
            h('div', { className: 'kd-cl-two kd-cl-pad' },
                h('div', { className: 'kd-panel' }, h('header', { className: 'kd-ph' }, h('div', { className: 'kd-pt' }, h('h3', null, t('waterfall')), h('span', { className: 'kd-sub' }, t('waterfall_sub')))), h('div', { className: 'kd-pb' }, h(Waterfall))),
                h('div', { className: 'kd-panel' }, h('header', { className: 'kd-ph' }, h('div', { className: 'kd-pt' }, h('h3', null, t('matrix')), h('span', { className: 'kd-sub' }, t('matrix_sub')))), h('div', { className: 'kd-pb' }, h(ApprovalMatrix))),
            ),
        );
    }

    /** (d) Sagdaki 1/4 ozet paneli. */
    function SidePanel() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const g = useGrand();
        const icmal = lab.data.icmal || {};
        const total = g.cost || 1;
        const m = (usd) => fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur, true);
        const counts = lineCounts(lab.model.all, lab.statusOf);

        return h('aside', { className: 'kd-cl-side' },
            h('div', { className: 'kd-cl-box' },
                h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'summary' }), h('b', null, t('icmal_title'))),
                h('div', { className: 'kd-cl-big' }, h('span', null, t('total_cost')), h('b', null, m(g.cost))),
                h('div', { className: 'kd-cl-big' }, h('span', null, t('total_sale')), h('b', null, m(g.sale))),
                h('div', { className: 'kd-cl-big good' }, h('span', null, t('net_profit') + ' ' + pct(fmt, g.netRate)), h('b', null, m(g.profit))),
                h('div', { className: 'kd-sep' }),
                h('ul', { className: 'kd-cl-catlist' },
                    (icmal.categories || []).filter((cat) => cat.total && cat.total.cost > 0).map((cat) => h('li', { key: cat.no },
                        h('button', { type: 'button', onClick: () => lab.focusSection('products', 'p-' + cat.no), title: t('go_section') },
                            h('span', { className: 'kd-mono' }, cat.no), h('span', { className: 'kd-cl-catn' }, cat.name), h('b', null, m(cat.total.cost)),
                        ),
                        h(Bar, { value: cat.total.cost, max: total, tone: 's1' }),
                    )),
                    [['staff', g.staff.cost], ['expenses', g.expenses.cost]].map((pair) => h('li', { key: pair[0] },
                        h('button', { type: 'button', onClick: () => lab.setTab(pair[0]) }, h('span', { className: 'kd-mono' }, '+'), h('span', { className: 'kd-cl-catn' }, t('tab_' + pair[0])), h('b', null, m(pair[1]))),
                        h(Bar, { value: pair[1], max: total, tone: 's2' }),
                    )),
                ),
            ),
            h(PriceBox),
            h('div', { className: 'kd-cl-box' },
                h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'shield' }), h('b', null, t('dept_progress'))),
                h(DeptProgress, { counts, layout: 'wide' }),
            ),
            h(RatesBox),
        );
    }

    /** (e) Maliyet selalesi: kategoriler -> genel giderler -> maliyet -> kar -> sozlesme. */
    function Waterfall() {
        const { t, fmt } = useApp();
        const lab = useLab();
        const [ref, width] = KD.useWidth(900);
        const g = useGrand();
        const icmal = lab.data.icmal || {};
        const steps = [];
        let run = 0;

        (icmal.categories || []).filter((cat) => cat.total && cat.total.cost > 0).forEach((cat) => {
            steps.push({ key: cat.no, label: cat.name, from: run, to: run + cat.total.cost, kind: 'up' });
            run += cat.total.cost;
        });
        steps.push({ key: 'st', label: t('tab_staff'), from: run, to: run + g.staff.cost, kind: 'oh' });
        run += g.staff.cost;
        steps.push({ key: 'ex', label: t('tab_expenses'), from: run, to: run + g.expenses.cost, kind: 'oh' });
        run += g.expenses.cost;
        steps.push({ key: 'tc', label: t('total_cost'), from: 0, to: run, kind: 'total' });
        steps.push({ key: 'pr', label: t('net_profit'), from: run, to: run + g.profit, kind: 'good' });
        steps.push({ key: 'sl', label: t('total_sale'), from: 0, to: g.sale, kind: 'total' });

        const H = 210;
        const pad = { l: 8, r: 8, t: 14, b: 54 };
        const max = g.sale || 1;
        const band = (width - pad.l - pad.r) / steps.length;
        const y = (v) => pad.t + (H - pad.t - pad.b) * (1 - v / max);
        const m = (usd) => fmt.money(lab.convert(usd, 'USD', lab.cur), lab.cur, true);

        return h('div', { className: 'kd-chart kd-cl-water', ref },
            h('svg', { width, height: H, viewBox: '0 0 ' + width + ' ' + H, role: 'img', 'aria-label': t('waterfall') },
                h('line', { x1: pad.l, x2: width - pad.r, y1: y(0), y2: y(0), className: 'kd-axis' }),
                steps.map((s, i) => {
                    const x = pad.l + band * i + band * 0.15;
                    const w = band * 0.7;
                    const top = y(Math.max(s.from, s.to));
                    const hh = Math.max(1, Math.abs(y(s.from) - y(s.to)));

                    return h('g', { key: s.key },
                        h('rect', { x, y: top, width: w, height: hh, rx: 2, className: 'kd-cl-wf ' + s.kind }, h('title', null, s.label + ': ' + m(s.to - s.from))),
                        i < steps.length - 1 && s.kind !== 'total' ? h('line', { x1: x + w, x2: x + band, y1: y(s.to), y2: y(s.to), className: 'kd-gl' }) : null,
                        h('text', { x: x + w / 2, y: top - 3, textAnchor: 'middle', className: 'kd-tick' }, (s.to - s.from) / max > 0.004 ? m(s.to - s.from) : ''),
                        h('text', { x: x + w / 2, y: H - pad.b + 12, textAnchor: 'end', transform: 'rotate(-35 ' + (x + w / 2) + ' ' + (H - pad.b + 12) + ')', className: 'kd-tick' }, s.label.length > 18 ? s.label.slice(0, 17) + '…' : s.label),
                    );
                }),
            ),
        );
    }

    /** (e) Bolum x departman onay matrisi; hucreye tiklayinca liste o bolume ve bekleyenlere gider. */
    function ApprovalMatrix() {
        const { t } = useApp();
        const lab = useLab();
        const depts = Object.keys(lab.departments).filter((dept) => lab.model.all.some((line) => line.depts.indexOf(dept) !== -1));
        const rows = [];

        ['products', 'staff', 'expenses'].forEach((tab) => {
            lab.model.tabs[tab].sections.forEach((sec) => {
                const lines = [].concat.apply([], sec.groups.map((g) => g.lines)).filter((line) => line.inScope);

                if (!lines.length) { return; }
                rows.push({ tab, sec, counts: lineCounts(lines, lab.statusOf) });
            });
        });

        return h('div', { className: 'kd-heat kd-cl-matrix' },
            h('table', null,
                h('thead', null, h('tr', null, h('th', null), depts.map((dept) => h('th', { key: dept, title: lab.departments[dept].name }, lab.departments[dept].short)))),
                h('tbody', null, rows.map((row) => h('tr', { key: row.sec.id },
                    h('th', { title: row.sec.name }, h('span', { className: 'kd-cl-tabdot ' + row.tab }), row.sec.no + ' ' + row.sec.name),
                    depts.map((dept) => {
                        const c = row.counts[dept];

                        if (!c) { return h('td', { key: dept, className: 'kd-cl-mx none' }, ''); }

                        const ratio = c.total ? c.ok / c.total : 0;

                        return h('td', {
                            key: dept,
                            className: cx('kd-cl-mx', { done: ratio === 1, bad: c.no > 0 }),
                            style: { '--r': ratio },
                            title: t('progress_title', { dept: lab.departments[dept].name, ok: c.ok, total: c.total, no: c.no, wait: c.wait }),
                            onClick: () => { lab.setPending(dept); lab.focusSection(row.tab, row.sec.id); },
                        }, c.ok + '/' + c.total);
                    }),
                ))),
            ),
            h('p', { className: 'kd-note' }, t('matrix_hint')),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Katalog eslestirme penceresi                                         */
    /* ------------------------------------------------------------------ */

    function MatchModal(props) {
        const { line, onClose } = props;
        const { t, toast } = useApp();
        const lab = useLab();
        const match = lab.matchOf(line);
        const catalog = useMemo(() => {
            const seen = {};
            return lab.model.tabs[line.tab].lines.reduce((list, other) => {
                const m = lab.matchOf(other);
                if (m.status === 'matched' && !seen[m.catalog]) { seen[m.catalog] = true; list.push({ name: m.catalog, path: m.path }); }
                return list;
            }, []);
        }, [line]);
        const [query, setQuery] = useState('');
        const [pick, setPick] = useState(match.status === 'matched' ? match.catalog : (match.suggestions && match.suggestions[0] ? match.suggestions[0].name : '__new'));
        const q = lower(query);
        const options = (match.suggestions || []).map((s) => ({ name: s.name, path: s.path, score: s.score }))
            .concat(catalog.filter((c) => !(match.suggestions || []).some((s) => s.name === c.name)).filter((c) => !q || lower(c.name).indexOf(q) !== -1 || lower(c.path).indexOf(q) !== -1).slice(0, q ? 40 : 12));

        const save = () => {
            if (pick === '__new') {
                lab.setMatch(line, { status: 'new', catalog: line.name, path: line.sec.name, uses: 1, suggestions: [] });
                toast(t('match_saved_new') + ' · ' + t('demo_toast'), 'demo');
            } else {
                const found = options.find((o) => o.name === pick) || { name: pick, path: '' };
                lab.setMatch(line, { status: 'matched', catalog: found.name, path: found.path, uses: 1, suggestions: [] });
                toast(t('match_saved', { name: found.name }) + ' · ' + t('demo_toast'), 'demo');
            }
            onClose();
        };

        return h(Modal, {
            title: t('match_modal_title'),
            onClose,
            footer: [
                h('button', { key: 'c', type: 'button', className: 'kd-btn cancel', onClick: onClose }, t('cancel')),
                h('button', { key: 's', type: 'button', className: 'kd-btn kd-cl-save', onClick: save }, h(Icon, { name: 'save' }), t('match_confirm')),
            ],
        },
            h('div', { className: 'kd-cl-mm-line' },
                h('span', { className: 'kd-muted' }, t('excel_line', { row: line.row, sheet: t('sheet_' + line.tab) })),
                h('b', null, line.name),
                h('span', { className: 'kd-muted' }, line.sec.name + (line.grp.flat ? '' : ' › ' + line.grp.name)),
            ),
            h('p', { className: 'kd-hint' }, t('match_modal_hint')),
            h('label', { className: 'kd-search kd-cl-mm-search' }, h(Icon, { name: 'search' }), h('input', { type: 'search', value: query, placeholder: t('catalog_search'), onChange: (event) => setQuery(event.target.value) })),
            h('div', { className: 'kd-cl-mm-list', role: 'radiogroup' },
                options.map((o) => h('label', { key: o.name, className: cx('kd-cl-mm-opt', { on: pick === o.name }) },
                    h('input', { type: 'radio', name: 'kd-cl-pick', checked: pick === o.name, onChange: () => setPick(o.name) }),
                    h('span', { className: 'kd-cl-mm-name' }, h('b', null, o.name), h('small', null, o.path)),
                    o.score ? h('em', { className: 'kd-cl-score' }, t('similar_pct', { p: o.score })) : null,
                )),
                h('label', { className: cx('kd-cl-mm-opt', 'new', { on: pick === '__new' }) },
                    h('input', { type: 'radio', name: 'kd-cl-pick', checked: pick === '__new', onChange: () => setPick('__new') }),
                    h('span', { className: 'kd-cl-mm-name' }, h('b', null, t('add_as_new')), h('small', null, t('add_as_new_hint'))),
                ),
            ),
        );
    }

    /* ------------------------------------------------------------------ */
    /* 6: Excel yukleme ve eslestirme onizlemesi                            */
    /* ------------------------------------------------------------------ */

    function UploadStep() {
        const { t, toast, fmt } = useApp();
        const lab = useLab();
        const [status, setStatus] = useState('similar');
        const [tab, setTab] = useState('all');
        const source = lab.data.source || {};
        const lines = lab.model.all.filter((line) => (tab === 'all' || line.tab === tab) && (status === 'all' || lab.matchOf(line).status === status));
        const mc = matchCounts(lab.model.all, lab.matchOf);
        const per = ['products', 'staff', 'expenses'].map((key) => ({ key, c: matchCounts(lab.model.tabs[key].lines, lab.matchOf) }));
        const sheetMap = [
            [source.sheets_used ? source.sheets_used[0] : '', t('tab_products'), lab.model.tabs.products.lines.length],
            [source.sheets_used ? source.sheets_used[1] : '', t('tab_staff') + ' + ' + t('tab_expenses'), lab.model.tabs.staff.lines.length + lab.model.tabs.expenses.lines.length],
            [source.sheets_used ? source.sheets_used[2] : '', t('tab_icmal'), null],
        ];
        const price = priceSplit(lab.model.all);

        return h('div', { className: 'kd-cl-upload' },
            h('ol', { className: 'kd-cl-steps' },
                h('li', { className: 'done' }, h('b', null, '1'), t('step_upload')),
                h('li', { className: 'on' }, h('b', null, '2'), t('step_match')),
                h('li', null, h('b', null, '3'), t('step_save')),
            ),
            h('div', { className: 'kd-cl-up-grid' },
                h('div', { className: 'kd-cl-box kd-cl-file' },
                    h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'excel' }), h('b', null, source.file), h('button', { type: 'button', className: 'kd-btn kd-cl-mini', disabled: true, title: t('demo_disabled') }, h(Icon, { name: 'upload' }), t('choose_other'))),
                    h('table', { className: 'kd-cl-mini-table' },
                        h('thead', null, h('tr', null, h('th', null, t('sheet')), h('th', null, t('becomes')), h('th', { className: 'kd-num-c' }, t('lines')))),
                        h('tbody', null,
                            sheetMap.map((row) => h('tr', { key: row[0] }, h('td', null, row[0]), h('td', null, row[1]), h('td', { className: 'kd-num-c' }, row[2] === null ? '–' : row[2]))),
                        ),
                    ),
                    h('p', { className: 'kd-note kd-cl-colors' },
                        h('span', null, h('i', { className: 'kd-cl-mark green' }), t('price_firm_long', { n: price.nFirm })),
                        h('span', null, h('i', { className: 'kd-cl-mark yellow' }), t('price_open_long', { n: price.nOpen })),
                        h('span', null, t('price_read_rule')),
                    ),
                ),
                h('div', { className: 'kd-cl-box' },
                    h('div', { className: 'kd-cl-box-h' }, h(Icon, { name: 'catalog' }), h('b', null, t('match_result'))),
                    h('p', { className: 'kd-cl-match-line' }, t('match_summary', { total: mc.total, matched: mc.matched, n: mc.new, s: mc.similar })),
                    h('table', { className: 'kd-cl-mini-table' },
                        h('thead', null, h('tr', null, h('th', null), h('th', { className: 'kd-num-c' }, t('match_matched')), h('th', { className: 'kd-num-c' }, t('match_new')), h('th', { className: 'kd-num-c' }, t('match_similar')))),
                        h('tbody', null, per.map((row) => h('tr', { key: row.key }, h('td', null, t('tab_' + row.key)), h('td', { className: 'kd-num-c' }, row.c.matched), h('td', { className: 'kd-num-c' }, row.c.new), h('td', { className: 'kd-num-c' }, row.c.similar)))),
                    ),
                    h('p', { className: 'kd-note' }, t('match_rule')),
                ),
            ),
            h('div', { className: 'kd-panel' },
                h('div', { className: 'kd-toolbar' },
                    h(Seg, { size: 'sm', value: status, onChange: setStatus, options: [
                        { value: 'similar', label: t('match_similar'), count: mc.similar },
                        { value: 'new', label: t('match_new'), count: mc.new },
                        { value: 'matched', label: t('match_matched'), count: mc.matched },
                        { value: 'all', label: t('match_all'), count: mc.total },
                    ] }),
                    h(Seg, { size: 'sm', value: tab, onChange: setTab, options: [{ value: 'all', label: t('all_tabs') }, { value: 'products', label: t('tab_products') }, { value: 'staff', label: t('tab_staff') }, { value: 'expenses', label: t('tab_expenses') }] }),
                    h('span', { className: 'kd-spacer' }),
                    h('span', { className: 'kd-count' }, t('rows_n', { n: lines.length })),
                ),
                h('div', { className: 'kd-tablewrap kd-cl-wrap' },
                    h('table', { className: 'kd-table kd-cl-table tight' },
                        h('thead', null, h('tr', null, h('th', null, t('excel_row')), h('th', null, t('col_tab')), h('th', null, t('col_group')), h('th', null, t('excel_name')), h('th', { className: 'kd-num-c' }, t('col_qty')), h('th', null, t('col_price')), h('th', null, t('catalog_counterpart')))),
                        h('tbody', null, lines.length ? lines.map((line) => {
                            const m = lab.matchOf(line);
                            let target;

                            if (m.status === 'similar') {
                                target = h('span', { className: 'kd-cl-pick' },
                                    h('select', {
                                        value: '',
                                        onChange: (event) => {
                                            const value = event.target.value;
                                            if (!value) { return; }
                                            if (value === '__new') {
                                                lab.setMatch(line, { status: 'new', catalog: line.name, path: line.sec.name, uses: 1, suggestions: [] });
                                            } else {
                                                const s = (m.suggestions || []).find((x) => x.name === value) || { name: value, path: '' };
                                                lab.setMatch(line, { status: 'matched', catalog: s.name, path: s.path, uses: 1, suggestions: [] });
                                            }
                                            toast(t('match_picked') + ' · ' + t('demo_toast'), 'demo');
                                        },
                                    },
                                        h('option', { value: '' }, t('pick_catalog')),
                                        (m.suggestions || []).map((s) => h('option', { key: s.name, value: s.name }, s.name + ' (' + t('similar_pct', { p: s.score }) + ')')),
                                        h('option', { value: '__new' }, t('add_as_new')),
                                    ),
                                );
                            } else if (m.status === 'new') {
                                target = h('span', { className: 'kd-cl-pick' }, h('span', { className: 'kd-cl-match new' }, h(Icon, { name: 'new' }), h('span', null, t('will_be_added'))), h('button', { type: 'button', className: 'kd-link', onClick: () => lab.openMatch(line) }, t('link_existing')));
                            } else {
                                target = h('span', { className: 'kd-cl-pick' }, h('span', { className: 'kd-cl-match matched' }, h(Icon, { name: 'link' })), h('span', { className: 'kd-ell' }, m.catalog), h('small', { className: 'kd-muted kd-ell' }, m.path));
                            }

                            return h('tr', { key: line.id, className: 'kd-cl-line' },
                                h('td', { className: 'kd-mono' }, line.row),
                                h('td', null, t('tab_' + line.tab)),
                                h('td', { className: 'kd-ell', title: line.sec.name + ' › ' + line.grp.name }, line.grp.flat ? line.sec.name : line.grp.name),
                                h('td', { className: 'kd-cl-label', title: line.name }, line.name),
                                h('td', { className: 'kd-num-c' }, line.qty ? fmt.num(line.qty, 2) + (line.unit ? ' ' + line.unit : '') : '–'),
                                h('td', null, priceOf(line) && line.inScope ? h('span', { className: cx('kd-cl-price', priceOf(line)) }, t(priceOf(line) === 'open' ? 'price_open' : 'price_firm')) : h('span', { className: 'kd-muted' }, '–')),
                                h('td', null, target),
                            );
                        }) : h('tr', null, h('td', { colSpan: 7, className: 'kd-empty' }, t('no_match')))),
                    ),
                ),
                h('div', { className: 'kd-cl-up-foot' },
                    h('span', { className: 'kd-hint' }, mc.similar ? t('similar_left', { n: mc.similar }) : t('all_resolved')),
                    h('button', { type: 'button', className: 'kd-btn kd-cl-save', onClick: () => toast(t('list_saved', { total: mc.total, n: mc.new }) + ' · ' + t('demo_toast'), 'demo') }, h(Icon, { name: 'save' }), t('save_list')),
                ),
            ),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Tasarimlar                                                           */
    /* ------------------------------------------------------------------ */

    const VARIANTS = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    const DATA_TABS = ['products', 'staff', 'expenses'];
    // D-188 birlesik ornekler: hangi tasarimda İcmal ve Excel yukleme sekme olarak var.
    const ICMAL_TAB = { 1: true, 7: true, 8: true, 9: true };
    const UPLOAD_TAB = { 7: true, 8: true, 9: true };

    function variantTabs(variant) {
        return DATA_TABS.concat(ICMAL_TAB[variant] ? ['icmal'] : [], UPLOAD_TAB[variant] ? ['upload'] : []);
    }

    function MatchLine() {
        const { t } = useApp();
        const lab = useLab();
        const mc = matchCounts(lab.model.all, lab.matchOf);

        return h('div', { className: 'kd-cl-matchbar' },
            h(Icon, { name: 'catalog' }),
            h('span', null, t('match_summary', { total: mc.total, matched: mc.matched, n: mc.new, s: mc.similar })),
            mc.similar ? h('button', { type: 'button', className: 'kd-link', onClick: () => lab.setMatchFilter('similar') }, t('show_similar')) : null,
            h('button', { type: 'button', className: 'kd-link', onClick: () => lab.openUpload() }, t('open_upload')),
        );
    }

    function ListPanel(props) {
        const { tab, tabs, mini, progress, inner, extra, fullIcmal, narrow } = props;
        const lab = useLab();
        let body;

        if (tab === 'icmal') {
            body = h(IcmalView, { full: fullIcmal });
        } else if (tab === 'upload') {
            body = h('div', { className: 'kd-cl-pad' }, h(UploadStep));
        } else {
            body = h(Fragment, null,
                inner ? h(InnerTotals, { tab }) : null,
                h(Toolbar, { tab, extra }),
                h(CostTable, { tab, narrow }),
            );
        }

        return h('div', { className: 'kd-panel kd-cl-panel' }, h(TabBar, { tabs, value: tab, onChange: lab.setTab, mini, progress }), body);
    }

    function Box(props) {
        const { title, sub, children } = props;

        return h('div', { className: 'kd-panel' },
            h('header', { className: 'kd-ph' }, h('div', { className: 'kd-pt' }, h('h3', null, title), sub ? h('span', { className: 'kd-sub' }, sub) : null)),
            h('div', { className: 'kd-pb' }, children),
        );
    }

    /** 7. tasarim: sag ozet panelini ac / kapat. */
    function SideToggle() {
        const { t } = useApp();
        const lab = useLab();

        return h('button', { type: 'button', className: cx('kd-btn', 'kd-cl-mini', { on: lab.sideOpen }), onClick: () => lab.setSideOpen(!lab.sideOpen) },
            h(Icon, { name: 'summary' }), t(lab.sideOpen ? 'side_hide' : 'side_show'));
    }

    function VariantBody() {
        const { t } = useApp();
        const lab = useLab();
        const tab = lab.tab;
        const dataTab = DATA_TABS.indexOf(tab) !== -1 ? tab : 'products';
        const tabs = variantTabs(lab.variant);
        const ownTab = tabs.indexOf(tab) !== -1 ? tab : 'products';
        const onData = DATA_TABS.indexOf(ownTab) !== -1;

        switch (lab.variant) {
            case 1:
                return h(ListPanel, { tab: ownTab, tabs, progress: true });
            case 7:
                // Hepsi bir arada: 2 (serit) + 3 (sekme ici toplamlar) + 4 (sag ozet) + 1/5 (İcmal sekmesinde selale ve matris) + 6 (yukleme sekmesi).
                // Sag panel acikken liste dar: katalog sutununda yalniz isaret; panel dugmeyle kapanir.
                return h('div', { className: 'kd-cl-stack' },
                    h(SummaryStrip),
                    onData && lab.sideOpen
                        ? h('div', { className: 'kd-cl-split' }, h(ListPanel, { tab: ownTab, tabs, progress: true, inner: true, narrow: true, extra: h(SideToggle) }), h(SidePanel))
                        : h(ListPanel, { tab: ownTab, tabs, progress: true, inner: true, fullIcmal: true, extra: onData ? h(SideToggle) : null }),
                );
            case 8:
                // Sade birlesim: serit + sekme ici toplamlar; İcmal (selale ve matris dahil) ve Excel yukleme sekmede.
                return h('div', { className: 'kd-cl-stack' },
                    h(SummaryStrip),
                    h(ListPanel, { tab: ownTab, tabs, mini: true, progress: true, inner: true, fullIcmal: true }),
                );
            case 9:
                // Fiyat ve onay odakli: netlesmeyen fiyatlar + onay matrisi ustte, altta liste.
                return h('div', { className: 'kd-cl-stack' },
                    h(SummaryStrip),
                    h('div', { className: 'kd-cl-two even' },
                        h(Box, { title: t('open_list_title'), sub: t('open_list_sub') }, h(OpenPriceList)),
                        h(Box, { title: t('matrix'), sub: t('matrix_sub') }, h(ApprovalMatrix)),
                    ),
                    h(ListPanel, { tab: ownTab, tabs, mini: true, progress: true, inner: true }),
                );
            case 2:
                return h('div', { className: 'kd-cl-stack' }, h(SummaryStrip), h(ListPanel, { tab: dataTab, tabs: DATA_TABS, mini: true, progress: true }));
            case 3:
                return h(ListPanel, { tab: dataTab, tabs: DATA_TABS, inner: true });
            case 4:
                return h('div', { className: 'kd-cl-split' }, h(ListPanel, { tab: dataTab, tabs: DATA_TABS, mini: true }), h(SidePanel));
            case 5:
                return h('div', { className: 'kd-cl-stack' },
                    h('div', { className: 'kd-cl-two' },
                        h('div', { className: 'kd-panel' }, h('header', { className: 'kd-ph' }, h('div', { className: 'kd-pt' }, h('h3', null, t('waterfall')), h('span', { className: 'kd-sub' }, t('waterfall_sub')))), h('div', { className: 'kd-pb' }, h(Waterfall))),
                        h('div', { className: 'kd-panel' }, h('header', { className: 'kd-ph' }, h('div', { className: 'kd-pt' }, h('h3', null, t('matrix')), h('span', { className: 'kd-sub' }, t('matrix_sub')))), h('div', { className: 'kd-pb' }, h(ApprovalMatrix))),
                    ),
                    h(ListPanel, { tab: dataTab, tabs: DATA_TABS, progress: true }),
                );
            default:
                return h(UploadStep);
        }
    }

    function Legend() {
        const { t } = useApp();

        return h('div', { className: 'kd-cl-legend' },
            h('span', null, h('span', { className: 'kd-cl-chip ok' }, h('b', null, CHECK), 'SA'), t('legend_ok')),
            h('span', null, h('span', { className: 'kd-cl-chip wait' }, h('b', null, DOT), 'SA'), t('legend_wait')),
            h('span', null, h('span', { className: 'kd-cl-chip no' }, h('b', null, CROSS), 'SA'), t('legend_no')),
            h('span', null, h('span', { className: 'kd-cl-match matched' }, h(Icon, { name: 'link' }), t('match_matched')), t('legend_matched')),
            h('span', null, h('span', { className: 'kd-cl-match new' }, h(Icon, { name: 'new' }), t('match_new')), t('legend_new')),
            h('span', null, h('span', { className: 'kd-cl-match similar' }, h(Icon, { name: 'similar' }), t('match_similar')), t('legend_similar')),
            h('span', null, h('i', { className: 'kd-cl-mark green' }), t('mark_green')),
            h('span', null, h('i', { className: 'kd-cl-mark yellow' }), h('span', { className: 'kd-cl-price open' }, t('price_open_short')), t('mark_yellow')),
            h('p', { className: 'kd-note' }, t('legend_demo')),
            h('p', { className: 'kd-note' }, t('legend_reference')),
        );
    }

    function CostLab() {
        const { config, t, toast } = useApp();
        const data = config.data || {};
        const model = useMemo(() => buildModel(data), [data]);
        const convert = useMemo(() => makeConvert(data.rates || {}), [data]);
        const departments = data.departments || {};

        // Adres ?v=7&tab=icmal ile belirli bir tasarim dogrudan acilir (karsilastirma baglantisi).
        const params = useMemo(() => new URLSearchParams(window.location.search), []);
        const [variant, setVariantState] = useState(() => {
            const fromUrl = parseInt(params.get('v') || '', 10);
            const v = VARIANTS.indexOf(fromUrl) !== -1 ? fromUrl : store.get('cost-lab:variant', 1);
            return VARIANTS.indexOf(v) !== -1 ? v : 1;
        });
        const [tab, setTab] = useState(() => (variantTabs(variant).indexOf(params.get('tab')) !== -1 ? params.get('tab') : 'products'));
        const [cur, setCurState] = useState(() => store.get('cost-lab:cur', 'USD'));
        const [query, setQuery] = useState('');
        const [pending, setPending] = useState('');
        const [matchFilter, setMatchFilter] = useState('all');
        const [priceFilter, setPriceFilter] = useState('all');
        const [sideOpen, setSideOpen] = useState(true);
        const [showZero, setShowZero] = useState(false);
        const [density, setDensity] = useState(() => store.get('cost-lab:density', 'tight'));
        const [fullChips, setFullChips] = useState(false);
        const [collapsed, setCollapsed] = useState({});
        const [overrides, setOverrides] = useState({});
        const [matches, setMatches] = useState({});
        const [managed, setManaged] = useState({});
        const [matchLine, setMatchLine] = useState(null);
        const [legend, setLegend] = useState(false);

        const setVariant = (v) => { setVariantState(v); store.set('cost-lab:variant', v); if (variantTabs(v).indexOf(tab) === -1) { setTab('products'); } };
        const setCur = (c) => { setCurState(c); store.set('cost-lab:cur', c); };

        const statusOf = useCallback((line, dept) => {
            const key = line.id + '|' + dept;
            return overrides[key] ? overrides[key].s : seedState(line, dept, line.depts.indexOf(dept));
        }, [overrides]);

        const whoOf = (line, dept) => {
            const key = line.id + '|' + dept;
            const def = departments[dept] || {};
            return overrides[key] ? t('who_you') : (def.title || def.name) + ' · ' + seedWhen(line, dept) + ' ' + t('demo_mark');
        };

        const matchOf = useCallback((line) => matches[line.id] || line.match || { status: 'matched', catalog: line.name, path: '', uses: 1, suggestions: [] }, [matches]);

        const lab = {
            data, model, departments, convert, cur, symbol: (config.currency_symbols || {})[cur] || cur, symbols: config.currency_symbols || {},
            variant, setVariant, tab, setTab,
            query, setQuery, pending, setPending, matchFilter, setMatchFilter, priceFilter, setPriceFilter, sideOpen, setSideOpen, showZero, setShowZero,
            density, setDensity: (d) => { setDensity(d); store.set('cost-lab:density', d); }, fullChips, setFullChips,
            collapsed, managed, setManaged,
            filtering: pending !== '' || matchFilter !== 'all' || priceFilter !== 'all',
            statusOf, whoOf, matchOf,
            openUpload: () => { if (UPLOAD_TAB[variant]) { setTab('upload'); } else { setVariant(6); } },
            showOpenPrices: () => {
                setPriceFilter('open');
                if (variant === 6) { setVariant(1); }
                if (DATA_TABS.indexOf(tab) === -1) {
                    const first = DATA_TABS.find((key) => priceSplit(model.tabs[key].lines).nOpen > 0) || 'products';
                    setTab(first);
                }
            },
            passes: (line) => {
                if (priceFilter !== 'all' && (!line.inScope || priceOf(line) !== priceFilter)) { return false; }
                if (!showZero && !line.inScope && !query) { return false; }
                if (query) {
                    const q = lower(query);
                    const hay = lower(line.name + ' ' + (line.note || '') + ' ' + (matchOf(line).catalog || '') + ' ' + line.grp.name + ' ' + line.sec.name);
                    if (hay.indexOf(q) === -1) { return false; }
                }
                if (pending) {
                    if (!line.inScope) { return false; }
                    const depts = pending === 'any' ? line.depts : (line.depts.indexOf(pending) !== -1 ? [pending] : []);
                    if (!depts.some((dept) => statusOf(line, dept) !== 'ok')) { return false; }
                }
                if (matchFilter !== 'all' && matchOf(line).status !== matchFilter) { return false; }
                return true;
            },
            toggle: (id) => setCollapsed(Object.assign({}, collapsed, { [id]: !collapsed[id] })),
            expandAll: (open, onlyTab) => {
                if (open) { setCollapsed({}); return; }
                const next = {};
                model.tabs[onlyTab || tab].sections.forEach((sec) => { next[sec.id] = true; sec.groups.forEach((g) => { next[g.id] = true; }); });
                setCollapsed(next);
            },
            cycle: (line, dept) => {
                const now = statusOf(line, dept);
                const next = now === 'wait' ? 'ok' : (now === 'ok' ? 'no' : 'wait');
                setOverrides(Object.assign({}, overrides, { [line.id + '|' + dept]: { s: next } }));
                toast((departments[dept] || {}).name + ': ' + t('state_' + next) + ' · ' + t('demo_toast'), 'demo');
            },
            bulk: (lines, depts) => {
                const next = Object.assign({}, overrides);
                let n = 0;
                lines.forEach((line) => {
                    if (!line.inScope) { return; }
                    depts.forEach((dept) => {
                        if (line.depts.indexOf(dept) === -1 || statusOf(line, dept) === 'ok') { return; }
                        next[line.id + '|' + dept] = { s: 'ok' };
                        n++;
                    });
                });
                setOverrides(next);
                toast(t('bulk_done', { n }) + ' · ' + t('demo_toast'), 'demo');
            },
            openMatch: (line) => setMatchLine(line),
            setMatch: (line, value) => setMatches(Object.assign({}, matches, { [line.id]: value })),
            focusSection: (targetTab, id) => {
                if (lab.variant === 6) { setVariant(1); }
                setTab(targetTab);
                const next = Object.assign({}, collapsed);
                delete next[id];
                setCollapsed(next);
                window.setTimeout(() => {
                    const el = document.getElementById('kd-cl-' + id);
                    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); el.classList.add('flash'); window.setTimeout(() => el.classList.remove('flash'), 1400); }
                }, 60);
            },
        };

        if (!data.products) {
            return h('div', { className: 'kd-app' }, h('div', { className: 'kd-empty' }, t('no_data')));
        }

        const source = data.source || {};

        return h(Ctx.Provider, { value: lab },
            h('div', { className: 'kd-app kd-cl' },
                h('div', { className: 'kd-head' },
                    h('div', { className: 'kd-head-t' },
                        h('h2', null, t('title')),
                        h('span', { className: 'kd-badge' }, t('demo_badge')),
                        h('span', { className: 'kd-muted' }, source.project + ' · ' + source.client),
                        h('span', { className: 'kd-spacer' }),
                        h(Seg, {
                            size: 'sm',
                            label: t('currency'),
                            value: cur,
                            onChange: setCur,
                            options: [{ value: 'TRY', label: '₺', title: t('cur_try') }, { value: 'USD', label: '$', title: t('cur_usd') }, { value: 'EUR', label: '€', title: t('cur_eur') }],
                        }),
                        h('button', { type: 'button', className: cx('kd-btn', { on: legend }), onClick: () => setLegend(!legend) }, h(Icon, { name: 'info' }), t('legend')),
                    ),
                    h('div', { className: 'kd-cl-variants', role: 'tablist', 'aria-label': t('variants') },
                        VARIANTS.map((v) => h('button', {
                            key: v, type: 'button', role: 'tab', 'aria-selected': variant === v ? 'true' : 'false',
                            className: cx('kd-cl-variant', { on: variant === v }), onClick: () => setVariant(v), title: t('v' + v + '_d'),
                        }, h('span', { className: 'kd-num' }, v), h('span', null, t('v' + v)))),
                    ),
                    h('p', { className: 'kd-hint' }, h('b', null, variant + '. ' + t('v' + variant) + ': '), t('v' + variant + '_d')),
                    legend ? h(Legend) : null,
                    variant !== 6 && tab !== 'upload' ? h(MatchLine) : null,
                ),
                h(VariantBody),
                matchLine ? h(MatchModal, { line: matchLine, onClose: () => setMatchLine(null) }) : null,
                h('p', { className: 'kd-note' }, t('source_line', { file: source.file, time: config.generated_at })),
            ),
        );
    }

    KD.mount('cost-lab', CostLab);
}());
