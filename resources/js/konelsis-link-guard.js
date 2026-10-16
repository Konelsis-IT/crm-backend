/*
 * Konelsis - baglantiya gitmeden once onay penceresi (D-200; kullanici 13 Ekim
 * 2026: "tablo linkleme olaylarinda ve herhangi bir linke gidilmek soz konusu
 * oldugunda 3 buton gelen bir uyari modali istiyorum ... sayfada kal butonu ve
 * ilgili satirin detayina git ... detay sayfasindaki ... linkli bir alansa ...
 * 2 buton". JavaScript icin kullanicinin bu ise ozel onayi: "JavaScript, tek
 * nokta"). 14 Ekim 2026 tasarim duzeltmesi (kullanici: "fynoai icin attigim
 * modal daha kaliteli ... hangi menuye gidilmeyi oneriyorsa ona uygun icon
 * olmalidir. Ve baslik belli olmalidir"): ortali simge dairesi (hedef kaydin
 * tur simgesi), belirgin baslik, ortali aciklama, kapat dugmesi, esit genislikte
 * dugmeler.
 *
 * - Tablo hucresindeki bagli kayit baglantisi (Filament: a.fi-ta-col-has-column-url
 *   ya da hucre icindeki baska bir baglanti): Sayfada kal / Satirin detayina git /
 *   "<Tur> sayfasina git". Satirin detay sayfasi yoksa iki dugme.
 * - Detay sayfasindaki bagli alan (a.fi-in-entry-content): Sayfada kal /
 *   "<Tur> sayfasina git".
 * - 15 Ekim 2026 ("Tum sistemde gecerlidir"): baska her yerde bir kaydin
 *   sayfasina giden baglanti (panolar, kartlar, is panosu, sohbet) ve ozel
 *   ekranlarin data-link-guard isaretli baglantilari da iki dugmeyle sorulur.
 * - Tur (baslik ve git dugmesi): hedef adresin kaynak oneki (targets,
 *   FilamentAssetsProvider); bilinmiyorsa sutun / alan adi, o da yoksa genel baslik.
 * - Simge: tiklanan baglantinin icindeki kayit turu simgesi (TableConventions /
 *   RecordLinks::iconFor); yoksa genel "sayfaya git" simgesi.
 * - Satira tiklamak (satirin kendi baglantisi, D-125), satir eylemleri,
 *   dugmeler ve menuler pencere acmaz. Ctrl / Cmd / Shift / orta tik (yeni
 *   sekme), indirme, yeni sekmede acilan, # / mailto / tel baglantilari ve
 *   data-no-link-guard isaretli baglanti dogrudan calisir.
 * - Ayarlar ve metinler window.filamentData.konelsisLinkGuard
 *   (FilamentAssetsProvider; Feature::LinkGuard kapaliysa veri yok, betik
 *   hicbir sey yapmaz).
 */
