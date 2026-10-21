/*
 * Konelsis - Yeni pano (D-207). UI Deneme > Yeni pano.
 *
 * Kullanici 14 Ekim 2026: "Filament ile yapalim demedim ben react ile yap.
 * Daha cok alan yonetimi daha fazla bilgi ama yine ayni boyutlara sigacak
 * sekilde olmalidir. Acil koduyla ustte duyuru paneli uyarisi olur. Ekranda bir
 * anda beliren carpi ile kapatilan acil yapilacak islem bildirilir, belirli
 * periyotlarla ekranda yeniden cikar. Isin acilligi ve surenin sikismasina
 * gore degisir." Kaynak: 12 Ekim 2026 toplanti notlari (Alim paneli, Huseyin
 * Bey bekleyenler, satin alma paneli).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement). Veri
 * sunucudan gelir (NewDashboardConfig); bu betik cizer, yonetici islemlerini
 * sayfanin Filament eylemlerine yollar (D-208: Onay ver, Hemen uyar, Ozel
 * bildirim, Departmani cagir, Uyari gonder; pencereler Filament'in).
 * Eski pano parcalari (dash-*) kullanilmaz.
 *
 * D-208 (14 Ekim 2026): ustteki acil serit elle kapatilamaz, en acil konu
 * sabit, digerleri kayan yazi; her panoda Sablonlar alani (indir / yeni sekmede ac).
 *
 * Tarayici hafizasi (localStorage, yalniz bu kisinin tarayicisinda):
 *  - kc-nd-snooze: kapatilan acil uyarinin yeniden gosterilecegi an;
 *  - kc-nd-fast: hizli deneme (araliklar dakika yerine saniye).
 */
