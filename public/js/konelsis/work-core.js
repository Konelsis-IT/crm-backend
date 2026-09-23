/*
 * Konelsis - IS PANOSU CEKIRDEGI (B36, D-115, 22 Eylul 2026).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement). Is panosu,
 * kontrol matrisi, analiz panosu ve Dikkat karti bu dosyanin kurdugu
 * window.KonelsisWork (KW) ad alanini kullanir. Gorunum "Konelsis Is Panosu"
 * taslak arayuzunun birebir karsiligidir; stiller konelsis-work.css (kw- on eki).
 *
 * Her ekran kok ogesi: <div data-kw-root="work-board" data-config="{...}">.
 * KW.mount(ad, Bilesen) kok ogeyi bulur, yapilandirmayi okur ve cizer.
 *
 * API: KW.h, KW.hooks, KW.mount(ad, Bilesen), KW.useApp() -> { config, t, api, url, toast, confirm }
 *   t(anahtar, { yer: deger })   etiket (config.labels); ":yer" yer tutuculari
 *   api.get(url, params) / api.post(url, govde) -> Promise<json>; hata: Error { status, code, message }
 *   url(sablon, id)              "__ID__" degistirir
 *   toast({ text, tone: 'ok'|'bad'|null, action: { label, onClick }, duration, countdown })
 *   confirm({ title, text, confirmLabel, danger }) -> Promise<boolean>
 * Bilesenler: Btn, Tabs, Chip, Dropdown, Opt, Modal, Seg, Toggle, Field, Input, TextArea, Select,
 *   SearchSelect, StatusDot, Tag, Avatar, State, Portal.
 * Bicim: fmt.date('Y-m-d') "14.09.2026" | fmt.dm "14.09" | fmt.hours(2.5) "2,5" | fmt.num | fmt.dayName.
 */
