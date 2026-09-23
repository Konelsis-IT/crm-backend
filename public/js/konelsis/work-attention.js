/*
 * Konelsis - DIKKAT KARTI (B36, D-115, 22 Eylul 2026; kullanici karari: madde 8
 * React bileseni).
 *
 * Personel kartinda, ozel yetkiyle (ViewAttentionCard:WorkItem) gorunur; kisi
 * kendi kartini gormez. Son 12 haftanin haftalik kontrol matrisi ve rapor
 * disiplini: genel uygunluk ve degisim, kriter basina cubuk ve yuzde, uyari
 * sayisi / tekrar eden konu, gunu ve haftayi zamaninda kapatma, kontrolor
 * aciklamalari.
 *
 * Kok: [data-kw-root="work-attention"]; data-config: WorkAppConfig::attention($personnel).
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;

    if (!KW) {
        return;
    }

    const { h, cx, useApp, State } = KW;

    function barClass(pct) {
        if (pct === null || pct === undefined) { return ''; }
        if (pct >= 90) { return ''; }
        return pct >= 70 ? 'mid' : 'low';
    }

    function criterionSub(row, t) {
        if (row.auto) {
            return t('sub_weekly', { closed: row.closed || 0, of: row.of || 0 });
        }

        if (!row.warnings) {
            return null;
        }

        if (row.consecutive) {
            return t('sub_consecutive', { n: row.warnings });
        }

        if (row.weeks_ago === 0) {
            return t('sub_this_week', { n: row.warnings });
        }

        if (row.weeks_ago !== null && row.weeks_ago !== undefined && row.weeks_ago < 6) {
            return t('sub_ago', { n: row.warnings, w: row.weeks_ago });
        }

        return t('sub_old', { n: row.warnings });
    }

    function AttentionCard() {
        const app = useApp();
        const { t, config, api } = app;
        const resource = KW.useResource(() => api.get(config.endpoints.attention), []);
        const data = resource.data;

        const head = h('div', { className: 'kw-hr-head' },
            h('div', null,
                h('h4', null, t('attention_title')),
                data ? h('p', null, t('attention_sub', { w: data.weeks, c: data.controls, d: data.workdays })) : null,
            ),
            h('span', { className: 'kw-lock' }, t('attention_lock')),
        );

        if (!data) {
            return h('div', { className: 'kw-attention-host' }, h('div', { className: 'kw-hr-card', 'aria-label': t('attention_title') },
                head,
                h(State, { loading: resource.loading, error: resource.error, onRetry: resource.reload }),
            ));
        }

        const delta = data.delta;
        const deltaText = delta === null || delta === undefined
            ? null
            : (delta > 0 ? t('delta_up', { n: delta, w: data.weeks }) : (delta < 0 ? t('delta_down', { n: Math.abs(delta), w: data.weeks }) : t('delta_same', { w: data.weeks })));

        return h('div', { className: 'kw-attention-host' }, h('div', { className: 'kw-hr-card', 'aria-label': t('attention_title') },
            head,
            data.checked ? h('div', { className: 'kw-hr-body' },
                h('div', { className: 'kw-hr-score' },
                    h('span', { className: 'kw-l' }, t('overall')),
                    h('span', { className: 'kw-big' }, data.score !== null ? data.score + '%' : '–'),
                    deltaText ? h('span', { className: cx('kw-delta', delta < 0 && 'down', delta === 0 && 'same') }, deltaText) : null,
                    h('span', { className: 'kw-l' }, t('checks', { ok: data.ok, total: data.checked })),
                ),
                h('div', { className: 'kw-crit' }, data.criteria.map((row) => {
                    const sub = criterionSub(row, t);

                    return h('div', { key: row.code, className: 'kw-crit-row' },
                        h('span', null, row.label),
                        h('span', { className: 'kw-bar', role: 'img', 'aria-label': row.label + ' ' + (row.pct === null ? '–' : row.pct + '%') },
                            h('b', { className: barClass(row.pct), style: { width: (row.pct || 0) + '%' } }),
                        ),
                        h('span', { className: 'kw-pct' }, row.pct === null ? '–' : row.pct + '%'),
                        sub ? h('span', { className: 'kw-crit-sub' }, sub) : null,
                    );
                })),
            ) : h(State, { text: t('attention_empty') }),
            h('div', { className: 'kw-hr-foot' },
                h('div', null, h('b', null, data.days_on_time + ' / ' + data.workdays), t('foot_days')),
                h('div', null, h('b', null, data.weeks_on_time + ' / ' + data.weeks), data.revisions ? t('foot_weeks', { r: data.revisions }) : t('foot_weeks_plain')),
                h('div', null, h('b', null, String(data.notes)), data.last_note ? t('foot_notes', { note: data.last_note }) : t('foot_notes_plain')),
            ),
            h('div', { className: 'kw-hr-note' },
                h('span', null, t('note_source')),
                h('span', null, t('note_viewers')),
                h('span', null, t('note_self')),
            ),
        ));
    }

    KW.mount('work-attention', AttentionCard);
}());
