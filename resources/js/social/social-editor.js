/*
 * Konelsis Sosyal Medya modulu - ZENGIN METIN DUZENLEYICISI (B31, D-106, 18 Eylul 2026).
 *
 * React 18 (UMD, JSX yok, derleme yok, dis kutuphane yok). social-core.js'ten SONRA yuklenir
 * ve yalniz onun API'sini (window.KonelsisSocial = KS) kullanir. Stiller:
 * resources/css/filament/konelsis-social.css, bolum EDITOR (on ekler ks-editor, ks-rich).
 *
 * Disa acilanlar / exports:
 *   KS.RichEditor({ value, onChange(html), placeholder, onUploadImage(file) -> Promise<url>, minHeight,
 *                   mode: 'blog' | 'article', disabled, label, maxBytes, stickyOffset, className })
 *       - value: sunucudan gelen (temizlenmis) body_html. Duzenleyici icerigi KENDI yonetir; value yalniz
 *         disaridan GERCEKTEN degistiginde (yeniden yukleme, surum geri alma) yeniden kurulur; kendi
 *         yaydigi deger geri geldiginde imlec bozulmaz.
 *       - onChange: temiz HTML (izin listesi sunucudaki SocialHtmlSanitizer ile ayni), 250 ms gecikmeli;
 *         odak kaybinda ve kaldirilirken bekleyen deger hemen yayilir. Govde bossa '' yayilir.
 *       - onUploadImage: verilmezse gorsel dugmesi hic cizilmez. Donus: adres metni ya da
 *         { preview_url | url, alt? } nesnesi; null/undefined = vazgecildi (sessiz). Reddedilen soz
 *         error.aborted | error.cancelled | error.silent tasiyorsa sessiz gecilir, digerleri KS.handleError.
 *         Adres modulun kendi gorsel rotasi olmalidir (.../social/media/<id>/file[?variant=...]).
 *       - mode 'article': tablo araci gizlenir (uzun metin); 'blog': tum araclar.
 *       - minHeight: sayi (px) ya da CSS uzunlugu. stickyOffset: arac cubugunun yapisacagi ust bosluk
 *         (varsayilan 0; kaplama icinde kaydirma kabinin ustu).
 *   KS.RichContent({ html, className, emptyText })
 *       Salt okunur gosterim. dangerouslySetInnerHTML YALNIZ burada kullanilir; sunucunun temizledigi
 *       HTML ayrica ayni izin listesinden gecirilir (ikinci savunma hatti), tablolar kaydirilabilir
 *       kutuya sarilir. Bos govde: emptyText varsa soluk paragraf, yoksa null.
 *   KS.richText = { clean(html) -> temiz HTML | '', isBlank(html), stats(html) -> { words, chars }, imageSrc(url) }
 *
 * Guvenlik (AMENDMENTS H1): yapistirilan / birakilan / disaridan gelen HTML yalniz DOMParser'in etkisiz
 * belgesinde ayristirilir ve dugumler izin listesinden YENIDEN kurulur; guvenilmeyen HTML hicbir
 * elemanin innerHTML'ine yazilmaz. Geri al / yinele kendi gecmis yigini ile calisir (dugum kopyalari).
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, Fragment, t, cx } = KS;
    const { useState, useEffect, useRef, useMemo, useLayoutEffect } = KS;

    /* ================================================================== */
    /* 1. Sabitler                                                         */
    /* ================================================================== */

    const ZERO_WIDTH = /[\u200B\uFEFF]/g;
    const IMAGE_WIDTHS = ['25%', '50%', '100%'];
    const ALT_MAX = 300;
    const HISTORY_LIMIT = 100;
    const MAX_IMAGES_AT_ONCE = 10;
    const GRID_ROWS = 6;
    const GRID_COLS = 6;
    const IS_MAC = /Mac|iPhone|iPad|iPod/i.test(String((window.navigator && (window.navigator.platform || window.navigator.userAgent)) || ''));

    function toSet(names) {
        const map = {};

        names.forEach((name) => {
            map[name] = true;
        });

        return map;
    }

    // Cikista kalabilen etiketler (sunucu listesinin duzgunlestirilmis hali: b -> strong, i -> em, span acilir).
    const ALLOWED = toSet(['P', 'BR', 'H2', 'H3', 'H4', 'STRONG', 'EM', 'U', 'S', 'BLOCKQUOTE', 'UL', 'OL', 'LI', 'A', 'IMG', 'FIGURE', 'FIGCAPTION', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'HR', 'PRE', 'CODE']);
    const RENAMED = { B: 'STRONG', I: 'EM', STRIKE: 'S', DEL: 'S', H1: 'H2', H5: 'H4', H6: 'H4', TFOOT: 'TBODY', MENU: 'UL', DIR: 'UL' };
    // Icerigiyle birlikte atilanlar.
    const DROPPED = toSet(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'NOSCRIPT', 'TEMPLATE', 'SVG', 'MATH', 'HEAD', 'TITLE', 'META', 'LINK', 'BASE', 'INPUT', 'BUTTON', 'SELECT', 'TEXTAREA', 'OPTION', 'OPTGROUP', 'DATALIST', 'VIDEO', 'AUDIO', 'SOURCE', 'TRACK', 'CANVAS', 'APPLET', 'FRAME', 'FRAMESET', 'COLGROUP', 'COL', 'CAPTION', 'MAP', 'AREA', 'DIALOG', 'PROGRESS', 'METER']);
    // Blok sarmalayicilar: blok cocugu yoksa paragrafa doner, varsa acilir.
    const WRAPPERS = toSet(['DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'MAIN', 'ASIDE', 'NAV', 'ADDRESS', 'DT', 'DD', 'DL', 'CENTER', 'DETAILS', 'SUMMARY', 'FORM', 'FIELDSET']);
    const BLOCKS = toSet(['P', 'H2', 'H3', 'H4', 'BLOCKQUOTE', 'UL', 'OL', 'LI', 'FIGURE', 'FIGCAPTION', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'HR', 'PRE']);
    const MARKS = toSet(['STRONG', 'EM', 'U', 'S', 'CODE']);
    // Yalniz satir ici icerik tasiyabilen bloklar.
    const INLINE_HOSTS = toSet(['P', 'H2', 'H3', 'H4', 'PRE', 'FIGCAPTION']);
    const FLOW_HOSTS = toSet(['BLOCKQUOTE', 'LI', 'TD', 'TH', 'FIGURE']);
    const VOID_TAGS = toSet(['br', 'hr', 'img']);
    const OUTPUT_ATTRS = { a: ['href', 'target', 'rel'], img: ['src', 'alt', 'width'], td: ['colspan', 'rowspan'], th: ['colspan', 'rowspan'] };

    // Canli duzenleme yuzeyinde karsilasilabilen bloklar (tarayici div / h1 uretebilir).
    const LIVE_BLOCKS = toSet(['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'UL', 'OL', 'LI', 'FIGURE', 'FIGCAPTION', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TH', 'TD', 'HR', 'PRE']);
    const LIVE_TEXT_BLOCKS = toSet(['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'PRE']);
    const LIVE_FLOW_HOSTS = toSet(['LI', 'TD', 'TH', 'BLOCKQUOTE']);
    const CELLS = toSet(['TD', 'TH']);
    const NEEDS_TRAILING = toSet(['TABLE', 'PRE', 'HR', 'FIGURE', 'BLOCKQUOTE']);
    const SPACED_BLOCKS = toSet(['TABLE', 'PRE', 'HR', 'FIGURE', 'BLOCKQUOTE']);
    const LIFT_HOSTS = toSet(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6']);
    const BLOCK_VALUES = { P: 'p', H2: 'h2', H3: 'h3', H4: 'h4', PRE: 'pre' };
    const TEXT_BLOCK_SELECTOR = 'p,h1,h2,h3,h4,h5,h6,pre,div';
    const NESTED_BLOCK_SELECTOR = 'p,h1,h2,h3,h4,h5,h6,pre,div,ul,ol,table,blockquote';

    const EMPTY_ACTIVE = { block: 'p', bold: false, italic: false, underline: false, strike: false, code: false, ul: false, ol: false, quote: false, link: false, table: false, tableHeader: false, inHeader: false };
    const EMPTY_LINK = { href: '', text: '', hasLink: false, collapsed: true };

    /* ================================================================== */
    /* 2. Temizleme: izin listesinden yeniden kurma                        */
    /* ================================================================== */

    let inertDocument = null;

    /** Etkisiz belge: burada kurulan <img> indirme baslatmaz; canli DOM'a eklenince benimsenir. */
    function inert() {
        if (!inertDocument) {
            inertDocument = document.implementation.createHTMLDocument('');
        }

        return inertDocument;
    }

    function slice(list) {
        return Array.prototype.slice.call(list || []);
    }

    function escapeRegExp(text) {
        return text.replace(/[.*+?^${}()|[\]\\\/]/g, '\\$&');
    }

    let imagePatternCache = null;

    /** Sunucudaki SocialHtmlSanitizer::imagePathPattern ile ayni: modulun gorsel rotasi + istege bagli ?variant=. */
    function imagePattern() {
        if (imagePatternCache) {
            return imagePatternCache;
        }

        const query = '(?:\\?variant=(?:original|thumbnail|preview))?$';
        let source = '^/(?:[A-Za-z0-9_\\-]+/)*social/media/[0-9]{1,18}/file' + query;

        if (KS.hasEndpoint('media.file')) {
            const sample = String(KS.url('media.file', 987654321) || '').split('?')[0];

            if (sample.charAt(0) === '/' && sample.charAt(1) !== '/' && sample.indexOf('987654321') !== -1) {
                source = '^' + escapeRegExp(sample).replace('987654321', '[0-9]{1,18}') + query;
            }
        }

        imagePatternCache = new RegExp(source);

        return imagePatternCache;
    }

    /** Gorsel kaynagi: ayni sunucunun tam adresi yola cevrilir; modul rotasina uymayan her sey (dis, data:, //) null. */
    function imageSrc(raw) {
        let value = String(raw === undefined || raw === null ? '' : raw).trim();

        if (value === '') {
            return null;
        }

        const origin = window.location && window.location.origin ? String(window.location.origin) : '';

        if (origin && value.length > origin.length && value.slice(0, origin.length + 1).toLowerCase() === origin.toLowerCase() + '/') {
            value = value.slice(origin.length);
        }

        if (!imagePattern().test(value)) {
            return null;
        }

        return KS.safeUrl(value);
    }

    /** Baglanti: yalniz http / https / mailto. */
    function cleanHref(raw) {
        const value = String(raw === undefined || raw === null ? '' : raw).trim();

        if (/^https?:\/\//i.test(value)) {
            return KS.safeUrl(value);
        }

        if (/^mailto:[^\s<>"'\\]+@[^\s<>"'\\]+$/i.test(value)) {
            return value;
        }

        return null;
    }

    /** Kullanicinin yazdigi adresi tamamlar: alan adi -> https://, e-posta -> mailto:. Gecersizse null. */
    function normalizeLink(raw) {
        const value = String(raw || '').trim();

        if (value === '' || /\s/.test(value)) {
            return null;
        }

        if (/^mailto:/i.test(value)) {
            return cleanHref(value);
        }

        if (/^[^\s@\/:]+@[^\s@\/:]+\.[^\s@\/:]+$/.test(value)) {
            return cleanHref('mailto:' + value);
        }

        let candidate = value;

        if (!/^https?:\/\//i.test(candidate)) {
            // "javascript:", "ftp:" gibi baska semalar reddedilir; "ornek.com:8080" (port) sema sayilmaz.
            if (/^[a-z][a-z0-9+.\-]*:(?!\d)/i.test(candidate) || !/^[^\s\/]+\.[^\s\/.]+/.test(candidate)) {
                return null;
            }

            candidate = 'https://' + candidate.replace(/^\/+/, '');
        }

        try {
            const parsed = new URL(candidate);

            if ((parsed.protocol !== 'http:' && parsed.protocol !== 'https:') || parsed.hostname === '') {
                return null;
            }
        } catch (error) {
            return null;
        }

        return cleanHref(candidate);
    }

    function cleanAlt(raw) {
        return String(raw === undefined || raw === null ? '' : raw).replace(/\s+/g, ' ').trim().slice(0, ALT_MAX);
    }

    function copySpan(source, target, attribute) {
        const value = String(source.getAttribute(attribute) || '').trim();

        if (/^[1-9][0-9]?$/.test(value) && value !== '1') {
            target.setAttribute(attribute, value);
        }
    }

    function hasBlockChild(node) {
        for (let child = node.firstChild; child; child = child.nextSibling) {
            if (child.nodeType === 1) {
                const raw = child.nodeName.toUpperCase();
                const name = RENAMED[raw] || raw;

                if (BLOCKS[name] || WRAPPERS[name]) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Yapistirilan span / font bicimini (kalin, italik, alti / ustu cizili) anlamli etiketlere cevirir. */
    function styleMarks(node, ctx) {
        const style = node.style;
        const marks = [];

        if (!style) {
            return marks;
        }

        const weight = String(style.fontWeight || '');

        if (!ctx.heading && (weight === 'bold' || weight === 'bolder' || parseInt(weight, 10) >= 600)) {
            marks.push('STRONG');
        }

        if (style.fontStyle === 'italic' || style.fontStyle === 'oblique') {
            marks.push('EM');
        }

        const line = String(style.textDecorationLine || style.textDecoration || '');

        if (line.indexOf('underline') !== -1 && !ctx.link) {
            marks.push('U');
        }

        if (line.indexOf('line-through') !== -1) {
            marks.push('S');
        }

        return marks.filter((name) => !ctx.marks[name]);
    }

    function cleanImage(node, doc) {
        const src = imageSrc(node.getAttribute('src'));

        if (!src) {
            return null;
        }

        const img = doc.createElement('img');
        const width = String(node.getAttribute('width') || '').trim();

        img.setAttribute('src', src);
        img.setAttribute('alt', cleanAlt(node.getAttribute('alt')));

        if (IMAGE_WIDTHS.indexOf(width) !== -1) {
            img.setAttribute('width', width);
        }

        return img;
    }

    /** Blok sonundaki duz bosluklar gorunmez (anlamli olanlar &nbsp; olarak gelir); cikista atilir. */
    function trimTrailingSpace(element) {
        let last = element.lastChild;

        while (last && last.nodeType === 3) {
            const trimmed = last.nodeValue.replace(/[ \t\r\n]+$/, '');

            if (trimmed === last.nodeValue) {
                return;
            }

            if (trimmed !== '') {
                last.nodeValue = trimmed;

                return;
            }

            const previous = last.previousSibling;

            element.removeChild(last);
            last = previous;
        }
    }

    function cleanChildren(source, target, ctx) {
        for (let child = source.firstChild; child; child = child.nextSibling) {
            cleanNode(child, target, ctx);
        }
    }

    /** Kaynak dugumu (DOMParser belgesi ya da canli yuzey) izin listesine gore hedefe YENIDEN kurar. */
    function cleanNode(node, target, ctx) {
        const doc = target.ownerDocument;

        if (node.nodeType === 3) {
            let text = node.nodeValue.replace(ZERO_WIDTH, '');

            if (!ctx.pre) {
                text = text.replace(/[ \t\r\n]+/g, ' ');
            }

            if (text !== '') {
                target.appendChild(doc.createTextNode(text));
            }

            return;
        }

        if (node.nodeType !== 1) {
            return;
        }

        const raw = node.nodeName.toUpperCase();

        if (DROPPED[raw]) {
            return;
        }

        if (raw === 'BR') {
            target.appendChild(doc.createElement('br'));

            return;
        }

        if (raw === 'IMG') {
            const img = cleanImage(node, doc);

            if (img) {
                target.appendChild(img);
            }

            return;
        }

        if (raw === 'HR') {
            if (!ctx.inlineOnly) {
                target.appendChild(doc.createElement('hr'));
            }

            return;
        }

        let name = RENAMED[raw] || raw;
        let marks = [];
        let fromWrapper = false;

        // Satir ici baglamda (p, baslik, pre ici) bloklar duzlestirilir.
        if (ctx.inlineOnly && (BLOCKS[name] || WRAPPERS[name])) {
            cleanChildren(node, target, ctx);
            target.appendChild(doc.createTextNode(ctx.pre ? '\n' : ' '));

            return;
        }

        if (WRAPPERS[name]) {
            name = hasBlockChild(node) ? '' : 'P';
            fromWrapper = name === 'P';
        }

        if (raw === 'SPAN' || raw === 'FONT') {
            marks = ctx.inlineOnly || !hasBlockChild(node) ? styleMarks(node, ctx) : [];
            name = '';
        }

        // Google Docs: <b style="font-weight:normal"> sarmalayicisi kalin degildir.
        if (raw === 'B' && /^(normal|[1-4]00)$/.test(String((node.style && node.style.fontWeight) || ''))) {
            name = '';
        }

        if ((MARKS[name] || name === 'A') && !ctx.inlineOnly && hasBlockChild(node)) {
            name = '';
        }

        if (MARKS[name] && ctx.marks[name]) {
            name = '';
        }

        let href = null;

        if (name === 'A') {
            href = ctx.link ? null : cleanHref(node.getAttribute('href'));

            if (!href) {
                name = '';
            }
        }

        if (name !== '' && !ALLOWED[name]) {
            name = '';
        }

        if (name === '') {
            let inner = target;
            let innerCtx = ctx;

            if (marks.length) {
                innerCtx = Object.assign({}, ctx, { marks: Object.assign({}, ctx.marks) });

                marks.forEach((mark) => {
                    const wrapper = doc.createElement(mark.toLowerCase());

                    inner.appendChild(wrapper);
                    inner = wrapper;
                    innerCtx.marks[mark] = true;
                });
            }

            cleanChildren(node, inner, innerCtx);

            return;
        }

        // Tablo basligi (caption) tablodan once paragraf olur.
        if (name === 'TABLE') {
            for (let child = node.firstChild; child; child = child.nextSibling) {
                if (child.nodeType === 1 && child.nodeName.toUpperCase() === 'CAPTION') {
                    const caption = doc.createElement('p');

                    cleanChildren(child, caption, Object.assign({}, ctx, { inlineOnly: true }));

                    if (caption.textContent.trim() !== '') {
                        target.appendChild(caption);
                    }
                }
            }
        }

        const element = doc.createElement(name.toLowerCase());
        const next = Object.assign({}, ctx);

        if (name === 'A') {
            element.setAttribute('href', href);
            element.setAttribute('target', '_blank');
            element.setAttribute('rel', 'noopener noreferrer');
            next.link = true;
        }

        if (MARKS[name]) {
            next.marks = Object.assign({}, ctx.marks);
            next.marks[name] = true;
        }

        if (CELLS[name]) {
            copySpan(node, element, 'colspan');
            copySpan(node, element, 'rowspan');
        }

        if (INLINE_HOSTS[name]) {
            next.inlineOnly = true;
            next.heading = name === 'H2' || name === 'H3' || name === 'H4';

            if (name === 'PRE') {
                next.pre = true;
            }
        }

        cleanChildren(node, element, next);

        if (INLINE_HOSTS[name] && name !== 'PRE') {
            trimTrailingSpace(element);
        }

        // Bosluk amacli bos sarmalayicilar (<div></div>) paragraf uretmez; <div><br></div> bos satir olarak kalir.
        if (fromWrapper && !element.firstChild) {
            return;
        }

        target.appendChild(element);
    }

    /* --- Yapi duzeltme ------------------------------------------------- */

    function isSpaceText(node) {
        return node.nodeType === 3 && /^[ \t\r\n]*$/.test(node.nodeValue);
    }

    function isBlockNode(node) {
        return node.nodeType === 1 && !!BLOCKS[node.nodeName];
    }

    function isLiveBlock(node) {
        return !!node && node.nodeType === 1 && !!LIVE_BLOCKS[node.nodeName];
    }

    function isEmptyBlock(element) {
        return element.textContent.replace(/[\s\u200B]/g, '') === '' && !element.querySelector('img,hr,table');
    }

    /** Elemani kaldirir, cocuklarini yerine birakir. memo verilirse secim noktasi son tasinan dugume kaydirilir. */
    function unwrapNode(element, memo) {
        const parent = element.parentNode;

        if (!parent) {
            return;
        }

        let last = null;

        while (element.firstChild) {
            last = element.firstChild;
            parent.insertBefore(last, element);
        }

        if (memo) {
            memo.swaps.push([element, last || parent]);
        }

        parent.removeChild(element);
    }

    /** Elemani baska etiketle degistirir (cocuklar tasinir). */
    function renameNode(element, tagName, memo) {
        const fresh = element.ownerDocument.createElement(tagName);

        while (element.firstChild) {
            fresh.appendChild(element.firstChild);
        }

        element.parentNode.replaceChild(fresh, element);

        if (memo) {
            memo.swaps.push([element, fresh]);
        }

        return fresh;
    }

    /** Kap icindeki ardisik satir ici dugumleri tagName ile sarar; yalniz bosluktan olusan kosular atilir. */
    function wrapRuns(container, tagName, blockTest, keepNode) {
        const doc = container.ownerDocument;
        let run = [];
        let changed = false;

        const flush = () => {
            if (!run.length) {
                return;
            }

            const meaningful = run.some((node) => !isSpaceText(node) || node === keepNode);

            changed = true;

            if (meaningful) {
                const wrapper = doc.createElement(tagName);

                container.insertBefore(wrapper, run[0]);
                run.forEach((node) => wrapper.appendChild(node));

                while (wrapper.firstChild && wrapper.firstChild !== keepNode && isSpaceText(wrapper.firstChild) && wrapper.childNodes.length > 1) {
                    wrapper.removeChild(wrapper.firstChild);
                }

                while (wrapper.lastChild && wrapper.lastChild !== keepNode && isSpaceText(wrapper.lastChild) && wrapper.childNodes.length > 1) {
                    wrapper.removeChild(wrapper.lastChild);
                }
            } else {
                run.forEach((node) => container.removeChild(node));
            }

            run = [];
        };

        slice(container.childNodes).forEach((node) => {
            if (blockTest(node)) {
                flush();
            } else if (node.nodeType === 1 || node.nodeType === 3) {
                run.push(node);
            } else {
                container.removeChild(node);
            }
        });

        flush();

        return changed;
    }

    function wrapOrphanItems(container) {
        const doc = container.ownerDocument;
        let list = null;

        slice(container.childNodes).forEach((node) => {
            if (node.nodeType === 1 && node.nodeName === 'LI') {
                if (!list) {
                    list = doc.createElement('ul');
                    container.insertBefore(list, node);
                }

                list.appendChild(node);
            } else if (!isSpaceText(node)) {
                list = null;
            }
        });
    }

    function fixList(list) {
        const doc = list.ownerDocument;
        let item = null;
        let filler = null;

        slice(list.childNodes).forEach((node) => {
            if (node.nodeType === 1 && node.nodeName === 'LI') {
                item = node;
                filler = null;

                return;
            }

            if (isSpaceText(node)) {
                list.removeChild(node);

                return;
            }

            if (node.nodeType === 1 && (node.nodeName === 'UL' || node.nodeName === 'OL') && item) {
                item.appendChild(node);

                return;
            }

            if (!filler) {
                filler = doc.createElement('li');
                list.insertBefore(filler, node);
                item = filler;
            }

            filler.appendChild(node);
        });
    }

    function fixTable(table) {
        const doc = table.ownerDocument;
        let body = null;

        slice(table.childNodes).forEach((node) => {
            const name = node.nodeType === 1 ? node.nodeName : '';

            if (name === 'THEAD' || name === 'TBODY') {
                body = null;

                return;
            }

            if (name === 'TR') {
                if (!body) {
                    body = doc.createElement('tbody');
                    table.insertBefore(body, node);
                }

                body.appendChild(node);

                return;
            }

            table.removeChild(node);
        });
    }

    function fixSection(section) {
        slice(section.childNodes).forEach((node) => {
            if (!(node.nodeType === 1 && node.nodeName === 'TR')) {
                section.removeChild(node);
            }
        });
    }

    function fixRow(row) {
        const doc = row.ownerDocument;
        let cell = null;

        slice(row.childNodes).forEach((node) => {
            if (node.nodeType === 1 && CELLS[node.nodeName]) {
                cell = null;

                return;
            }

            if (isSpaceText(node)) {
                row.removeChild(node);

                return;
            }

            if (!cell) {
                cell = doc.createElement('td');
                row.insertBefore(cell, node);
            }

            cell.appendChild(node);
        });
    }

    function structure(container, isRoot, wrapRoot) {
        const name = isRoot ? '' : container.nodeName;

        if (name === 'UL' || name === 'OL') {
            fixList(container);
        } else if (name === 'TABLE') {
            fixTable(container);
        } else if (name === 'THEAD' || name === 'TBODY') {
            fixSection(container);
        } else if (name === 'TR') {
            fixRow(container);
        } else if (isRoot || FLOW_HOSTS[name]) {
            wrapOrphanItems(container);

            const blocky = Array.prototype.some.call(container.childNodes, isBlockNode);

            if ((isRoot && wrapRoot === 'always') || ((isRoot || name === 'BLOCKQUOTE') && blocky)) {
                wrapRuns(container, 'p', isBlockNode, null);
            }
        }

        slice(container.children).forEach((child) => structure(child, false, wrapRoot));

        slice(container.children).forEach((child) => {
            const childName = child.nodeName;
            const hollow = ((childName === 'UL' || childName === 'OL' || childName === 'THEAD' || childName === 'TBODY' || childName === 'TR') && !child.children.length)
                || (childName === 'TABLE' && !child.querySelector('td,th'));

            if (hollow) {
                container.removeChild(child);
            }
        });
    }

    function pruneMarks(tree) {
        const marks = tree.querySelectorAll('strong,em,u,s,code,a');

        for (let index = marks.length - 1; index >= 0; index -= 1) {
            const mark = marks[index];

            if (mark.textContent.replace(ZERO_WIDTH, '') === '' && !mark.querySelector('img')) {
                unwrapNode(mark, null);
            }
        }
    }

    /** Kaynagin temiz kopyasini etkisiz belgede kurar. wrapRoot: 'always' (govde) | 'auto' (yapistirma). */
    function buildTree(source, wrapRoot) {
        const box = inert().createElement('div');

        if (source) {
            cleanChildren(source, box, { pre: false, link: false, inlineOnly: false, heading: false, marks: {} });
        }

        structure(box, true, wrapRoot);
        pruneMarks(box);

        return box;
    }

    /** Duzenleme icin: bos bloklara imlec konabilsin diye <br> eklenir. */
    function padEmptyBlocks(box) {
        const list = box.querySelectorAll('p,h2,h3,h4,li,td,th,pre,figcaption,blockquote');

        for (let index = 0; index < list.length; index += 1) {
            if (!list[index].firstChild) {
                list[index].appendChild(list[index].ownerDocument.createElement('br'));
            }
        }
    }

    /** Cikis oncesi: bos basliklar paragrafa doner, bas ve sondaki bos paragraflar atilir. Donus: govde bos mu. */
    function finalize(tree) {
        const headings = tree.querySelectorAll('h2,h3,h4');

        for (let index = 0; index < headings.length; index += 1) {
            if (isEmptyBlock(headings[index])) {
                renameNode(headings[index], 'p', null);
            }
        }

        const trimmable = (node) => (node.nodeType === 3 ? isSpaceText(node) : (node.nodeType === 1 && node.nodeName === 'P' && isEmptyBlock(node)));

        while (tree.firstChild && trimmable(tree.firstChild)) {
            tree.removeChild(tree.firstChild);
        }

        while (tree.lastChild && trimmable(tree.lastChild)) {
            tree.removeChild(tree.lastChild);
        }

        // Tablo, cizgi, kod blogu gibi bloklara bitisik bos paragraflar yalniz imlec icindir; ciktida yer almaz.
        slice(tree.children).forEach((child) => {
            if (child.nodeName !== 'P' || !isEmptyBlock(child)) {
                return;
            }

            const before = child.previousElementSibling;
            const after = child.nextElementSibling;

            if ((before && SPACED_BLOCKS[before.nodeName]) || (after && SPACED_BLOCKS[after.nodeName])) {
                tree.removeChild(child);
            }
        });

        const paragraphs = tree.querySelectorAll('p');

        for (let index = 0; index < paragraphs.length; index += 1) {
            if (!paragraphs[index].firstChild) {
                paragraphs[index].appendChild(paragraphs[index].ownerDocument.createElement('br'));
            }
        }

        // Sunucudaki isBlank ile ayni: gorunur metin de gorsel de yoksa bos.
        return tree.textContent.replace(/[\s\u200B]/g, '') === '' && !tree.querySelector('img');
    }

    function escapeText(text) {
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\u00A0/g, '&nbsp;');
    }

    function escapeAttr(text) {
        return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\u00A0/g, '&nbsp;');
    }

    function writeNode(node, out, options) {
        if (node.nodeType === 3) {
            out.push(escapeText(node.nodeValue));

            return;
        }

        if (node.nodeType !== 1) {
            return;
        }

        const tag = node.nodeName.toLowerCase();
        const wrap = !!(options && options.wrapTables && tag === 'table');
        let open = '<' + tag;

        (OUTPUT_ATTRS[tag] || []).forEach((name) => {
            if (node.hasAttribute(name)) {
                open += ' ' + name + '="' + escapeAttr(node.getAttribute(name)) + '"';
            }
        });

        if (wrap) {
            out.push('<div class="ks-rich__scroll">');
        }

        out.push(open + '>');

        if (!VOID_TAGS[tag]) {
            // HTML ayristirici <pre> hemen ardindaki ilk satir sonunu yutar; korunmasi icin bir tane eklenir.
            if (tag === 'pre' && node.firstChild && node.firstChild.nodeType === 3 && node.firstChild.nodeValue.charAt(0) === '\n') {
                out.push('\n');
            }

            for (let child = node.firstChild; child; child = child.nextSibling) {
                writeNode(child, out, options);
            }

            out.push('</' + tag + '>');
        }

        if (wrap) {
            out.push('</div>');
        }
    }

    function toHtml(tree, options) {
        const out = [];

        for (let child = tree.firstChild; child; child = child.nextSibling) {
            writeNode(child, out, options);
        }

        return out.join('');
    }

    function parseBody(html) {
        if (html === undefined || html === null || String(html).trim() === '') {
            return null;
        }

        try {
            const parsed = new DOMParser().parseFromString(String(html), 'text/html');

            return parsed && parsed.body ? parsed.body : null;
        } catch (error) {
            return null;
        }
    }

    /** HTML metni -> izin listesinden gecmis HTML metni ('' = bos govde). */
    function cleanHtml(html, options) {
        const tree = buildTree(parseBody(html), 'always');

        return finalize(tree) ? '' : toHtml(tree, options);
    }

    /** Dugum agacinin duz metni: bloklar ve <br> satir sonu olur (sayac icin). */
    function plainText(rootNode) {
        const out = [];

        const walk = (node) => {
            if (node.nodeType === 3) {
                out.push(node.nodeValue);

                return;
            }

            if (node.nodeType !== 1) {
                return;
            }

            if (node.nodeName === 'BR') {
                out.push('\n');

                return;
            }

            for (let child = node.firstChild; child; child = child.nextSibling) {
                walk(child);
            }

            if (LIVE_BLOCKS[node.nodeName]) {
                out.push('\n');
            }
        };

        walk(rootNode);

        return out.join('').replace(ZERO_WIDTH, '').replace(/\u00A0/g, ' ');
    }

    function textStats(text) {
        const trimmed = String(text || '').trim();

        return {
            words: trimmed === '' ? 0 : trimmed.split(/\s+/).length,
            chars: Array.from(String(text || '').replace(/[\r\n]+/g, '')).length,
        };
    }

    function byteLength(text) {
        try {
            return new Blob([text]).size;
        } catch (error) {
            return String(text).length;
        }
    }

    function quietFocus(element) {
        if (!element || typeof element.focus !== 'function') {
            return;
        }

        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    }

    function emptyParagraph() {
        const paragraph = document.createElement('p');

        paragraph.appendChild(document.createElement('br'));

        return paragraph;
    }

    function newCell(tagName) {
        const cell = document.createElement(tagName);

        cell.appendChild(document.createElement('br'));

        return cell;
    }

    function spanOf(cell) {
        return Math.max(1, parseInt(cell.getAttribute('colspan') || '1', 10) || 1);
    }

    function colStart(cell) {
        let index = 0;

        for (let item = cell.previousElementSibling; item; item = item.previousElementSibling) {
            index += spanOf(item);
        }

        return index;
    }

    function cellAt(row, column) {
        let index = 0;

        for (let position = 0; position < row.cells.length; position += 1) {
            const cell = row.cells[position];
            const span = spanOf(cell);

            if (column < index + span) {
                return { cell, start: index, span };
            }

            index += span;
        }

        return null;
    }

    function gridWidth(row) {
        let width = 0;

        for (let position = 0; position < row.cells.length; position += 1) {
            width += spanOf(row.cells[position]);
        }

        return Math.max(1, width);
    }

    function setLinkAttributes(anchor, href) {
        if (href) {
            anchor.setAttribute('href', href);
        }

        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
    }

    /* ================================================================== */
    /* 3. Duzenleme motoru (React'ten bagimsiz DOM mantigi)                */
    /* ================================================================== */

    /**
     * createEngine({ change(), active(state), history({ undo, redo }) })
     * Yuzey (contentEditable) uzerindeki butun islemler: secim, gecmis, bicim, tablo, yapistirma.
     */
    function createEngine(notify) {
        let root = null;
        let saved = null;
        const history = { stack: [], index: -1, timer: 0, pending: false };

        /* --- Secim ----------------------------------------------------- */

        function currentRange() {
            const selection = window.getSelection ? window.getSelection() : null;

            if (!root || !selection || selection.rangeCount === 0) {
                return null;
            }

            const range = selection.getRangeAt(0);

            return root.contains(range.commonAncestorContainer) ? range : null;
        }

        function setRange(range) {
            const selection = window.getSelection ? window.getSelection() : null;

            if (!selection) {
                return;
            }

            selection.removeAllRanges();
            selection.addRange(range);
            saved = range.cloneRange();
        }

        function caretTo(node, atStart) {
            const range = document.createRange();

            range.selectNodeContents(node);
            range.collapse(!!atStart);
            setRange(range);
        }

        function caretAtEnd(node) {
            const range = document.createRange();

            if (node.nodeType === 3) {
                range.setStart(node, node.nodeValue.length);
            } else if (node.nodeName === 'BR' || node.nodeName === 'IMG' || node.nodeName === 'HR') {
                range.setStartAfter(node);
            } else {
                range.selectNodeContents(node);
                range.collapse(false);
            }

            range.collapse(true);
            setRange(range);
        }

        function rangeAlive(range) {
            return !!range && !!root && root.contains(range.startContainer) && root.contains(range.endContainer);
        }

        function focusRestore() {
            if (!root) {
                return;
            }

            const live = currentRange();

            quietFocus(root);

            if (live) {
                return;
            }

            if (rangeAlive(saved)) {
                setRange(saved);
            } else {
                caretTo(root.lastElementChild && LIVE_TEXT_BLOCKS[root.lastElementChild.nodeName] ? root.lastElementChild : root, false);
            }
        }

        function closestIn(node, names) {
            let current = node;

            while (current && current !== root) {
                if (current.nodeType === 1 && names[current.nodeName]) {
                    return current;
                }

                current = current.parentNode;
            }

            return null;
        }

        /** Secimi dugum referanslariyla saklar; DOM tasimalarindan sonra restoreMemo ile geri kurulur. */
        function memoSelection() {
            const range = currentRange();

            if (!range) {
                return null;
            }

            const point = (container, offset) => (container.nodeType === 3
                ? { node: container, offset }
                : { parent: container, before: container.childNodes[offset] || null });

            return { start: point(range.startContainer, range.startOffset), end: point(range.endContainer, range.endOffset), swaps: [] };
        }

        function resolvePoint(memo, point) {
            if (point.node) {
                return root.contains(point.node) ? [point.node, Math.min(point.offset, point.node.nodeValue.length)] : null;
            }

            if (point.before && root.contains(point.before)) {
                return [point.before.parentNode, Array.prototype.indexOf.call(point.before.parentNode.childNodes, point.before)];
            }

            let parent = point.parent;

            memo.swaps.forEach((pair) => {
                if (parent === pair[0]) {
                    parent = pair[1];
                }
            });

            if (parent && (parent === root || root.contains(parent))) {
                return parent.nodeType === 3 ? [parent, parent.nodeValue.length] : [parent, parent.childNodes.length];
            }

            return null;
        }

        function restoreMemo(memo) {
            if (!memo) {
                return;
            }

            const start = resolvePoint(memo, memo.start);
            const end = resolvePoint(memo, memo.end);

            if (!start) {
                return;
            }

            try {
                const range = document.createRange();

                range.setStart(start[0], start[1]);

                if (end) {
                    range.setEnd(end[0], end[1]);
                } else {
                    range.collapse(true);
                }

                setRange(range);
            } catch (error) {
                // Nokta gecersizse secim oldugu gibi birakilir.
            }
        }

        /* --- Gecmis (geri al / yinele) ---------------------------------- */

        function pathOf(node) {
            const path = [];
            let current = node;

            while (current && current !== root) {
                const parent = current.parentNode;

                if (!parent) {
                    return null;
                }

                path.unshift(Array.prototype.indexOf.call(parent.childNodes, current));
                current = parent;
            }

            return current === root ? path : null;
        }

        function nodeAt(path) {
            let current = root;

            for (let index = 0; index < path.length; index += 1) {
                current = current.childNodes[path[index]];

                if (!current) {
                    return null;
                }
            }

            return current;
        }

        function selectionPaths() {
            const live = currentRange();
            const range = live || (rangeAlive(saved) ? saved : null);

            if (!range) {
                return null;
            }

            const startPath = pathOf(range.startContainer);
            const endPath = pathOf(range.endContainer);

            return startPath && endPath ? { startPath, startOffset: range.startOffset, endPath, endOffset: range.endOffset } : null;
        }

        function publishHistory() {
            notify.history({ undo: history.index > 0 || history.pending, redo: history.index < history.stack.length - 1 && !history.pending });
        }

        function commit() {
            window.clearTimeout(history.timer);
            history.pending = false;

            if (!root) {
                return;
            }

            const html = root.innerHTML;
            const top = history.stack[history.index];

            if (top && top.html === html) {
                top.selection = selectionPaths() || top.selection;
                publishHistory();

                return;
            }

            const store = inert();

            history.stack = history.stack.slice(0, history.index + 1);
            history.stack.push({ html, nodes: slice(root.childNodes).map((node) => store.importNode(node, true)), selection: selectionPaths() });

            if (history.stack.length > HISTORY_LIMIT) {
                history.stack.shift();
            }

            history.index = history.stack.length - 1;
            publishHistory();
        }

        function restoreEntry(entry) {
            while (root.firstChild) {
                root.removeChild(root.firstChild);
            }

            entry.nodes.forEach((node) => root.appendChild(document.importNode(node, true)));
            quietFocus(root);

            const selection = entry.selection;
            const startNode = selection ? nodeAt(selection.startPath) : null;
            const endNode = selection ? nodeAt(selection.endPath) : null;
            const limit = (node, offset) => Math.min(offset, node.nodeType === 3 ? node.nodeValue.length : node.childNodes.length);

            if (startNode && endNode) {
                try {
                    const range = document.createRange();

                    range.setStart(startNode, limit(startNode, selection.startOffset));
                    range.setEnd(endNode, limit(endNode, selection.endOffset));
                    setRange(range);

                    return;
                } catch (error) {
                    // Asagidaki varsayilana dusulur.
                }
            }

            caretTo(root.lastElementChild || root, false);
        }

        function stepHistory(delta) {
            if (!root) {
                return;
            }

            commit();

            const target = history.index + delta;

            if (target < 0 || target > history.stack.length - 1) {
                return;
            }

            history.index = target;
            restoreEntry(history.stack[target]);
            publishHistory();
            notify.change();
            publishActive();
        }

        /* --- Yapi bakimi ------------------------------------------------ */

        function hasLiveBlockChild(element) {
            return Array.prototype.some.call(element.childNodes, isLiveBlock);
        }

        /**
         * Chrome liste komutunda <p><ul>...</ul></p> uretebilir; paragraf / baslik icinde kalan bloklar
         * (liste, tablo, alinti, kod, cizgi) disari cikarilir, ardindaki icerik yeni bir bloga tasinir.
         */
        function liftNestedBlocks(memo) {
            let changed = false;

            slice(root.querySelectorAll('ul,ol,table,blockquote,pre,hr')).forEach((block) => {
                const host = block.parentNode;

                if (!host || host === root || !LIFT_HOSTS[host.nodeName] || !host.parentNode) {
                    return;
                }

                const rest = document.createElement(host.nodeName.toLowerCase());

                changed = true;

                while (block.nextSibling) {
                    rest.appendChild(block.nextSibling);
                }

                host.parentNode.insertBefore(block, host.nextSibling);

                if (!isEmptyBlock(rest)) {
                    host.parentNode.insertBefore(rest, block.nextSibling);
                }

                if (isEmptyBlock(host)) {
                    if (memo) {
                        memo.swaps.push([host, block]);
                    }

                    host.parentNode.removeChild(host);
                }
            });

            return changed;
        }

        /** Kok duzeyindeki basibos metin / satir ici dugumler paragrafa, div'ler paragrafa cevrilir. */
        function normalizeRoot() {
            if (!root) {
                return;
            }

            const range = currentRange();
            const keepNode = range && range.startContainer.nodeType === 3 && range.startContainer.parentNode === root ? range.startContainer : null;
            const memo = memoSelection();
            let changed = liftNestedBlocks(memo);

            for (let pass = 0; pass < 3; pass += 1) {
                let dirty = wrapRuns(root, 'p', isLiveBlock, keepNode);

                slice(root.children).forEach((child) => {
                    if (child.nodeName !== 'DIV') {
                        return;
                    }

                    dirty = true;

                    if (hasLiveBlockChild(child)) {
                        unwrapNode(child, memo);
                    } else {
                        renameNode(child, 'p', memo);
                    }
                });

                if (!dirty) {
                    break;
                }

                changed = true;
            }

            if (changed && memo) {
                restoreMemo(memo);
            }
        }

        function ensureStructure() {
            if (!root) {
                return;
            }

            const hollow = !root.firstElementChild && root.textContent.replace(/[\s\u200B]/g, '') === '';

            if (hollow) {
                const focused = document.activeElement === root;

                while (root.firstChild) {
                    root.removeChild(root.firstChild);
                }

                const paragraph = emptyParagraph();

                root.appendChild(paragraph);

                if (focused) {
                    caretTo(paragraph, true);
                }

                return;
            }

            const last = root.lastElementChild;

            if (last && NEEDS_TRAILING[last.nodeName]) {
                root.appendChild(emptyParagraph());
            }
        }

        function tidy() {
            normalizeRoot();
            ensureStructure();
        }

        /* --- Etkin durum ------------------------------------------------ */

        function queryState(command) {
            try {
                return !!document.queryCommandState(command);
            } catch (error) {
                return false;
            }
        }

        function computeState() {
            const range = currentRange();

            if (!range) {
                return null;
            }

            const names = {};
            let block = null;
            let cell = null;
            let list = null;
            let current = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentNode;

            while (current && current !== root) {
                const name = current.nodeName;

                names[name] = true;

                if (!block && BLOCK_VALUES[name]) {
                    block = BLOCK_VALUES[name];
                }

                if (!cell && CELLS[name]) {
                    cell = current;
                }

                if (!list && name === 'LI' && current.parentNode) {
                    list = current.parentNode.nodeName;
                }

                current = current.parentNode;
            }

            const table = cell ? closestIn(cell, { TABLE: true }) : null;
            const heading = block === 'h2' || block === 'h3' || block === 'h4';

            return {
                block: block || 'p',
                bold: !!(names.STRONG || names.B) || (!heading && !names.TH && queryState('bold')),
                italic: !!(names.EM || names.I) || queryState('italic'),
                underline: !!names.U || (!names.A && queryState('underline')),
                strike: !!(names.S || names.STRIKE || names.DEL) || queryState('strikeThrough'),
                code: !!names.CODE && !names.PRE,
                ul: list === 'UL',
                ol: list === 'OL',
                quote: !!names.BLOCKQUOTE,
                link: !!names.A,
                table: !!table,
                tableHeader: !!(table && table.tHead),
                inHeader: !!(cell && cell.parentNode && cell.parentNode.parentNode && cell.parentNode.parentNode.nodeName === 'THEAD'),
            };
        }

        function publishActive() {
            const state = computeState();

            if (state) {
                notify.active(state);
            }
        }

        /* --- Komut sarmalayici ------------------------------------------ */

        function run(action) {
            if (!root) {
                return;
            }

            focusRestore();
            commit();

            try {
                action();
            } catch (error) {
                console.error('KonelsisSocial duzenleyici:', error);
            }

            tidy();
            commit();
            notify.change();
            publishActive();
        }

        /* --- Satir ici bicim -------------------------------------------- */

        function inline(command) {
            try {
                document.execCommand('styleWithCSS', false, false);
            } catch (error) {
                // Desteklemeyen tarayici: varsayilan davranis yeterli.
            }

            document.execCommand(command, false, null);
        }

        /* --- Blok turu -------------------------------------------------- */

        function endsAtStart(range, element) {
            if (!element.contains(range.endContainer)) {
                return false;
            }

            const probe = document.createRange();

            probe.selectNodeContents(element);
            probe.setEnd(range.endContainer, range.endOffset);

            return probe.toString().replace(ZERO_WIDTH, '') === '' && !probe.cloneContents().querySelector('img');
        }

        function textBlocksIn(range) {
            const found = [];
            const all = root.querySelectorAll(TEXT_BLOCK_SELECTOR);

            for (let index = 0; index < all.length; index += 1) {
                const element = all[index];

                if (!range.intersectsNode(element) || element.querySelector(NESTED_BLOCK_SELECTOR)) {
                    continue;
                }

                // Uc tiklama secimi bir sonraki blogun basinda biter; o blok dahil edilmez.
                if (!range.collapsed && !element.contains(range.startContainer) && endsAtStart(range, element)) {
                    continue;
                }

                found.push(element);
            }

            return found;
        }

        /** Imlecin bulundugu basibos satir ici kosuyu (li / td / alinti / kok icinde) yeni bir blokla sarar. */
        function wrapRunAt(range, tagName) {
            let host = range.startContainer;

            while (host && host !== root && !(host.nodeType === 1 && LIVE_FLOW_HOSTS[host.nodeName])) {
                host = host.parentNode;
            }

            if (!host) {
                return null;
            }

            let child = range.startContainer === host ? (host.childNodes[range.startOffset] || host.lastChild) : range.startContainer;

            while (child && child.parentNode !== host) {
                child = child.parentNode;
            }

            const wrapper = document.createElement(tagName);

            if (!child) {
                wrapper.appendChild(document.createElement('br'));
                host.appendChild(wrapper);

                return wrapper;
            }

            if (isLiveBlock(child)) {
                return null;
            }

            let first = child;
            let last = child;

            while (first.previousSibling && !isLiveBlock(first.previousSibling)) {
                first = first.previousSibling;
            }

            while (last.nextSibling && !isLiveBlock(last.nextSibling)) {
                last = last.nextSibling;
            }

            host.insertBefore(wrapper, first);

            let current = first;

            while (current) {
                const following = current === last ? null : current.nextSibling;

                wrapper.appendChild(current);
                current = following;
            }

            return wrapper;
        }

        /** <pre> terk edilirken: metindeki satir sonlari <br> olur; secim noktalari parcalara tasinir. */
        function breakLines(block, memo) {
            const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT, null);
            const texts = [];

            while (walker.nextNode()) {
                texts.push(walker.currentNode);
            }

            texts.forEach((textNode) => {
                const value = textNode.nodeValue;

                if (value.indexOf('\n') === -1) {
                    return;
                }

                const parent = textNode.parentNode;
                const points = memo ? [memo.start, memo.end].filter((point) => point.node === textNode) : [];
                const offsets = points.map((point) => point.offset);
                let consumed = 0;

                value.split('\n').forEach((part, index) => {
                    if (index > 0) {
                        parent.insertBefore(document.createElement('br'), textNode);
                    }

                    const piece = document.createTextNode(part);

                    parent.insertBefore(piece, textNode);

                    points.forEach((point, position) => {
                        const offset = offsets[position];

                        if (point.node === textNode && offset >= consumed && offset <= consumed + part.length) {
                            point.node = piece;
                            point.offset = offset - consumed;
                        }
                    });

                    consumed += part.length + 1;
                });

                parent.removeChild(textNode);
            });
        }

        /** <pre> olusurken: <br> satir sonu metnine doner. */
        function joinLines(block, memo) {
            const breaks = slice(block.querySelectorAll('br'));

            if (breaks.length === 1 && block.childNodes.length === 1) {
                return;
            }

            breaks.forEach((node) => {
                const text = document.createTextNode('\n');

                node.parentNode.replaceChild(text, node);

                if (memo) {
                    [memo.start, memo.end].forEach((point) => {
                        if (point.before === node) {
                            point.before = text;
                        }
                    });
                }
            });
        }

        function setBlock(tagName) {
            const range = currentRange();

            if (!range) {
                return;
            }

            const memo = memoSelection();
            let blocks = textBlocksIn(range);

            if (!blocks.length) {
                const wrapped = wrapRunAt(range, 'p');

                if (wrapped) {
                    blocks = [wrapped];
                }
            }

            if (!blocks.length) {
                return;
            }

            const wanted = String(tagName).toUpperCase();
            const target = wanted !== 'P' && blocks.every((block) => block.nodeName === wanted) ? 'P' : wanted;

            blocks.forEach((block) => {
                if (block.nodeName === target) {
                    return;
                }

                if (block.nodeName === 'PRE') {
                    breakLines(block, memo);
                }

                const fresh = renameNode(block, target.toLowerCase(), memo);

                if (target === 'PRE') {
                    joinLines(fresh, memo);
                }
            });

            restoreMemo(memo);
        }

        /* --- Alinti ------------------------------------------------------ */

        function childOf(host, container, offset, isEnd) {
            let node = container;

            if (container === host) {
                node = isEnd ? (host.childNodes[offset - 1] || host.firstChild) : (host.childNodes[offset] || host.lastChild);
            }

            while (node && node.parentNode !== host) {
                node = node.parentNode;
            }

            return node || null;
        }

        function toggleQuote() {
            const range = currentRange();

            if (!range) {
                return;
            }

            const memo = memoSelection();
            const quote = closestIn(range.startContainer, { BLOCKQUOTE: true });

            if (quote) {
                wrapRuns(quote, 'p', isLiveBlock, null);
                unwrapNode(quote, memo);
                restoreMemo(memo);

                return;
            }

            // Liste icindeyken listenin tamami alintilanir; hucre icindeyken alinti hucrede kalir.
            const host = closestIn(range.startContainer, { TD: true, TH: true }) || root;
            let first = childOf(host, range.startContainer, range.startOffset, false);

            if (first && !isLiveBlock(first)) {
                first = wrapRunAt(range, 'p');
            }

            if (!first) {
                return;
            }

            let last = first;

            if (!range.collapsed && host.contains(range.endContainer)) {
                const candidate = childOf(host, range.endContainer, range.endOffset, true);
                const follows = candidate && candidate !== first && (first.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;

                if (follows && candidate.parentNode === host) {
                    last = candidate;
                }
            }

            if (last !== first && last.nodeType === 1 && endsAtStart(range, last)) {
                last = last.previousSibling || first;
            }

            const wrapper = document.createElement('blockquote');

            host.insertBefore(wrapper, first);

            let current = first;

            while (current) {
                const following = current === last ? null : current.nextSibling;

                wrapper.appendChild(current);
                current = following;
            }

            restoreMemo(memo);
        }

        /* --- Liste ------------------------------------------------------- */

        function toggleList(kind) {
            const state = computeState();

            if (state && state.block !== 'p') {
                setBlock('p');
            }

            document.execCommand(kind === 'ol' ? 'insertOrderedList' : 'insertUnorderedList', false, null);
        }

        /* --- Satir ici kod ve bicim temizleme ----------------------------- */

        function toggleCode() {
            const range = currentRange();

            if (!range || closestIn(range.startContainer, { PRE: true })) {
                return;
            }

            const existing = closestIn(range.startContainer, { CODE: true });

            if (existing) {
                const tail = document.createRange();

                tail.selectNodeContents(existing);
                tail.setStart(range.endContainer, range.endOffset);

                if (range.collapsed && tail.toString().replace(ZERO_WIDTH, '') === '') {
                    // Kodun sonunda: imlec kodun disina cikarilir.
                    const spacer = document.createTextNode('\u200B');

                    existing.parentNode.insertBefore(spacer, existing.nextSibling);
                    caretAtEnd(spacer);

                    return;
                }

                const memo = memoSelection();

                unwrapNode(existing, memo);
                restoreMemo(memo);

                return;
            }

            const code = document.createElement('code');

            if (range.collapsed) {
                const seed = document.createTextNode('\u200B');

                code.appendChild(seed);
                range.insertNode(code);
                caretAtEnd(seed);

                return;
            }

            // Secim birden cok bloga yayiliyorsa ilk blokla sinirlanir (kod blok sinirini asamaz).
            const block = closestIn(range.startContainer, LIVE_TEXT_BLOCKS) || closestIn(range.startContainer, LIVE_FLOW_HOSTS);

            if (block && !block.contains(range.endContainer)) {
                range.setEnd(block, block.childNodes.length);
            }

            const content = range.extractContents();

            slice(content.querySelectorAll('code')).forEach((inner) => unwrapNode(inner, null));
            code.appendChild(content);
            range.insertNode(code);

            const selected = document.createRange();

            selected.selectNodeContents(code);
            setRange(selected);
        }

        function clearFormat() {
            document.execCommand('removeFormat', false, null);

            const range = currentRange();

            if (!range) {
                return;
            }

            const memo = memoSelection();

            slice(root.querySelectorAll('code')).forEach((code) => {
                if (range.intersectsNode(code) && !closestIn(code.parentNode, { PRE: true })) {
                    unwrapNode(code, memo);
                }
            });

            restoreMemo(memo);
        }

        /* --- Blok ekleme: cizgi, tablo, gorsel ------------------------------ */

        function topLevel(container, offset) {
            let node = container === root ? (root.childNodes[offset] || root.lastChild) : container;

            while (node && node.parentNode !== root) {
                node = node.parentNode;
            }

            return node && node.nodeType === 1 ? node : null;
        }

        /** Blogu imlecin bulundugu ust duzey blogun ardina koyar; ardinda yazilabilir bir paragraf garanti eder. */
        function placeBlock(block) {
            const range = currentRange();
            const top = range ? topLevel(range.startContainer, range.startOffset) : null;

            if (!top) {
                root.appendChild(block);
            } else if (top.nodeName === 'P' && isEmptyBlock(top)) {
                root.replaceChild(block, top);
            } else {
                root.insertBefore(block, top.nextSibling);
            }

            let next = block.nextElementSibling;

            if (!next || !LIVE_TEXT_BLOCKS[next.nodeName]) {
                next = emptyParagraph();
                root.insertBefore(next, block.nextSibling);
            }

            return next;
        }

        function insertRule() {
            caretTo(placeBlock(document.createElement('hr')), true);
        }

        function insertTable(rows, columns, withHeader) {
            const table = document.createElement('table');
            const bodyRows = withHeader ? rows - 1 : rows;

            if (withHeader) {
                const head = document.createElement('thead');
                const row = document.createElement('tr');

                for (let column = 0; column < columns; column += 1) {
                    row.appendChild(newCell('th'));
                }

                head.appendChild(row);
                table.appendChild(head);
            }

            if (bodyRows > 0) {
                const body = document.createElement('tbody');

                for (let index = 0; index < bodyRows; index += 1) {
                    const row = document.createElement('tr');

                    for (let column = 0; column < columns; column += 1) {
                        row.appendChild(newCell('td'));
                    }

                    body.appendChild(row);
                }

                table.appendChild(body);
            }

            placeBlock(table);

            const firstCell = table.querySelector('th,td');

            if (firstCell) {
                caretTo(firstCell, true);
            }
        }

        function insertImage(src, alt) {
            const img = document.createElement('img');

            img.setAttribute('src', src);
            img.setAttribute('alt', cleanAlt(alt));
            img.setAttribute('width', '100%');

            const range = currentRange();

            if (range && closestIn(range.startContainer, { LI: true, TD: true, TH: true, BLOCKQUOTE: true })) {
                range.deleteContents();
                range.insertNode(img);
                caretAtEnd(img);

                return img;
            }

            const holder = document.createElement('p');

            holder.appendChild(img);
            caretTo(placeBlock(holder), true);

            return img;
        }

        function setImageWidth(img, width) {
            if (img && root.contains(img) && IMAGE_WIDTHS.indexOf(width) !== -1) {
                img.setAttribute('width', width);
            }
        }

        function setImageAlt(img, alt) {
            if (img && root.contains(img)) {
                img.setAttribute('alt', cleanAlt(alt));
            }
        }

        function removeImage(img) {
            if (!img || !root.contains(img)) {
                return;
            }

            const parent = img.parentNode;

            parent.removeChild(img);

            if (parent !== root && !parent.firstChild) {
                parent.appendChild(document.createElement('br'));
            }

            caretTo(parent === root ? (root.lastElementChild || root) : parent, parent !== root && isEmptyBlock(parent));
        }

        function selectNode(node) {
            if (!node || !root.contains(node)) {
                return;
            }

            const range = document.createRange();

            range.selectNode(node);
            setRange(range);
        }

        /* --- Tablo islemleri ------------------------------------------------ */

        function tableContext() {
            const range = currentRange();
            const cell = range ? closestIn(range.startContainer, CELLS) : null;

            if (!cell) {
                return null;
            }

            const row = cell.parentNode;
            const table = closestIn(row, { TABLE: true });

            return table ? { cell, row, table } : null;
        }

        function removeTable(table) {
            const paragraph = emptyParagraph();

            table.parentNode.replaceChild(paragraph, table);
            caretTo(paragraph, true);
        }

        function insertRow(table, row, after) {
            const fresh = document.createElement('tr');
            const width = gridWidth(row);
            const section = row.parentNode;

            for (let column = 0; column < width; column += 1) {
                fresh.appendChild(newCell('td'));
            }

            if (section.nodeName === 'THEAD') {
                let body = table.tBodies[0];

                if (!body) {
                    body = document.createElement('tbody');
                    table.appendChild(body);
                }

                body.insertBefore(fresh, body.firstChild);
            } else {
                section.insertBefore(fresh, after ? row.nextSibling : row);
            }

            return fresh;
        }

        function insertColumn(table, cell, after) {
            const at = colStart(cell) + (after ? spanOf(cell) : 0);

            slice(table.rows).forEach((row) => {
                const tagName = row.parentNode.nodeName === 'THEAD' ? 'th' : 'td';
                let index = 0;
                let placed = false;

                for (let position = 0; position < row.cells.length && !placed; position += 1) {
                    const item = row.cells[position];
                    const span = spanOf(item);

                    if (index === at) {
                        row.insertBefore(newCell(tagName), item);
                        placed = true;
                    } else if (at > index && at < index + span) {
                        item.setAttribute('colspan', String(span + 1));
                        placed = true;
                    }

                    index += span;
                }

                if (!placed) {
                    row.appendChild(newCell(tagName));
                }
            });
        }

        function removeColumn(context) {
            const at = colStart(context.cell);

            slice(context.table.rows).forEach((row) => {
                const hit = cellAt(row, at);

                if (!hit) {
                    return;
                }

                if (hit.span > 2) {
                    hit.cell.setAttribute('colspan', String(hit.span - 1));
                } else if (hit.span === 2) {
                    hit.cell.removeAttribute('colspan');
                } else {
                    row.removeChild(hit.cell);
                }
            });

            if (!context.table.querySelector('td,th')) {
                removeTable(context.table);

                return;
            }

            const target = root.contains(context.row) ? (cellAt(context.row, Math.max(0, at - 1)) || cellAt(context.row, 0)) : null;

            if (target) {
                caretTo(target.cell, true);
            }
        }

        function removeRow(context) {
            const neighbour = context.row.nextElementSibling || context.row.previousElementSibling;
            const section = context.row.parentNode;

            section.removeChild(context.row);

            if (!section.children.length && section.parentNode) {
                section.parentNode.removeChild(section);
            }

            if (!context.table.rows.length) {
                removeTable(context.table);

                return;
            }

            const target = (neighbour && neighbour.cells && neighbour.cells[0]) || context.table.rows[0].cells[0];

            if (target) {
                caretTo(target, true);
            }
        }

        function retagCells(row, tagName, memo) {
            slice(row.cells).forEach((cell) => {
                if (cell.nodeName.toLowerCase() === tagName) {
                    return;
                }

                const fresh = renameNode(cell, tagName, memo);

                copySpan(cell, fresh, 'colspan');
                copySpan(cell, fresh, 'rowspan');
            });
        }

        function toggleHeader(context) {
            const table = context.table;
            const memo = memoSelection();
            const head = table.tHead;

            if (head) {
                let body = table.tBodies[0];

                if (!body) {
                    body = document.createElement('tbody');
                    table.appendChild(body);
                }

                slice(head.rows).reverse().forEach((row) => {
                    retagCells(row, 'td', memo);
                    body.insertBefore(row, body.firstChild);
                });

                if (head.parentNode) {
                    head.parentNode.removeChild(head);
                }
            } else {
                const first = table.rows[0];

                if (!first) {
                    return;
                }

                const section = first.parentNode;
                const fresh = document.createElement('thead');

                table.insertBefore(fresh, table.firstChild);
                fresh.appendChild(first);
                retagCells(first, 'th', memo);

                if (section !== table && section !== fresh && !section.children.length && section.parentNode) {
                    section.parentNode.removeChild(section);
                }
            }

            restoreMemo(memo);
        }

        function tableOp(op) {
            const context = tableContext();

            if (!context) {
                return;
            }

            const inHead = context.row.parentNode && context.row.parentNode.nodeName === 'THEAD';

            if (op === 'row-above') {
                if (!inHead) {
                    caretTo(insertRow(context.table, context.row, false).cells[0], true);
                }
            } else if (op === 'row-below') {
                caretTo(insertRow(context.table, context.row, true).cells[0], true);
            } else if (op === 'col-left') {
                insertColumn(context.table, context.cell, false);
            } else if (op === 'col-right') {
                insertColumn(context.table, context.cell, true);
            } else if (op === 'row-remove') {
                removeRow(context);
            } else if (op === 'col-remove') {
                removeColumn(context);
            } else if (op === 'header') {
                toggleHeader(context);
            } else if (op === 'remove') {
                removeTable(context.table);
            }
        }

        /** Tablo icinde Tab / Shift+Tab: sonraki / onceki hucre; son hucrede yeni satir. */
        function tab(backwards) {
            const context = tableContext();

            if (!context) {
                return false;
            }

            const cells = Array.prototype.filter.call(context.table.querySelectorAll('td,th'), (item) => closestIn(item, { TABLE: true }) === context.table);
            const index = cells.indexOf(context.cell);
            let target = cells[index + (backwards ? -1 : 1)] || null;

            if (!target && !backwards) {
                commit();
                target = insertRow(context.table, cells[cells.length - 1].parentNode, true).cells[0];
                caretTo(target, true);
                tidy();
                commit();
                notify.change();
                publishActive();

                return true;
            }

            if (target) {
                caretTo(target, isEmptyBlock(target));
                publishActive();
            }

            return true;
        }

        /* --- Enter: kod blogundan ve alintidan cikis -------------------------- */

        function leaveTo(anchor) {
            let next = anchor.nextElementSibling;

            if (!next || !LIVE_TEXT_BLOCKS[next.nodeName] || next.nodeName === 'PRE') {
                next = emptyParagraph();
                anchor.parentNode.insertBefore(next, anchor.nextSibling);
            }

            caretTo(next, true);
            tidy();
            commit();
            notify.change();
            publishActive();
        }

        function enter() {
            const range = currentRange();

            if (!range || !range.collapsed) {
                return false;
            }

            const pre = closestIn(range.startContainer, { PRE: true });

            if (pre) {
                const before = document.createRange();
                const after = document.createRange();

                before.selectNodeContents(pre);
                before.setEnd(range.startContainer, range.startOffset);
                after.selectNodeContents(pre);
                after.setStart(range.startContainer, range.startOffset);

                const head = before.toString().replace(ZERO_WIDTH, '');
                const tail = after.toString().replace(ZERO_WIDTH, '');

                // Bos son satirda Enter: kod blogundan cikilir.
                if (!/\n$/.test(head) || (tail !== '' && tail !== '\n')) {
                    return false;
                }

                commit();

                let strip = tail === '\n' ? 2 : 1;

                while (strip > 0 && pre.lastChild && pre.lastChild.nodeType === 3) {
                    const lastText = pre.lastChild;

                    if (lastText.nodeValue === '') {
                        pre.removeChild(lastText);
                    } else if (/\n$/.test(lastText.nodeValue)) {
                        lastText.nodeValue = lastText.nodeValue.slice(0, -1);
                        strip -= 1;
                    } else {
                        break;
                    }
                }

                if (!pre.firstChild) {
                    pre.appendChild(document.createElement('br'));
                }

                leaveTo(pre);

                return true;
            }

            const block = closestIn(range.startContainer, LIVE_TEXT_BLOCKS);
            const quote = block && block.parentNode && block.parentNode.nodeName === 'BLOCKQUOTE' ? block.parentNode : null;

            // Alintinin sonundaki bos paragrafta Enter: alintidan cikilir.
            if (quote && isEmptyBlock(block) && block === quote.lastElementChild && quote.children.length > 1) {
                commit();
                quote.parentNode.insertBefore(block, quote.nextSibling);
                caretTo(block, true);
                tidy();
                commit();
                notify.change();
                publishActive();

                return true;
            }

            return false;
        }

        /* --- Baglanti ---------------------------------------------------------- */

        function linkInfo() {
            const range = currentRange() || (rangeAlive(saved) ? saved : null);

            if (!range) {
                return EMPTY_LINK;
            }

            const anchor = closestIn(range.startContainer, { A: true }) || (range.collapsed ? null : closestIn(range.endContainer, { A: true }));

            return {
                href: anchor ? (anchor.getAttribute('href') || '') : '',
                text: range.collapsed ? '' : range.toString(),
                hasLink: !!anchor,
                collapsed: range.collapsed,
            };
        }

        function applyLink(href, text) {
            const range = currentRange();

            if (!range || !href) {
                return;
            }

            const anchor = closestIn(range.startContainer, { A: true }) || (range.collapsed ? null : closestIn(range.endContainer, { A: true }));

            if (anchor) {
                setLinkAttributes(anchor, href);

                return;
            }

            if (!range.collapsed) {
                document.execCommand('createLink', false, href);
                slice(root.querySelectorAll('a')).forEach((item) => setLinkAttributes(item, null));

                return;
            }

            const fresh = document.createElement('a');
            const spacer = document.createTextNode('\u00A0');

            setLinkAttributes(fresh, href);
            fresh.appendChild(document.createTextNode(text || href.replace(/^mailto:/i, '')));
            range.insertNode(fresh);
            fresh.parentNode.insertBefore(spacer, fresh.nextSibling);
            caretAtEnd(spacer);
        }

        function removeLink() {
            const range = currentRange();

            if (!range) {
                return;
            }

            const anchor = closestIn(range.startContainer, { A: true });

            if (anchor) {
                const memo = memoSelection();

                unwrapNode(anchor, memo);
                restoreMemo(memo);

                return;
            }

            document.execCommand('unlink', false, null);
        }

        /* --- Yapistirma / birakma ------------------------------------------------ */

        function plainContext() {
            const range = currentRange();

            return !!range && !!(closestIn(range.startContainer, { PRE: true }) || closestIn(range.startContainer, { CODE: true }));
        }

        /** Temizlenmis kutuyu (etkisiz belgedeki div) imlece yerlestirir. */
        function insertClean(box) {
            const range = currentRange();

            if (!range || !box || !box.firstChild) {
                return;
            }

            range.deleteContents();

            let nodes = slice(box.childNodes);

            if (nodes.length === 1 && nodes[0].nodeType === 1 && nodes[0].nodeName === 'P') {
                nodes = slice(nodes[0].childNodes);
            }

            if (!nodes.length) {
                return;
            }

            const blocky = nodes.some(isBlockNode);
            const host = closestIn(range.startContainer, LIVE_TEXT_BLOCKS);

            if (!blocky || !host || host.nodeName === 'PRE') {
                const fragment = document.createDocumentFragment();

                nodes.forEach((node) => fragment.appendChild(node));

                const lastNode = fragment.lastChild;

                range.insertNode(fragment);
                caretAtEnd(lastNode);

                return;
            }

            // Blok yapistirma: bulunulan blok imlecten bolunur, bloklar araya girer.
            const tailRange = document.createRange();

            tailRange.setStart(range.startContainer, range.startOffset);
            tailRange.setEnd(host, host.childNodes.length);

            const tail = tailRange.extractContents();
            const tailHasContent = tail.textContent.replace(ZERO_WIDTH, '') !== '' || !!tail.querySelector('img');
            let hostEmpty = isEmptyBlock(host);

            if (nodes[0].nodeType === 1 && nodes[0].nodeName === 'P') {
                if (hostEmpty) {
                    while (host.firstChild) {
                        host.removeChild(host.firstChild);
                    }
                }

                const lead = nodes.shift();

                while (lead.firstChild) {
                    host.appendChild(lead.firstChild);
                }

                hostEmpty = false;
            }

            let reference = host;

            nodes.forEach((node) => {
                reference.parentNode.insertBefore(node, reference.nextSibling);
                reference = node;
            });

            if (reference === host || (reference.nodeType === 1 && (BLOCK_VALUES[reference.nodeName] && reference.nodeName !== 'PRE'))) {
                caretAtEnd(reference);

                const caret = currentRange();
                const keep = caret ? caret.cloneRange() : null;

                if (tailHasContent) {
                    reference.appendChild(tail);
                }

                if (keep) {
                    setRange(keep);
                }
            } else if (tailHasContent) {
                const rest = document.createElement(host.nodeName === 'DIV' ? 'p' : host.nodeName.toLowerCase());

                rest.appendChild(tail);
                reference.parentNode.insertBefore(rest, reference.nextSibling);
                caretTo(rest, true);
            } else if (reference.nodeName === 'UL' || reference.nodeName === 'OL') {
                caretAtEnd(reference.lastElementChild || reference);
            } else {
                let next = reference.nextElementSibling;

                if (!next || !LIVE_TEXT_BLOCKS[next.nodeName]) {
                    next = emptyParagraph();
                    reference.parentNode.insertBefore(next, reference.nextSibling);
                }

                caretTo(next, true);
            }

            if (hostEmpty && reference !== host && host.parentNode) {
                host.parentNode.removeChild(host);
            } else if (!host.firstChild) {
                host.appendChild(document.createElement('br'));
            }
        }

        function insertPlain(text) {
            const range = currentRange();
            const value = String(text || '').replace(/\r\n?/g, '\n');

            if (!range || value === '') {
                return;
            }

            const inPre = !!closestIn(range.startContainer, { PRE: true });

            if (inPre || plainContext() || value.indexOf('\n') === -1) {
                const node = document.createTextNode(inPre ? value : value.replace(/\n+/g, ' '));

                range.deleteContents();
                range.insertNode(node);
                caretAtEnd(node);

                return;
            }

            const doc = inert();
            const box = doc.createElement('div');

            value.split(/\n{2,}/).forEach((chunk) => {
                if (chunk.trim() === '') {
                    return;
                }

                const paragraph = doc.createElement('p');

                chunk.split('\n').forEach((line, index) => {
                    if (index > 0) {
                        paragraph.appendChild(doc.createElement('br'));
                    }

                    paragraph.appendChild(doc.createTextNode(line));
                });

                box.appendChild(paragraph);
            });

            insertClean(box);
        }

        function insertHtml(html) {
            const box = buildTree(parseBody(html), 'auto');

            padEmptyBlocks(box);
            insertClean(box);
        }

        function caretFromPoint(x, y) {
            let range = null;

            if (document.caretRangeFromPoint) {
                range = document.caretRangeFromPoint(x, y);
            } else if (document.caretPositionFromPoint) {
                const position = document.caretPositionFromPoint(x, y);

                if (position) {
                    range = document.createRange();
                    range.setStart(position.offsetNode, position.offset);
                    range.collapse(true);
                }
            }

            if (range && root.contains(range.startContainer)) {
                setRange(range);
            }
        }

        /* --- Yasam dongusu ---------------------------------------------------------- */

        function load(html) {
            if (!root) {
                return;
            }

            const box = buildTree(parseBody(html), 'always');

            padEmptyBlocks(box);

            while (root.firstChild) {
                root.removeChild(root.firstChild);
            }

            while (box.firstChild) {
                root.appendChild(box.firstChild);
            }

            ensureStructure();
            saved = null;
            window.clearTimeout(history.timer);
            history.stack = [];
            history.index = -1;
            history.pending = false;
            commit();
        }

        function serialize() {
            if (!root) {
                return '';
            }

            const tree = buildTree(root, 'always');

            return finalize(tree) ? '' : toHtml(tree, null);
        }

        function input(composing) {
            if (!root) {
                return;
            }

            if (!composing) {
                tidy();
            }

            history.pending = true;
            publishHistory();
            window.clearTimeout(history.timer);
            history.timer = window.setTimeout(commit, 450);
            notify.change();
        }

        function selectionChanged() {
            const range = currentRange();

            if (!range) {
                return;
            }

            saved = range.cloneRange();
            publishActive();
        }

        function isEmpty() {
            if (!root) {
                return true;
            }

            if (root.children.length > 1 || root.querySelector('img,hr,table,li,pre,blockquote,h1,h2,h3,h4')) {
                return false;
            }

            return root.textContent.replace(/[\s\u200B]/g, '') === '';
        }

        return {
            attach(node) {
                root = node;
            },
            detach() {
                window.clearTimeout(history.timer);
            },
            root: () => root,
            contains: (node) => !!root && !!node && root.contains(node),
            text: () => (root ? plainText(root) : ''),
            remember() {
                const range = currentRange();

                if (range) {
                    saved = range.cloneRange();
                }
            },
            focus: focusRestore,
            load,
            serialize,
            isEmpty,
            input,
            commit,
            selectionChanged,
            run,
            undo: () => stepHistory(-1),
            redo: () => stepHistory(1),
            inline,
            setBlock,
            toggleQuote,
            toggleList,
            toggleCode,
            clearFormat,
            insertRule,
            insertTable,
            tableOp,
            tab,
            enter,
            linkInfo,
            applyLink,
            removeLink,
            insertImage,
            setImageWidth,
            setImageAlt,
            removeImage,
            selectNode,
            plainContext,
            insertHtml,
            insertPlain,
            caretFromPoint,
        };
    }

    /* ================================================================== */
    /* 4. Yardimci arayuz bilesenleri                                      */
    /* ================================================================== */

    /**
     * Flyout: masaustunde KS.Popover, < 768px ekranda alttan acilan KS.Modal ('sm').
     * props: { trigger(triggerProps), title, icon, width, align, onOpenChange(next), children(close) }
     */
    function Flyout(props) {
        const isMobile = KS.useIsMobile();
        const [open, setOpen] = useState(false);

        const report = (next) => {
            if (typeof props.onOpenChange === 'function') {
                props.onOpenChange(next);
            }
        };

        if (!isMobile) {
            return h(KS.Popover, {
                trigger: props.trigger,
                label: props.title,
                align: props.align || 'start',
                width: props.width,
                panelClassName: 'ks-editor-pop',
                onOpenChange: report,
            }, (close) => props.children(() => close({ keepFocus: false })));
        }

        const close = () => {
            setOpen(false);
            report(false);
        };

        return h(Fragment, null,
            props.trigger({
                onClick: () => {
                    const next = !open;

                    report(next);
                    setOpen(next);
                },
                'aria-expanded': open ? 'true' : 'false',
                'aria-haspopup': 'dialog',
            }),
            open ? h(KS.Modal, { title: props.title, icon: props.icon, size: 'sm', onClose: close }, props.children(close)) : null,
        );
    }

    /** Baglanti formu: adres dogrulama (http / https / mailto), gorunen metin, kaldir, yeni sekmede ac. */
    function LinkForm(props) {
        const initial = props.initial || EMPTY_LINK;
        const [address, setAddress] = useState(initial.href || '');
        const [text, setText] = useState('');
        const [error, setError] = useState('');
        const inputRef = useRef(null);
        const needsText = !initial.hasLink && initial.collapsed;
        const resolved = normalizeLink(address);
        const openable = resolved ? KS.safeUrl(resolved) : null;

        useEffect(() => {
            const node = inputRef.current;

            if (node) {
                quietFocus(node);

                if (typeof node.select === 'function') {
                    node.select();
                }
            }
        }, []);

        const submit = () => {
            if (!resolved) {
                setError(t('editor_link_invalid'));

                return;
            }

            props.onApply(resolved, text.trim());
        };

        return h('div', { className: 'ks-editor-form' },
            h(KS.Field, { label: t('editor_link_url'), error: error || undefined, hint: error ? undefined : t('editor_link_hint'), required: true },
                h(KS.TextInput, {
                    value: address,
                    onChange: (value) => {
                        setAddress(value);
                        setError('');
                    },
                    onEnter: submit,
                    inputRef,
                    icon: 'link',
                    placeholder: t('editor_link_url_placeholder'),
                    inputMode: 'url',
                    autoCapitalize: 'off',
                    autoCorrect: 'off',
                    spellCheck: false,
                    maxLength: 500,
                }),
            ),
            needsText ? h(KS.Field, { label: t('editor_link_text'), hint: t('editor_link_text_hint') },
                h(KS.TextInput, { value: text, onChange: setText, onEnter: submit, maxLength: 200 }),
            ) : null,
            h('div', { className: 'ks-editor-form__actions' },
                h('div', { className: 'ks-editor-form__side' },
                    initial.hasLink ? h(KS.Button, { variant: 'danger', size: 'sm', icon: 'link-off', onClick: props.onRemove }, t('editor_link_remove')) : null,
                    openable ? h(KS.Button, { variant: 'link', size: 'sm', icon: 'external', href: openable, target: '_blank' }, t('open_in_new_tab')) : null,
                ),
                h('div', { className: 'ks-editor-form__main' },
                    h(KS.Button, { variant: 'ghost', size: 'sm', onClick: props.onCancel }, t('cancel')),
                    h(KS.Button, { variant: 'primary', size: 'sm', icon: 'check', onClick: submit }, t('apply')),
                ),
            ),
        );
    }

    /** Alternatif metin formu (gorsel araclari icinden). */
    function AltForm(props) {
        const [value, setValue] = useState(props.initial || '');
        const inputRef = useRef(null);

        useEffect(() => {
            quietFocus(inputRef.current);
        }, []);

        const submit = () => props.onApply(value);

        return h('div', { className: 'ks-editor-form' },
            h(KS.Field, { label: t('editor_image_alt'), hint: t('editor_image_alt_hint') },
                h(KS.TextInput, { value, onChange: setValue, onEnter: submit, inputRef, maxLength: ALT_MAX, placeholder: t('editor_image_alt_placeholder') }),
            ),
            h('div', { className: 'ks-editor-form__actions' },
                h('div', { className: 'ks-editor-form__side' }),
                h('div', { className: 'ks-editor-form__main' },
                    h(KS.Button, { variant: 'ghost', size: 'sm', onClick: props.onCancel }, t('cancel')),
                    h(KS.Button, { variant: 'primary', size: 'sm', icon: 'check', onClick: submit }, t('apply')),
                ),
            ),
        );
    }

    const TABLE_OPS = [
        { op: 'row-above', icon: 'arrow-up', label: 'editor_table_row_above', notInHeader: true },
        { op: 'row-below', icon: 'arrow-down', label: 'editor_table_row_below' },
        { op: 'col-left', icon: 'back', label: 'editor_table_col_left' },
        { op: 'col-right', icon: 'arrow-right', label: 'editor_table_col_right' },
        { divider: true },
        { op: 'header', icon: 'heading', label: 'editor_table_header_toggle', toggle: true },
        { divider: true },
        { op: 'row-remove', icon: 'minus', label: 'editor_table_row_remove' },
        { op: 'col-remove', icon: 'minus', label: 'editor_table_col_remove' },
        { op: 'remove', icon: 'trash-none', label: 'editor_table_remove', danger: true },
    ];

    /** Tablo paneli: imlec tablodaysa islemler, degilse boyut secici (tikla = ekle). */
    function TablePanel(props) {
        const [pick, setPick] = useState({ rows: 3, cols: 3 });
        const [header, setHeader] = useState(true);
        const gridRef = useRef(null);

        if (props.active.table) {
            return h('div', { className: 'ks-menu ks-editor-tablemenu', role: 'group', 'aria-label': t('editor_table_tools') },
                TABLE_OPS.map((item, index) => {
                    if (item.divider) {
                        return h('div', { key: 'd' + index, className: 'ks-menu__divider', role: 'separator' });
                    }

                    const pressed = item.toggle ? !!props.active.tableHeader : undefined;

                    return h('button', {
                        key: item.op,
                        type: 'button',
                        className: cx('ks-menu__item', item.danger && 'ks-menu__item--danger', pressed && 'is-active'),
                        disabled: !!(item.notInHeader && props.active.inHeader),
                        'aria-pressed': pressed === undefined ? undefined : (pressed ? 'true' : 'false'),
                        onClick: () => props.onOp(item.op),
                    },
                        h(KS.Icon, { name: item.icon }),
                        h('span', { className: 'ks-menu__text' }, h('span', { className: 'ks-menu__label' }, t(item.label))),
                        pressed ? h(KS.Icon, { name: 'check', className: 'ks-menu__check' }) : null,
                    );
                }),
            );
        }

        const focusCell = (rows, cols) => {
            const node = gridRef.current ? gridRef.current.querySelector('[data-cell="' + rows + '-' + cols + '"]') : null;

            quietFocus(node);
        };

        const onKeyDown = (event) => {
            const moves = { ArrowRight: [0, 1], ArrowLeft: [0, -1], ArrowDown: [1, 0], ArrowUp: [-1, 0] };
            const delta = moves[event.key];

            if (!delta) {
                return;
            }

            event.preventDefault();

            const next = { rows: KS.clamp(pick.rows + delta[0], 1, GRID_ROWS), cols: KS.clamp(pick.cols + delta[1], 1, GRID_COLS) };

            setPick(next);
            focusCell(next.rows, next.cols);
        };

        const cells = [];

        for (let row = 1; row <= GRID_ROWS; row += 1) {
            for (let col = 1; col <= GRID_COLS; col += 1) {
                const on = row <= pick.rows && col <= pick.cols;
                const current = row === pick.rows && col === pick.cols;

                cells.push(h('button', {
                    key: row + '-' + col,
                    type: 'button',
                    'data-cell': row + '-' + col,
                    className: cx('ks-editor-grid__cell', on && 'is-on'),
                    tabIndex: current ? 0 : -1,
                    'aria-label': t('editor_table_size', { rows: row, cols: col }),
                    onMouseEnter: () => setPick({ rows: row, cols: col }),
                    onFocus: () => setPick({ rows: row, cols: col }),
                    onClick: () => props.onInsert(row, col, header),
                }));
            }
        }

        return h('div', { className: 'ks-editor-form' },
            h('div', { className: 'ks-editor-grid__head' },
                h('span', { className: 'ks-editor-grid__title' }, t('editor_table_pick')),
                h('span', { className: 'ks-editor-grid__size', 'aria-live': 'polite' }, t('editor_table_size', { rows: pick.rows, cols: pick.cols })),
            ),
            h('div', { ref: gridRef, className: 'ks-editor-grid', role: 'group', 'aria-label': t('editor_table_pick'), onKeyDown }, cells),
            h(KS.Checkbox, { checked: header, onChange: setHeader, label: t('editor_table_header') }),
        );
    }

    /** Secili gorselin ustunde yuzen araclar: genislik, alternatif metin, metinden cikarma. */
    function ImageTools(props) {
        const ref = useRef(null);
        const box = props.box;
        const current = props.image.getAttribute('width') || '';

        useLayoutEffect(() => {
            const node = ref.current;

            if (!node || !node.parentNode) {
                return;
            }

            const hostWidth = node.parentNode.clientWidth;
            const width = node.offsetWidth;
            const height = node.offsetHeight;
            const left = Math.max(6, Math.min(box.left + (box.width - width) / 2, hostWidth - width - 6));
            let top = box.top - height - 8;

            if (top < 6) {
                top = box.top + 8;
            }

            node.style.left = Math.round(left) + 'px';
            node.style.top = Math.round(top) + 'px';
        });

        return h('div', {
            ref,
            className: 'ks-editor__imgtools',
            role: 'toolbar',
            'aria-label': t('editor_image_tools'),
            onMouseDown: (event) => {
                const target = event.target;

                if (!(target && target.closest && target.closest('input,textarea,select'))) {
                    event.preventDefault();
                }
            },
        },
            h(KS.Segmented, {
                size: 'sm',
                label: t('editor_image_width'),
                value: current,
                onChange: props.onWidth,
                items: IMAGE_WIDTHS.map((width) => ({ value: width, label: t('editor_image_width_value', { n: width.replace('%', '') }) })),
            }),
            h(Flyout, {
                title: t('editor_image_alt'),
                icon: 'image',
                width: 320,
                onOpenChange: props.onAltOpenChange,
                trigger: (triggerProps) => h(KS.Button, Object.assign({}, triggerProps, { variant: 'ghost', size: 'sm', icon: 'text' }), t('editor_image_alt')),
            }, (close) => h(AltForm, {
                initial: props.image.getAttribute('alt') || '',
                onApply: (value) => {
                    close();
                    props.onAlt(value);
                },
                onCancel: close,
            })),
            h(KS.IconButton, { icon: 'trash-none', label: t('editor_image_remove'), variant: 'danger', size: 'sm', onClick: props.onRemove }),
        );
    }

    /* ================================================================== */
    /* 5. KS.RichEditor                                                    */
    /* ================================================================== */

    function shortcutKey(event) {
        const key = event.key || '';

        // Turkce klavye: "I" tusu "ı", "İ" tusu "i" uretir; ikisi de italik kisayoludur.
        if (key === '\u0131' || key === '\u0130') {
            return 'i';
        }

        return key.length === 1 ? key.toLowerCase() : key;
    }

    function shortcutTitle(label, letter) {
        return label + ' (' + (IS_MAC ? '\u2318' + letter : 'Ctrl+' + letter) + ')';
    }

    function hasFiles(data) {
        return !!data && Array.prototype.indexOf.call(data.types || [], 'Files') !== -1;
    }

    function imageFilesOf(data) {
        const files = [];

        if (!data) {
            return files;
        }

        slice(data.files).forEach((file) => {
            if (file && /^image\//i.test(file.type || '')) {
                files.push(file);
            }
        });

        return files;
    }

    function pickUpload(result) {
        if (typeof result === 'string') {
            return { url: result, alt: '' };
        }

        if (result && typeof result === 'object') {
            const address = result.preview_url || result.url || null;

            return address ? { url: String(address), alt: result.alt || '' } : null;
        }

        return null;
    }

    function toLength(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }

        return typeof value === 'number' ? value + 'px' : String(value);
    }

    function RichEditor(props) {
        const mode = props.mode === 'article' ? 'article' : 'blog';
        const disabled = !!props.disabled;
        const canUpload = typeof props.onUploadImage === 'function';
        const isMobile = KS.useIsMobile();
        const coarsePointer = KS.useMediaQuery('(pointer: coarse)');

        const surfaceRef = useRef(null);
        const bodyRef = useRef(null);
        const fileRef = useRef(null);
        const linkButtonRef = useRef(null);
        const imageRef = useRef(null);
        const propsRef = useRef(props);
        const mountedRef = useRef(false);
        const pendingRef = useRef(false);
        const altOpenRef = useRef(false);
        const dragRef = useRef({ internal: false, depth: 0 });
        const memoryRef = useRef({ loaded: false, last: null, recent: [] });
        const bridgeRef = useRef({});
        const engineRef = useRef(null);
        const emitRef = useRef(null);
        const surfaceId = useMemo(() => KS.uid('ks-editor'), []);

        const [active, setActive] = useState(EMPTY_ACTIVE);
        const [hist, setHist] = useState({ undo: false, redo: false });
        const [stats, setStats] = useState({ words: 0, chars: 0, bytes: 0 });
        const [empty, setEmpty] = useState(true);
        const [image, setImage] = useState(null);
        const [uploading, setUploading] = useState(0);
        const [dragOver, setDragOver] = useState(false);
        const [linkInitial, setLinkInitial] = useState(EMPTY_LINK);

        propsRef.current = props;

        if (!engineRef.current) {
            engineRef.current = createEngine({
                change: () => bridgeRef.current.change && bridgeRef.current.change(),
                active: (state) => bridgeRef.current.active && bridgeRef.current.active(state),
                history: (state) => bridgeRef.current.history && bridgeRef.current.history(state),
            });
        }

        const engine = engineRef.current;

        /* --- Yayim (onChange) ve sayaclar -------------------------------- */

        const remember = (value) => {
            const memory = memoryRef.current;

            if (memory.recent.indexOf(value) === -1) {
                memory.recent.push(value);

                if (memory.recent.length > 8) {
                    memory.recent.shift();
                }
            }
        };

        const refreshMeta = (html) => {
            if (!mountedRef.current) {
                return;
            }

            const counted = textStats(engine.text());
            const next = { words: counted.words, chars: counted.chars, bytes: html === '' ? 0 : byteLength(html) };

            setStats((previous) => (KS.shallowEqual(previous, next) ? previous : next));
            setEmpty(engine.isEmpty());
        };

        const emitNow = () => {
            pendingRef.current = false;

            if (!engine.root()) {
                return;
            }

            const html = engine.serialize();
            const memory = memoryRef.current;

            refreshMeta(html);

            if (html !== memory.last) {
                memory.last = html;
                remember(html);

                if (typeof propsRef.current.onChange === 'function') {
                    propsRef.current.onChange(html);
                }
            }
        };

        bridgeRef.current.emit = emitNow;

        if (!emitRef.current) {
            emitRef.current = KS.debounce(() => bridgeRef.current.emit(), 250);
        }

        const flushEmit = () => {
            if (pendingRef.current) {
                emitRef.current.cancel();
                emitNow();
            }
        };

        /* --- Gorsel secimi ------------------------------------------------- */

        const measureImage = () => {
            const img = imageRef.current;
            const body = bodyRef.current;

            if (!img) {
                return;
            }

            if (!body || !engine.contains(img)) {
                imageRef.current = null;
                setImage(null);

                return;
            }

            const a = img.getBoundingClientRect();
            const b = body.getBoundingClientRect();
            const box = { top: Math.round(a.top - b.top), left: Math.round(a.left - b.left), width: Math.round(a.width), height: Math.round(a.height) };

            setImage((previous) => (previous && previous.el === img && KS.shallowEqual(previous.box, box) ? previous : { el: img, box }));
        };

        const clearImage = () => {
            if (imageRef.current) {
                imageRef.current = null;
                setImage(null);
            }
        };

        const selectImage = (img, withSelection) => {
            imageRef.current = img;

            if (withSelection) {
                engine.selectNode(img);
            }

            measureImage();
        };

        bridgeRef.current.measure = measureImage;

        bridgeRef.current.change = () => {
            if (!mountedRef.current) {
                return;
            }

            pendingRef.current = true;
            setEmpty(engine.isEmpty());
            measureImage();
            emitRef.current();
        };

        bridgeRef.current.active = (state) => {
            if (mountedRef.current) {
                setActive((previous) => (KS.shallowEqual(previous, state) ? previous : state));
            }
        };

        bridgeRef.current.history = (state) => {
            if (mountedRef.current) {
                setHist((previous) => (KS.shallowEqual(previous, state) ? previous : state));
            }
        };

        KS.useClickOutside(bodyRef, () => {
            if (!altOpenRef.current) {
                clearImage();
            }
        }, !!image);

        /* --- Baglama ve deger esitleme --------------------------------------- */

        useLayoutEffect(() => {
            mountedRef.current = true;
            engine.attach(surfaceRef.current);

            return () => {
                mountedRef.current = false;

                // Bekleyen degisiklik kaybolmasin: kaldirilirken son hal yayilir (durum guncellemesi yapilmaz).
                if (pendingRef.current) {
                    emitRef.current.cancel();
                    bridgeRef.current.emit();
                }

                engine.detach();
            };
        }, []);

        useLayoutEffect(() => {
            const incoming = props.value === undefined || props.value === null ? '' : String(props.value);
            const memory = memoryRef.current;

            if (memory.loaded && (incoming === memory.last || memory.recent.indexOf(incoming) !== -1)) {
                return;
            }

            // Sunucunun duzgunlestirdigi ama icerikce ayni deger: imlec bozulmasin diye yeniden kurulmaz.
            if (memory.loaded && cleanHtml(incoming, null) === engine.serialize()) {
                remember(incoming);

                return;
            }

            emitRef.current.cancel();
            pendingRef.current = false;
            engine.load(incoming);
            memory.loaded = true;
            memory.last = engine.serialize();
            memory.recent = [incoming];
            imageRef.current = null;
            setImage(null);
            refreshMeta(memory.last);
        }, [props.value]);

        useEffect(() => {
            const surface = surfaceRef.current;

            if (!surface) {
                return undefined;
            }

            const onBeforeInput = (event) => {
                if (event.inputType === 'historyUndo') {
                    event.preventDefault();
                    engine.undo();
                } else if (event.inputType === 'historyRedo') {
                    event.preventDefault();
                    engine.redo();
                }
            };

            const onLoad = (event) => {
                if (event.target && event.target.nodeName === 'IMG') {
                    bridgeRef.current.measure();
                }
            };

            const onSelection = () => engine.selectionChanged();
            const onResize = () => bridgeRef.current.measure();
            const onDragEnd = () => {
                dragRef.current.internal = false;
            };

            surface.addEventListener('beforeinput', onBeforeInput);
            surface.addEventListener('load', onLoad, true);
            surface.addEventListener('scroll', onResize);
            document.addEventListener('selectionchange', onSelection);
            document.addEventListener('dragend', onDragEnd);
            window.addEventListener('resize', onResize);

            return () => {
                surface.removeEventListener('beforeinput', onBeforeInput);
                surface.removeEventListener('load', onLoad, true);
                surface.removeEventListener('scroll', onResize);
                document.removeEventListener('selectionchange', onSelection);
                document.removeEventListener('dragend', onDragEnd);
                window.removeEventListener('resize', onResize);
            };
        }, []);

        /* --- Gorsel yukleme ------------------------------------------------------ */

        const uploadFiles = (files) => {
            const handler = propsRef.current.onUploadImage;

            if (typeof handler !== 'function' || propsRef.current.disabled) {
                return;
            }

            const limits = KS.limits();
            const mimes = Array.isArray(limits.image_mimes) ? limits.image_mimes : [];
            const maxBytes = Number(limits.max_image_kb) > 0 ? Number(limits.max_image_kb) * 1024 : 0;
            const accepted = [];

            files.slice(0, MAX_IMAGES_AT_ONCE).forEach((file) => {
                const typeOk = mimes.length ? mimes.indexOf(String(file.type || '').toLowerCase()) !== -1 : /^image\//i.test(file.type || '');

                if (!typeOk) {
                    KS.toast.error(t('editor_image_type', { name: file.name || '' }));
                } else if (maxBytes && file.size > maxBytes) {
                    KS.toast.error(t('editor_image_too_large', { name: file.name || '', max: KS.fmt.bytes(maxBytes) }));
                } else {
                    accepted.push(file);
                }
            });

            if (!accepted.length) {
                return;
            }

            engine.remember();
            setUploading((count) => count + accepted.length);

            let chain = Promise.resolve();

            accepted.forEach((file) => {
                chain = chain
                    .then(() => handler(file))
                    .then((result) => {
                        const picked = pickUpload(result);

                        if (!mountedRef.current || !picked) {
                            return;
                        }

                        const src = imageSrc(picked.url);

                        if (!src) {
                            KS.toast.error(t('editor_image_rejected'));

                            return;
                        }

                        let inserted = null;

                        engine.run(() => {
                            inserted = engine.insertImage(src, picked.alt);
                        });

                        if (inserted) {
                            selectImage(inserted, false);
                        }
                    })
                    .catch((error) => {
                        if (mountedRef.current && !(error && (error.aborted || error.cancelled || error.silent))) {
                            KS.handleError(error);
                        }
                    })
                    .then(() => {
                        if (mountedRef.current) {
                            setUploading((count) => Math.max(0, count - 1));
                        }
                    });
            });
        };

        /** Pano ya da surukleme verisini isler: gorsel dosyalari yuklenir, HTML temizlenir, duz metin paragraflanir. */
        const ingest = (data) => {
            const files = imageFilesOf(data);
            const html = data.getData('text/html');
            const text = data.getData('text/plain');

            if (files.length && canUpload && String(text || '').trim() === '') {
                uploadFiles(files);

                return;
            }

            engine.run(() => {
                if (html && !engine.plainContext()) {
                    engine.insertHtml(html);
                } else {
                    engine.insertPlain(text);
                }
            });
        };

        /* --- Olaylar ----------------------------------------------------------------- */

        const keep = (event) => event.preventDefault();
        const later = (action) => window.setTimeout(action, 30);
        const exec = (action) => () => engine.run(action);

        const onKeyDown = (event) => {
            if (disabled || (event.nativeEvent && event.nativeEvent.isComposing)) {
                return;
            }

            const key = shortcutKey(event);

            if ((event.ctrlKey || event.metaKey) && !event.altKey) {
                let handled = true;

                if (key === 'b' && !event.shiftKey) {
                    engine.run(() => engine.inline('bold'));
                } else if (key === 'i' && !event.shiftKey) {
                    engine.run(() => engine.inline('italic'));
                } else if (key === 'u' && !event.shiftKey) {
                    engine.run(() => engine.inline('underline'));
                } else if (key === 'k' && !event.shiftKey) {
                    if (linkButtonRef.current) {
                        linkButtonRef.current.click();
                    }
                } else if (key === 'z') {
                    if (event.shiftKey) {
                        engine.redo();
                    } else {
                        engine.undo();
                    }
                } else if (key === 'y' && !event.shiftKey) {
                    engine.redo();
                } else {
                    handled = false;
                }

                if (handled) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                return;
            }

            if (imageRef.current) {
                if (key === 'Backspace' || key === 'Delete') {
                    const target = imageRef.current;

                    event.preventDefault();
                    clearImage();
                    engine.run(() => engine.removeImage(target));

                    return;
                }

                if (key !== 'Shift' && key !== 'Control' && key !== 'Alt' && key !== 'Meta') {
                    clearImage();
                }
            }

            if (key === 'Tab' && !event.altKey) {
                if (engine.tab(event.shiftKey)) {
                    event.preventDefault();
                }

                return;
            }

            if (key === 'Enter' && !event.shiftKey && engine.enter()) {
                event.preventDefault();
            }
        };

        const onPaste = (event) => {
            if (disabled || !event.clipboardData) {
                return;
            }

            event.preventDefault();
            ingest(event.clipboardData);
        };

        const onDrop = (event) => {
            dragRef.current.depth = 0;
            setDragOver(false);

            if (disabled) {
                event.preventDefault();

                return;
            }

            // Duzenleyici icindeki surukleme (tasima) tarayiciya birakilir; icerik zaten temizdir.
            if (dragRef.current.internal || !event.dataTransfer) {
                return;
            }

            event.preventDefault();
            quietFocus(surfaceRef.current);
            engine.caretFromPoint(event.clientX, event.clientY);
            ingest(event.dataTransfer);
        };

        const onDragEnter = (event) => {
            if (disabled || !canUpload || !hasFiles(event.dataTransfer)) {
                return;
            }

            dragRef.current.depth += 1;
            setDragOver(true);
        };

        const onDragLeave = (event) => {
            if (!hasFiles(event.dataTransfer)) {
                return;
            }

            dragRef.current.depth = Math.max(0, dragRef.current.depth - 1);

            if (dragRef.current.depth === 0) {
                setDragOver(false);
            }
        };

        const onDragOver = (event) => {
            if (!disabled && hasFiles(event.dataTransfer)) {
                event.preventDefault();

                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = canUpload ? 'copy' : 'none';
                }
            }
        };

        const onClick = (event) => {
            if (disabled) {
                return;
            }

            if (event.target && event.target.nodeName === 'IMG') {
                selectImage(event.target, true);
            } else {
                clearImage();
            }
        };

        const onFocus = () => {
            try {
                document.execCommand('defaultParagraphSeparator', false, 'p');
            } catch (error) {
                // Desteklemeyen tarayicida kok duzeltme (normalizeRoot) ayni sonucu verir.
            }
        };

        const onBlur = () => {
            engine.commit();
            flushEmit();
        };

        /* --- Arac cubugu -------------------------------------------------------------- */

        const tool = (key, icon, label, action, options) => {
            const extra = options || {};
            const config = {
                key,
                icon,
                label,
                variant: 'plain',
                size: 'sm',
                className: 'ks-editor__tool',
                disabled: disabled || !!extra.disabled,
                onMouseDown: keep,
                onClick: action,
            };

            if (extra.toggle) {
                config.active = !!extra.active;
                config.pressed = !!extra.active;
            }

            if (extra.shortcut) {
                config.title = shortcutTitle(label, extra.shortcut);
                config['aria-keyshortcuts'] = (IS_MAC ? 'Meta+' : 'Control+') + extra.shortcut;
            }

            return h(KS.IconButton, config);
        };

        const blockItems = [
            { key: 'p', value: 'p', icon: 'paragraph', label: t('editor_block_p'), hint: t('editor_block_p_hint') },
            { key: 'h2', value: 'h2', icon: 'heading', label: t('editor_block_h2'), hint: t('editor_block_h2_hint') },
            { key: 'h3', value: 'h3', icon: 'heading', label: t('editor_block_h3'), hint: t('editor_block_h3_hint') },
            { key: 'h4', value: 'h4', icon: 'heading', label: t('editor_block_h4'), hint: t('editor_block_h4_hint') },
            { key: 'pre', value: 'pre', icon: 'code', label: t('editor_block_pre'), hint: t('editor_block_pre_hint') },
        ].map((item) => Object.assign(item, {
            active: active.block === item.value,
            onSelect: () => engine.run(() => engine.setBlock(item.value)),
        }));
        const currentBlock = blockItems.find((item) => item.active) || blockItems[0];

        const blockMenu = h(KS.Menu, {
            label: t('editor_block_type'),
            align: 'start',
            width: 260,
            items: blockItems,
            trigger: (triggerProps) => h(KS.Button, Object.assign({}, triggerProps, {
                variant: 'ghost',
                size: 'sm',
                icon: currentBlock.icon,
                iconRight: isMobile ? undefined : 'chevron-down',
                className: 'ks-editor__block',
                disabled,
                title: t('editor_block_type'),
                ariaLabel: t('editor_block_type') + ': ' + currentBlock.label,
                onMouseDown: keep,
            }), isMobile ? null : currentBlock.label),
        });

        const linkTool = h(Flyout, {
            title: active.link ? t('editor_link_edit') : t('editor_link_add'),
            icon: 'link',
            width: 360,
            onOpenChange: (next) => {
                if (next) {
                    engine.remember();
                    setLinkInitial(engine.linkInfo());
                }
            },
            trigger: (triggerProps) => h(KS.IconButton, Object.assign({}, triggerProps, {
                icon: 'link',
                label: t('editor_link'),
                title: shortcutTitle(t('editor_link'), 'K'),
                'aria-keyshortcuts': (IS_MAC ? 'Meta+' : 'Control+') + 'K',
                variant: 'plain',
                size: 'sm',
                className: 'ks-editor__tool',
                active: active.link,
                disabled,
                buttonRef: linkButtonRef,
                onMouseDown: keep,
            })),
        }, (close) => h(LinkForm, {
            initial: linkInitial,
            onApply: (href, text) => {
                close();
                later(() => engine.run(() => engine.applyLink(href, text)));
            },
            onRemove: () => {
                close();
                later(() => engine.run(() => engine.removeLink()));
            },
            onCancel: () => {
                close();
                later(() => engine.focus());
            },
        }));

        const tableTool = mode === 'blog' ? h(Flyout, {
            title: active.table ? t('editor_table_tools') : t('editor_table_insert'),
            icon: 'table',
            width: active.table ? 250 : (coarsePointer ? 296 : 264),
            onOpenChange: (next) => {
                if (next) {
                    engine.remember();
                }
            },
            trigger: (triggerProps) => h(KS.IconButton, Object.assign({}, triggerProps, {
                icon: 'table',
                label: active.table ? t('editor_table_tools') : t('editor_table_insert'),
                variant: 'plain',
                size: 'sm',
                className: 'ks-editor__tool',
                active: active.table,
                disabled,
                onMouseDown: keep,
            })),
        }, (close) => h(TablePanel, {
            active,
            onInsert: (rows, cols, header) => {
                close();
                later(() => engine.run(() => engine.insertTable(rows, cols, header)));
            },
            onOp: (op) => {
                close();
                later(() => engine.run(() => engine.tableOp(op)));
            },
        })) : null;

        const imageTool = canUpload ? tool('image', 'image', t('editor_image'), () => {
            engine.remember();

            if (fileRef.current) {
                fileRef.current.click();
            }
        }, { disabled: uploading > 0 }) : null;

        const group = (key, children) => h('div', { key, className: 'ks-editor__group', role: 'group' }, children);

        const toolbar = h('div', { className: 'ks-editor__toolbar', role: 'toolbar', 'aria-label': t('editor_toolbar'), 'aria-controls': surfaceId },
            group('block', blockMenu),
            group('marks', [
                tool('bold', 'bold', t('editor_bold'), exec(() => engine.inline('bold')), { toggle: true, active: active.bold, shortcut: 'B' }),
                tool('italic', 'italic', t('editor_italic'), exec(() => engine.inline('italic')), { toggle: true, active: active.italic, shortcut: 'I' }),
                tool('underline', 'underline', t('editor_underline'), exec(() => engine.inline('underline')), { toggle: true, active: active.underline, shortcut: 'U' }),
                tool('strike', 'strike', t('editor_strike'), exec(() => engine.inline('strikeThrough')), { toggle: true, active: active.strike }),
            ]),
            group('lists', [
                tool('ul', 'list-ul', t('editor_list_ul'), exec(() => engine.toggleList('ul')), { toggle: true, active: active.ul }),
                tool('ol', 'list-ol', t('editor_list_ol'), exec(() => engine.toggleList('ol')), { toggle: true, active: active.ol }),
                tool('quote', 'quote', t('editor_quote'), exec(() => engine.toggleQuote()), { toggle: true, active: active.quote }),
            ]),
            group('insert', [
                h(Fragment, { key: 'link' }, linkTool),
                imageTool,
                tableTool ? h(Fragment, { key: 'table' }, tableTool) : null,
                tool('hr', 'minus', t('editor_hr'), exec(() => engine.insertRule())),
            ]),
            group('code', [
                tool('code', 'code', t('editor_code'), exec(() => engine.toggleCode()), { toggle: true, active: active.code, disabled: active.block === 'pre' }),
                tool('clear', 'clear-format', t('editor_clear_format'), exec(() => engine.clearFormat())),
            ]),
            group('history', [
                tool('undo', 'undo', t('editor_undo'), () => engine.undo(), { shortcut: 'Z', disabled: !hist.undo }),
                tool('redo', 'redo', t('editor_redo'), () => engine.redo(), { shortcut: 'Y', disabled: !hist.redo }),
            ]),
        );

        /* --- Alt bilgi --------------------------------------------------------------------- */

        const limit = Number(props.maxBytes) > 0 ? Number(props.maxBytes) : (Number(KS.limits().body_html_max_bytes) || 0);
        let status = null;

        if (uploading > 0) {
            status = h(KS.Spinner, { size: 'sm', label: t('editor_image_uploading') });
        } else if (limit && stats.bytes > limit) {
            status = h(KS.Badge, { color: 'red', icon: 'alert', size: 'sm' }, t('editor_too_long', { used: KS.fmt.bytes(stats.bytes), max: KS.fmt.bytes(limit) }));
        } else if (limit && stats.bytes > limit * 0.85) {
            status = h(KS.Badge, { color: 'amber', icon: 'warning', size: 'sm' }, t('editor_near_limit', { used: KS.fmt.bytes(stats.bytes), max: KS.fmt.bytes(limit) }));
        }

        const style = {
            '--ks-editor-min-h': toLength(props.minHeight, mode === 'blog' ? '22rem' : '14rem'),
            '--ks-editor-sticky-top': toLength(props.stickyOffset, '0px'),
        };

        return h('div', { className: cx('ks-editor', 'ks-editor--' + mode, disabled && 'is-disabled', dragOver && 'is-dragover', props.className), style },
            toolbar,
            h('div', { ref: bodyRef, className: 'ks-editor__body' },
                h('div', {
                    ref: surfaceRef,
                    id: surfaceId,
                    className: 'ks-editor__surface ks-rich',
                    contentEditable: disabled ? 'false' : 'true',
                    suppressContentEditableWarning: true,
                    role: 'textbox',
                    'aria-multiline': 'true',
                    'aria-label': props.label || t('editor_label'),
                    'aria-disabled': disabled ? 'true' : undefined,
                    'data-placeholder': props.placeholder || t('editor_placeholder'),
                    'data-empty': empty ? 'true' : 'false',
                    spellCheck: true,
                    onInput: (event) => engine.input(!!(event.nativeEvent && event.nativeEvent.isComposing)),
                    onCompositionEnd: () => engine.input(false),
                    onKeyDown,
                    onPaste,
                    onDrop,
                    onDragEnter,
                    onDragLeave,
                    onDragOver,
                    onDragStart: () => {
                        dragRef.current.internal = true;
                    },
                    onClick,
                    onFocus,
                    onBlur,
                }),
                image && !disabled ? h('div', { className: 'ks-editor__imgframe', 'aria-hidden': 'true', style: { top: image.box.top + 'px', left: image.box.left + 'px', width: image.box.width + 'px', height: image.box.height + 'px' } }) : null,
                image && !disabled ? h(ImageTools, {
                    image: image.el,
                    box: image.box,
                    onAltOpenChange: (next) => {
                        altOpenRef.current = !!next;
                    },
                    onWidth: (width) => {
                        engine.run(() => engine.setImageWidth(image.el, width));
                        later(measureImage);
                    },
                    onAlt: (value) => {
                        altOpenRef.current = false;
                        engine.run(() => engine.setImageAlt(image.el, value));
                    },
                    onRemove: () => {
                        const target = image.el;

                        clearImage();
                        engine.run(() => engine.removeImage(target));
                    },
                }) : null,
                dragOver ? h('div', { className: 'ks-editor__drophint', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'upload' }), h('span', null, t('editor_drop_image'))) : null,
            ),
            h('div', { className: 'ks-editor__footer' },
                h('span', { className: 'ks-editor__counts' },
                    h('span', null, t('words', { count: KS.fmt.number(stats.words) })),
                    h('span', { className: 'ks-editor__dot', 'aria-hidden': 'true' }, '\u00B7'),
                    h('span', null, t('characters', { count: KS.fmt.number(stats.chars) })),
                ),
                h('span', { className: 'ks-editor__status', 'aria-live': 'polite' }, status),
            ),
            canUpload ? h('input', {
                ref: fileRef,
                type: 'file',
                accept: (Array.isArray(KS.limits().image_mimes) && KS.limits().image_mimes.length ? KS.limits().image_mimes.join(',') : 'image/*'),
                multiple: true,
                hidden: true,
                tabIndex: -1,
                'aria-hidden': 'true',
                onChange: (event) => {
                    const files = slice(event.target.files);

                    event.target.value = '';
                    uploadFiles(files);
                },
            }) : null,
        );
    }

    /* ================================================================== */
    /* 6. KS.RichContent (salt okunur)                                     */
    /* ================================================================== */

    function RichContent(props) {
        const html = useMemo(() => cleanHtml(props.html, { wrapTables: true }), [props.html]);

        if (html === '') {
            return props.emptyText ? h('p', { className: cx('ks-rich-empty', props.className) }, props.emptyText) : null;
        }

        return h('div', { className: cx('ks-rich', props.className), dangerouslySetInnerHTML: { __html: html } });
    }

    /* ================================================================== */
    /* 7. Kayit                                                            */
    /* ================================================================== */

    KS.RichEditor = RichEditor;
    KS.RichContent = RichContent;
    KS.richText = {
        clean: (html) => cleanHtml(html, null),
        isBlank: (html) => cleanHtml(html, null) === '',
        stats(html) {
            const tree = buildTree(parseBody(html), 'always');

            return textStats(plainText(tree));
        },
        imageSrc,
    };
}());