(function () {
    'use strict';

    if (!window.React || !window.ReactDOM) {
        console.error('KonelsisWork: React yok');
        return;
    }

    if (window.KonelsisWork) {
        return;
    }

    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const h = React.createElement;
    const Fragment = React.Fragment;
    const { useState, useEffect, useRef, useCallback, useMemo, useContext, createContext } = React;

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    function cx() {
        const out = [];

        for (let i = 0; i < arguments.length; i++) {
            const part = arguments[i];

            if (!part) {
                continue;
            }

            if (typeof part === 'string') {
                out.push(part);
            } else if (typeof part === 'object') {
                Object.keys(part).forEach((key) => { if (part[key]) { out.push(key); } });
            }
        }

        return out.join(' ');
    }

    function readConfig(el) {
        try {
            return JSON.parse(el.getAttribute('data-config') || '{}') || {};
        } catch (error) {
            console.error('KonelsisWork: data-config okunamadi', error);
            return {};
        }
    }

    function makeT(labels) {
        return function t(key, params) {
            let text = labels && typeof labels[key] === 'string' ? labels[key] : key;

            if (params) {
                Object.keys(params)
                    .sort((a, b) => b.length - a.length)
                    .forEach((name) => { text = text.split(':' + name).join(params[name] === null || params[name] === undefined ? '' : String(params[name])); });
            }

            return text;
        };
    }

    function makeApi(csrf) {
        async function request(method, url, body, options) {
            if (!url) {
                const missing = new Error('missing_endpoint');
                missing.status = 0;
                throw missing;
            }

            const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf || '' };
            let target = url;

            if (method === 'GET' && body) {
                const query = new URLSearchParams();

                Object.keys(body).forEach((key) => {
                    const value = body[key];

                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    if (Array.isArray(value)) {
                        value.forEach((item) => query.append(key + '[]', String(item)));
                    } else {
                        query.append(key, String(value));
                    }
                });

                const qs = query.toString();

                if (qs) {
                    target += (target.indexOf('?') === -1 ? '?' : '&') + qs;
                }
            } else if (method !== 'GET') {
                headers['Content-Type'] = 'application/json';
            }

            let response;

            try {
                response = await fetch(target, {
                    method,
                    headers,
                    credentials: 'same-origin',
                    body: method === 'GET' ? undefined : JSON.stringify(body || {}),
                    signal: options && options.signal,
                });
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    error.aborted = true;
                    throw error;
                }

                const network = new Error('network_error');
                network.status = 0;
                network.network = true;
                throw network;
            }

            let data = null;

            try {
                data = await response.json();
            } catch (ignored) {
                data = null;
            }

            if (!response.ok) {
                const error = new Error((data && data.message) || response.statusText || 'error');
                error.status = response.status;
                error.code = data && data.code;
                error.payload = data;
                throw error;
            }

            return data;
        }

        return {
            get: (url, params, options) => request('GET', url, params, options),
            post: (url, body, options) => request('POST', url, body, options),
        };
    }

    function describeError(error, t) {
        if (!error) {
            return t('error_generic');
        }

        if (error.network) {
            return t('network_error');
        }

        switch (error.status) {
            case 401:
            case 419:
                return t('session_expired');
            case 403:
                return t('forbidden');
            case 409:
                return error.message && error.message !== 'error' ? error.message : t('stale');
            case 422:
                return error.message || t('error_generic');
            default:
                return t('error_generic');
        }
    }

    function fillUrl(template, id) {
        return template ? String(template).split('__ID__').join(encodeURIComponent(String(id))) : null;
    }

    const pad = (n) => (n < 10 ? '0' : '') + n;

    const fmt = {
        date(ymd) {
            if (!ymd) { return ''; }
            const p = String(ymd).slice(0, 10).split('-');
            return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : String(ymd);
        },
        dm(ymd) {
            if (!ymd) { return ''; }
            const p = String(ymd).slice(0, 10).split('-');
            return p.length === 3 ? p[2] + '.' + p[1] : String(ymd);
        },
        hours(value) {
            if (value === null || value === undefined || value === '' || isNaN(Number(value))) { return ''; }
            return Number(value).toLocaleString('tr-TR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        },
        num(value, digits) {
            if (value === null || value === undefined || isNaN(Number(value))) { return '–'; }
            return Number(value).toLocaleString('tr-TR', { minimumFractionDigits: digits || 0, maximumFractionDigits: digits || 0 });
        },
        ymd(date) {
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        },
        parse(ymd) {
            if (!ymd) { return null; }
            const p = String(ymd).slice(0, 10).split('-').map(Number);
            return p.length === 3 ? new Date(p[0], p[1] - 1, p[2]) : null;
        },
        addDays(ymd, days) {
            const d = fmt.parse(ymd);
            if (!d) { return ymd; }
            d.setDate(d.getDate() + days);
            return fmt.ymd(d);
        },
        dayName(ymd, locale) {
            const d = fmt.parse(ymd);
            return d ? d.toLocaleDateString(locale || 'tr-TR', { weekday: 'long' }) : '';
        },
        daysBetween(a, b) {
            const da = fmt.parse(a);
            const db = fmt.parse(b);
            return da && db ? Math.round((db - da) / 86400000) : 0;
        },
    };

    const storage = {
        get(key, fallback) {
            try {
                const raw = window.localStorage.getItem(key);
                return raw === null ? fallback : JSON.parse(raw);
            } catch (ignored) {
                return fallback;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, JSON.stringify(value));
            } catch (ignored) {
                // Ozel pencere / engelli depolama: tercih hatirlanmaz.
            }
        },
    };

    function useClickOutside(ref, handler, active) {
        useEffect(() => {
            if (!active) { return undefined; }

            const listener = (event) => {
                if (ref.current && !ref.current.contains(event.target)) {
                    handler(event);
                }
            };

            document.addEventListener('mousedown', listener);
            document.addEventListener('touchstart', listener);

            return () => {
                document.removeEventListener('mousedown', listener);
                document.removeEventListener('touchstart', listener);
            };
        }, [ref, handler, active]);
    }

    // Escape yalniz en ustteki katmani (acilir kutu, pencere) kapatir.
    const escapeStack = [];

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !escapeStack.length) { return; }

        const top = escapeStack[escapeStack.length - 1];
        event.stopPropagation();
        top.current(event);
    });

    function useEscape(handler, active) {
        const ref = useRef(handler);
        ref.current = handler;

        useEffect(() => {
            if (!active) { return undefined; }

            escapeStack.push(ref);

            return () => {
                const index = escapeStack.lastIndexOf(ref);

                if (index !== -1) { escapeStack.splice(index, 1); }
            };
        }, [active]);
    }

    /* ------------------------------------------------------------------ */
    /* Uygulama baglami: yapilandirma, etiketler, api, bildirim, onay       */
    /* ------------------------------------------------------------------ */

    const AppContext = createContext(null);

    function useApp() {
        return useContext(AppContext);
    }

    let portalNode = null;

    function portalRoot() {
        if (!portalNode) {
            portalNode = document.createElement('div');
            portalNode.className = 'kw kw-portal';
            document.body.appendChild(portalNode);
        }

        return portalNode;
    }

    function Portal(props) {
        return ReactDOM.createPortal(props.children, portalRoot());
    }

    function ToastHost(props) {
        const { toasts, onDismiss } = props;

        if (!toasts.length) { return null; }

        return h(Portal, null, h('div', { className: 'kw-toasts', role: 'status', 'aria-live': 'polite' },
            toasts.map((toast) => h(ToastView, { key: toast.id, toast, onDismiss })),
        ));
    }

    function ToastView(props) {
        const { toast, onDismiss } = props;
        const [left, setLeft] = useState(toast.countdown ? Math.round((toast.duration || 10000) / 1000) : 0);

        useEffect(() => {
            const timer = window.setTimeout(() => {
                onDismiss(toast.id);

                if (toast.onExpire) { toast.onExpire(); }
            }, toast.duration || 5000);

            return () => window.clearTimeout(timer);
        }, []);

        useEffect(() => {
            if (!toast.countdown) { return undefined; }

            const tick = window.setInterval(() => setLeft((value) => Math.max(0, value - 1)), 1000);

            return () => window.clearInterval(tick);
        }, []);

        return h('div', { className: 'kw-toast' },
            toast.tone === 'ok' ? h('span', { className: 'kw-ok', 'aria-hidden': 'true' }, '✓') : null,
            toast.tone === 'bad' ? h('span', { className: 'kw-bad', 'aria-hidden': 'true' }, '!') : null,
            h('span', null, toast.text),
            toast.action ? h('button', {
                type: 'button',
                className: 'kw-toast-action',
                onClick: () => {
                    onDismiss(toast.id);
                    toast.action.onClick();
                },
            }, toast.action.label) : null,
            toast.countdown ? h('span', { className: 'kw-count-down' }, left + ' sn') : null,
        );
    }

    function ConfirmHost(props) {
        const { request, onDone, t } = props;

        if (!request) { return null; }

        return h(Modal, {
            title: request.title || '',
            size: 'small',
            onClose: () => onDone(false),
            footer: [
                h(Btn, { key: 'c', onClick: () => onDone(false) }, request.cancelLabel || t('cancel')),
                h(Btn, { key: 'o', variant: 'primary', autoFocus: true, onClick: () => onDone(true) }, request.confirmLabel || t('yes')),
            ],
        }, h('p', { style: { fontSize: '13px' } }, request.text || ''));
    }

    function AppShell(props) {
        const { config, children } = props;
        const t = useMemo(() => makeT(config.labels || {}), [config]);
        const api = useMemo(() => makeApi(config.csrf), [config]);
        const [toasts, setToasts] = useState([]);
        const [confirmRequest, setConfirmRequest] = useState(null);
        const seq = useRef(0);

        const dismiss = useCallback((id) => setToasts((list) => list.filter((toast) => toast.id !== id)), []);
        const toast = useCallback((options) => {
            seq.current += 1;
            const id = seq.current;
            setToasts((list) => list.concat([{ id, ...options }]).slice(-3));

            return id;
        }, []);
        const confirm = useCallback((options) => new Promise((resolve) => setConfirmRequest({ ...options, resolve })), []);
        const fail = useCallback((error) => {
            if (error && error.aborted) { return; }
            toast({ text: describeError(error, t), tone: 'bad', duration: 6000 });
        }, [t, toast]);

        const value = useMemo(() => ({ config, t, api, url: fillUrl, toast, confirm, fail, describe: (e) => describeError(e, t) }), [config, t, api, toast, confirm, fail]);

        return h(AppContext.Provider, { value },
            children,
            h(ToastHost, { toasts, onDismiss: dismiss }),
            h(ConfirmHost, {
                request: confirmRequest,
                t,
                onDone: (answer) => {
                    const request = confirmRequest;
                    setConfirmRequest(null);

                    if (request) { request.resolve(answer); }
                },
            }),
        );
    }

    function mount(name, Component) {
        const start = () => {
            document.querySelectorAll('[data-kw-root="' + name + '"]').forEach((el) => {
                if (el.getAttribute('data-kw-mounted') === '1') { return; }

                el.setAttribute('data-kw-mounted', '1');
                el.classList.add('kw');

                try {
                    const config = readConfig(el);
                    ReactDOM.createRoot(el).render(h(AppShell, { config }, h(Component, null)));
                } catch (error) {
                    el.removeAttribute('data-kw-mounted');
                    console.error('KonelsisWork: ' + name + ' baglanamadi', error);
                }
            });
        };

        if (document.readyState !== 'loading') {
            start();
        } else {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        }

        // Livewire gezinmesi (wire:navigate) sonrasi yeniden bagla.
        document.addEventListener('livewire:navigated', start);
    }

    /* ------------------------------------------------------------------ */
    /* Bilesenler                                                           */
    /* ------------------------------------------------------------------ */

    function Btn(props) {
        const { variant, size, className, children, href, ...rest } = props;
        const classes = cx('kw-btn', variant, size, className);

        if (href) {
            return h('a', { className: classes, href, ...rest }, children);
        }

        return h('button', { type: 'button', className: classes, ...rest }, children);
    }

    function Tabs(props) {
        const { items, value, onChange, label } = props;

        return h('div', { className: 'kw-tabs', role: 'tablist', 'aria-label': label },
            items.map((item) => h('button', {
                key: item.value,
                type: 'button',
                role: 'tab',
                className: 'kw-tab',
                'aria-selected': String(item.value === value),
                onClick: () => onChange(item.value),
            }, item.label, item.badge ? h('span', { className: 'kw-badge' }, item.badge) : null)),
        );
    }

    function Chip(props) {
        const { label, value, check, on, open, onClick, title, hasCaret } = props;

        return h('button', {
            type: 'button',
            className: cx('kw-chip', { check, on, open }),
            onClick,
            title,
            'aria-pressed': check ? String(!!on) : undefined,
            'aria-expanded': hasCaret === false || check ? undefined : String(!!open),
        },
        label,
        value !== undefined && value !== null ? h(Fragment, null, ' ', h('b', null, value)) : null,
        hasCaret === false || check ? null : h('span', { className: 'kw-caret', 'aria-hidden': 'true' }, open ? '▲' : '▼'));
    }

    /**
     * Acilir kutu: trigger(open, toggle) ogesi + panel (children fonksiyonu close alir).
     */
    function Dropdown(props) {
        const { trigger, children, align, width } = props;
        const [open, setOpen] = useState(false);
        const ref = useRef(null);
        const close = useCallback(() => setOpen(false), []);

        useClickOutside(ref, close, open);
        useEscape(close, open);

        return h('div', { className: 'kw-dd', ref },
            trigger(open, () => setOpen((value) => !value)),
            open ? h('div', { className: cx('kw-dd-panel', align === 'end' && 'end'), style: width ? { minWidth: width } : null },
                h('div', { className: 'kw-menu-box' }, typeof children === 'function' ? children(close) : children),
            ) : null,
        );
    }

    function Opt(props) {
        const { on, radio, plain, sub, danger, dot, count, children, onClick, disabled } = props;

        if (sub) {
            return h('div', { className: 'kw-opt sub' }, children);
        }

        return h('button', {
            type: 'button',
            className: cx('kw-opt', { on, radio, plain, danger }),
            onClick,
            disabled,
            role: radio ? 'menuitemradio' : (plain ? 'menuitem' : 'menuitemcheckbox'),
            'aria-checked': plain ? undefined : String(!!on),
        },
        dot ? h('i', { className: 'kw-dot-' + dot }) : null,
        h('span', null, children),
        count !== undefined && count !== null ? h('span', { className: 'kw-n' }, count) : null);
    }

    let modalDepth = 0;

    function Modal(props) {
        const { title, sub, size, onClose, footer, children, light, labelledBy } = props;
        const app = useApp();
        const boxRef = useRef(null);
        const titleId = useMemo(() => labelledBy || 'kw-modal-' + Math.random().toString(36).slice(2), []);

        useEscape(() => onClose && onClose(), true);

        useEffect(() => {
            modalDepth += 1;
            const previous = document.activeElement;
            const body = document.body;
            const overflow = body.style.overflow;
            body.style.overflow = 'hidden';

            window.requestAnimationFrame(() => {
                const box = boxRef.current;

                if (!box) { return; }

                const target = box.querySelector('[data-autofocus]') || box.querySelector('input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled])') || box;
                target.focus({ preventScroll: true });
            });

            return () => {
                modalDepth -= 1;

                if (modalDepth <= 0) {
                    body.style.overflow = overflow;
                }

                if (previous && typeof previous.focus === 'function') {
                    previous.focus({ preventScroll: true });
                }
            };
        }, []);

        const onKeyDown = (event) => {
            if (event.key !== 'Tab' || !boxRef.current) { return; }

            const focusables = boxRef.current.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');

            if (!focusables.length) { return; }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        return h(Portal, null, h('div', {
            className: cx('kw-overlay', light && 'light'),
            onMouseDown: (event) => {
                if (event.target === event.currentTarget && onClose) { onClose(); }
            },
        },
        h('div', {
            className: cx('kw-modal', size),
            role: 'dialog',
            'aria-modal': 'true',
            'aria-labelledby': titleId,
            tabIndex: -1,
            ref: boxRef,
            onKeyDown,
        },
        h('div', { className: 'kw-modal-head' },
            h('div', null, h('h4', { id: titleId }, title), sub ? h('p', null, sub) : null),
            onClose ? h('button', { type: 'button', className: 'kw-x', 'aria-label': app ? app.t('close') : 'Kapat', onClick: onClose }, '×') : null,
        ),
        h('div', { className: 'kw-modal-body' }, children),
        footer ? h('div', { className: 'kw-modal-foot' }, footer) : null,
        )));
    }

    function Seg(props) {
        const { items, value, onChange, label } = props;

        return h('div', { className: 'kw-seg', role: 'radiogroup', 'aria-label': label },
            items.map((item) => h('button', {
                key: item.value,
                type: 'button',
                role: 'radio',
                'aria-checked': String(item.value === value),
                className: cx({ on: item.value === value }),
                onClick: () => onChange(item.value),
            }, item.label)),
        );
    }

    function Toggle(props) {
        const { checked, onChange, label } = props;

        return h('button', {
            type: 'button',
            role: 'switch',
            'aria-checked': String(!!checked),
            className: cx('kw-toggle', { on: checked }),
            onClick: () => onChange(!checked),
        }, h('i', { 'aria-hidden': 'true' }), label);
    }

    /**
     * Form alani. `size`: 'sm' (kisa: tarih, sayi, evet/hayir), 'md',
     * 'lg' (genis) ya da bos (yarim satir); `full` tum satir.
     */
    function Field(props) {
        const { label, help, error, full, size, children, htmlFor } = props;

        return h('div', { className: cx('kw-f', full ? 'full' : size) },
            label ? h('label', { htmlFor }, label) : null,
            children,
            error ? h('p', { className: 'kw-error' }, error) : (help ? h('p', { className: 'kw-help' }, help) : null),
        );
    }

    function Input(props) {
        const { value, onChange, className, invalid, ...rest } = props;

        return h('input', {
            className: cx('kw-input', className, invalid && 'invalid'),
            value: value === null || value === undefined ? '' : value,
            onChange: (event) => onChange && onChange(event.target.value, event),
            ...rest,
        });
    }

    function TextArea(props) {
        const { value, onChange, rows, ...rest } = props;

        return h('textarea', {
            className: 'kw-input area',
            rows: rows || 3,
            value: value === null || value === undefined ? '' : value,
            onChange: (event) => onChange && onChange(event.target.value, event),
            ...rest,
        });
    }

    function Select(props) {
        const { value, onChange, options, placeholder, className, ...rest } = props;

        return h('select', {
            className: cx('kw-input', className),
            value: value === null || value === undefined ? '' : String(value),
            onChange: (event) => {
                const raw = event.target.value;
                const match = options.find((option) => String(option.value) === raw);
                onChange(match ? match.value : '');
            },
            ...rest,
        },
        placeholder !== false ? h('option', { value: '' }, placeholder || '–') : null,
        options.map((option) => option.group
            ? h('optgroup', { key: 'g-' + option.group, label: option.group }, option.options.map((inner) => h('option', { key: inner.value, value: String(inner.value) }, inner.label)))
            : h('option', { key: option.value, value: String(option.value) }, option.label)));
    }

    /**
     * Aranabilir secim: sabit liste (options) ya da uzak arama (load(q) -> Promise<[{value,label}]>).
     * Tetikleyici .kw-field / .kw-input gorunumundedir.
     */
    function SearchSelect(props) {
        const { value, label, onChange, options, load, placeholder, searchPlaceholder, emptyText, variant, disabled, clearable } = props;
        const app = useApp();
        const [open, setOpen] = useState(false);
        const [query, setQuery] = useState('');
        const [remote, setRemote] = useState([]);
        const [loading, setLoading] = useState(false);
        const ref = useRef(null);
        const close = useCallback(() => setOpen(false), []);

        useClickOutside(ref, close, open);
        useEscape(close, open);

        useEffect(() => {
            if (!open || !load) { return undefined; }

            let alive = true;
            setLoading(true);
            const timer = window.setTimeout(() => {
                load(query).then((rows) => { if (alive) { setRemote(rows || []); } }).catch(() => { if (alive) { setRemote([]); } }).finally(() => { if (alive) { setLoading(false); } });
            }, 220);

            return () => {
                alive = false;
                window.clearTimeout(timer);
            };
        }, [open, query, load]);

        const list = load ? remote : (options || []).filter((option) => !query || String(option.label).toLocaleLowerCase('tr-TR').indexOf(query.toLocaleLowerCase('tr-TR')) !== -1);
        const current = label || ((options || []).find((option) => String(option.value) === String(value)) || {}).label;
        const triggerClass = variant === 'field' ? cx('kw-field', open && 'open', !current && 'ph') : cx('kw-input', !current && 'ph');

        return h('div', { className: 'kw-dd', ref, style: { width: '100%' } },
            h('button', {
                type: 'button',
                className: triggerClass,
                disabled,
                onClick: () => setOpen((state) => !state),
                'aria-haspopup': 'listbox',
                'aria-expanded': String(open),
            }, h('span', null, current || placeholder || app.t('select')), h('span', { className: 'kw-caret', style: { marginLeft: 'auto', color: 'var(--kw-muted)', fontSize: '10px' }, 'aria-hidden': 'true' }, '▼')),
            open ? h('div', { className: 'kw-dd-panel', style: { width: '100%', minWidth: '240px' } },
                h('div', { className: 'kw-menu-box', role: 'listbox' },
                    h('div', { className: 'kw-search' }, '🔍 ', h('input', {
                        value: query,
                        autoFocus: true,
                        placeholder: searchPlaceholder || app.t('search'),
                        onChange: (event) => setQuery(event.target.value),
                        onKeyDown: (event) => {
                            if (event.key === 'Enter' && list.length) {
                                event.preventDefault();
                                onChange(list[0].value, list[0]);
                                close();
                            }
                        },
                    })),
                    clearable && (value !== null && value !== undefined && value !== '') ? h(Opt, { plain: true, onClick: () => { onChange(null, null); close(); } }, '– ' + app.t('clear')) : null,
                    loading ? h('div', { className: 'kw-opt plain', style: { cursor: 'default' } }, app.t('loading')) : null,
                    !loading && !list.length ? h('div', { className: 'kw-opt plain', style: { cursor: 'default', color: 'var(--kw-muted)' } }, emptyText || app.t('no_results')) : null,
                    !loading ? list.slice(0, 60).map((option) => h(Opt, {
                        key: option.value,
                        radio: true,
                        on: String(option.value) === String(value),
                        onClick: () => {
                            onChange(option.value, option);
                            close();
                        },
                    }, option.label)) : null,
                ),
            ) : null,
        );
    }

    function Tag(props) {
        const { kind, children, href, title } = props;

        if (href) {
            return h('a', { className: cx('kw-tag', kind), href, title, onClick: (event) => event.stopPropagation() }, children);
        }

        return h('span', { className: cx('kw-tag', kind), title }, children);
    }

    function Avatar(props) {
        return h('span', { className: 'kw-avatar', 'aria-hidden': 'true' }, props.initials || '');
    }

    function StatusBadge(props) {
        return h('span', { className: 'kw-status ' + props.status }, h('i', null), props.label);
    }

    function State(props) {
        const { loading, error, onRetry, text } = props;
        const app = useApp();

        if (loading) {
            return h('div', { className: 'kw-state' }, h('span', { className: 'kw-spinner', 'aria-hidden': 'true' }), app.t('loading'));
        }

        return h('div', { className: 'kw-state' },
            h('span', null, error ? app.describe(error) : text),
            onRetry ? h(Btn, { size: 'sm', onClick: onRetry }, app.t('retry')) : null,
        );
    }

    /** Bir kaynagi yukle: { data, error, loading, reload, setData }. */
    function useResource(loader, deps) {
        const [state, setState] = useState({ data: null, error: null, loading: true });
        const counter = useRef(0);

        const run = useCallback(() => {
            counter.current += 1;
            const ticket = counter.current;
            setState((previous) => ({ data: previous.data, error: null, loading: true }));

            Promise.resolve()
                .then(loader)
                .then((data) => { if (ticket === counter.current) { setState({ data, error: null, loading: false }); } })
                .catch((error) => { if (ticket === counter.current && !(error && error.aborted)) { setState((previous) => ({ data: previous.data, error, loading: false })); } });
        }, deps);

        useEffect(() => { run(); }, [run]);

        return {
            data: state.data,
            error: state.error,
            loading: state.loading,
            reload: run,
            setData: (next) => setState((previous) => ({ ...previous, data: typeof next === 'function' ? next(previous.data) : next })),
        };
    }

    window.KonelsisWork = {
        h,
        Fragment,
        hooks: { useState, useEffect, useRef, useCallback, useMemo, useContext },
        cx,
        fmt,
        storage,
        mount,
        useApp,
        useResource,
        useClickOutside,
        useEscape,
        describeError,
        fillUrl,
        Portal,
        portalRoot,
        Btn,
        Tabs,
        Chip,
        Dropdown,
        Opt,
        Modal,
        Seg,
        Toggle,
        Field,
        Input,
        TextArea,
        Select,
        SearchSelect,
        Tag,
        Avatar,
        StatusBadge,
        State,
    };
}());
