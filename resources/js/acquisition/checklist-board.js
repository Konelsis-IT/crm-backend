/*
 * Konelsis - TEKLIF ONCESI KONTROL LISTESI TAHTASI (D-157, 5 Ekim 2026
 * kullanici talimati: "Tiklayinca tik, tekrar tiklayinca carpi, hic secmezsen
 * - (bilinmiyor) durumu olsun. Ayni kontrol matrisindeki gibi"; "Checklist
 * listesi anlamsiz buyuk"; "istedigim tasarim Filament ile olmadiginda React
 * ile yapabilirsin").
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement), is panosu
 * ve kontrol matrisiyle ayni calisma zamani. Durum Filament alanindadir
 * (App\Filament\Forms\Components\ChecklistBoard):
 *
 *   {statePath}.{sablon}.q1_3   'yes' | 'no' | null   ("–" = bilinmiyor)
 *   {statePath}.{sablon}.doc_1  madde belgesi (Livewire gecici yuklemesi)
 *
 * - Hucre tiklandikca – -> ✓ -> ✗ -> –. Cevap Livewire durumuna ertelenmis
 *   yazilir ($wire.$set(yol, deger, false)); kaydetme istegiyle sunucuya gider.
 *   Renk cevabin olumlu olup olmadigini soyler: cogu soruda ✓ olumlu, olumsuz
 *   kurulmus sorularda (or. "Ilave yatirim sarti var mi?") ✗ olumlu.
 * - Teklif sicakligi tarayicida hemen hesaplanir; sunucu kaydederken ayni
 *   kurali uygular (ChecklistTemplates::heat). Lisansli projede Cagri mektubu
 *   opsiyoneldir, agirliklari sunucu verir.
 * - GES 1.3 ✗ olunca teklif tipi aninda Butcesel olur ve bildirim cikar.
 * - Belge: kucuk "Belge yukle" dugmesi; dosya Livewire ile gecici yuklenir,
 *   kaydedince belge (ya da yeni revizyon) olur.
 *
 * Kok: ChecklistBoard Blade gorunumu -> KonelsisChecklist.mount(kok, dis kutu, $wire).
 * Yapilandirma dis kutunun data-config ozelligidir; Livewire her cizimde
 * yeniler, tahta MutationObserver ile izler.
 */