(function () {
    'use strict';

    if (!window.React || !window.ReactDOM) {
        console.error('Konelsis yeni pano: React yok');

        return;
    }

    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const h = React.createElement;
    const { useState, useEffect, useMemo, useRef, useCallback } = React;

    // ------------------------------------------------------------------ yardimcilar

    function cx() {
        return Array.prototype.filter.call(arguments, Boolean).join(' ');
    }

    function store(key, fallback) {
        try {
            const raw = window.localStorage.getItem(key);

            return raw === null ? fallback : JSON.parse(raw);
        } catch (error) {
            return fallback;
        }
    }

    function save(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) { /* gizli pencere: yalniz ekranda kalir */ }
    }

    function makeT(labels) {
        return function t(path, params) {
            let value = String(path).split('.').reduce((node, key) => (node && node[key] !== undefined ? node[key] : undefined), labels);

            if (typeof value !== 'string') {
                return path;
            }

            Object.keys(params || {}).forEach((key) => {
                value = value.split(':' + key).join(String(params[key]));
            });

            return value;
        };
    }

    // Turkce arama (D-214): buyuk / kucuk harf, I-i-I-i ve isaretler (s/s, g/g...) ayirt edilmez;
    // sunucudaki aramayla (utf8mb4_general_ci) ayni sonuc.
    function norm(text) {
        const value = String(text === undefined || text === null ? '' : text).toLocaleLowerCase('tr-TR').replace(/ı/g, 'i');

        return typeof value.normalize === 'function' ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : value;
    }

    function ageTone(days) {
        if (days === null || days === undefined) {
            return 'gray';
        }

        if (days >= 180) {
            return 'red';
        }

        if (days >= 90) {
            return 'orange';
        }

        return days >= 30 ? 'amber' : 'gray';
    }

    function leftTone(days) {
        if (days === null || days === undefined) {
            return 'gray';
        }

        if (days <= 1) {
            return 'red';
        }

        return days <= 3 ? 'amber' : 'gray';
    }

    function initials(name) {
        return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toLocaleUpperCase('tr-TR')).join('');
    }

    const WIDE = 1180;

    // "Ayni boyutlara sigsin": kutu, sayfanin basindan pencerenin altina kadar
    // uzar; listeler kendi icinde kayar. Dar ekranda (wideOnly) dogal yukseklik.
    function useFitHeight(min, wideOnly) {
        const ref = useRef(null);
        const [height, setHeight] = useState(null);

        useEffect(() => {
            const measure = () => {
                const element = ref.current;

                if (!element) {
                    return;
                }

                if (wideOnly && window.innerWidth < WIDE) {
                    setHeight(null);

                    return;
                }

                const top = element.getBoundingClientRect().top + window.scrollY;
                setHeight(Math.max(min, Math.floor(window.innerHeight - top - 16)));
            };
            const late = window.setTimeout(measure, 400);

            measure();
            window.addEventListener('resize', measure);
            document.addEventListener('nd:relayout', measure);

            return () => {
                window.clearTimeout(late);
                window.removeEventListener('resize', measure);
                document.removeEventListener('nd:relayout', measure);
            };
        }, [min, wideOnly]);

        return [ref, height];
    }

    function relayout() {
        window.setTimeout(() => document.dispatchEvent(new Event('nd:relayout')), 0);
    }

    // ------------------------------------------------------------------ simgeler (Heroicons outline)

    const PATHS = {
        megaphone: 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46',
        briefcase: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0',
        clipboard: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z',
        calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
        document: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        cart: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
        receipt: 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
        plus: 'M12 4.5v15m7.5-7.5h-15',
        alert: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        x: 'M6 18 18 6M6 6l12 12',
        search: 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
        chevron: 'm19.5 8.25-7.5 7.5-7.5-7.5',
        clock: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        truck: 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
        users: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        eye: 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        bolt: 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        arrow: 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        check: 'm4.5 12.75 6 6 9-13.5',
        bell: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
        bellAlert: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5',
        chat: 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
        phone: 'M20.25 3.75v4.5m0-4.5h-4.5m4.5 0-6 6m3 12c-8.284 0-15-6.716-15-15V4.5A2.25 2.25 0 0 1 4.5 2.25h1.372c.516 0 .966.351 1.091.852l1.106 4.423c.11.44-.054.902-.417 1.173l-1.293.97a1.062 1.062 0 0 0-.38 1.21 12.035 12.035 0 0 0 7.143 7.143c.441.162.928-.004 1.21-.38l.97-1.293a1.125 1.125 0 0 1 1.173-.417l4.423 1.106c.5.125.852.575.852 1.091V19.5a2.25 2.25 0 0 1-2.25 2.25h-2.25Z',
        download: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
        external: 'M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25',
        checkCircle: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        // D-222: proje tipi simgeleri (ProjectScopeType::getIcon ile ayni Heroicons).
        sun: 'M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z',
        arrowPath: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
        beaker: 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
        battery: 'M21 10.5h.375c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H21M4.5 10.5H18V15H4.5v-4.5ZM3.75 18h15A2.25 2.25 0 0 0 21 15.75v-6a2.25 2.25 0 0 0-2.25-2.25h-15A2.25 2.25 0 0 0 1.5 9.75v6A2.25 2.25 0 0 0 3.75 18Z',
        arrowsRL: 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
        cpu: 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z',
        folder: 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
    };

    function Icon(props) {
        return h('svg', { className: cx('nd-icon', props.className), viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.6, 'aria-hidden': 'true' },
            h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: PATHS[props.name] || PATHS.document }));
    }

    function Pill(props) {
        return h('span', { className: cx('nd-pill', 'nd-tone-' + (props.tone || 'gray'), props.className), title: props.title }, props.children);
    }

    function Avatar(props) {
        return props.name ? h('span', { className: 'nd-avatar', title: props.name }, initials(props.name)) : null;
    }

    function Codes(props) {
        return props.codes && props.codes.length ? h('span', { className: 'nd-codes' }, props.codes.join(' · ')) : null;
    }

    // D-222 (19 Ekim 2026): proje tipi rozeti kendi renginde ve simgesiyle (GES gunes, RES ruzgar...).
    const TYPE_ICONS = { ges: 'sun', res: 'arrowPath', tm: 'bolt', hes: 'beaker', bes: 'battery', enh_eih: 'arrowsRL', automation: 'cpu' };

    function TypeBadges(props) {
        const types = props.types || [];

        // Kartta birden fazla tip yalniz simge (adi ustune gelince ve pencerede); tek tip kisa adiyla.
        const iconOnly = props.compact && types.length > 1;

        return types.length ? h('span', { className: 'nd-types' }, types.map((type) => h('span', { key: type.value, className: cx('nd-type', 'nd-type--' + (type.color || 'gray'), iconOnly && 'is-icon'), title: type.label, 'aria-label': type.label },
            h(Icon, { name: TYPE_ICONS[type.value] || 'document' }), iconOnly ? null : type.label))) : null;
    }

    // Tek satir kayan yazi (19 Ekim 2026 kullanici: "Ilgili kartin basligi 2 satirda gorulmesin ...
    // sigmayan satirlar aciklama ve baslik kisminda ilgili satir alt yazi gibi calissin. donsun
    // dursun"): sigan metin oldugu gibi durur; sigmayan metin bastan sona kayar, bekler, geri doner.
    // Hareket azaltma tercihinde kayma yok, uc nokta var.
    // Kaydirilan alan (D-225, 19 Ekim 2026 kullanici: kaydirma cubugu "gereksiz alan kaybina yol
    // aciyor ... gosterilecek son satirin orada kaydirilabilir oldugu hafif soluklasan bulaniklasan bir
    // alan olabilir ve uzerinde asagi ok isareti olabilir"): cubuk gizli; altta devami varsa son satir
    // solar ve bulaniklasir, ustunde asagi ok. Oka basinca bir sayfa kayar; sona gelince ikisi kaybolur.
    // Tekerlek ve dokunmayla kaydirma oldugu gibi.
    function ScrollArea(props) {
        const ref = useRef(null);
        const [more, setMore] = useState(false);

        useEffect(() => {
            const el = ref.current;

            if (!el) {
                return undefined;
            }

            const check = () => setMore(el.scrollTop + el.clientHeight < el.scrollHeight - 4);
            check();
            el.addEventListener('scroll', check, { passive: true });
            // Boyut (pencere, sigdirma) ve icerik (grup ac / kapa, yeni veri) degisince yeniden bak.
            const resize = window.ResizeObserver ? new ResizeObserver(check) : null;
            const mutate = window.MutationObserver ? new MutationObserver(check) : null;

            if (resize) { resize.observe(el); }
            if (mutate) { mutate.observe(el, { childList: true, subtree: true }); }

            return () => {
                el.removeEventListener('scroll', check);
                if (resize) { resize.disconnect(); }
                if (mutate) { mutate.disconnect(); }
            };
        }, []);

        const down = () => {
            const el = ref.current;

            if (el) {
                el.scrollBy({ top: Math.max(80, Math.round(el.clientHeight * 0.8)), behavior: 'smooth' });
            }
        };

        return h('div', { className: cx('nd-scroll-area', more && 'has-more') },
            h('div', Object.assign({ ref, className: props.className }, props.attrs || {}), props.children),
            more ? h('div', { className: 'nd-scroll-more' },
                h('button', { type: 'button', className: 'nd-scroll-more-btn', title: props.label, 'aria-label': props.label, onClick: down }, h(Icon, { name: 'chevron' }))) : null);
    }

    function Marquee(props) {
        const outer = useRef(null);
        const inner = useRef(null);
        const [shift, setShift] = useState(0);

        useEffect(() => {
            const measure = () => {
                if (!outer.current || !inner.current) { return; }
                const overflow = inner.current.scrollWidth - outer.current.clientWidth;
                setShift(overflow > 2 ? overflow : 0);
            };

            measure();
            const observer = window.ResizeObserver ? new ResizeObserver(measure) : null;

            if (observer && outer.current) { observer.observe(outer.current); }

            return () => { if (observer) { observer.disconnect(); } };
        }, [props.children]);

        return h('span', {
            ref: outer,
            className: cx('nd-marquee', shift > 0 && 'is-moving', props.className),
            title: props.title,
            style: shift > 0 ? { '--nd-shift': (-shift) + 'px', '--nd-marquee-time': Math.min(18, Math.max(6, 4 + shift / 20)).toFixed(1) + 's' } : undefined,
        }, h('span', { ref: inner, className: 'nd-marquee-text' }, props.children));
    }

    // Sicaklik kalbi (D-222, 19 Ekim 2026 kullanici: "Sicaklik kelimesini kullanma kalp emojisi
    // kalbin rengi vs.. burada da calissin kalp + % 'lik degeri gorulsun"): kontrol listesindeki
    // kalp (konelsis.css .kc-heat), seviyeye gore renk ve atis; 0'da gri, atmaz.
    const HEART = 'm11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z';

    function heatLevel(heat) {
        if (!heat || heat <= 0) {
            return 'none';
        }

        return heat < 25 ? 'cold' : (heat < 50 ? 'warm' : (heat < 75 ? 'hot' : 'burning'));
    }

    function Heat(props) {
        const heat = Number(props.heat) || 0;

        return h('span', { className: cx('kc-heat', 'kc-heat--sm', 'kc-heat--' + heatLevel(heat), 'nd-heat'), title: props.title },
            h('span', { className: 'kc-heat__heart', 'aria-hidden': 'true' }, h('svg', { viewBox: '0 0 24 24', fill: 'currentColor' }, h('path', { d: HEART }))),
            h('span', { className: 'kc-heat__value' }, '%' + heat));
    }

    // Kart penceresi (D-222; kullanici: "uzerine tiklanirsa modal acilsin ve bu modal standart
    // yonlendirmeden farkli olarak hem yonlendirme sorusuna sahip olsun hemde bu kucuk kartlarin
    // biraz daha detayli hali seklinde gorulsun. Ama cok detay verip bogma insanlari.").
    const CardContext = React.createContext(() => {});

    function CardDialog(props) {
        const { card, onClose, t } = props;
        const primary = useRef(null);

        useEffect(() => {
            if (!card) {
                return undefined;
            }

            const onKey = (event) => { if (event.key === 'Escape') { onClose(); } };
            document.addEventListener('keydown', onKey);
            window.setTimeout(() => { if (primary.current) { primary.current.focus(); } }, 0);

            return () => document.removeEventListener('keydown', onKey);
        }, [card, onClose]);

        if (!card) {
            return null;
        }

        const { item, lane } = card;
        const kind = item.kind || lane.label;
        const left = item.mode ? item.mode === 'left' : (lane.age === false && lane.key !== 'projects');
        const status = item.status ? (STATUS_TONES[item.status] ? t('status.' + item.status) : item.status) : null;
        const days = item.days === null || item.days === undefined ? null : (left
            ? h(Pill, { tone: leftTone(item.days) }, item.days < 0 ? t('late_days', { days: -item.days }) : t('left_days', { days: item.days }))
            : h(Pill, { tone: ageTone(item.days) }, t('age_days', { days: item.days })));
        const go = (newTab) => {
            if (!item.url) { return; }
            if (newTab) { window.open(item.url, '_blank', 'noopener'); onClose(); } else { window.location.assign(item.url); }
        };
        // Etiketsiz, birkac satir (19 Ekim 2026: "bu bilgilere gerek yok"): kim / durum,
        // proje veya firma, tip + kalp + tutar + tarih.
        const related = [item.project, item.party && item.party !== item.project ? item.party : null].filter(Boolean).join(' · ');
        const lines = [
            item.owner || status || (item.priority_label && item.priority !== 'normal') ? h('div', { key: 'who', className: 'nd-dialog-line' },
                item.owner ? h('span', { className: 'nd-owner' }, h(Avatar, { name: item.owner }), h('span', null, item.owner)) : null,
                status ? h(Pill, { tone: STATUS_TONES[item.status] || 'blue' }, status) : null,
                item.priority_label && item.priority !== 'normal' ? h(Pill, { tone: item.priority === 'critical' ? 'red' : (item.priority === 'high' ? 'amber' : 'gray') }, item.priority_label) : null) : null,
            related ? h('div', { key: 'rel', className: 'nd-dialog-line nd-dialog-muted' }, h(Icon, { name: 'briefcase' }), h('span', null, related)) : null,
            (item.types && item.types.length) || (item.heat !== undefined && item.heat !== null) || item.amount || item.when || days ? h('div', { key: 'meta', className: 'nd-dialog-line' },
                h(TypeBadges, { types: item.types }),
                item.heat !== undefined && item.heat !== null ? h(Heat, { heat: item.heat, title: t('heat_title') }) : null,
                item.amount ? h('span', { className: 'nd-amount' }, item.amount) : null,
                item.when ? h('span', { className: 'nd-dialog-date' }, h(Icon, { name: 'calendar' }), item.when) : null,
                days) : null,
        ].filter(Boolean);

        return h('div', { className: 'nd-dialog-wrap', 'data-no-link-guard': '', onMouseDown: (event) => { if (event.target === event.currentTarget) { onClose(); } } },
            h('div', { className: cx('nd-dialog', item.level && 'nd-level-' + item.level), role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'nd-dialog-title' },
                h('div', { className: 'nd-dialog-head' },
                    h('span', { className: 'nd-dialog-icon' }, h(Icon, { name: LANE_ICONS[lane.key] || 'document' })),
                    h('div', { className: 'nd-dialog-headtext' },
                        h('span', { className: 'nd-dialog-kicker' }, kind),
                        h(Codes, { codes: item.codes })),
                    h('button', { type: 'button', className: 'nd-popup-close', onClick: onClose, 'aria-label': t('card.close'), title: t('card.close') }, h(Icon, { name: 'x' }))),
                h('div', { id: 'nd-dialog-title', className: 'nd-dialog-title' }, item.title),
                lines.length ? h('div', { className: 'nd-dialog-facts' }, lines) : null,
                item.url ? h('p', { className: 'nd-dialog-question' }, t('card.question', { kind })) : null,
                h('div', { className: 'nd-dialog-actions' },
                    h('button', { type: 'button', className: 'nd-btn', onClick: onClose }, t('card.stay')),
                    item.url ? h('button', { type: 'button', className: 'nd-btn nd-btn-primary', ref: primary, onClick: () => go(false) }, h(Icon, { name: 'arrow' }), t('card.go', { kind })) : null,
                    item.url ? h('button', { type: 'button', className: 'nd-icon-btn', onClick: () => go(true), title: t('card.new_tab'), 'aria-label': t('card.new_tab') }, h(Icon, { name: 'external' })) : null)));
    }

    // ------------------------------------------------------------------ Livewire koprusu (D-208)

    // Yonetici islemleri sayfanin Filament eylemleridir; pencereleri Filament
    // cizer. Basaridan sonra sunucu yeni veriyi `nd-config` olayiyla yollar.
    const Bridge = {
        root: null,
        mount(name, args) {
            const host = this.root ? this.root.closest('[wire\\:id]') : null;
            const component = host && window.Livewire ? window.Livewire.find(host.getAttribute('wire:id')) : null;

            if (component) {
                component.mountAction(name, args || {});
            }
        },
    };

    // ------------------------------------------------------------------ acil serit (D-208)

    // "Acil veya duyuru alani elle kapatilamazdir. En acil konu orada olacaktir.
    // Ayrica bir akan alt yazi gibi yazi olmalidir." Ustte en acil konu sabit;
    // altinda diger acil konular kayan yazi. Kapatma dugmesi yoktur.
    const STRIP_FRESH_DAYS = 3;

    function stripTopics(announcements, urgent, t) {
        const fromAnnouncement = (item) => ({
            key: 'a-' + item.id, kind: 'announcement', title: item.title, text: item.body, meta: item.meta, url: item.url, level: 'critical',
        });
        const fromUrgent = (item) => ({
            key: 'u-' + item.id, kind: 'urgent', title: item.title, text: [item.kindLabel, item.meta].filter(Boolean).join(' · '), meta: dueText(item, t), url: item.url, level: item.level,
        });
        const fresh = announcements.filter((item) => item.days !== null && item.days !== undefined && item.days <= STRIP_FRESH_DAYS);
        const older = announcements.filter((item) => fresh.indexOf(item) === -1);
        const critical = urgent.filter((item) => item.level === 'critical');
        const rest = urgent.filter((item) => item.level !== 'critical');

        return [].concat(fresh.map(fromAnnouncement), critical.map(fromUrgent), older.map(fromAnnouncement), rest.map(fromUrgent));
    }

    function UrgentStrip(props) {
        const { announcements, urgent, t } = props;
        const topics = stripTopics(announcements, urgent, t);

        if (!topics.length) {
            return null;
        }

        const top = topics[0];
        const others = topics.slice(1, 13);
        // Kayan yazi ve "Ac" baglanti uyarisini acar (15 Ekim 2026: "akan alt yazi
        // ekraninda bir route tiklayinca ... buraya gidiyorsunuz uyari modali acilmali").
        const tickerItem = (topic, copy) => h('a', { key: copy + topic.key, href: topic.url || '#', className: cx('nd-ticker-item', 'nd-level-' + topic.level), tabIndex: copy === 'b' ? -1 : 0, 'data-link-guard': t('strip.field'), 'data-link-guard-name': topic.title },
            h('span', { className: 'nd-level-dot' }),
            h('strong', null, topic.title),
            topic.text ? h('span', { className: 'nd-ticker-text' }, topic.text) : null,
            topic.meta ? h('span', { className: 'nd-ticker-meta' }, topic.meta) : null);
        // Akis hizi icerige gore: kisa listede yavas kalmasin, uzun listede kosmasin.
        const duration = Math.max(25, others.length * 7);

        return h('div', { className: 'nd-strip', role: 'alert' },
            h('div', { className: 'nd-strip-top' },
                h('span', { className: 'nd-ann-icon' }, h(Icon, { name: top.kind === 'announcement' ? 'megaphone' : 'alert' })),
                h('span', { className: 'nd-ann-label' }, top.kind === 'announcement' ? t('announcement.label') : t('strip.label')),
                h('div', { className: 'nd-ann-text' },
                    h('strong', null, top.title),
                    top.text ? h('span', { className: 'nd-ann-body' }, ' — ' + top.text) : null),
                top.meta ? h('span', { className: 'nd-ann-meta' }, top.meta) : null,
                top.url ? h('a', { className: 'nd-ann-link', href: top.url, 'data-link-guard': t('strip.field'), 'data-link-guard-name': top.title }, t('strip.open')) : null),
            others.length ? h('div', { className: 'nd-ticker', title: t('strip.more', { count: others.length }) },
                h('div', { className: 'nd-ticker-track', style: { animationDuration: duration + 's' } },
                    h('div', { className: 'nd-ticker-run' }, others.map((topic) => tickerItem(topic, 'a'))),
                    h('div', { className: 'nd-ticker-run', 'aria-hidden': 'true' }, others.map((topic) => tickerItem(topic, 'b')))))
                : null);
    }

    // ------------------------------------------------------------------ yonetici islem dugmeleri (D-208)

    function stop(event) {
        event.preventDefault();
        event.stopPropagation();
    }

    function ApproveButton(props) {
        const { request, t } = props;

        if (!request) {
            return null;
        }

        return h('button', { type: 'button', className: 'nd-approve', title: t('actions.approve'), onClick: (event) => { stop(event); Bridge.mount('ndApprove', { request }); } },
            h(Icon, { name: 'checkCircle' }), t('actions.approve'));
    }

    function WarnButtons(props) {
        const { target, can, t } = props;

        if (!target || !can || !can.warn) {
            return null;
        }

        return h('span', { className: 'nd-warn' },
            h('button', { type: 'button', className: 'nd-icon-btn nd-icon-btn-warn', title: t('actions.warn_now'), 'aria-label': t('actions.warn_now'), onClick: (event) => { stop(event); Bridge.mount('ndWarnNow', target); } }, h(Icon, { name: 'bellAlert' })),
            h('button', { type: 'button', className: 'nd-icon-btn', title: t('actions.warn_custom'), 'aria-label': t('actions.warn_custom'), onClick: (event) => { stop(event); Bridge.mount('ndWarnCustom', target); } }, h(Icon, { name: 'chat' })));
    }

    function DepartmentButtons(props) {
        const { department, tab, lane, can, t } = props;

        if (!department || !can || !can.departments) {
            return null;
        }

        const empty = !department.members;
        const title = (key) => (empty ? t('actions.no_members') : t('actions.' + key + '_named', { department: department.name }));

        return h('span', { className: 'nd-dept' },
            h('button', { type: 'button', className: 'nd-icon-btn', disabled: empty, title: title('call_department'), 'aria-label': title('call_department'), onClick: () => Bridge.mount('ndCallDepartment', { unit: department.id }) }, h(Icon, { name: 'phone' })),
            h('button', { type: 'button', className: 'nd-icon-btn nd-icon-btn-warn', disabled: empty, title: title('alert_department'), 'aria-label': title('alert_department'), onClick: () => Bridge.mount('ndAlertDepartment', { tab, lane }) }, h(Icon, { name: 'bellAlert' })));
    }

    // ------------------------------------------------------------------ sablonlar (D-208)

    // "Tum departmanlar dashboardda indirebilecegi standart sablonlarina
    // erisebilmelidir. Tek tusla indirebilmeli veya yan sekmede acabilmelidir.
    // ... hangi dashboard tasarimi olursa olsun hepsinde dokuman alani bulunacaktir."
    function panelTemplates(documents, panel) {
        return (documents.items || []).filter((item) => (item.panels || []).indexOf(panel) !== -1);
    }

    function TemplateLinks(props) {
        const { item, t } = props;

        return h('span', { className: 'nd-tpl-links' },
            h('a', { className: 'nd-icon-btn', href: item.download, download: '', title: t('documents.download'), 'aria-label': t('documents.download') }, h(Icon, { name: 'download' })),
            item.open
                ? h('a', { className: 'nd-icon-btn', href: item.open, target: '_blank', rel: 'noopener', title: t('documents.open'), 'aria-label': t('documents.open') }, h(Icon, { name: 'external' }))
                : h('span', { className: 'nd-icon-btn is-disabled', title: t('documents.no_preview') }, h(Icon, { name: 'external' })));
    }

    function TemplatesBar(props) {
        const { documents, panel, onOpenAll, t } = props;

        // Yonetici panelinde sablon alani yok (15 Ekim 2026); yalniz departman panolari.
        if (!(documents.panels || {})[panel]) {
            return null;
        }

        const items = panelTemplates(documents, panel);
        const scope = ((documents.panels || {})[panel] || { units: [] }).units.join(', ');

        return h('section', { className: 'nd-tpl-bar', 'data-no-link-guard': '' },
            h('span', { className: 'nd-tpl-head' }, h(Icon, { name: 'folder' }), h('strong', null, t('documents.title')), scope ? h('span', { className: 'nd-tpl-scope' }, scope) : null),
            items.length === 0
                ? h('span', { className: 'nd-tpl-empty' }, t('documents.empty'),
                    documents.createUrl ? h('a', { className: 'nd-link', href: documents.createUrl, title: t('documents.empty_help') }, t('documents.add')) : null)
                : h('div', { className: 'nd-tpl-chips' }, items.slice(0, 12).map((item) => h('span', { key: item.id, className: 'nd-tpl-chip', title: [item.title, item.unit || t('documents.general'), item.size].filter(Boolean).join(' · ') },
                    item.ext ? h('span', { className: 'nd-tpl-ext' }, item.ext) : h(Icon, { name: 'document' }),
                    h('span', { className: 'nd-tpl-name' }, item.title),
                    h(TemplateLinks, { item, t })))),
            items.length ? h('button', { type: 'button', className: 'nd-btn nd-btn-sm', onClick: onOpenAll }, t('documents.all', { count: items.length })) : null);
    }

    function TemplatesDrawer(props) {
        const { open, documents, panel, onClose, t } = props;
        const [query, setQuery] = useState('');
        const [unit, setUnit] = useState('');

        if (!open) {
            return null;
        }

        const items = panelTemplates(documents, panel);
        const units = Array.from(new Set(items.map((item) => item.unit || t('documents.general')))).sort((a, b) => a.localeCompare(b, 'tr'));
        const term = norm(query);
        const shown = items.filter((item) => (!unit || (item.unit || t('documents.general')) === unit)
            && (!term || norm([item.title, item.code, item.unit].join(' ')).indexOf(term) !== -1));

        return h('div', { className: 'nd-drawer-wrap', onMouseDown: (event) => { if (event.target === event.currentTarget) { onClose(); } } },
            h('aside', { className: 'nd-drawer', role: 'dialog', 'aria-label': t('documents.title'), 'data-no-link-guard': '' },
                h('div', { className: 'nd-drawer-head' },
                    h('span', { className: 'nd-drawer-title' }, h(Icon, { name: 'folder' }), t('documents.title'), h(Pill, { tone: 'blue' }, items.length)),
                    h('button', { type: 'button', className: 'nd-popup-close', onClick: onClose, 'aria-label': t('urgent.close') }, h(Icon, { name: 'x' }))),
                h('label', { className: 'nd-search nd-search-full' }, h(Icon, { name: 'search' }), h('input', { type: 'search', value: query, placeholder: t('documents.search'), onChange: (event) => setQuery(event.target.value) })),
                units.length > 1 ? h('div', { className: 'nd-chips' },
                    h('button', { type: 'button', className: cx('nd-chip-toggle', !unit && 'is-active'), onClick: () => setUnit('') }, t('documents.all_units')),
                    units.map((name) => h('button', { key: name, type: 'button', className: cx('nd-chip-toggle', unit === name && 'is-active'), onClick: () => setUnit(unit === name ? '' : name) }, name))) : null,
                shown.length === 0 ? h('div', { className: 'nd-empty' }, t('documents.none_found')) : h('ul', { className: 'nd-drawer-list', 'data-no-link-guard': '' }, shown.map((item) => h('li', { key: item.id, className: 'nd-drawer-item nd-tpl-row' },
                    h('span', { className: 'nd-tpl-ext nd-tpl-ext-lg' }, item.ext || '–'),
                    h('div', { className: 'nd-drawer-main' },
                        h('a', { href: item.url || item.download, className: 'nd-drawer-name' }, item.title),
                        h('span', { className: 'nd-drawer-sub' }, [item.code, item.unit || t('documents.general'), item.size, item.updated].filter(Boolean).join(' · '))),
                    h(TemplateLinks, { item, t })))),
                documents.createUrl ? h('div', { className: 'nd-drawer-foot' }, h('a', { className: 'nd-btn', href: documents.createUrl, title: t('documents.empty_help') }, h(Icon, { name: 'plus' }), t('documents.add'))) : null));
    }

    // ------------------------------------------------------------------ acil uyari (pencere + cekmece)

    function dueText(item, t) {
        if (item.days === null || item.days === undefined) {
            return item.flagged ? t('urgent.flagged') : '';
        }

        if (item.days < 0) {
            return t('urgent.late', { days: -item.days });
        }

        if (item.days === 0) {
            return t('urgent.today');
        }

        if (item.days === 1) {
            return t('urgent.tomorrow');
        }

        return t('urgent.left', { days: item.days });
    }

    function intervalText(item, fast, t) {
        if (fast) {
            return t('urgent.seconds', { count: item.interval });
        }

        return item.interval >= 60 ? t('urgent.hours', { count: Math.round(item.interval / 60) }) : t('urgent.minutes', { count: item.interval });
    }

    // Bir uyari kapatilinca siradaki hemen cikmaz: arada kisa bir sessizlik.
    const QUIET_MS = 60000;
    const QUIET_FAST_MS = 5000;

    function useUrgent(items, fast) {
        const [snooze, setSnooze] = useState(() => {
            const kept = {};
            const saved = store('kc-nd-snooze', {});

            Object.keys(saved || {}).forEach((key) => { if (saved[key] > Date.now()) { kept[key] = saved[key]; } });

            return kept;
        });
        const [now, setNow] = useState(Date.now());
        const [ready, setReady] = useState(false);
        const [quietUntil, setQuietUntil] = useState(0);

        // "Ekranda bir anda belirir": sayfa acildiktan kisa sure sonra.
        useEffect(() => {
            const timer = window.setTimeout(() => setReady(true), 1200);

            return () => window.clearTimeout(timer);
        }, []);

        useEffect(() => {
            const timer = window.setInterval(() => setNow(Date.now()), fast ? 1000 : 15000);

            return () => window.clearInterval(timer);
        }, [fast]);

        const unit = fast ? 1000 : 60000;
        const due = items.filter((item) => !(snooze[item.id] && snooze[item.id] > now));

        const dismiss = useCallback((item) => {
            const next = Object.assign({}, snooze, { [item.id]: Date.now() + item.interval * unit });
            setSnooze(next);
            save('kc-nd-snooze', next);
            setQuietUntil(Date.now() + (fast ? QUIET_FAST_MS : QUIET_MS));
        }, [snooze, unit, fast]);

        const reset = useCallback(() => {
            setSnooze({});
            save('kc-nd-snooze', {});
            setQuietUntil(0);
            setNow(Date.now());
        }, []);

        const showing = ready && now >= quietUntil ? due[0] || null : null;

        return { current: showing, waiting: due.length, snooze, dismiss, reset, unit, now };
    }

    function UrgentPopup(props) {
        const { item, waiting, fast, onDismiss, onOpenDrawer, can, t } = props;

        if (!item) {
            return null;
        }

        return h('div', { className: cx('nd-popup', 'nd-level-' + item.level), role: 'alertdialog', 'aria-labelledby': 'nd-popup-title', key: item.id },
            h('div', { className: 'nd-popup-head' },
                h('span', { className: 'nd-popup-icon' }, h(Icon, { name: 'alert' })),
                h('div', { className: 'nd-popup-headtext' },
                    h('span', { className: 'nd-popup-kicker' }, t('urgent.popup')),
                    h('span', { className: 'nd-popup-level' }, t('urgent.levels.' + item.level) + ' · ' + item.kindLabel)),
                h('button', { type: 'button', className: 'nd-popup-close', onClick: () => onDismiss(item), 'aria-label': t('urgent.close'), title: t('urgent.close') }, h(Icon, { name: 'x' }))),
            h('div', { className: 'nd-popup-body' },
                h('div', { id: 'nd-popup-title', className: 'nd-popup-title' }, item.title),
                h(TypeBadges, { types: item.types }),
                h('div', { className: 'nd-popup-meta' },
                    item.meta ? h('span', null, item.meta) : null,
                    item.due ? h('span', null, t('urgent.due', { date: item.due })) : null,
                    h(Pill, { tone: item.level === 'critical' ? 'red' : (item.level === 'high' ? 'amber' : 'gray') }, dueText(item, t)))),
            h('div', { className: 'nd-popup-actions' },
                item.url ? h('a', { className: 'nd-btn nd-btn-primary', href: item.url, 'data-link-guard': t('urgent.field'), 'data-link-guard-name': item.title }, h(Icon, { name: 'arrow' }), t('urgent.open')) : null,
                h('button', { type: 'button', className: 'nd-btn', onClick: () => onDismiss(item) }, h(Icon, { name: 'clock' }), t('urgent.later'))),
            item.approve || (item.target && can && can.warn) ? h('div', { className: 'nd-popup-manage' },
                h(ApproveButton, { request: item.approve, t }),
                item.target && can && can.warn ? h('button', { type: 'button', className: 'nd-btn nd-btn-sm nd-btn-warn', onClick: () => Bridge.mount('ndWarnNow', item.target) }, h(Icon, { name: 'bellAlert' }), t('actions.warn_now')) : null,
                item.target && can && can.warn ? h('button', { type: 'button', className: 'nd-btn nd-btn-sm', onClick: () => Bridge.mount('ndWarnCustom', item.target) }, h(Icon, { name: 'chat' }), t('actions.warn_custom')) : null) : null,
            h('div', { className: 'nd-popup-foot' },
                h('span', null, t('urgent.reappear', { interval: intervalText(item, fast, t) })),
                waiting > 1 ? h('button', { type: 'button', className: 'nd-link', onClick: onOpenDrawer }, t('urgent.more', { count: waiting - 1 })) : null));
    }

    function UrgentDrawer(props) {
        const { open, items, urgent, fast, setFast, onClose, can, t } = props;

        if (!open) {
            return null;
        }

        return h('div', { className: 'nd-drawer-wrap', onMouseDown: (event) => { if (event.target === event.currentTarget) { onClose(); } } },
            h('aside', { className: 'nd-drawer', role: 'dialog', 'aria-label': t('urgent.title') },
                h('div', { className: 'nd-drawer-head' },
                    h('span', { className: 'nd-drawer-title' }, h(Icon, { name: 'alert' }), t('urgent.title'), h(Pill, { tone: 'red' }, items.length)),
                    h('button', { type: 'button', className: 'nd-popup-close', onClick: onClose, 'aria-label': t('urgent.close') }, h(Icon, { name: 'x' }))),
                h('p', { className: 'nd-drawer-rule' }, t('urgent.rule')),
                h('label', { className: 'nd-switch' },
                    h('input', { type: 'checkbox', checked: fast, onChange: (event) => setFast(event.target.checked) }),
                    h('span', null, t('fast_demo'))),
                items.length === 0 ? h('div', { className: 'nd-empty' }, t('urgent.none')) : h('ul', { className: 'nd-drawer-list', 'data-no-link-guard': '' },
                    items.map((item) => {
                        const until = urgent.snooze[item.id];
                        const snoozed = until && until > urgent.now;

                        return h('li', { key: item.id, className: cx('nd-drawer-item', 'nd-level-' + item.level, snoozed && 'is-snoozed') },
                            h('span', { className: 'nd-level-dot' }),
                            h('div', { className: 'nd-drawer-main' },
                                h('a', { href: item.url || '#', className: 'nd-drawer-name' }, item.title),
                                h(TypeBadges, { types: item.types }),
                                h('span', { className: 'nd-drawer-sub' }, [item.kindLabel, item.meta, item.due ? t('urgent.due', { date: item.due }) : null].filter(Boolean).join(' · '))),
                            h('div', { className: 'nd-drawer-side' },
                                h('span', { className: 'nd-drawer-tools' },
                                    h(ApproveButton, { request: item.approve, t }),
                                    h(WarnButtons, { target: item.target, can, t }),
                                    h(Pill, { tone: item.level === 'critical' ? 'red' : (item.level === 'high' ? 'amber' : 'gray') }, t('urgent.levels.' + item.level))),
                                h('span', { className: 'nd-drawer-when' }, snoozed
                                    ? t('urgent.snoozed', { time: new Date(until).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: fast ? '2-digit' : undefined }) })
                                    : dueText(item, t))));
                    })),
                h('div', { className: 'nd-drawer-foot' },
                    h('button', { type: 'button', className: 'nd-btn', onClick: urgent.reset }, h(Icon, { name: 'bell' }), t('urgent.reset')))));
    }

    // ------------------------------------------------------------------ ust bolum

    function EntryMenu(props) {
        const { entry, t } = props;
        const [open, setOpen] = useState(false);
        const ref = useRef(null);

        useEffect(() => {
            const close = (event) => { if (ref.current && !ref.current.contains(event.target)) { setOpen(false); } };
            document.addEventListener('mousedown', close);

            return () => document.removeEventListener('mousedown', close);
        }, []);

        if (!entry.length) {
            return null;
        }

        return h('div', { className: 'nd-menu', ref },
            h('button', { type: 'button', className: 'nd-btn nd-btn-create', onClick: () => setOpen(!open), 'aria-expanded': open }, h(Icon, { name: 'plus' }), t('new'), h(Icon, { name: 'chevron', className: 'nd-icon-sm' })),
            open ? h('div', { className: 'nd-menu-list' }, entry.map((item) => h('a', { key: item.key, href: item.url, className: 'nd-menu-item' }, h(Icon, { name: item.icon }), item.label))) : null);
    }

    const TAB_ICONS = { acquisition: 'clipboard', executive: 'eye', purchasing: 'cart' };

    function Header(props) {
        const { config, panels, tab, setTab, urgentItems, openDrawer, t } = props;
        const tabs = panels.map((key) => [key, TAB_ICONS[key] || 'document']);
        const critical = urgentItems.filter((item) => item.level === 'critical').length;

        return h('div', { className: 'nd-header' },
            h('div', { className: 'nd-title' },
                h('h1', null, config.title || t('title')),
                h('span', { className: 'nd-today' }, config.today)),
            // D-221: tek panelde sekme yok (her personel yalniz kendi panelini gorur).
            tabs.length > 1 ? h('div', { className: 'nd-tabs', role: 'tablist' }, tabs.map(([key, icon]) => h('button', {
                key, type: 'button', role: 'tab', 'aria-selected': tab === key, className: cx('nd-tab', tab === key && 'is-active'), onClick: () => setTab(key),
            }, h(Icon, { name: icon }), t('tabs.' + key)))) : null,
            h('div', { className: 'nd-header-actions' },
                // Panele uygun veri girisi (15 Ekim 2026); yonetici panelinde hepsi.
                h(EntryMenu, { entry: (config.entry || []).filter((item) => !item.panels || item.panels.indexOf(tab) !== -1), t }),
                h('button', { type: 'button', className: cx('nd-btn', 'nd-btn-urgent', critical > 0 && 'has-critical'), onClick: openDrawer },
                    h(Icon, { name: 'alert' }), t('urgent_button'),
                    urgentItems.length ? h('span', { className: 'nd-count' }, urgentItems.length) : null)));
    }

    // Uyari seridi (D-227, 19 Ekim 2026 kullanici: "Pano bilesenleri => 15 Uyari seridi kismini da
    // dashboard'a alalim. Ama ilgili uyarilar gercekci olmali ve hepsine tiklanabilmeli ... 8
    // incelenecek rapor var diyorsa ... filtrelenmis seklinde 8 kayit gelecek"). Sunucu sayiyi,
    // metni ve listenin suzulu adresini verir (DashboardAlerts); sayi listedeki kayit sayisidir.
    function Alerts(props) {
        const items = (props.items || []).filter((item) => item.count > 0);

        if (!items.length) {
            return null;
        }

        return h('nav', { className: 'nd-alerts', 'aria-label': props.t('alerts_title') }, items.map((item) => h('a', {
            key: item.key,
            className: cx('nd-alert', 'nd-tone-' + (item.tone || 'blue')),
            href: item.url,
            title: item.title || undefined,
        },
            h(Icon, { name: item.icon || 'alert' }),
            h('span', { className: 'nd-alert-count' }, item.count),
            h('span', { className: 'nd-alert-text' }, item.text))));
    }

    function Kpis(props) {
        return h('div', { className: 'nd-kpis' }, props.items.map((item) => h('div', { key: item.key, className: cx('nd-kpi', 'nd-tone-' + (item.tone || 'gray')), title: item.title },
            h('span', { className: 'nd-kpi-label' }, item.icon ? h(Icon, { name: item.icon }) : null, item.label),
            h('span', { className: 'nd-kpi-value' }, item.value === null || item.value === undefined ? '–' : item.value),
            item.sub ? h('span', { className: 'nd-kpi-sub' }, item.sub) : null)));
    }

    // ------------------------------------------------------------------ alt kartlar (her panel, D-212)

    // "Dun · Bugun · Yarin alani, Bekleme yasi, Acil isler gibi alanlar tum
    // panellerde gorulecek ama sekmeli tabli yapidan sonra ... alt kisimda."
    // Panele gore turler; yonetici panelinde hepsi.
    const PANEL_KINDS = {
        acquisition: ['proposal_submitted', 'proposal_created', 'tender_deadline', 'meeting', 'case_created'],
        purchasing: ['delivery', 'purchase_request', 'purchase_need', 'order_approval'],
    };

    function forPanel(items, panel) {
        const kinds = PANEL_KINDS[panel];

        return kinds ? (items || []).filter((item) => kinds.indexOf(item.kind) !== -1) : (items || []);
    }

    function inBucket(item, bucket) {
        return item.days !== null && item.days !== undefined && item.days >= bucket.min && (bucket.max === null || item.days <= bucket.max);
    }

    function DayCard(props) {
        const { days, panel, t } = props;
        const [day, setDay] = useState('today');
        const pick = (key) => forPanel(days[key], panel);
        const items = pick(day);
        const icons = { proposal_submitted: 'arrow', proposal_created: 'clipboard', tender_deadline: 'megaphone', meeting: 'calendar', case_created: 'briefcase', delivery: 'truck' };

        return h('section', { className: 'nd-card' },
            h('div', { className: 'nd-card-head' }, h('span', { className: 'nd-card-title' }, h(Icon, { name: 'calendar' }), t('days.title'))),
            h('div', { className: 'nd-seg' }, ['yesterday', 'today', 'tomorrow'].map((key) => h('button', {
                key, type: 'button', className: cx('nd-seg-btn', day === key && 'is-active'), onClick: () => setDay(key),
            }, t('days.' + key), h('span', { className: 'nd-seg-count' }, pick(key).length)))),
            items.length === 0 ? h('div', { className: 'nd-empty nd-empty-sm' }, t('days.empty')) : h('ul', { className: 'nd-mini-list', 'data-no-link-guard': '' }, items.map((item, index) => h('li', { key: index },
                h('span', { className: 'nd-mini-icon' }, h(Icon, { name: icons[item.kind] || 'document' })),
                h('div', { className: 'nd-mini-main' },
                    h('a', { href: item.url || '#', className: 'nd-mini-title' }, item.title),
                    h('span', { className: 'nd-mini-sub' }, [item.kindLabel, item.code, item.time].filter(Boolean).join(' · ')))))));
    }

    function AgingCard(props) {
        const { buckets, active, onPick, sub, t } = props;
        const max = Math.max(1, ...buckets.map((bucket) => bucket.count));
        const tones = ['gray', 'amber', 'orange', 'red'];

        return h('section', { className: 'nd-card' },
            h('div', { className: 'nd-card-head nd-card-head-stack' },
                h('span', { className: 'nd-card-title' }, h(Icon, { name: 'clock' }), t('aging_title')),
                h('span', { className: 'nd-card-sub' }, sub)),
            h('div', { className: 'nd-bars' }, buckets.map((bucket, index) => h('button', {
                key: bucket.key, type: 'button', title: t('aging_filter'),
                className: cx('nd-bar', 'nd-tone-' + tones[index], active && active.key === bucket.key && 'is-active'),
                onClick: () => onPick(bucket),
            },
                h('span', { className: 'nd-bar-label' }, bucket.label),
                h('span', { className: 'nd-bar-track' }, h('span', { className: 'nd-bar-fill', style: { width: Math.round(bucket.count * 100 / max) + '%' } })),
                h('span', { className: 'nd-bar-value' }, bucket.count)))),
            active ? h('button', { type: 'button', className: 'nd-chip', onClick: () => onPick(active) }, active.label, h(Icon, { name: 'x', className: 'nd-icon-sm' })) : null);
    }

    function UrgentCard(props) {
        const { items, openDrawer, t } = props;

        return h('section', { className: 'nd-card' },
            h('div', { className: 'nd-card-head' },
                h('span', { className: 'nd-card-title' }, h(Icon, { name: 'alert' }), t('urgent.title')),
                items.length > 4 ? h('button', { type: 'button', className: 'nd-link', onClick: openDrawer }, t('urgent.more', { count: items.length - 4 })) : null),
            items.length === 0 ? h('div', { className: 'nd-empty nd-empty-sm' }, t('urgent.none')) : h('ul', { className: 'nd-mini-list', 'data-no-link-guard': '' }, items.slice(0, 4).map((item) => h('li', { key: item.id, className: 'nd-level-' + item.level },
                h('span', { className: 'nd-level-dot' }),
                h('div', { className: 'nd-mini-main' },
                    h('a', { href: item.url || '#', className: 'nd-mini-title' }, item.title),
                    h('span', { className: 'nd-mini-sub' }, [item.kindLabel, dueText(item, t)].filter(Boolean).join(' · ')))))));
    }

    // Gorusulecekler (D-212): "Is gelistirmeye bu hafta - bu gun - bu ay
    // gorusulecekleri gosteren bir yine alan eklicez kucuk sekilde."
    function MeetingsCard(props) {
        const { meetings, t } = props;
        const [span, setSpan] = useState('week');
        const ends = { today: meetings.today, week: meetings.weekEnd, month: meetings.monthEnd };
        const pick = (key) => (meetings.items || []).filter((item) => item.on && item.on <= ends[key]);
        const items = pick(span);

        return h('section', { className: 'nd-card' },
            h('div', { className: 'nd-card-head' }, h('span', { className: 'nd-card-title' }, h(Icon, { name: 'users' }), t('meetings.title'))),
            h('div', { className: 'nd-seg' }, ['today', 'week', 'month'].map((key) => h('button', {
                key, type: 'button', className: cx('nd-seg-btn', span === key && 'is-active'), onClick: () => setSpan(key),
            }, t('meetings.' + key), h('span', { className: 'nd-seg-count' }, pick(key).length)))),
            items.length === 0 ? h('div', { className: 'nd-empty nd-empty-sm' }, t('meetings.empty')) : h('ul', { className: 'nd-mini-list', 'data-no-link-guard': '' }, items.slice(0, 8).map((item) => h('li', { key: item.id },
                h('span', { className: 'nd-mini-date' }, item.when),
                h('div', { className: 'nd-mini-main' },
                    h('a', { href: item.url || '#', className: 'nd-mini-title' }, item.title),
                    h('span', { className: 'nd-mini-sub' }, [item.subject, item.owner].filter(Boolean).join(' · ')))))));
    }

    function Bottom(props) {
        const { panel, config, lanes, ageFilter, setAgeFilter, urgentItems, openDrawer, t } = props;
        const agedItems = [].concat(...lanes.filter((lane) => lane.aging).map((lane) => aged(lane.items)));
        const buckets = ((config.aging || {})[panel] || []).map((bucket) => Object.assign({}, bucket, { count: agedItems.filter((item) => inBucket(item, bucket)).length }));

        return h('div', { className: 'nd-bottom' },
            h(DayCard, { days: config.days || {}, panel, t }),
            h(AgingCard, { buckets, active: ageFilter, sub: t('aging_subs.' + panel), onPick: (bucket) => setAgeFilter(ageFilter && ageFilter.key === bucket.key ? null : bucket), t }),
            h(UrgentCard, { items: forPanel(urgentItems, panel), openDrawer, t }),
            panel === 'acquisition' && config.meetings ? h(MeetingsCard, { meetings: config.meetings, t }) : null);
    }

    // ------------------------------------------------------------------ seritler (her panel)

    // "Derece diye bir kolon yok. Sicaklik var." (D-212)
    function heatTone(heat) {
        if (heat >= 70) {
            return 'red';
        }

        if (heat >= 40) {
            return 'orange';
        }

        return heat > 0 ? 'amber' : 'gray';
    }

    const STATUS_TONES = { won: 'green', lost: 'gray', draft: 'amber' };

    function LaneCard(props) {
        const { item, lane, can, t } = props;
        // D-222: tiklamak kart penceresini acar (biraz daha ayrinti + "sayfasina git" sorusu);
        // basliga Ctrl / Cmd / Shift / orta tik yeni sekmede acar. Icinde dugmeler var.
        const openCard = React.useContext(CardContext);
        const open = (event) => {
            if (!event.defaultPrevented) {
                openCard({ item, lane });
            }
        };
        const titleClick = (event) => {
            event.stopPropagation();

            if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
                return;
            }

            event.preventDefault();
            openCard({ item, lane });
        };
        // Karisik seritte (D-217: proje isi + onay) kart kendi gun turunu tasir (`mode`).
        const left = item.mode ? item.mode === 'left' : (lane.age === false && lane.key !== 'projects');
        const status = item.status ? (STATUS_TONES[item.status] ? t('status.' + item.status) : item.status) : null;

        // D-217: aciliyete gore ton (kritik kirmizi, yuksek turuncu, orta sari).
        return h('div', { className: cx('nd-lane-card', item.urgent && 'is-urgent', item.level && 'is-level-' + item.level), role: 'link', tabIndex: 0, onClick: open, onKeyDown: (event) => { if (event.key === 'Enter') { open(event); } } },
            h('div', { className: 'nd-lane-card-top' },
                h('a', { className: 'nd-lane-card-title', href: item.url || '#', onClick: titleClick }, h(Marquee, { title: item.title }, item.title)),
                item.urgent ? h(Pill, { tone: 'red' }, t('status.urgent')) : null,
                h(WarnButtons, { target: item.target, can, t })),
            (item.codes && item.codes.length) || item.party ? h(Marquee, { className: 'nd-lane-card-sub' }, [(item.codes || []).join(' · '), item.party].filter(Boolean).join(' · ')) : null,
            lane.key === 'projects' ? h('div', { className: 'nd-progress' },
                h('span', { className: 'nd-progress-track' }, h('span', { className: 'nd-progress-fill', style: { width: (item.progress || 0) + '%' } })),
                h('span', { className: 'nd-progress-text' }, t('project_progress', { ordered: item.ordered, open: item.open }))) : null,
            h('div', { className: 'nd-lane-card-foot' },
                // D-222: sorumlunun yalniz avatari (adi ustune gelince ve pencerede); yaninda proje tipi.
                h('span', { className: 'nd-lane-card-who' }, item.owner ? h(Avatar, { name: item.owner }) : null, h(TypeBadges, { types: item.types, compact: true })),
                h('span', { className: 'nd-lane-card-tags' },
                    item.heat !== undefined && item.heat !== null ? h(Heat, { heat: item.heat, title: t('heat_title') }) : null,
                    status ? h(Pill, { tone: STATUS_TONES[item.status] || (item.approve ? 'amber' : 'blue') }, status) : null,
                    h(ApproveButton, { request: item.approve, t }),
                    item.amount ? h('span', { className: 'nd-amount' }, item.amount) : null,
                    item.days === null || item.days === undefined ? null : (left
                        ? h(Pill, { tone: leftTone(item.days) }, item.days < 0 ? t('late_days', { days: -item.days }) : t('left_days', { days: item.days }))
                        : h(Pill, { tone: ageTone(item.days) }, t('age_days', { days: item.days }))))));
    }

    // D-217: gruplu serit (Yonetici panelinde Proje - Operasyon ve Satin alma,
    // projeye gore). Grup basligi ac / kapat; secim tarayicida kalir, secim
    // yoksa acil kaydi olan grup acik gelir.
    const GROUPS_KEY = 'konelsis.nd.groups';

    function LaneGroups(props) {
        const { lane, items, can, t } = props;
        const [open, setOpen] = useState(() => store(GROUPS_KEY, {}));
        const toggle = (key, current) => setOpen((state) => {
            const next = Object.assign({}, state, { [lane.key + ':' + key]: !current });
            save(GROUPS_KEY, next);
            return next;
        });

        return (lane.groups || []).map((group) => {
            const rows = items.filter((item) => item.group === group.key);

            if (rows.length === 0) {
                return null;
            }

            const urgent = rows.filter((item) => item.level === 'critical' || item.level === 'high').length;
            const stored = open[lane.key + ':' + group.key];
            const isOpen = stored === undefined ? urgent > 0 : !!stored;

            return h('div', { key: group.key, className: cx('nd-group', isOpen && 'is-open', urgent > 0 && 'has-urgent') },
                h('div', { className: 'nd-group-head' },
                    h('button', { type: 'button', className: 'nd-group-toggle', 'aria-expanded': String(isOpen), title: t('group_toggle', { title: group.title }), onClick: () => toggle(group.key, isOpen) },
                        h(Icon, { name: 'chevron' }),
                        h('span', { className: 'nd-group-title' }, group.title),
                        h('span', { className: 'nd-lane-count' }, rows.length),
                        urgent > 0 ? h(Pill, { tone: 'red' }, t('group_urgent', { count: urgent })) : null),
                    group.url ? h('a', { className: 'nd-group-link', href: group.url, title: t('group_open'), 'aria-label': t('group_open') }, h(Icon, { name: 'external' })) : null),
                isOpen ? h('div', { className: 'nd-group-body' }, rows.map((item) => h(LaneCard, { key: item.id, item, lane, can, t }))) : null);
        });
    }

    // Bekleme yasi suzgecine yalniz yasi olan kart girer (kalan gunlu kart girmez).
    function aged(items) {
        return (items || []).filter((item) => item.mode !== 'left');
    }

    function Lanes(props) {
        const { lanes, icons, tab, can, ageFilter, t } = props;
        const [ref, height] = useFitHeight(360, true);
        // Serit en dar genisligi CSS'te (--nd-lane-min): telefonda serit genisler, yatay kayar.
        const style = { gridTemplateColumns: 'repeat(' + Math.max(1, lanes.length) + ', minmax(var(--nd-lane-min, 11rem), 1fr))' };

        if (height) {
            style.height = height + 'px';
        }

        return h('div', { className: cx('nd-lanes', height && 'is-fit'), ref, style }, lanes.map((lane) => {
            const items = ageFilter && lane.aging ? aged(lane.items).filter((item) => inBucket(item, ageFilter)) : (lane.items || []);

            // D-217: yonetici panelinde her departman seridi kendi yumusak tonunda.
            return h('section', { key: lane.key, className: cx('nd-lane', lane.tone && 'nd-lane--' + lane.tone) },
                h('div', { className: 'nd-lane-head' },
                    h('span', { className: 'nd-lane-name' },
                        // Baslik tek satir (dar seritte sigmazsa uc nokta, tamami ipucunda).
                        h('span', { className: 'nd-card-title', title: lane.label }, h(Icon, { name: icons[lane.key] || 'document' }), h('span', { className: 'nd-lane-label' }, lane.label)),
                        lane.oldest ? h('span', { className: 'nd-lane-oldest' }, t('lane_oldest', { days: lane.oldest })) : null),
                    h('span', { className: 'nd-lane-count' }, ageFilter && lane.aging ? t('lane_filtered', { shown: items.length, total: lane.count }) : lane.count),
                    h(DepartmentButtons, { department: lane.department, tab, lane: lane.key, can, t })),
                h(ScrollArea, { className: 'nd-lane-body', attrs: { 'data-no-link-guard': '' }, label: t('scroll_more') }, items.length === 0
                    ? h('div', { className: 'nd-empty nd-empty-sm' }, t('lane_empty'))
                    : (lane.groups
                        ? h(LaneGroups, { lane, items, can, t })
                        : items.map((item) => h(LaneCard, { key: item.id, item, lane, can, t })))));
        }));
    }

    const LANE_ICONS = {
        tenders: 'megaphone', investors: 'users', potentials: 'briefcase', to_submit: 'clipboard', awaiting: 'arrow', concluded: 'check',
        cases: 'briefcase', offers: 'clipboard', approvals: 'check', purchasing: 'cart',
        entry: 'cart', projects: 'briefcase', orders: 'clock', production: 'truck',
    };

    function kpiItems(panel, data, t) {
        const kpis = data.kpis || {};

        if (panel === 'acquisition') {
            return [
                { key: 'to_submit', icon: 'clipboard', label: t('kpi.to_submit'), value: kpis.to_submit, tone: 'amber' },
                { key: 'awaiting', icon: 'arrow', label: t('kpi.awaiting'), value: kpis.awaiting, sub: kpis.oldest !== null && kpis.oldest !== undefined ? t('kpi.oldest', { days: kpis.oldest }) : null, tone: 'blue' },
                { key: 'won', icon: 'check', label: t('kpi.won'), value: kpis.won, tone: 'green' },
                { key: 'lost', icon: 'x', label: t('kpi.lost'), value: kpis.lost, tone: 'gray' },
                { key: 'win_rate', icon: 'bolt', label: t('kpi.win_rate'), value: kpis.win_rate === null || kpis.win_rate === undefined ? null : '%' + kpis.win_rate, sub: t('kpi.win_rate_sub', { count: (kpis.won || 0) + (kpis.lost || 0) }), tone: 'green' },
                { key: 'pipeline', icon: 'receipt', label: t('kpi.pipeline'), value: (kpis.pipeline || [])[0] || null, sub: (kpis.pipeline || []).slice(1, 3).join(' · ') || null, tone: 'blue' },
            ];
        }

        if (panel === 'purchasing') {
            return [
                { key: 'entry', icon: 'cart', label: t('kpi.entry'), value: kpis.entry, sub: kpis.urgent ? kpis.urgent + ' ' + t('kpi.urgent') : null, tone: kpis.urgent ? 'red' : 'amber' },
                { key: 'projects', icon: 'briefcase', label: t('kpi.projects'), value: kpis.projects, tone: 'blue' },
                { key: 'pending', icon: 'clock', label: t('kpi.pending'), value: kpis.pending, tone: 'amber' },
                { key: 'pending_total', icon: 'receipt', label: t('kpi.pending_total'), value: (kpis.pending_total || [])[0] || null, sub: (kpis.pending_total || []).slice(1, 3).join(' · ') || null, tone: 'blue' },
                { key: 'production', icon: 'truck', label: t('kpi.production'), value: kpis.production, sub: kpis.late ? kpis.late + ' ' + t('kpi.late') : null, tone: kpis.late ? 'red' : 'green' },
            ];
        }

        return (data.lanes || []).map((lane) => ({ key: lane.key, icon: LANE_ICONS[lane.key], label: lane.label, value: lane.count, sub: lane.oldest ? t('lane_oldest', { days: lane.oldest }) : null, tone: lane.oldest >= 90 ? 'red' : (lane.oldest >= 30 ? 'amber' : 'blue') }));
    }

    // Uc panel ayni bicimde (D-212: "Is gelistirme paneli kismini Satin alma
    // paneli formatinda olustur"): sayi seridi, seritler, alt kartlar.
    function Panel(props) {
        const { panel, config, can, urgentItems, openDrawer, t } = props;
        const data = config[panel] || {};
        const lanes = data.lanes || [];
        const [ageFilter, setAgeFilter] = useState(null);

        return h('div', { className: 'nd-panel' },
            config.show_kpis === false ? null : h(Kpis, { items: kpiItems(panel, data, t) }),
            h(Alerts, { items: data.alerts, t }),
            h(Lanes, { lanes, icons: LANE_ICONS, tab: panel, can, ageFilter, t }),
            h(Bottom, { panel, config, lanes, ageFilter, setAgeFilter, urgentItems, openDrawer, t }));
    }

    // ------------------------------------------------------------------ uygulama

    const PANEL_ORDER = ['executive', 'acquisition', 'purchasing'];

    function App(props) {
        // Yonetici isleminden sonra sunucu yeni veriyi gonderir (InteractsWithNewDashboard::refreshDashboard).
        const [config, setConfig] = useState(props.config);
        const t = useMemo(() => makeT(config.labels || {}), [config.labels]);
        const [templates, setTemplates] = useState(false);

        useEffect(() => {
            const update = (event) => {
                const detail = event && event.detail;
                const next = detail && (detail.config || (Array.isArray(detail) && detail[0] && detail[0].config));

                if (next) {
                    setConfig(next);
                    relayout();
                }
            };
            window.addEventListener('nd-config', update);

            return () => window.removeEventListener('nd-config', update);
        }, []);
        // Kisinin panolari (D-212): ust yonetim hepsini, departman calisani kendi panelini.
        const panels = config.panels && config.panels.length ? config.panels : PANEL_ORDER;
        const initialTab = (() => {
            try {
                const value = new URL(window.location.href).searchParams.get('pano');

                return panels.indexOf(value) !== -1 ? value : panels[0];
            } catch (error) {
                return panels[0];
            }
        })();
        const [tab, setTabState] = useState(initialTab);
        const [drawer, setDrawer] = useState(false);
        const [card, setCard] = useState(null);
        const closeCard = useCallback(() => setCard(null), []);
        const [fast, setFastState] = useState(() => store('kc-nd-fast', false) === true);
        const urgentItems = config.urgent || [];
        const urgent = useUrgent(urgentItems, fast);

        const setTab = (next) => {
            setTabState(next);
            try {
                const address = new URL(window.location.href);
                address.searchParams.set('pano', next);
                window.history.replaceState(window.history.state, '', address.toString());
            } catch (error) { /* secim yalniz ekranda kalir */ }
        };

        const setFast = (value) => {
            setFastState(value);
            save('kc-nd-fast', value);
        };

        // Hemen uyar, Ozel bildirim, Departmani cagir, Uyari gonder yalniz yonetici
        // panelinde (15 Ekim 2026); Onay ver onay sirasi gelen kisiye her panelde.
        const can = tab === 'executive' ? (config.can || {}) : {};
        const documents = config.documents || { items: [], panels: {} };
        const openDrawer = () => setDrawer(true);

        return h(CardContext.Provider, { value: setCard }, h('div', { className: 'nd-app' },
            h(UrgentStrip, { announcements: config.announcements || [], urgent: urgentItems, t }),
            h(Header, { config, panels, tab, setTab, urgentItems, openDrawer, t }),
            h(TemplatesBar, { documents, panel: tab, onOpenAll: () => setTemplates(true), t }),
            h(Panel, { key: tab, panel: tab, config, can, urgentItems, openDrawer, t }),
            drawer || templates || card ? null : h(UrgentPopup, { item: urgent.current, waiting: urgent.current ? urgentItems.filter((item) => !(urgent.snooze[item.id] && urgent.snooze[item.id] > urgent.now)).length : 0, fast, onDismiss: urgent.dismiss, onOpenDrawer: openDrawer, can, t }),
            h(UrgentDrawer, { open: drawer, items: urgentItems, urgent, fast, setFast, onClose: () => setDrawer(false), can, t }),
            h(TemplatesDrawer, { open: templates, documents, panel: tab, onClose: () => setTemplates(false), t }),
            h(CardDialog, { card, onClose: closeCard, t })));
    }

    function mount() {
        document.querySelectorAll('[data-nd-root]').forEach((element) => {
            if (element.dataset.ndMounted === '1') {
                return;
            }

            let config = {};

            try {
                config = JSON.parse(element.getAttribute('data-config') || '{}');
            } catch (error) {
                console.error('Konelsis yeni pano: veri okunamadi', error);
            }

            element.dataset.ndMounted = '1';
            Bridge.root = element;
            ReactDOM.createRoot(element).render(h(App, { config }));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }

    document.addEventListener('livewire:navigated', mount);
}());
