/*
 * Konelsis - DEPARTMAN PANOLARI (D-173, 8 Ekim 2026; kullanici onayi: "tamamen
 * React ile").
 *
 * UI Deneme > Departman panolari. Ustteki departman seciciyle Genel /
 * Teklif - Is Gelistirme / Yonetici panolari arasinda gecilir; ekran secilen
 * departmana gore yeniden dizilir. Varsayilan kip kisinin kendi panosudur
 * (config.viewer.default_mode); secim adres cubugunda ?pano= olarak durur.
 * Gosterge bandi her kipte ustte yapiskandir. Durum dugmeleri denemedir:
 * kayit degismez (dash-widgets.js useDemoStatus).
 *
 * Kok: [data-kd-root="dash-app"]; data-config: DashboardAppConfig::make().
 */
(function () {
    'use strict';

    const KD = window.KonelsisDash;

    if (!KD || !KD.W) {
        return;
    }

    const { h, cx, useApp, Icon, Panel, Seg, Empty } = KD;
    const { useState } = KD.hooks;
    const W = KD.W;

    function Header(props) {
        const { mode, setMode } = props;
        const { config, t } = useApp();
        const own = config.viewer && config.viewer.default_mode;

        return h('div', { className: 'kd-head' },
            h('div', { className: 'kd-head-t' },
                h('h2', null, t('title_layout')),
                h('span', { className: 'kd-badge' }, t('demo_badge')),
                h('span', { className: 'kd-muted' }, t('generated', { time: config.generated_at })),
            ),
            h('div', { className: 'kd-head-s' },
                h('span', { className: 'kd-muted' }, t('switch_label')),
                h(Seg, {
                    value: mode,
                    onChange: setMode,
                    label: t('switch_label'),
                    options: (config.modes || []).map((m) => ({ value: m, label: t('mode_' + m), icon: m === own ? 'person' : null, title: m === own ? t('your_default', { mode: t('mode_' + m) }) : t('mode_hint_' + m) })),
                }),
            ),
            h('p', { className: 'kd-hint' }, t('mode_hint_' + mode), own ? ' ' + t('your_default', { mode: t('mode_' + own) }) + '.' : ''),
        );
    }

    /** Genel: ozel panosu olmayan herkes; mevcut Genel bakis degismez. */
    function GeneralMode(props) {
        const { data } = props;
        const { config, t } = useApp();

        return h('div', { className: 'kd-grid' },
            h('div', { className: 'kd-span-12 kd-callout' },
                h(Icon, { name: 'home' }),
                h('div', null, h('b', null, t('general_title')), h('p', null, t('general_note'))),
                config.urls && config.urls.dashboard ? h('a', { className: 'kd-btn primary', href: config.urls.dashboard }, h('span', null, t('open_standard')), h(Icon, { name: 'external' })) : null,
            ),
            h(Panel, { title: t('week_moves'), className: 'kd-span-12' }, h(W.WeekMoves, { data })),
            h(Panel, { title: t('alerts'), className: 'kd-span-12' }, h(W.AlertStrip, { data, only: ['past_meetings', 'overdue_actions', 'critical_work'] })),
            h(Panel, { title: t('notes'), className: 'kd-span-8', flush: true }, h(W.ActivityFeed, { data, limit: 10 })),
            h(Panel, { title: t('chart_weekly'), sub: t('one_axis_note'), className: 'kd-span-4' }, h(W.WeeklyCombo, { data, height: 150 })),
        );
    }

    /** Teklif - Is Gelistirme: teklif masasi. */
    function OfferMode(props) {
        const { data } = props;
        const { t } = useApp();

        return h('div', { className: 'kd-grid' },
            h('div', { className: 'kd-span-12' }, h(W.AlertStrip, { data })),
            h(Panel, { title: t('chart_monthly'), sub: t('one_axis_note'), className: 'kd-span-6' }, h(W.MonthlyCombo, { data, height: 168 })),
            h(Panel, { title: t('funnel'), className: 'kd-span-3' }, h(W.FunnelBox, { data })),
            h(Panel, { title: t('types'), className: 'kd-span-3' }, h(W.TypeDonut, { data }), h('div', { className: 'kd-sep' }), h(W.KindsBar, { data })),
            h('div', { className: 'kd-span-12' }, h(W.ProposalDesk, { rows: data.proposals, variant: 'cells', tableKey: 'offer-desk', maxHeight: 560 })),
            h(Panel, { title: t('critical'), className: 'kd-span-4', flush: true }, h(W.CriticalList, { data, limit: 8 })),
            h(Panel, { title: t('notes'), className: 'kd-span-8', flush: true }, h(W.ActivityFeed, { data, limit: 8 })),
            h(Panel, { title: t('orderbook'), className: 'kd-span-4' }, h(W.OrderBook, { data })),
            h(Panel, { title: t('parties'), className: 'kd-span-8', flush: true }, h(W.PartyBoard, { data, limit: 10, exportable: true })),
        );
    }

    /** Yonetici: sirketin tamami (D-147: canlida yalniz ust yonetim). */
    function ExecutiveMode(props) {
        const { data } = props;
        const { config, t } = useApp();
        const preview = !(config.viewer && config.viewer.is_executive);

        return h('div', { className: 'kd-grid' },
            preview ? h('div', { className: 'kd-span-12 kd-callout warn' }, h(Icon, { name: 'info' }), h('p', null, t('preview_executive'))) : null,
            h('div', { className: 'kd-span-12' }, h(W.AlertStrip, { data, only: ['stale_submitted', 'past_meetings', 'reports_waiting', 'approvals_waiting', 'critical_work'] })),
            h(Panel, { title: t('status_mix'), className: 'kd-span-4' }, h(W.StatusMix, { data }), h('div', { className: 'kd-sep' }), h(W.MoneyBoxes, { data })),
            h(Panel, { title: t('exec_trend'), sub: t('one_axis_note'), className: 'kd-span-5' }, h(W.MonthlyCombo, { data, focus: 'outcome', height: 190 })),
            h('div', { className: 'kd-span-3 kd-stack-col' },
                h(Panel, { title: t('exec_reports') }, h(W.ReportsBox, { data })),
                h(Panel, { title: t('week_moves') }, h(W.WeekMoves, { data })),
            ),
            h(Panel, { title: t('chart_weekly'), sub: t('one_axis_note'), className: 'kd-span-6' }, h(W.WeeklyCombo, { data, height: 160 })),
            h(Panel, { title: t('funnel'), className: 'kd-span-3' }, h(W.FunnelBox, { data })),
            h(Panel, { title: t('types'), className: 'kd-span-3' }, h(W.TypeDonut, { data })),
            h(Panel, { title: t('people'), sub: t('people_sub'), className: 'kd-span-6' }, h(W.TeamHeat, { data })),
            h(Panel, { title: t('people'), className: 'kd-span-6', flush: true }, h(W.PeopleTable, { data })),
            h(Panel, { title: t('parties'), className: 'kd-span-7', flush: true }, h(W.PartyBoard, { data, limit: 10, exportable: true })),
            h(Panel, { title: t('critical'), className: 'kd-span-5', flush: true }, h(W.CriticalList, { data, limit: 10 })),
            h('div', { className: 'kd-span-12' }, h(W.ProposalDesk, { rows: data.proposals, variant: 'docked', tableKey: 'exec-desk', maxHeight: 520 })),
        );
    }

    function DashApp() {
        const { config, t } = useApp();
        const data = config.data || {};
        const [mode, setModeState] = useState(config.mode || 'general');

        const setMode = (next) => {
            setModeState(next);
            try {
                const address = new URL(window.location.href);
                address.searchParams.set('pano', next);
                window.history.replaceState(window.history.state, '', address.toString());
            } catch (error) { /* adres guncellenemezse secim yalniz ekranda kalir */ }
        };

        return h('div', { className: cx('kd-app', 'kd-mode-' + mode) },
            h(Header, { mode, setMode }),
            !data.ready ? h(Empty, { text: t('not_ready') }) : h(KD.Fragment, null,
                // Genel kipte sirket geneli teklif bandi yok; o kisiler haftanin hareketlerini gorur.
                mode === 'general' ? null : h(W.Ticker, { data, sticky: true }),
                mode === 'offer' ? h(OfferMode, { data }) : (mode === 'executive' ? h(ExecutiveMode, { data }) : h(GeneralMode, { data })),
            ),
        );
    }

    KD.mount('dash-app', DashApp);
}());