(function () {
    'use strict';

    if (window.KonelsisChecklist) {
        return;
    }

    if (!window.React || !window.ReactDOM) {
        console.error('KonelsisChecklist: React yok');
        return;
    }

    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const h = React.createElement;
    const { useState, useEffect, useMemo, useRef } = React;

    // Tiklama sirasi: – -> ✓ -> ✗ -> –.
    const NEXT = { none: 'yes', yes: 'no', no: 'none' };
    const SYMBOL = { yes: '✓', no: '✗', none: '–' };
    const ECG = '0,16 34,16 40,16 45,7 51,26 57,4 62,16 74,16 79,12 84,16 120,16';

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    function cx() {
        return Array.prototype.filter.call(arguments, Boolean).join(' ');
    }

    function norm(value) {
        return value === 'yes' || value === 'no' ? value : 'none';
    }

    function favourable(question, value) {
        return question.negative ? value === 'no' : value === 'yes';
    }

    function readConfig(host) {
        try {
            return JSON.parse(host.getAttribute('data-config') || '{}') || {};
        } catch (error) {
            console.error('KonelsisChecklist: data-config okunamadi', error);
            return {};
        }
    }

    function label(labels, key, params) {
        let text = labels && typeof labels[key] === 'string' ? labels[key] : key;

        if (params) {
            Object.keys(params)
                .sort((a, b) => b.length - a.length)
                .forEach((name) => { text = text.split(':' + name).join(String(params[name])); });
        }

        return text;
    }

    /**
     * Livewire durumundaki cevaplar (ertelenmis yazimlar dahil). Salt okunur
     * tahtada (potansiyel is detayi, D-158) cevaplar yapilandirmadan gelir.
     */
    function readAnswers(wire, config) {
        let state = {};

        if (config.readOnly || !wire || !config.statePath) {
            state = config.answers || {};
        } else {
            try {
                state = wire.$get(config.statePath) || {};
            } catch (error) {
                state = {};
            }
        }

        const answers = {};

        (config.templates || []).forEach((template) => {
            const given = state[template.code] || {};
            const own = {};

            template.items.forEach((item) => item.questions.forEach((question) => {
                const value = norm(given[question.key]);

                if (value !== 'none') {
                    own[question.key] = value;
                }
            }));

            answers[template.code] = own;
        });

        return answers;
    }

    /** Maddenin olumlu payi: olumlu sorular + yuklu belge (D-159: belge de bir pay). */
    function goodParts(item, given, hasDocument) {
        return item.questions.filter((question) => favourable(question, norm(given[question.key]))).length + (hasDocument ? 1 : 0);
    }

    /**
     * Teklif sicakligi: madde agirligi x (olumlu soru + belge) / (soru + 1);
     * sunucudaki ChecklistTemplates::heat ile ayni kural.
     */
    function heatOf(templates, answers, docOf) {
        if (!templates.length) {
            return null;
        }

        let total = 0;

        templates.forEach((template) => {
            const given = answers[template.code] || {};

            template.items.forEach((item) => {
                if (!item.counts || !item.questions.length) {
                    return;
                }

                total += item.exact * goodParts(item, given, docOf(template, item)) / (item.questions.length + 1);
            });
        });

        return Math.max(0, Math.min(100, Math.round(total / templates.length)));
    }

    function progressOf(templates, answers, docOf) {
        let answered = 0;
        let total = 0;
        let documents = 0;
        let documentTotal = 0;

        templates.forEach((template) => {
            const given = answers[template.code] || {};

            template.items.forEach((item) => {
                if (!item.counts) {
                    return;
                }

                documentTotal++;
                documents += docOf(template, item) ? 1 : 0;

                item.questions.forEach((question) => {
                    total++;
                    answered += norm(given[question.key]) === 'none' ? 0 : 1;
                });
            });
        });

        return { answered, total, documents, documentTotal };
    }

    /** Acik sorularin hepsi olumlu cikar ve eksik belgeler yuklenirse ulasilacak sicaklik. */
    function potentialOf(templates, answers) {
        const filled = {};

        templates.forEach((template) => {
            const given = answers[template.code] || {};
            const own = {};

            template.items.forEach((item) => item.questions.forEach((question) => {
                const value = norm(given[question.key]);
                own[question.key] = value === 'none' ? (question.negative ? 'no' : 'yes') : value;
            }));

            filled[template.code] = own;
        });

        return heatOf(templates, filled, () => true);
    }

    function level(heat) {
        if (heat === null || heat === undefined) {
            return 'none';
        }

        if (heat < 25) {
            return 'cold';
        }

        if (heat < 50) {
            return 'warm';
        }

        return heat < 75 ? 'hot' : 'burning';
    }

    function notify(text) {
        if (window.FilamentNotification) {
            new window.FilamentNotification().title(text).warning().send();
        }
    }

    /* ------------------------------------------------------------------ */
    /* Simgeler (heroicons)                                                 */
    /* ------------------------------------------------------------------ */

    function HeartIcon() {
        return h('svg', { viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': 'true' },
            h('path', { d: 'm11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z' }));
    }

    function OutlineIcon(props) {
        return h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.6, 'aria-hidden': 'true' },
            h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: props.d }));
    }

    const PAPERCLIP = 'm18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13';
    const DOCUMENT = 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z';
    const CLOCK = 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z';

    /* ------------------------------------------------------------------ */
    /* Bilesenler                                                           */
    /* ------------------------------------------------------------------ */

    /** Teklif sicakligi: atan kalp, yuzde, seviye, serit ve EKG cizgisi. */
    function Heat(props) {
        const { heat, labels } = props;
        const lvl = level(heat);
        const value = heat === null ? '–' : '%' + heat;
        const text = heat === null ? label(labels, 'heat_none') : label(labels, 'level_' + lvl);

        return h('div', {
            className: 'kc-heat kc-heat--lg kc-heat--' + lvl,
            style: { '--kc-heat-pct': (heat || 0) + '%' },
            role: 'img',
            'aria-label': label(labels, 'heat') + ': ' + value + ' · ' + text,
        },
        h('span', { className: 'kc-heat__heart', 'aria-hidden': 'true' }, h(HeartIcon)),
        h('span', { className: 'kc-heat__body' },
            h('span', { className: 'kc-heat__top' },
                h('span', { className: 'kc-heat__value' }, value),
                h('span', { className: 'kc-heat__label' }, label(labels, 'heat') + ' · ' + text)),
            h('span', { className: 'kc-heat__bar' }, h('span', { className: 'kc-heat__rest' }))),
        h('svg', { className: 'kc-heat__ecg', viewBox: '0 0 120 32', preserveAspectRatio: 'none', 'aria-hidden': 'true' },
            h('polyline', { className: 'kc-heat__ecg-base', points: ECG }),
            h('polyline', { className: 'kc-heat__ecg-trace', points: ECG })));
    }

    function Cell(props) {
        const { value, question, onClick, disabled, labels } = props;
        const tone = value === 'none' ? 'none' : (favourable(question, value) ? 'good' : 'bad');
        const answer = label(labels, 'answer_' + value);
        const toneText = tone === 'none' ? '' : ' (' + label(labels, tone === 'good' ? 'favourable' : 'unfavourable') + ')';

        return h('button', {
            type: 'button',
            className: 'kc-cl-cell',
            'data-state': value,
            'data-tone': tone,
            disabled: disabled,
            onClick: onClick,
            title: question.code + ' ' + question.label + ': ' + answer + toneText + (disabled ? '' : ' · ' + label(labels, 'click_hint')),
            'aria-label': question.code + ' ' + question.label + ': ' + answer,
        }, SYMBOL[value]);
    }

    function DocumentControl(props) {
        const { item, pendingName, upload, onPick, onRemove, disabled, readOnly, labels } = props;
        const input = useRef(null);
        const uploading = upload && upload.state === 'uploading';
        const parts = [];

        // Salt okunur tahtada belgesiz maddede de satir durur (D-159: "belge
        // eklenmedi yazilabilir, bu sayede tasarimda kayma olmaz").
        if (readOnly && !item.document) {
            parts.push(h('span', { key: 'none', className: 'kc-cl-doc-none' }, h(OutlineIcon, { d: DOCUMENT }), label(labels, 'no_document')));
        }

        if (item.document) {
            parts.push(h('a', {
                key: 'current',
                className: 'kc-cl-doc-link',
                href: item.document.url || undefined,
                target: '_blank',
                rel: 'noopener',
                title: label(labels, 'open_document'),
            }, h(OutlineIcon, { d: DOCUMENT }), h('span', null, item.document.name + (item.document.revision ? ' · ' + item.document.revision : ''))));
        }

        if (pendingName) {
            parts.push(h('span', { key: 'pending', className: 'kc-cl-doc-pending', title: label(labels, 'pending') },
                h(OutlineIcon, { d: CLOCK }), h('span', null, pendingName)));

            if (!disabled) {
                parts.push(h('button', { key: 'remove', type: 'button', className: 'kc-cl-doc-btn is-quiet', onClick: onRemove }, label(labels, 'remove')));
            }
        }

        if (uploading) {
            parts.push(h('span', { key: 'progress', className: 'kc-cl-doc-progress' }, label(labels, 'uploading', { progress: upload.progress || 0 })));
        } else if (!disabled && !pendingName) {
            parts.push(h('button', {
                key: 'pick',
                type: 'button',
                className: 'kc-cl-doc-btn',
                onClick: () => input.current && input.current.click(),
            }, h(OutlineIcon, { d: PAPERCLIP }), label(labels, item.document ? 'replace' : 'upload')));
        }

        if (upload && upload.state === 'error') {
            parts.push(h('span', { key: 'error', className: 'kc-cl-doc-error' }, label(labels, 'upload_failed')));
        }

        if (item.documentRequired && !item.document && !pendingName && !uploading) {
            parts.push(h('span', { key: 'required', className: 'kc-cl-doc-req' }, label(labels, 'document_required')));
        }

        parts.push(h('input', {
            key: 'input',
            ref: input,
            type: 'file',
            className: 'kc-cl-file',
            tabIndex: -1,
            onChange: (event) => {
                const file = event.target.files && event.target.files[0];
                event.target.value = '';

                if (file) {
                    onPick(file);
                }
            },
        }));

        return h('div', { className: 'kc-cl-doc' }, parts);
    }

    function Item(props) {
        const { template, item, answers, onCycle, disabled, labels, docProps, hasDocument } = props;
        const given = answers[template.code] || {};
        // Uc soru ve belge: dort pay (D-159).
        const good = goodParts(item, given, hasDocument);
        const parts = item.questions.length + 1;
        let chip = item.counts ? '%' + item.weight : label(labels, item.exempt ? 'licensed_optional' : 'optional');

        return h('div', { className: cx('kc-cl-item', !item.counts && 'is-optional') },
            h('div', { className: 'kc-cl-item-head' },
                h('span', { className: 'kc-cl-no' }, item.code),
                h('span', { className: 'kc-cl-title' }, item.label),
                h('span', { className: cx('kc-cl-weight', !item.counts && 'is-off') }, chip),
                h('span', { className: 'kc-cl-score', title: label(labels, 'item_score', { good: good, total: parts }) }, good + '/' + parts)),
            item.questions.map((question) => h('div', { key: question.key, className: 'kc-cl-q' },
                h('span', { className: 'kc-cl-q-no' }, question.code),
                h('span', { className: 'kc-cl-q-text' },
                    question.label,
                    question.negative ? h('span', { className: 'kc-cl-q-neg', title: label(labels, 'negative_hint'), 'aria-label': label(labels, 'negative_hint') }, 'ⓘ') : null),
                h(Cell, {
                    value: norm(given[question.key]),
                    question: question,
                    disabled: disabled,
                    labels: labels,
                    onClick: () => onCycle(template, question),
                }))),
            h(DocumentControl, docProps));
    }

    function Board(props) {
        const { config, version, wire } = props;
        const labels = config.labels || {};
        const templates = config.templates || [];
        const readOnly = !!config.readOnly;
        const disabled = !!config.disabled || readOnly;
        const [answers, setAnswers] = useState(() => readAnswers(wire, config));
        const [uploads, setUploads] = useState({});
        // Hizli art arda tiklamalar ayni cizimi okumasin diye en son cevaplar.
        const latest = useRef(answers);
        latest.current = answers;

        // Sunucu yeniden cizdiginde (kaydetme, proje tipi / durum degisimi) cevaplar
        // Livewire durumundan tazelenir; ertelenmis yazimlar orada da durur.
        useEffect(() => {
            const fresh = readAnswers(wire, config);
            latest.current = fresh;
            setAnswers(fresh);
        }, [version]);

        // Madde belgesi: kayitli belge ya da kaydedilmeyi bekleyen yukleme (D-159: belge de bir pay).
        const docOf = (template, item) => !!item.document || !!(((config.pending || {})[template.code] || {})[item.code]);
        const heat = useMemo(() => heatOf(templates, answers, docOf), [version, answers]);
        const progress = useMemo(() => progressOf(templates, answers, docOf), [version, answers]);
        const potential = useMemo(() => potentialOf(templates, answers), [version, answers]);

        function setUpload(key, value) {
            setUploads((previous) => {
                const next = { ...previous };

                if (value) {
                    next[key] = value;
                } else {
                    delete next[key];
                }

                return next;
            });
        }

        function cycle(template, question) {
            if (disabled) {
                return;
            }

            const before = latest.current;
            const current = norm((before[template.code] || {})[question.key]);
            const next = NEXT[current];
            const own = { ...(before[template.code] || {}) };

            if (next === 'none') {
                delete own[question.key];
            } else {
                own[question.key] = next;
            }

            latest.current = { ...before, [template.code]: own };
            setAnswers(latest.current);

            wire.$set(config.statePath + '.' + template.code + '.' + question.key, next === 'none' ? null : next, false);

            // GES 1.3 ✗: teklif tipi hemen Butcesel (sunucu kaydederken de uygular).
            if (question.budgetary && next === 'no' && config.offerType !== config.budgetary) {
                wire.$set(config.offerTypePath, config.budgetary, true);
                notify(label(labels, 'budgetary_message'));
            }
        }

        function pick(template, item, file) {
            const key = template.code + '.' + item.code;
            const path = config.statePath + '.' + template.code + '.' + item.documentKey;

            if (config.maxBytes && file.size > config.maxBytes) {
                setUpload(key, { state: 'error' });
                return;
            }

            setUpload(key, { state: 'uploading', progress: 0 });
            wire.$set(path, null, false);
            wire.$upload(
                path,
                file,
                () => setUpload(key, null),
                () => setUpload(key, { state: 'error' }),
                (event) => setUpload(key, { state: 'uploading', progress: event && event.detail ? event.detail.progress : 0 }),
            );
        }

        function remove(template, item) {
            wire.$set(config.statePath + '.' + template.code + '.' + item.documentKey, null, true);
        }

        if (!templates.length) {
            return h('div', { className: 'kc-cl' }, h('p', { className: 'kc-cl-empty' }, label(labels, 'choose_type_first')));
        }

        const open = progress.total - progress.answered;
        const openDocuments = progress.documentTotal - progress.documents;
        let potentialText = label(labels, 'complete');

        if (open > 0 && openDocuments > 0) {
            potentialText = label(labels, 'potential_both', { count: open, docs: openDocuments, heat: potential });
        } else if (open > 0) {
            potentialText = label(labels, 'potential', { count: open, heat: potential });
        } else if (openDocuments > 0) {
            potentialText = label(labels, 'potential_docs', { docs: openDocuments, heat: potential });
        }

        return h('div', { className: cx('kc-cl', readOnly && 'is-readonly') },
            h('div', { className: 'kc-cl-head' },
                h(Heat, { heat: heat, labels: labels }),
                h('div', { className: 'kc-cl-facts' },
                    h('span', null,
                        h('b', null, label(labels, 'answered', { answered: progress.answered, total: progress.total })),
                        ' · ',
                        h('b', null, label(labels, 'documents_count', { present: progress.documents, total: progress.documentTotal }))),
                    h('span', null, potentialText),
                    h('span', { className: 'kc-cl-legend' }, label(labels, 'legend')))),
            templates.map((template) => h('section', { key: template.code, className: 'kc-cl-template' },
                h('h4', null, template.title),
                h('div', { className: 'kc-cl-items' }, template.items.map((item) => {
                    const key = template.code + '.' + item.code;

                    return h(Item, {
                        key: key,
                        template: template,
                        item: item,
                        answers: answers,
                        onCycle: cycle,
                        disabled: disabled,
                        labels: labels,
                        hasDocument: docOf(template, item),
                        docProps: {
                            item: item,
                            pendingName: ((config.pending || {})[template.code] || {})[item.code] || null,
                            upload: uploads[key] || null,
                            onPick: (file) => pick(template, item, file),
                            onRemove: () => remove(template, item),
                            disabled: disabled,
                            readOnly: readOnly,
                            labels: labels,
                        },
                    });
                })))));
    }

    /* ------------------------------------------------------------------ */
    /* Kurulum                                                              */
    /* ------------------------------------------------------------------ */

    function mount(root, host, wire) {
        const reactRoot = ReactDOM.createRoot(root);
        const render = () => {
            const raw = host.getAttribute('data-config') || '{}';
            reactRoot.render(h(Board, { config: readConfig(host), version: raw, wire: wire }));
        };

        render();

        const observer = new MutationObserver((mutations) => {
            if (mutations.some((mutation) => mutation.attributeName === 'data-config')) {
                render();
            }
        });

        observer.observe(host, { attributes: true, attributeFilter: ['data-config'] });

        return {
            unmount() {
                observer.disconnect();
                reactRoot.unmount();
            },
        };
    }

    window.KonelsisChecklist = { mount };
    window.dispatchEvent(new CustomEvent('konelsis-checklist:ready'));
})();
