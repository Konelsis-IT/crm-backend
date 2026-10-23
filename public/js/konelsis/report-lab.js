/*
 * Konelsis - RAPOR YAZMA DENEMESI (UI Deneme, D-240, 23 Ekim 2026).
 *
 * Kullanici: "Tum rapor tiplerinin rapor arayuzunun tasarimini react ile modern
 * benzer formatta bir arayuze cevirelim. Tum rapor tipleri icin bu duz text yapisi
 * gibi ... rapor icine is olusturma mantigi nasil dizayn edilebilirse raporlar
 * kismindaki tum rapor tipleri icin ornek calismalarini UI deneme kismina ekle ...
 * Inanilmaz basit ve temiz bir arayuz lazim insanlari yormamaliyiz."
 *
 * - Solda rapor tipleri (gercek taslaklar, ReportLabConfig); sagda tek sayfa rapor.
 * - Metin alanlari Akilli is ekle duzenleyicisidir (prose: sabit durum / Sorumlu
 *   satiri yok, yalniz kayit onerileri). "Satirlari ise donustur": her satir ayri is.
 * - Plan alani (lines) dogrudan is yazma alanidir (baslangic yarin / gelecek
 *   pazartesi); plan alani olmayan raporlarda "Bu rapordan cikan isler". Konu
 *   secilince yeni is satirlari konunun rozetiyle baslar.
 * - Kaynak onerileri (gorusme notu, rapor) isaretlenir; "Ise donustur" sonraki
 *   adimi is satiri yapar. Calisma raporlarinda donemin isleri panodan gelir.
 * - Duz yazi: raporun tamami tek metin (Kopyala). Kayit yoktur (deneme).
 *
 * Kok [data-kw-root="report-lab"]; yapilandirma ReportLabConfig::make().
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;
    const Q = window.KonelsisWorkQuick;

    if (!KW || !Q || window.KonelsisReportLab) {
        return;
    }

    const { h, cx, useApp, Btn, State } = KW;
    const { useState, useEffect, useRef, useCallback, useMemo } = KW.hooks;
    const { Icon } = Q;

    const GROUPS = ['work', 'record', 'people', 'system'];
    const CALENDAR = { day: true, week: true, month: true };
    const OWN_WORK = { daily: true, weekly: true, monthly: true };
    const RANGES = ['this_week', 'last_week', 'this_month'];
    const STORE = 'konelsis.report-lab.type';
    const PROSE = { long_text: true };
    const noop = () => {};

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    const hasText = (model) => (model || []).some((item) => item.t === 'text' && item.v.trim() !== '');

    function addDays(ymd, days) {
        const parts = ymd.split('-').map(Number);

        return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2] + days)).toISOString().slice(0, 10);
    }

    /** Plan satirinin baslangici: "Yarin plani" -> yarin, "Gelecek hafta plani" -> gelecek pazartesi. */
    function planStart(period, today) {
        if (period !== 'week') { return addDays(today, 1); }

        const parts = today.split('-').map(Number);
        const weekday = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2])).getUTCDay();

        return addDays(today, ((8 - weekday) % 7) || 7);
    }

    /** Yazidaki satir sonlari (yapistirilan metinde "\n") ayri satir sayilir. */
    function splitBreaks(model) {
        const out = [];

        (model || []).forEach((item) => {
            if (item.t !== 'text' || item.v.indexOf('\n') === -1) {
                out.push(item);

                return;
            }

            item.v.split('\n').forEach((part, index) => {
                if (index) { out.push({ t: 'br' }); }
                if (part) { out.push({ t: 'text', v: part }); }
            });
        });

        return out;
    }

    /** Yazinin satirlari (bos olanlar haric), her biri ayri okunus; bas ve sondaki bosluk atilir. */
    function linesOf(model) {
        return Q.paragraphsOf(splitBreaks(model)).filter(hasText).map((line) => {
            const out = line.filter((item) => !(item.t === 'text' && item.v === ''));

            while (out.length && out[0].t === 'text' && !out[0].v.trim()) { out.shift(); }
            while (out.length && out[out.length - 1].t === 'text' && !out[out.length - 1].v.trim()) { out.pop(); }

            return out;
        });
    }

    /** Konu rozeti: yalniz is satirinin tasiyabilecegi kayitlar (kisi Sorumlu olacagi icin haric). */
    function subjectBadge(subject, ctx) {
        if (!subject || subject.type === 'person') { return null; }

        if (Q.ENTITY_TYPES.indexOf(subject.type) === -1 && (ctx.linkTypes || []).indexOf(subject.type) === -1) { return null; }

        return { t: 'badge', b: { type: subject.type, id: subject.id, label: subject.label, code: subject.code || undefined } };
    }

    /* ------------------------------------------------------------------ */
    /* Alanlar                                                              */
    /* ------------------------------------------------------------------ */

    function FieldLabel(props) {
        const { field, htmlFor, children, note } = props;
        const { t } = useApp();

        return h('div', { className: 'rl-label' },
            h(htmlFor ? 'label' : 'span', { className: 'rl-label-text', htmlFor },
                field.label,
                field.required ? h('small', { className: 'rl-req' }, t('rl_required')) : null),
            note ? h('span', { className: 'rl-label-note' }, note) : null,
            children ? h('span', { className: 'rl-label-tools' }, children) : null);
    }

    function ProseField(props) {
        const { field, ctx, value, setValue, onToWork, invalid } = props;
        const { t } = useApp();
        const apiRef = useRef(null);
        const onChange = useCallback((model) => setValue(field.name, model), [field.name, setValue]);

        return h('div', { className: cx('rl-field', invalid && 'invalid') },
            h(FieldLabel, { field },
                hasText(value) ? h('button', {
                    type: 'button',
                    className: 'rl-link',
                    title: t('rl_lines_to_work_help'),
                    onMouseDown: (event) => event.preventDefault(),
                    onClick: () => onToWork(linesOf(value)),
                }, h(Icon, { name: 'arrow' }), t('rl_lines_to_work')) : null),
            h(Q.QuickEditor, { apiRef, ctx, prose: true, placeholder: field.help || t('rl_write'), onChange, onSave: noop }));
    }

    function TextField(props) {
        const { field, value, setValue, invalid } = props;
        const id = 'rl-' + field.name;

        return h('div', { className: cx('rl-field', invalid && 'invalid') },
            h(FieldLabel, { field, htmlFor: id }),
            h('input', { id, className: 'rl-input', value: value || '', placeholder: field.help || '', onChange: (event) => setValue(field.name, event.target.value) }));
    }

    function ChoiceField(props) {
        const { field, value, setValue, invalid } = props;

        return h('div', { className: cx('rl-field', invalid && 'invalid') },
            h(FieldLabel, { field }),
            h('div', { className: 'rl-pills', role: 'radiogroup', 'aria-label': field.label }, field.options.map((option) => h('button', {
                key: option.value,
                type: 'button',
                role: 'radio',
                'aria-checked': String(value === option.value),
                className: cx('rl-pill', value === option.value && 'on'),
                onClick: () => setValue(field.name, value === option.value ? null : option.value),
            }, option.label))));
    }

    function RatingField(props) {
        const { field, value, setValue, invalid } = props;
        const { t } = useApp();
        const min = field.min === null || field.min === undefined ? 1 : field.min;
        const max = field.max === null || field.max === undefined ? 5 : field.max;
        const steps = [];

        for (let n = min; n <= max; n++) { steps.push(n); }

        return h('div', { className: cx('rl-field', 'rl-inline', invalid && 'invalid') },
            h(FieldLabel, { field }),
            h('div', { className: 'rl-rating', role: 'radiogroup', 'aria-label': field.label },
                steps.map((n) => h('button', {
                    key: n,
                    type: 'button',
                    role: 'radio',
                    'aria-checked': String(value === n),
                    'aria-label': String(n),
                    className: cx('rl-dot', value !== null && value !== undefined && n <= value && 'on'),
                    onClick: () => setValue(field.name, value === n ? null : n),
                }, n)),
                h('span', { className: 'rl-rating-val' }, value ? t('rl_out_of', { value, max }) : '')));
    }

    function PercentField(props) {
        const { field, value, setValue, invalid } = props;
        const id = 'rl-' + field.name;
        const set = value !== null && value !== undefined && value !== '';

        return h('div', { className: cx('rl-field', 'rl-inline', invalid && 'invalid') },
            h(FieldLabel, { field, htmlFor: id }),
            h('div', { className: cx('rl-percent', !set && 'unset') },
                h('input', {
                    id,
                    type: 'range',
                    min: field.min === null ? 0 : field.min,
                    max: field.max === null ? 100 : field.max,
                    step: 5,
                    value: set ? value : 0,
                    onChange: (event) => setValue(field.name, Number(event.target.value)),
                }),
                h('output', { className: 'rl-percent-val', htmlFor: id }, set ? value + '%' : '–')));
    }

    function NumberField(props) {
        const { field, value, setValue, invalid } = props;
        const id = 'rl-' + field.name;
        const decimal = field.type === 'decimal';

        return h('div', { className: cx('rl-field', 'rl-inline', invalid && 'invalid') },
            h(FieldLabel, { field, htmlFor: id }),
            h('div', { className: 'rl-num' },
                h('input', {
                    id,
                    type: 'number',
                    inputMode: decimal ? 'decimal' : 'numeric',
                    min: field.min === null ? undefined : field.min,
                    max: field.max === null ? undefined : field.max,
                    step: decimal ? '0.01' : '1',
                    value: value === null || value === undefined ? '' : value,
                    onChange: (event) => setValue(field.name, event.target.value === '' ? null : event.target.value),
                }),
                field.unit && field.unit !== '%' ? h('span', { className: 'rl-unit' }, field.unit) : null));
    }

    function KeyValueField(props) {
        const { field, value, setValue } = props;
        const { t } = useApp();
        const rows = value && value.length ? value : [{ k: '', v: '' }];
        const update = (index, patch) => setValue(field.name, rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));

        return h('div', { className: 'rl-field' },
            h(FieldLabel, { field }),
            field.help ? h('p', { className: 'rl-help' }, field.help) : null,
            h('div', { className: 'rl-kv' },
                rows.map((row, index) => h('div', { key: index, className: 'rl-kv-row' },
                    h('input', { className: 'rl-input', value: row.k, placeholder: t('rl_kv_name'), 'aria-label': t('rl_kv_name'), onChange: (event) => update(index, { k: event.target.value }) }),
                    h('input', { className: 'rl-input', value: row.v, placeholder: t('rl_kv_value'), 'aria-label': t('rl_kv_value'), onChange: (event) => update(index, { v: event.target.value }) }),
                    h('button', {
                        type: 'button',
                        className: 'kq-row-btn',
                        title: t('rl_kv_remove'),
                        'aria-label': t('rl_kv_remove'),
                        disabled: rows.length === 1,
                        onClick: () => setValue(field.name, rows.filter((_, i) => i !== index)),
                    }, h(Icon, { name: 'close' })))),
                h('button', { type: 'button', className: 'rl-link', onClick: () => setValue(field.name, rows.concat([{ k: '', v: '' }])) }, h(Icon, { name: 'plus' }), t('rl_kv_add'))));
    }

    /** Gorusme notu / rapor onerileri: isaretliler rapora girer; sonraki adim ise donusur. */
    function SourcesField(props) {
        const { field, block, value, setValue, onToWork } = props;
        const { t } = useApp();
        const rows = block ? block.rows : [];
        const off = value || {};

        return h('div', { className: 'rl-field' },
            h(FieldLabel, { field }),
            field.help ? h('p', { className: 'rl-help' }, field.help) : null,
            block && block.sample ? h('p', { className: 'rl-sample' }, t('rl_sources_sample')) : null,
            rows.length ? h('ul', { className: 'rl-sources' }, rows.map((row) => h('li', { key: row.key, className: cx('rl-source', off[row.key] && 'off') },
                h('label', { className: 'rl-source-head' },
                    h('input', { type: 'checkbox', checked: !off[row.key], onChange: () => setValue(field.name, { ...off, [row.key]: !off[row.key] }) }),
                    h('span', { className: 'rl-source-title' }, row.title),
                    row.date ? h('span', { className: 'rl-source-date' }, row.date) : null),
                row.meta ? h('p', { className: 'rl-source-meta' }, row.meta) : null,
                row.text ? h('p', { className: 'rl-source-text' }, row.text) : null,
                h('div', { className: 'rl-source-foot' },
                    row.next ? h('span', { className: 'rl-source-next' }, t('rl_next', { text: row.next })) : h('span'),
                    h('button', {
                        type: 'button',
                        className: 'rl-link',
                        onClick: () => onToWork([[{ t: 'text', v: row.next || row.title }]]),
                    }, h(Icon, { name: 'arrow' }), t('rl_to_work')))))) : h('p', { className: 'rl-empty' }, t('rl_sources_empty')));
    }

    /** Donemin isleri (calisma raporlari): panodan, yalniz okunur. */
    function PeriodWork(props) {
        const { type, period } = props;
        const { t } = useApp();
        const work = (period && period.work) || [];

        return h('div', { className: 'rl-field' },
            h('div', { className: 'rl-label' },
                h('span', { className: 'rl-label-text' }, t('rl_work_' + type.period)),
                h('span', { className: 'rl-label-note' }, t('rl_work_auto'))),
            work.length
                ? h('ul', { className: 'rl-work' }, work.map((item) => h('li', { key: item.id }, h(Q.SentenceView, { segments: item.sentence }))))
                : h('p', { className: 'rl-empty' }, t('rl_work_empty')));
    }

    /** Is yazma alani: plan satirlari ya da "Bu rapordan cikan isler". */
    function WorkBlock(props) {
        const { id, title, required, hint, placeholder, seed, apiRef, onModels, invalid } = props;
        const c = Q.useComposer({ mode: 'create', compact: true, seed, placeholder });
        const signature = JSON.stringify(c.models);

        apiRef.current = { push: c.push };

        useEffect(() => { onModels(c.models); }, [signature]);

        return h('div', { className: cx('rl-field', 'rl-works', invalid && 'invalid'), id },
            h(FieldLabel, { field: { label: title, required }, note: hint }),
            c.loadError ? h(State, { error: c.loadError, onRetry: c.load }) : h('div', { className: 'kq rl-kq' }, c.toolbar, c.rowsEl, c.addButton, c.pickerEl));
    }

    function RangeChip(props) {
        const { value, onChange } = props;
        const { t } = useApp();
        const [open, setOpen] = useState(false);
        const ref = useRef(null);
        const close = useCallback(() => setOpen(false), []);

        KW.useClickOutside(ref, close, open);
        KW.useEscape(close, open);

        return h('div', { className: 'kq-menu', ref },
            h('button', { type: 'button', className: 'rl-chip', 'aria-expanded': String(open), onClick: () => setOpen(!open) },
                h(Icon, { name: 'start' }), t('range_' + value), h(Icon, { name: 'chevron', className: 'kq-caret' })),
            open ? h('div', { className: 'kq-menu-box rl-range-box', role: 'menu' }, RANGES.map((range) => h('button', {
                key: range,
                type: 'button',
                role: 'menuitemradio',
                'aria-checked': String(range === value),
                className: cx('kq-menu-item', 'kq-t-start', range === value && 'on'),
                onClick: () => { setOpen(false); onChange(range); },
            }, t('range_' + range)))) : null);
    }

    /* ------------------------------------------------------------------ */
    /* Rapor                                                                */
    /* ------------------------------------------------------------------ */

    function fieldText(field, value, t, block) {
        if (value === null || value === undefined || value === '') { return ''; }

        switch (field.type) {
            case 'long_text':
                return Q.plainText(value, t);
            case 'rating':
                return t('rl_out_of', { value, max: field.max || 5 });
            case 'percent':
                return value + '%';
            case 'integer':
            case 'decimal':
                return String(value) + (field.unit && field.unit !== '%' ? ' ' + field.unit : '');
            case 'choice':
                return ((field.options.find((option) => option.value === value) || {}).label) || value;
            case 'key_value':
                return value.filter((row) => row.k.trim() || row.v.trim()).map((row) => row.k + ': ' + row.v).join('\n');
            default:
                return String(value);
        }
    }

    function ReportDoc(props) {
        const { type, ctx } = props;
        const app = useApp();
        const { t, config } = app;
        const period = CALENDAR[type.period] ? (config.periods || {})[type.period] || null : null;
        const [range, setRange] = useState('this_month');
        const [subject, setSubject] = useState(null);
        const [picker, setPicker] = useState(null);
        const [values, setValues] = useState({});
        const [works, setWorks] = useState([]);
        const [plain, setPlain] = useState(false);
        const [missing, setMissing] = useState([]);
        const [epoch, setEpoch] = useState(0);
        const workApi = useRef(null);
        const mounted = useRef(false);
        const planField = type.fields.find((field) => field.type === 'lines') || null;
        const workText = works.filter(hasText);
        // Donemin isleri yalniz kisinin calisma raporlarinda (koordinasyon panosu kisisel degil).
        const showWork = !!period && type.board && OWN_WORK[type.kind] === true;

        const setValue = useCallback((name, value) => {
            setValues((map) => ({ ...map, [name]: value }));
            setMissing((list) => (list.indexOf(name) === -1 ? list : list.filter((other) => other !== name)));
        }, []);

        const badge = subjectBadge(subject, ctx);
        const seed = useMemo(() => {
            const out = [];

            if (badge) { out.push(badge); }

            if (planField && config.today) {
                out.push({ t: 'badge', b: { type: 'start', value: planStart(type.period, config.today), label: t('qa_type_start') } });
            }

            return out;
        }, [badge ? badge.b.type + ':' + badge.b.id : '', planField, config.today, type.period, t]);

        // Konu degisti ve henuz is yazilmadi: is satirlari yeni konuyla baslar.
        useEffect(() => {
            if (!mounted.current) {
                mounted.current = true;

                return;
            }

            if (!workText.length) { setEpoch((value) => value + 1); }
        }, [badge ? badge.b.type + ':' + badge.b.id : '']);

        const toWork = (list) => {
            if (!workApi.current || !list.length) { return; }

            const extra = seed.length ? [{ t: 'text', v: ' ' }].concat(seed) : [];
            workApi.current.push(list.map((segments) => segments.concat(extra)));
            app.toast({ text: t('rl_converted', { n: list.length }), tone: 'ok' });
        };

        const required = () => {
            const out = type.fields.filter((field) => {
                if (!field.required) { return false; }

                const value = values[field.name];

                return field.type === 'long_text' ? !hasText(value) : value === null || value === undefined || value === '';
            }).map((field) => field.name);

            if (subject === null && type.subject) { out.unshift('__subject'); }

            return out;
        };

        const send = () => {
            if (!type.manual) {
                app.toast(workText.length ? { text: t('qa_demo_saved', { n: workText.length }), tone: 'ok' } : { text: t('qa_empty_rows'), tone: 'bad' });

                return;
            }

            const list = required();
            setMissing(list);

            if (list.length) {
                const names = list.map((name) => (name === '__subject' ? Q.typeLabel(t, type.subject) : (type.fields.find((field) => field.name === name) || {}).label));
                app.toast({ text: t('rl_missing', { list: names.join(', ') }), tone: 'bad', duration: 6000 });

                return;
            }

            app.toast({ text: t('rl_demo_sent', { n: workText.length }), tone: 'ok' });
        };

        const plainReport = () => {
            const out = [type.name + (period ? ' · ' + period.label : (type.period === 'range' ? ' · ' + t('range_' + range) : ''))];

            if (subject) { out.push(t('rl_subject') + ': ' + subject.label); }

            if (showWork && period.work.length) {
                out.push('', t('rl_work_' + type.period));
                period.work.forEach((item) => out.push('• ' + Q.plainText(Q.editorSegments(item.sentence), t).replace(/\n/g, ' · ')));
            }

            type.fields.forEach((field) => {
                let text = '';

                if (field.type === 'lines') {
                    text = workText.map((model) => '• ' + Q.plainText(model, t).replace(/\n/g, ' · ')).join('\n');
                } else if (field.type === 'sources') {
                    const block = period ? (period.sources || {})[field.source] : null;
                    const off = values[field.name] || {};
                    text = (block ? block.rows : []).filter((row) => !off[row.key]).map((row) => '• ' + row.title + (row.date ? ' (' + row.date + ')' : '')).join('\n');
                } else {
                    text = fieldText(field, values[field.name], t);
                }

                if (text) { out.push('', field.label, text); }
            });

            if (!planField && workText.length) {
                out.push('', t('rl_works_title'));
                workText.forEach((model) => out.push('• ' + Q.plainText(model, t).replace(/\n/g, ' · ')));
            }

            return out.join('\n');
        };

        const fieldEl = (field) => {
            const common = { key: field.name, field, value: values[field.name], setValue, invalid: missing.indexOf(field.name) !== -1 };

            if (field.type === 'lines') {
                return h(WorkBlock, {
                    key: 'w' + epoch,
                    id: 'rl-works',
                    title: field.label,
                    required: field.required,
                    hint: t('rl_plan_hint'),
                    placeholder: t('rl_plan_placeholder'),
                    seed,
                    apiRef: workApi,
                    onModels: setWorks,
                });
            }

            if (PROSE[field.type]) { return h(ProseField, { ...common, ctx, onToWork: toWork }); }

            switch (field.type) {
                case 'choice': return h(ChoiceField, common);
                case 'rating': return h(RatingField, common);
                case 'percent': return h(PercentField, common);
                case 'integer':
                case 'decimal': return h(NumberField, common);
                case 'key_value': return h(KeyValueField, common);
                case 'sources': return h(SourcesField, { ...common, block: period ? (period.sources || {})[field.source] : null, onToWork: toWork });
                default: return h(TextField, common);
            }
        };

        // Kisa alanlar (puan, secim, yuzde, sayi) yan yana tek kartta; yazi alanlari alt alta.
        const SHORT = { rating: true, choice: true, percent: true, integer: true, decimal: true };
        const shortFields = type.fields.filter((field) => SHORT[field.type]);
        const longFields = type.fields.filter((field) => !SHORT[field.type]);

        const subjectEl = type.subject ? (subject
            ? h('button', {
                type: 'button',
                className: cx('kq-badge', 'kq-t-' + subject.type, 'rl-subject'),
                title: t('rl_subject'),
                onClick: (event) => setPicker({ rect: event.currentTarget.getBoundingClientRect() }),
            }, h(Icon, { name: subject.type }), h('span', { className: 'kq-badge-text' }, subject.label))
            : h('button', {
                type: 'button',
                className: cx('rl-chip', 'rl-chip-add', 'kq-t-' + type.subject, missing.indexOf('__subject') !== -1 && 'invalid'),
                onClick: (event) => setPicker({ rect: event.currentTarget.getBoundingClientRect() }),
            }, h(Icon, { name: type.subject }), t('rl_subject_pick', { type: Q.typeLabel(t, type.subject) }))) : null;

        return h('article', { className: 'rl-doc', 'aria-label': type.name },
            h('header', { className: 'rl-head' },
                h('p', { className: 'rl-kicker' }, type.kind_label),
                h('h2', { className: 'rl-name' }, type.name),
                h('p', { className: 'rl-desc' }, type.description),
                h('div', { className: 'rl-chips' },
                    period ? h('span', { className: 'rl-chip static' }, h(Icon, { name: 'start' }), period.label) : null,
                    type.period === 'range' ? h(RangeChip, { value: range, onChange: setRange }) : null,
                    subjectEl,
                    type.confidential ? h('span', { className: 'rl-chip static rl-chip-lock' }, h(Icon, { name: 'lock' }), t('rl_confidential')) : null)),

            !type.manual ? h('div', { className: 'rl-body' },
                h('div', { className: 'rl-system' },
                    h(Icon, { name: 'cog' }),
                    h('div', null,
                        h('p', null, t('rl_system_note')),
                        h('ul', { className: 'rl-system-list' }, type.fields.map((field) => h('li', { key: field.name }, field.label))))),
                showWork ? h(PeriodWork, { type, period }) : null,
                h(WorkBlock, { key: 'w' + epoch, id: 'rl-works', title: t('rl_works_title'), hint: t('rl_works_hint'), placeholder: t('rl_works_placeholder'), seed, apiRef: workApi, onModels: setWorks }),
            ) : h('div', { className: 'rl-body' },
                showWork ? h(PeriodWork, { type, period }) : null,
                shortFields.length ? h('div', { className: 'rl-short' }, shortFields.map(fieldEl)) : null,
                longFields.map(fieldEl),
                planField ? null : h(WorkBlock, { key: 'w' + epoch, id: 'rl-works', title: t('rl_works_title'), hint: t('rl_works_hint'), placeholder: t('rl_works_placeholder'), seed, apiRef: workApi, onModels: setWorks }),
            ),

            plain ? h('div', { className: 'rl-plain' }, h(Q.PlainBox, { text: plainReport() })) : null,

            h('footer', { className: 'rl-foot' },
                h('button', {
                    type: 'button',
                    className: cx('kq-row-btn', 'rl-plain-btn', plain && 'on'),
                    'aria-pressed': String(plain),
                    onClick: () => setPlain(!plain),
                }, h(Icon, { name: 'text' }), t('qa_plain')),
                h('span', { className: 'rl-foot-gap' }),
                type.manual ? h(Btn, { onClick: () => app.toast({ text: t('rl_demo_draft'), tone: 'ok' }) }, t('rl_draft')) : null,
                h(Btn, { variant: 'primary', onClick: send }, h(Icon, { name: 'send' }), type.manual ? t('rl_send') : t('qa_save_one'))),

            picker ? h(Q.PickerPop, {
                type: type.subject,
                rect: picker.rect,
                ctx,
                onClose: () => setPicker(null),
                onPick: (item) => { setPicker(null); setSubject(item); setMissing((list) => list.filter((name) => name !== '__subject')); },
            }) : null,
        );
    }

    function TypeNav(props) {
        const { types, value, onChange } = props;
        const { t } = useApp();

        return h('nav', { className: 'rl-nav', 'aria-label': t('rl_types') }, GROUPS.map((group) => {
            const list = types.filter((type) => type.group === group);

            if (!list.length) { return null; }

            return h('div', { key: group, className: 'rl-nav-group' },
                h('p', { className: 'rl-nav-title' }, t('rl_group_' + group)),
                list.map((type) => h('button', {
                    key: type.code,
                    type: 'button',
                    className: cx('rl-nav-item', type.code === value && 'on'),
                    'aria-current': type.code === value ? 'page' : undefined,
                    onClick: () => onChange(type.code),
                },
                h('span', { className: 'rl-nav-name' }, type.name),
                type.confidential ? h('span', { className: 'rl-nav-lock', title: t('rl_confidential') }, h(Icon, { name: 'lock' })) : null)));
        }));
    }

    function ReportLab() {
        const { t, config } = useApp();
        const types = config.types || [];
        const quick = Q.useQuickContext();
        const [code, setCode] = useState(() => {
            const saved = KW.storage.get(STORE, null);

            return types.some((type) => type.code === saved) ? saved : (types[0] ? types[0].code : null);
        });
        const type = types.find((item) => item.code === code) || null;

        const choose = (next) => {
            setCode(next);
            KW.storage.set(STORE, next);
        };

        return h('div', { className: 'rl' },
            h('header', { className: 'rl-top' },
                h('h1', { className: 'rl-title' }, t('rl_title')),
                h('p', { className: 'rl-sub' }, t('rl_sub'))),
            h('div', { className: 'rl-layout' },
                h(TypeNav, { types, value: code, onChange: choose }),
                h('div', { className: 'rl-stage' },
                    quick.loadError ? h(State, { error: quick.loadError, onRetry: quick.load }) : null,
                    type ? h(ReportDoc, { key: type.code, type, ctx: quick.ctx }) : null)));
    }

    window.KonelsisReportLab = { ReportLab };

    KW.mount('report-lab', ReportLab);
}());
