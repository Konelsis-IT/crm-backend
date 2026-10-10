/*
 * Konelsis - PANO BILESEN KATALOGU (D-173, 8 Ekim 2026).
 *
 * UI Deneme > Pano bilesenleri: departman panolarina konabilecek numarali
 * bilesen secenekleri. Kullanici numarayla secer ("3 ve 7'yi kullan").
 * Hepsi gercek veriyle (salt okunur); durum dugmeleri kayit degistirmez.
 * Bilesenler dash-widgets.js'tedir; burada yalniz numarali dizilir.
 * Numaralar sabittir: yeni secenek sona eklenir, mevcut numara degismez
 * (UI Deneme kalici katalogdur, hicbir sey silinmez).
 *
 * Kok: [data-kd-root="dash-catalog"]; data-config: DashboardAppConfig::make().
 */
(function () {
    'use strict';

    const KD = window.KonelsisDash;

    if (!KD || !KD.W) {
        return;
    }

    const { h, useApp, Panel, Empty } = KD;
    const { useState } = KD.hooks;
    const W = KD.W;

    /** 10: sayili bolmeli dugme (suzgec gibi) + tek dugme (siradaki duruma gecer). */
    function StatusVariants(props) {
        const { data } = props;
        const { t } = useApp();
        const rows = (data.proposals || []).slice(0, 6);
        const demo = W.useDemoStatus();
        const [filter, setFilter] = useState('all');

        if (!rows.length) { return h(Empty, { text: t('no_rows_permission') }); }

        return h('div', { className: 'kd-variants' },
            h(W.StatusSeg, { rows: data.proposals || [], value: filter, onChange: setFilter, statusOf: demo.statusOf }),
            h('table', { className: 'kd-table' }, h('tbody', null, rows.map((row) => h('tr', { key: row.id },
                h('td', { className: 'kd-mono' }, row.no),
                h('td', { className: 'kd-strong', title: row.party_full }, row.party),
                h('td', { className: 'kd-ell' }, row.title),
                h('td', { className: 'kd-act' }, h(W.StatusButtons, { value: demo.statusOf(row), variant: 'cycle', onPick: (next) => demo.pick(row, next) })),
            )))),
        );
    }

    /** 9: yalniz simge grubu, birkac satirda. */
    function IconGroupDemo(props) {
        const { data } = props;
        const { t } = useApp();
        const rows = (data.proposals || []).slice(0, 6);
        const demo = W.useDemoStatus();

        if (!rows.length) { return h(Empty, { text: t('no_rows_permission') }); }

        return h('table', { className: 'kd-table' }, h('tbody', null, rows.map((row) => h('tr', { key: row.id, className: demo.overrides[row.id] ? 'kd-demo' : null },
            h('td', { className: 'kd-mono' }, row.no),
            h('td', { className: 'kd-strong', title: row.party_full }, row.party),
            h('td', null, h(KD.StatusPill, { status: demo.statusOf(row) })),
            h('td', { className: 'kd-act' }, h(W.StatusButtons, { value: demo.statusOf(row), onPick: (next) => demo.pick(row, next) })),
        ))));
    }

    function Catalog() {
        const { config, t } = useApp();
        const data = config.data || {};

        if (!data.ready) {
            return h('div', { className: 'kd-app' }, h(Empty, { text: t('not_ready') }));
        }

        const items = [
            [1, 12, h(W.Ticker, { data })],
            [2, 12, h(W.KpiTiles, { data })],
            [3, 6, h(W.MonthlyCombo, { data })],
            [4, 6, h(W.WeeklyCombo, { data })],
            [5, 12, h(W.Multiples, { data })],
            [6, 12, h(W.ProposalDesk, { rows: data.proposals, variant: 'cells', tableKey: 'cat-6', maxHeight: 360, title: t('c6'), num: null })],
            [7, 12, h(W.ProposalDesk, { rows: data.proposals, variant: 'rowbg', tableKey: 'cat-7', maxHeight: 360, title: t('c7') })],
            [8, 12, h(W.ProposalDesk, { rows: data.proposals, variant: 'docked', tableKey: 'cat-8', maxHeight: 360, title: t('c8') })],
            [9, 6, h(IconGroupDemo, { data })],
            [10, 6, h(StatusVariants, { data })],
            [11, 4, h(W.FunnelBox, { data })],
            [12, 8, h(W.TeamHeat, { data })],
            [13, 8, h(W.PartyBoard, { data, exportable: true })],
            [14, 4, h(W.TypeDonut, { data })],
            [15, 12, h(W.AlertStrip, { data })],
            [16, 6, h(W.ActivityFeed, { data })],
            [17, 6, h('div', null, h(W.StatusMix, { data }), h('div', { className: 'kd-sep' }), h(W.MoneyBoxes, { data }))],
            [18, 4, h(W.OrderBook, { data })],
            [19, 3, h(W.CriticalList, { data })],
            [20, 5, h(W.PeopleTable, { data, limit: 8 })],
        ];

        return h('div', { className: 'kd-app' },
            h('div', { className: 'kd-head' },
                h('div', { className: 'kd-head-t' },
                    h('h2', null, t('title_catalog')),
                    h('span', { className: 'kd-badge' }, t('demo_badge')),
                    h('span', { className: 'kd-muted' }, t('generated', { time: config.generated_at })),
                ),
                h('p', { className: 'kd-hint' }, t('catalog_intro')),
                h('nav', { className: 'kd-toc' }, items.map((item) => h('a', { key: item[0], href: '#kd-c' + item[0], title: t('c' + item[0]) }, item[0]))),
            ),
            h('div', { className: 'kd-grid' }, items.map((item) => {
                const [num, span, body] = item;
                // 6-8 kendi panelini cizer (arac cubugu ile); numara ve aciklama ustte.
                const own = num >= 6 && num <= 8;

                return h('div', { key: num, id: 'kd-c' + num, className: 'kd-span-' + span + ' kd-cat' },
                    h('div', { className: 'kd-cat-h' }, h('span', { className: 'kd-num big' }, num), h('div', null, h('b', null, t('c' + num)), h('p', null, t('c' + num + '_d')))),
                    own ? body : h(Panel, { flush: [13, 16, 19, 20].indexOf(num) !== -1 }, body),
                );
            })),
        );
    }

    KD.mount('dash-catalog', Catalog);
}());