(function () {
    'use strict';

    const data = (window.filamentData || {}).konelsisLinkGuard;

    if (!data || window.KonelsisLinkGuard) {
        return;
    }

    const labels = data.labels || {};
    const targets = data.targets || {};
    const SVG_NS = 'http://www.w3.org/2000/svg';
    // Heroicons outline: arrow-top-right-on-square (genel "sayfaya git") ve x-mark.
    const GO_PATH = 'M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25';
    const CLOSE_PATH = 'M6 18 18 6M6 6l12 12';
    let open = null;

    function text(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    /** Baglantinin ana metni: hucredeki aciklama satiri (alt satir) haric. */
    function targetText(anchor) {
        const copy = anchor.cloneNode(true);

        Array.prototype.forEach.call(copy.querySelectorAll('.fi-ta-text-description, .fi-in-text-description, .sr-only'), function (node) {
            node.remove();
        });

        return text(copy.textContent) || text(anchor.textContent);
    }

    function format(template, values) {
        return String(template || '').replace(/:(\w+)/g, function (match, key) {
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : match;
        });
    }

    /** Metni ogeye yazar; yer tutucular (alan, kayit) kalin yazilir (tirnak yok, 14 Ekim 2026). */
    function fillBold(element, template, values) {
        const source = String(template || '');
        const pattern = /:(\w+)/g;
        let last = 0;
        let match;

        while ((match = pattern.exec(source)) !== null) {
            if (!Object.prototype.hasOwnProperty.call(values, match[1])) {
                continue;
            }

            element.appendChild(document.createTextNode(source.slice(last, match.index)));

            const strong = document.createElement('strong');
            strong.textContent = values[match[1]];
            element.appendChild(strong);
            last = match.index + match[0].length;
        }

        element.appendChild(document.createTextNode(source.slice(last)));
    }

    /** Adresin en uzun eslesen kaynak oneki ve yolu; ayni kokende degilse null. */
    function matchTarget(url) {
        let path = '';
        let found = '';

        try {
            const target = new URL(url, window.location.href);

            if (target.origin !== window.location.origin) {
                return null;
            }

            path = target.pathname.replace(/\/+$/, '');
        } catch (ignored) {
            return null;
        }

        Object.keys(targets).forEach(function (prefix) {
            if ((path === prefix || path.indexOf(prefix + '/') === 0) && prefix.length > found.length) {
                found = prefix;
            }
        });

        return found ? { prefix: found, rest: path.slice(found.length) } : null;
    }

    /** Gidilecek kaydin turu: adresin en uzun eslesen kaynak oneki ("/admin/projects/12" => "Proje"). */
    function typeFor(url) {
        const match = matchTarget(url);

        return match ? targets[match.prefix] : '';
    }

    /** Bir kaydin sayfasi mi ("/admin/projects/12", ".../12/edit"); liste ya da olustur degil. */
    function isRecordUrl(url) {
        const match = matchTarget(url);

        return !!match && /^\/\d+(\/[\w-]+)?$/.test(match.rest);
    }

    function outlineIcon(path) {
        const svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.5');
        svg.setAttribute('aria-hidden', 'true');

        const line = document.createElementNS(SVG_NS, 'path');
        line.setAttribute('stroke-linecap', 'round');
        line.setAttribute('stroke-linejoin', 'round');
        line.setAttribute('d', path);
        svg.appendChild(line);

        return svg;
    }

    /** Hedef kaydin tur simgesi (baglantinin icindeki simge) ya da genel simge. */
    function targetIcon(anchor) {
        const found = anchor.querySelector('svg');

        if (!found) {
            return outlineIcon(GO_PATH);
        }

        const svg = found.cloneNode(true);
        svg.removeAttribute('class');
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        svg.setAttribute('aria-hidden', 'true');

        return svg;
    }

    function skippable(anchor, event) {
        const href = anchor.getAttribute('href') || '';

        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return true;
        }

        if (href === '' || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) {
            return true;
        }

        // Dosya kartlari (onizleme yeni sekmede, indirme sayfadan ayrilmaz) pencere acmaz.
        if (anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self') || anchor.closest('[data-no-link-guard], .kc-file-chip')) {
            return true;
        }

        try {
            const target = new URL(anchor.href, window.location.href);

            if (target.origin === window.location.origin && target.pathname === window.location.pathname && target.search === window.location.search) {
                return true;
            }
        } catch (ignored) {
            return true;
        }

        return false;
    }

    /** Tablo hucresinin sutun basligi. */
    function headerFor(cell) {
        const table = cell.closest('table');
        const row = table ? table.querySelector('thead tr') : null;

        if (!row || cell.cellIndex < 0) {
            return '';
        }

        const header = row.children[cell.cellIndex];

        return header ? text(header.textContent) : '';
    }

    // Genel kuralin disinda kalan yerler ve baglantilar: menu, ust cubuk, gezinme
    // satiri, sekmeler, sayfalama, acilir menuler, bildirimler, sayfa basligi
    // eylemleri; dugme / eylem / menu ogesi gorunumlu baglantilar.
    const QUIET_AREAS = '.fi-sidebar, .fi-topbar, .fi-breadcrumbs, .fi-tabs, .fi-pagination, .fi-dropdown-panel, .fi-no, .fi-header, .kw-crumbs, nav, [role="tablist"], [role="menu"]';
    const QUIET_LINKS = '.fi-btn, .fi-icon-btn, .fi-link, [class*="fi-ac-"], [role="menuitem"], [role="tab"]';

    /** Tiklanan baglantinin turu, alan adi, hedef ve (tabloda) satirin detayi. */
    function describe(anchor) {
        // Ozel ekranlarin isaretli kayit baglantilari (data-link-guard="alan adi",
        // istege bagli data-link-guard-name="kayit adi"): her zaman sorulur.
        const marked = anchor.closest('[data-link-guard]');

        if (marked) {
            return {
                kind: 'detail',
                field: marked.getAttribute('data-link-guard') || '',
                target: marked.getAttribute('data-link-guard-name') || targetText(anchor),
                url: anchor.href,
                rowUrl: null,
            };
        }

        const tableCell = anchor.closest('td.fi-ta-cell');

        if (tableCell) {
            if (anchor.closest('.fi-ta-actions') || anchor.matches('.fi-btn, .fi-icon-btn, .fi-link, [class*="fi-ac-"]')) {
                return null;
            }

            const isColumnLink = anchor.classList.contains('fi-ta-col-has-column-url');

            // Satirin kendi baglantisi (satir tiklamasi = detay, D-125): pencere yok.
            if (anchor.classList.contains('fi-ta-col') && !isColumnLink) {
                return null;
            }

            const row = anchor.closest('tr');
            let rowUrl = null;

            if (row) {
                const own = Array.prototype.find.call(row.querySelectorAll('a.fi-ta-col[href]'), function (candidate) {
                    return !candidate.classList.contains('fi-ta-col-has-column-url') && candidate.href !== anchor.href;
                });

                rowUrl = own ? own.href : null;
            }

            return { kind: 'table', field: headerFor(tableCell), target: targetText(anchor), url: anchor.href, rowUrl: rowUrl };
        }

        if (anchor.classList.contains('fi-in-entry-content') || anchor.closest('.fi-in-entry-content')) {
            const entry = anchor.closest('.fi-in-entry');
            const cell = anchor.closest('td');
            let field = cell ? headerFor(cell) : '';

            if (!field && entry) {
                const label = entry.querySelector('.fi-in-entry-label');
                field = label ? text(label.textContent) : '';
            }

            return { kind: 'detail', field: field, target: targetText(anchor), url: anchor.href, rowUrl: null };
        }

        // Sistemin geri kalaninda (15 Ekim 2026 kullanici: "akan alt yazi ekraninda
        // bir route tiklayinca yine ayni mantikta buraya gidiyorsunuz uyari modali
        // acilmali. Tum sistemde gecerlidir."): bir kaydin sayfasina giden her
        // baglanti (kartlar, panolar, is panosu, sohbet...). Kart gorunumunde
        // kartin kendi baglantisi satir tiklamasi gibidir, sormaz.
        if (anchor.closest(QUIET_AREAS) || anchor.matches(QUIET_LINKS)) {
            return null;
        }

        if (anchor.classList.contains('fi-ta-record-content') || (anchor.classList.contains('fi-ta-col') && !anchor.classList.contains('fi-ta-col-has-column-url'))) {
            return null;
        }

        if (!isRecordUrl(anchor.href)) {
            return null;
        }

        return { kind: 'detail', field: '', target: targetText(anchor), url: anchor.href, rowUrl: null };
    }

    /** Dugme yazisi birden fazla satira kaydi mi (satir kutusu sayisi). */
    function wraps(element) {
        const range = document.createRange();
        range.selectNodeContents(element);

        return range.getClientRects().length > 1;
    }

    function button(label, tone, onClick) {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'kc-lg-btn kc-lg-btn-' + tone;
        element.textContent = label;
        element.addEventListener('click', onClick);

        return element;
    }

    function close() {
        if (!open) {
            return;
        }

        open.overlay.remove();
        document.removeEventListener('keydown', open.onKey, true);
        document.documentElement.classList.remove('kc-lg-locked');

        if (open.trigger && typeof open.trigger.focus === 'function') {
            open.trigger.focus({ preventScroll: true });
        }

        open = null;
    }

    function go(url) {
        close();
        window.location.assign(url);
    }

    function show(info, anchor) {
        close();

        const type = typeFor(info.url) || info.field || '';
        const values = { type: type, field: info.field || '', target: info.target || '' };
        const withRow = info.kind === 'table' && !!info.rowUrl;
        let bodyText = labels.body_no_target;

        if (info.target) {
            bodyText = info.field ? labels.body : labels.body_no_field;
        }

        const overlay = document.createElement('div');
        overlay.className = 'kc-lg-overlay';
        overlay.addEventListener('mousedown', function (event) {
            if (event.target === overlay) {
                close();
            }
        });

        const dialog = document.createElement('div');
        dialog.className = 'kc-lg-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'kc-lg-title');
        dialog.setAttribute('aria-describedby', 'kc-lg-body');

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'kc-lg-close';
        dismiss.setAttribute('aria-label', labels.close || 'Kapat');
        dismiss.appendChild(outlineIcon(CLOSE_PATH));
        dismiss.addEventListener('click', close);

        const icon = document.createElement('div');
        icon.className = 'kc-lg-icon';
        icon.appendChild(targetIcon(anchor));

        const title = document.createElement('h2');
        title.id = 'kc-lg-title';
        title.className = 'kc-lg-title';
        title.textContent = type ? format(labels.title_type, values) : (labels.title || '');

        const body = document.createElement('p');
        body.id = 'kc-lg-body';
        body.className = 'kc-lg-body';
        fillBold(body, bodyText, values);

        const actions = document.createElement('div');
        actions.className = 'kc-lg-actions' + (withRow ? ' kc-lg-actions-3' : '');

        const stay = button(labels.stay || 'Sayfada kal', 'stay', close);
        actions.appendChild(stay);

        if (withRow) {
            actions.appendChild(button(labels.row || '', 'row', function () { go(info.rowUrl); }));
        }

        actions.appendChild(button(type ? format(labels.go_type, values) : (labels.go || 'Git'), 'go', function () { go(info.url); }));

        dialog.appendChild(dismiss);
        dialog.appendChild(icon);
        dialog.appendChild(title);
        dialog.appendChild(body);
        dialog.appendChild(actions);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        document.documentElement.classList.add('kc-lg-locked');

        // Yazisi tek satira sigmayan dugme varsa dugmeler alt alta, tam genislikte.
        if (Array.prototype.some.call(actions.children, wraps)) {
            actions.classList.add('kc-lg-actions-stack');
        }

        const onKey = function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
            }
        };

        document.addEventListener('keydown', onKey, true);
        open = { overlay: overlay, onKey: onKey, trigger: anchor };
        stay.focus({ preventScroll: true });
    }

    document.addEventListener('click', function (event) {
        const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;

        if (!anchor || skippable(anchor, event)) {
            return;
        }

        const info = describe(anchor);

        if (!info) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        show(info, anchor);
    }, true);

    window.KonelsisLinkGuard = { close: close };
})();
