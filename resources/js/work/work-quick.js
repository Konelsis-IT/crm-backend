/*
 * Konelsis - AKILLI IS EKLE (D-233, 21 Ekim 2026; D-235, 22 Ekim 2026).
 *
 * Kullanici (D-233): "Sadece bir tane textarea ekrani olacak ... (Departman,
 * personel, proje, teklif, is gelistirme, ihale secenekleri) secilirken direk
 * text area ekranina dashboard'taki renk koduyla birebir olacak sekilde badge
 * yapida aktarilacak ... 'Proje cizimi bitirilecek #aksa #melih' ... Om
 * yazildigi anda altta acilir sekilde Omer personeli gorunecek ... + butonu
 * ile yeni is yeni is yeni is diye eklenebilir olacak."
 *
 * D-235: "Akilli is ekle butonu hizli is ekle alaninin yerini alsin" (panoda
 * hep acik, aciklama yazilari olmadan); satirda "coglat"; gecmis tarihli is
 * ("is onceki gunlerin isi de olabilir"); isin butun alanlari rozet olarak
 * ("Efor, Is suresi, Kategori, Talep Eden, Durum, Tum Ekip, ... Bekletici";
 * satin alma yayina girdikce "Belge, Urun Hizmet, Talep, Gorusme, Siparis,
 * Satin alinacak urun"); duzenleme de ayni pencere; ust cubuktaki "Akilli is"
 * dugmesi her sayfadan acar; panoda karta tiklayinca is ayni okunusla gelir.
 *
 * Yapi:
 * - Her is bir duzenleyicidir (contenteditable). Rozet, duzenlenemeyen bir
 *   <span class="kq-badge"> dugumudur. Yazarken imlecin solundaki kelime
 *   (ya da # / @ ile baslayan) aranir; kucuk listeler (personel, departman,
 *   taahhut, teklif, is gelistirme, ihale) pencere acilinca bir kez yuklenir,
 *   belge / talep / gorusme / satin alma kayitlari yazarken sunucuda aranir.
 * - useComposer: arac cubugu + is satirlari + kaydet. Pencere (olustur /
 *   duzenle / yalniz oku), panodaki acik alan ve ust cubuk ayni bileseni
 *   kullanir.
 * - Rozet renkleri pano sutunlarinin renkleri (konelsis-work.css kq-t-*);
 *   sunucudaki okunus WorkItemSentence ile aynidir.
 *
 * Disari: window.KonelsisWorkQuick = { QuickAddModal, InlineComposer,
 * SentenceView, ExtraLinks, Icon }. Kok [data-kw-root="work-quick"]: ust
 * cubuk penceresi ("kq-open" / "kq-edit" olaylari; kayit sonrasi "kq-saved").
 */
(function () {
    'use strict';

    const KW = window.KonelsisWork;

    if (!KW || window.KonelsisWorkQuick) {
        return;
    }

    const { h, Fragment, cx, fmt, useApp, Btn, Modal, Portal } = KW;
    const { useState, useEffect, useRef, useCallback, useMemo } = KW.hooks;
    const useLayoutEffect = window.React.useLayoutEffect;
    const fold = KW.fold;

    /* ------------------------------------------------------------------ */
    /* Turler                                                               */
    /* ------------------------------------------------------------------ */

    // Pencere acilinca yuklenen kayitlar.
    const ENTITY_TYPES = ['person', 'unit', 'project', 'proposal', 'case', 'tender'];
    // Yazarken sunucuda aranan bagli kayitlar (gorunenler config.features.link_types).
    const REMOTE_TYPES = ['document', 'request', 'meeting', 'need', 'order', 'catalog'];
    // Alan rozetleri.
    const FIELD_TYPES = ['status', 'owner', 'start', 'due', 'hours', 'priority', 'discipline', 'category', 'team', 'requester', 'waiting'];
    const SOURCE = { person: 'people', unit: 'units', project: 'projects', proposal: 'proposals', case: 'cases', tender: 'tenders' };
    const LINK_KIND = {
        proposal: 'proposal', case: 'business_case', tender: 'tender_notice', document: 'document', request: 'work_request',
        meeting: 'meeting_plan', need: 'purchase_need', order: 'purchase_order', catalog: 'catalog_item', supply: 'supply_item',
    };
    // Bir iste en fazla bir tane: yenisi eskisinin yerine gecer.
    const SINGLE = {
        unit: true, project: true, status: true, start: true, due: true, hours: true, priority: true,
        discipline: true, category: true, team: true, requester: true, waiting: true,
    };
    const TYPE_ORDER = { person: 0, project: 1, unit: 2, case: 3, proposal: 4, tender: 5, document: 6, request: 7, meeting: 8, need: 9, order: 10, catalog: 11 };
    const isField = (type) => FIELD_TYPES.indexOf(type) !== -1;
    // D-247: son satirin (durum, Sorumlu, baslangic, termin, efor, kritiklik) sirasi.
    const META_ORDER = { status: 0, owner: 1, start: 2, due: 3, hours: 4, priority: 5 };
    const isLineTwo = (type) => META_ORDER[type] !== undefined && type !== 'status' && type !== 'owner';

    // Heroicons 24'luk cizgi yollari (Filament'in simge seti).
    const PATHS = {
        person: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
        unit: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
        team: 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
        project: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0',
        proposal: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        case: 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
        tender: 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46',
        document: 'm18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13',
        request: 'M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3',
        meeting: 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155',
        need: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
        order: 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75',
        catalog: 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        supply: 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
        status: 'M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
        due: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        start: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
        hours: 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        priority: 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5',
        discipline: 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z',
        category: 'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122',
        requester: 'M10.05 4.575a1.575 1.575 0 1 0-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 0 1 3.15 0v1.5m-3.15 0 .075 5.925m3.075.75V4.575m0 0a1.575 1.575 0 0 1 3.15 0V15M6.9 7.575a1.575 1.575 0 1 0-3.15 0v8.175a6.75 6.75 0 0 0 6.75 6.75h2.018a5.25 5.25 0 0 0 3.712-1.538l1.732-1.732a5.25 5.25 0 0 0 1.538-3.712l.003-2.024a.668.668 0 0 1 .198-.471 1.575 1.575 0 1 0-2.228-2.228 3.818 3.818 0 0 0-1.12 2.687M6.9 7.575V12m6.27 4.318A4.49 4.49 0 0 1 16.35 15m.002 0h-.002',
        waiting: 'M14.25 9v6m-4.5 0V9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        party: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
        text: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
        bolt: 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        plus: 'M12 4.5v15m7.5-7.5h-15',
        close: 'M6 18 18 6M6 6l12 12',
        copy: 'M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75',
        check: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        warn: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        search: 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
        chevron: 'm19.5 8.25-7.5 7.5-7.5-7.5',
        fields: 'M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75',
        link: 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
        sun: 'M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z',
        week: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z',
        report: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        arrow: 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        archive: 'm20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z',
        edit: 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
        undo: 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
        mic: 'M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z',
        stop: 'M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z',
        eye: 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        lock: 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
        badge: 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z',
        clipboard: 'M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184',
        upload: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5',
        download: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
        left: 'M15.75 19.5 8.25 12l7.5-7.5',
        right: 'm8.25 4.5 7.5 7.5-7.5 7.5',
        send: 'M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5',
        cog: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    };

    // Cevaplar (D-241): gorusme simgesiyle ayni cizgi.
    PATHS.chat = PATHS.meeting;

    function Icon(props) {
        return h('svg', {
            className: cx('kq-icon', props.className),
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: props.strokeWidth || 1.8,
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            'aria-hidden': 'true',
        }, h('path', { d: PATHS[props.name] || '' }));
    }

    function iconNode(name) {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.9');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('class', 'kq-icon');
        const path = document.createElementNS(ns, 'path');
        path.setAttribute('d', PATHS[name] || '');
        svg.appendChild(path);

        return svg;
    }

    /* ------------------------------------------------------------------ */
    /* Tarih ve efor                                                        */
    /* ------------------------------------------------------------------ */

    const pad = (n) => (n < 10 ? '0' : '') + n;
    const DAY_KEYS = ['pazar', 'pazartesi', 'sali', 'carsamba', 'persembe', 'cuma', 'cumartesi'];
    const MONTH_KEYS = ['ocak', 'subat', 'mart', 'nisan', 'mayis', 'haziran', 'temmuz', 'agustos', 'eylul', 'ekim', 'kasim', 'aralik'];

    /** Kelimenin basladigi en uzun anahtar ("cumaya" -> cuma, "pazartesiye" -> pazartesi). */
    function keyOf(keys, q) {
        let found = -1;

        keys.forEach((key, index) => {
            if ((key.indexOf(q) === 0 && q.length >= 3) || q.indexOf(key) === 0) {
                if (found === -1 || key.length > keys[found].length) { found = index; }
            }
        });

        return found;
    }

    /** "25 ekim": onceki kelime gun, bu kelime ay. */
    const monthDay = (word, prev) => /^\d{1,2}$/.test(String(prev || '').trim()) && keyOf(MONTH_KEYS, fold(word).trim()) !== -1;

    /** Tarih rozeti onceki kelimeyi de kapsar mi ("gecen cuma", "25 ekim"). */
    const dateUsesPrev = (word, prev) => fold(prev || '') === 'gecen' || monthDay(word, prev);

    function parseYmd(ymd) {
        const parts = String(ymd || '').split('-').map(Number);

        return parts.length === 3 ? new Date(Date.UTC(parts[0], parts[1] - 1, parts[2])) : null;
    }

    const toYmd = (date) => date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());

    function addDays(ymd, days) {
        const date = parseYmd(ymd);
        date.setUTCDate(date.getUTCDate() + days);

        return toYmd(date);
    }

    function validYmd(y, m, d) {
        const date = new Date(Date.UTC(y, m - 1, d));

        return date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d ? toYmd(date) : null;
    }

    /** Gun adinin bir sonraki (ya da "gecen" ile bir onceki) tarihi. */
    function weekday(today, index, back) {
        const current = parseYmd(today).getUTCDay();

        return back
            ? addDays(today, -(((current - index + 7) % 7) || 7))
            : addDays(today, ((index - current + 7) % 7) || 7);
    }

    /**
     * Yazilan kelimeden tarih: bugun, yarin, obur gun, haftaya, dun, evvelsi
     * gun, gun adlari (sonraki; "gecen" ile onceki), "gecen hafta", 25.10 /
     * 25.10.2026 / 25/10. Bulunamazsa null.
     */
    function dateFromWord(word, today, prev) {
        const q = fold(word).trim();
        const back = fold(prev || '') === 'gecen';

        if (!q) { return null; }

        const numeric = q.match(/^(\d{1,2})[./-](\d{1,2})(?:[./-](\d{2,4}))?$/);

        if (numeric) {
            const year = numeric[3] ? (numeric[3].length === 2 ? 2000 + Number(numeric[3]) : Number(numeric[3])) : Number(today.slice(0, 4));

            return validYmd(year, Number(numeric[2]), Number(numeric[1]));
        }

        if (monthDay(word, prev)) {
            return validYmd(Number(today.slice(0, 4)), keyOf(MONTH_KEYS, q) + 1, Number(String(prev).trim()));
        }

        if (q.length < 3) { return null; }

        if ('bugun'.indexOf(q) === 0 || q.indexOf('bugun') === 0) { return today; }
        if ('yarin'.indexOf(q) === 0 || q.indexOf('yarin') === 0) { return addDays(today, 1); }
        if (q === 'dun' || (q.indexOf('dun') === 0 && q.length <= 6 && q.indexOf('duny') !== 0)) { return addDays(today, -1); }
        if (q.indexOf('obur') === 0) { return addDays(today, 2); }
        if (q.indexOf('evvelsi') === 0 || q.indexOf('onceki') === 0) { return addDays(today, -2); }
        if ('haftaya'.indexOf(q) === 0) { return addDays(today, 7); }
        if (back && 'hafta'.indexOf(q) === 0) { return addDays(today, -7); }
        if (['gelecek', 'onumuzdeki', 'haftaya'].indexOf(fold(prev || '')) !== -1 && q.indexOf('hafta') === 0) { return addDays(today, 7); }

        const day = keyOf(DAY_KEYS, q);

        return day !== -1 ? weekday(today, day, back) : null;
    }

    const dateLabel = (ymd) => fmt.date(ymd) + ' ' + fmt.dayName(ymd);

    /** Efor: "2s", "2sa", "2saat", "1,5saat", "90dk"; once sayi sonra "saat" yazildiysa da. */
    function hoursFromWord(word, prev) {
        const q = fold(word).replace(',', '.');
        const both = q.match(/^(\d+(?:\.\d+)?)(s|sa|saat|h)$/);

        if (both) { return Number(both[1]); }

        const minutes = q.match(/^(\d+)(dk|dakika)$/);

        if (minutes) { return Math.round((Number(minutes[1]) / 60) * 100) / 100; }

        const p = fold(prev || '').replace(',', '.');

        if (/^(s|sa|saat\w*|dk|dakika\w*)$/.test(q) && /^\d+(?:\.\d+)?$/.test(p)) {
            return q === 'dk' || q.indexOf('dakika') === 0 ? Math.round((Number(p) / 60) * 100) / 100 : Number(p);
        }

        return null;
    }

    const hoursText = (value) => String(Math.round(Number(value) * 100) / 100).replace('.', ',');

    /* ------------------------------------------------------------------ */
    /* Secenekler ve arama                                                  */
    /* ------------------------------------------------------------------ */

    let optionsCache = null;

    /** Pencere secenekleri (sayfa boyu 5 dk onbellek; kayitlar sonradan eklenebilir). */
    function loadOptions(api, url) {
        if (!optionsCache || Date.now() - optionsCache.at > 300000) {
            optionsCache = { at: Date.now(), promise: api.get(url).catch((error) => { optionsCache = null; throw error; }) };
        }

        return optionsCache.promise;
    }

    const remoteCache = new Map();

    function remoteSearch(api, url, query) {
        const key = fold(query).trim();

        if (!url || key.length < 2) { return Promise.resolve([]); }

        if (!remoteCache.has(key)) {
            remoteCache.set(key, api.get(url, { q: query }).then((json) => json.items || []).catch(() => { remoteCache.delete(key); return []; }));
        }

        return remoteCache.get(key);
    }

    const foldCode = (value) => fold(value).replace(/[^a-z0-9]/g, '');
    const words = (folded) => ' ' + folded.replace(/[^a-z0-9]+/g, ' ').trim() + ' ';

    function indexRow(type, row) {
        return {
            type,
            id: row.id,
            label: row.label,
            sub: [row.party, row.sub].filter(Boolean).join(' · ') || null,
            code: row.code || null,
            long: row.long && row.long !== row.label ? row.long : null,
            fLabel: fold(row.label),
            fLong: row.long ? fold(row.long) : '',
            fCode: row.code ? foldCode(row.code) : '',
            wLabel: words(fold(row.label)),
            wLong: row.long ? words(fold(row.long)) : '',
            wParty: row.party ? words(fold(row.party)) : '',
        };
    }

    function buildIndex(options) {
        const rows = [];

        ENTITY_TYPES.forEach((type) => {
            ((options && options[SOURCE[type]]) || []).forEach((row) => {
                // Atanamayan kisi sorumlu rozeti olamaz (baskasina is yazma kurali).
                if (type === 'person' && row.assignable === false) { return; }

                rows.push(indexRow(type, row));
            });
        });

        return rows;
    }

    /**
     * Siralama: tam ad > adda tam kelime > isverende tam kelime (taahhut) > adin
     * basi > adda kelime basi > kod > uzun ad > isveren kelime basi > icinde.
     */
    function scoreOf(row, q, qCode) {
        const word = ' ' + q + ' ';
        const start = ' ' + q;

        if (row.fLabel === q) { return 120; }
        if (row.wLabel.indexOf(word) !== -1) { return 110; }
        if (q.length >= 3 && row.wParty && row.wParty.indexOf(word) !== -1) { return row.type === 'project' ? 100 : 72; }
        if (row.fLabel.indexOf(q) === 0) { return 92 - Math.min(20, row.fLabel.length / 10); }
        if (row.wLabel.indexOf(start) !== -1) { return 82; }
        if (qCode.length >= 2 && row.fCode && row.fCode.indexOf(qCode) !== -1) { return 76; }
        if (row.wLong && row.wLong.indexOf(start) !== -1) { return 66; }
        if (q.length >= 3 && row.wParty && row.wParty.indexOf(start) !== -1) { return row.type === 'project' ? 60 : 44; }
        if (q.length >= 3 && row.fLabel.indexOf(q) !== -1) { return 50; }
        if (q.length >= 3 && row.fLong && row.fLong.indexOf(q) !== -1) { return 40; }

        return 0;
    }

    function searchRows(rows, query, onlyType, limit) {
        const q = fold(query).trim();
        const qCode = foldCode(query);
        const scored = [];

        rows.forEach((row) => {
            if (onlyType && row.type !== onlyType) { return; }

            const score = q ? scoreOf(row, q, qCode) : 1;

            if (score > 0) { scored.push([score, row]); }
        });

        scored.sort((a, b) => (b[0] - a[0]) || ((TYPE_ORDER[a[1].type] || 0) - (TYPE_ORDER[b[1].type] || 0)) || a[1].label.localeCompare(b[1].label, 'tr'));

        return scored.slice(0, limit || 8).map((pair) => pair[1]);
    }

    const FIELD_WORDS = {
        due: ['termin', 'son tarih', 'son gun', 'bitis', 'teslim', 'deadline', 'tarih'],
        start: ['baslangic', 'baslama', 'basla', 'is tarihi', 'tarih', 'gun'],
        hours: ['efor', 'sure', 'is suresi', 'saat', 'calisma'],
        status: ['durum'],
        priority: ['oncelik', 'kritiklik', 'acil', 'onemli'],
        discipline: ['is grubu', 'grubu', 'grup'],
        category: ['kategori'],
        team: ['tum ekip', 'ekip', 'herkes'],
        requester: ['talep eden', 'isteyen', 'talep'],
        waiting: ['bekletici', 'bekleme', 'bekleniyor', 'bekle'],
    };

    const matchesWord = (list, q) => q.length >= 3 && list.some((word) => word.indexOf(q) === 0 || (q.length >= 4 && q.indexOf(word) === 0));

    /** Alan onerileri; deger isteyenler (tarih yok, efor yok, talep eden...) secim penceresi acar. */
    function searchFields(token, ctx) {
        const query = token.query;
        const q = fold(query).trim();
        const out = [];

        if (!q) { return out; }

        const date = dateFromWord(query, ctx.today, token.prev);

        if (date) {
            const due = { type: 'due', value: date, label: ctx.t('qa_type_due'), sub: dateLabel(date) };
            const start = { type: 'start', value: date, label: ctx.t('qa_type_start'), sub: dateLabel(date) };

            // Gecmis gun (dun, gecen cuma) cogunlukla onceki gunun isidir: once Baslangic.
            if (date < ctx.today) { out.push(start, due); } else { out.push(due, start); }
        }

        ['due', 'start'].forEach((type) => {
            if (!date && matchesWord(FIELD_WORDS[type], q)) {
                out.push({ type, value: null, label: ctx.t('qa_type_' + type), sub: ctx.t('qa_pick_date') });
            }
        });

        const hours = hoursFromWord(query, token.prev);

        if (hours !== null && hours > 0) {
            out.push({ type: 'hours', value: hours, label: ctx.t('qa_type_hours'), sub: hoursText(hours) + ' ' + ctx.t('qa_hours_unit') });
        } else if (matchesWord(FIELD_WORDS.hours, q)) {
            out.push({ type: 'hours', value: null, label: ctx.t('qa_type_hours'), sub: ctx.t('qa_pick_hours') });
        }

        const allStatus = matchesWord(FIELD_WORDS.status, q);
        (ctx.statuses || []).forEach((status) => {
            if (allStatus || (q.length >= 3 && fold(status.label).indexOf(q) === 0)) {
                out.push({ type: 'status', value: status.value, label: status.label, sub: ctx.t('qa_type_status') });
            }
        });

        if (ctx.extended) {
            const all = matchesWord(FIELD_WORDS.priority, q);
            const levels = [];
            (ctx.priorities || []).forEach((level) => {
                const byName = q.length >= 3 && fold(level.label).indexOf(q) === 0;
                const urgent = q.indexOf('acil') === 0 && (level.value === 'critical' || level.value === 'high');

                if (all || byName || urgent) {
                    levels.push([byName || urgent ? 2 : 1, { type: 'priority', value: level.value, label: ctx.t('qa_type_priority') + ': ' + level.label, text: level.label }]);
                }
            });
            levels.sort((a, b) => b[0] - a[0]).forEach((pair) => out.push(pair[1]));

            const groups = matchesWord(FIELD_WORDS.discipline, q);
            const found = [];
            (ctx.disciplines || []).forEach((group) => {
                const byName = q.length >= 3 && (' ' + fold(group.label)).indexOf(' ' + q) !== -1;

                if (groups || byName) {
                    found.push([byName ? 2 : 1, { type: 'discipline', value: group.value, label: ctx.t('qa_type_discipline') + ': ' + group.label, text: group.label }]);
                }
            });
            found.sort((a, b) => b[0] - a[0]).forEach((pair) => out.push(pair[1]));

            if (ctx.canShare && matchesWord(FIELD_WORDS.team, q)) {
                out.push({ type: 'team', value: '1', label: ctx.t('qa_type_team'), sub: ctx.t('qa_team_help') });
            }
        }

        if (q.length >= 3) {
            const cats = matchesWord(FIELD_WORDS.category, q);
            (ctx.categories || []).filter((cat) => cats || (' ' + fold(cat.label)).indexOf(' ' + q) !== -1).slice(0, 4).forEach((cat) => {
                out.push({ type: 'category', value: cat.value, label: ctx.t('qa_type_category') + ': ' + cat.label, text: cat.label });
            });
        }

        if (matchesWord(FIELD_WORDS.requester, q)) {
            out.push({ type: 'requester', value: null, label: ctx.t('qa_type_requester'), sub: ctx.t('qa_pick_source') });
        }

        if (matchesWord(FIELD_WORDS.waiting, q)) {
            out.push({ type: 'waiting', value: null, label: ctx.t('qa_type_waiting'), sub: ctx.t('qa_pick_source') });
        }

        return out;
    }

    /*
     * Sesle yazma (D-236, kullanici 22 Ekim 2026: "bu hizli is olusturmayi ses ile
     * kayit yapacak sekilde gelistirelim. Yani biz konusurken yazsin. Konusurken
     * de yine tavsiyelerle ilgili baglantili konuyu bulsun"). Soylenen her cumlede
     * kisi, departman, taahhut, teklif, is gelistirme, ihale, tarih, efor ve durum
     * aranir; bulunanlar oneri olarak gosterilir, secilince rozet olur.
     */
    const VOICE_STOP = ['ve', 'ile', 'bir', 'bu', 'su', 'icin', 'da', 'de', 'mi', 'mu', 'ki', 'olan', 'olarak', 'gibi', 'kadar', 'sonra', 'once', 'ama', 'veya', 'ya', 'ne', 'cok', 'daha', 'en', 'her', 'tum', 'is', 'isi', 'isin', 'proje', 'projesi', 'teklif', 'teklifi', 'ihale', 'ihalesi', 'gun', 'saat', 'hafta', 'ay', 'yil'];
    const VOICE_SUFFIXES = ['nin', 'nun', 'dan', 'den', 'tan', 'ten', 'yla', 'yle', 'nda', 'nde', 'na', 'ne', 'ya', 'ye', 'da', 'de', 'ta', 'te', 'la', 'le', 'in', 'un', 'yi', 'yu', 'i', 'u', 'a', 'e'];

    function entityMatches(q, ctx) {
        const qCode = foldCode(q);
        const scored = [];

        (ctx.index || []).forEach((row) => {
            const score = scoreOf(row, q, qCode);

            // Tam ad, adda tam kelime ya da taahhudun isvereni.
            if (score >= 110 || (score === 100 && row.type === 'project')) { scored.push([score, row]); }
        });

        if (!scored.length) { return []; }

        scored.sort((a, b) => b[0] - a[0]);
        const top = scored[0][0];
        const rows = scored.filter((pair) => pair[0] === top).map((pair) => pair[1]);

        // Cok yaygin kelime (bircok kayitta geciyor): oneri gurultusu olmasin.
        return rows.length <= 4 ? rows : [];
    }

    const NUMBER_WORDS = {
        sifir: 0, bir: 1, iki: 2, uc: 3, dort: 4, bes: 5, alti: 6, yedi: 7, sekiz: 8, dokuz: 9,
        on: 10, yirmi: 20, otuz: 30, kirk: 40, elli: 50, altmis: 60, yarim: 0.5,
    };

    /** Soylenen kelimeler: ham yazi + aranacak bicim ("yirmi bes" -> 25, "bir bucuk" -> 1.5). */
    function voiceTokens(text) {
        const raw = (String(text).match(/[^\s,;:!?()"]+/g) || []).map((word) => word.replace(/[.…]+$/, '')).filter(Boolean);
        const tokens = [];

        for (let i = 0; i < raw.length; i++) {
            const q = fold(raw[i]);

            if (NUMBER_WORDS[q] !== undefined) {
                let value = NUMBER_WORDS[q];
                let phrase = raw[i];

                if (value >= 10 && value % 10 === 0 && raw[i + 1] && NUMBER_WORDS[fold(raw[i + 1])] !== undefined && NUMBER_WORDS[fold(raw[i + 1])] < 10 && NUMBER_WORDS[fold(raw[i + 1])] >= 1) {
                    value += NUMBER_WORDS[fold(raw[i + 1])];
                    phrase += ' ' + raw[i + 1];
                    i++;
                }

                if (raw[i + 1] && fold(raw[i + 1]) === 'bucuk') {
                    value += 0.5;
                    phrase += ' ' + raw[i + 1];
                    i++;
                }

                tokens.push({ raw: phrase, base: String(value), q: String(value) });
                continue;
            }

            const base = raw[i].split(/['’]/)[0];
            tokens.push({ raw: raw[i], base, q: fold(base) });
        }

        return tokens;
    }

    const ROLE_WORDS = {
        waiting: ['beklet', 'bekliyor', 'bekleniyor', 'beklen', 'beklemede', 'bekleyen'],
        requester: ['talep', 'istedi', 'istiyor', 'istegi', 'istek', 'rica'],
    };

    /** Kelimenin yakininda (3 kelime) rol sozu var mi ("Ozgur bekletti", "Huseyin Bey istedi"). */
    function roleNear(tokens, index, role) {
        for (let k = Math.max(0, index - 3); k <= Math.min(tokens.length - 1, index + 3); k++) {
            if (k !== index && ROLE_WORDS[role].some((word) => tokens[k].q.indexOf(word) === 0)) { return true; }
        }

        return false;
    }

    function labelMatches(list, tokens, i) {
        const found = [];

        (list || []).forEach((option) => {
            const code = foldCode(option.label);
            const size = (fold(option.label).match(/\S+/g) || []).length;

            if (!code || size < 1 || i + size > tokens.length) { return; }

            const phrase = tokens.slice(i, i + size);

            if (foldCode(phrase.map((tok) => tok.base).join(' ')) === code) {
                found.push({ option, phrase: phrase.map((tok) => tok.raw).join(' '), size });
            }
        });

        return found;
    }

    function voiceHits(text, ctx) {
        const tokens = voiceTokens(text);
        const hits = [];
        const remote = [];
        const seen = {};
        const add = (phrase, item) => {
            const key = item.type + ':' + (item.id || item.value);

            if (seen[key]) { return; }

            seen[key] = true;
            hits.push({ phrase, item });
        };
        const priorityContext = tokens.some((tok) => tok.q.indexOf('kritiklik') === 0 || tok.q.indexOf('oncelik') === 0 || tok.q.indexOf('onem') === 0);
        const priorityOf = (value) => (ctx.priorities || []).find((level) => level.value === value);
        const addPriority = (phrase, value) => {
            const level = priorityOf(value);

            if (ctx.extended && level) { add(phrase, { type: 'priority', value, label: ctx.t('qa_type_priority') + ': ' + level.label, text: level.label, src: 'field' }); }
        };

        for (let i = 0; i < tokens.length; i++) {
            const tok = tokens[i];
            const prev = tokens[i - 1] || null;
            const next = tokens[i + 1] || null;
            const q = tok.q;
            const date = dateFromWord(tok.base, ctx.today, prev ? prev.base : '');

            if (date && (q.length >= 3 || /\d/.test(q))) {
                const withPrev = prev && (dateUsesPrev(tok.base, prev.base) || ['gelecek', 'onumuzdeki', 'haftaya'].indexOf(prev.q) !== -1 && q.indexOf('hafta') === 0);
                const phrase = withPrev ? prev.raw + ' ' + tok.raw : tok.raw;
                const first = date < ctx.today ? 'start' : 'due';
                const second = first === 'start' ? 'due' : 'start';
                add(phrase, { type: first, value: date, label: ctx.t('qa_type_' + first), src: 'field' });
                add(phrase, { type: second, value: date, label: ctx.t('qa_type_' + second), src: 'field' });
                continue;
            }

            const hours = hoursFromWord(tok.base, prev ? prev.base : '');

            if (hours !== null && hours > 0) {
                add(/^(s|sa|saat\w*|dk|dakika\w*)$/.test(q) && prev ? prev.raw + ' ' + tok.raw : tok.raw, { type: 'hours', value: hours, label: ctx.t('qa_type_hours'), src: 'field' });
                continue;
            }

            // Kritiklik: "kritik", "acil"; "kritiklik seviyesi yuksek", "oncelik dusuk".
            if (q.indexOf('kritik') === 0 && q.indexOf('kritiklik') !== 0) { addPriority(tok.raw, 'critical'); }
            if (q.indexOf('acil') === 0) { addPriority(tok.raw, 'critical'); addPriority(tok.raw, 'high'); }
            if (priorityContext && q.indexOf('yuksek') === 0) { addPriority(tok.raw, 'high'); }
            if (priorityContext && q.indexOf('dusuk') === 0) { addPriority(tok.raw, 'low'); }
            if (priorityContext && q === 'normal') { addPriority(tok.raw, 'normal'); }

            // Durum ("tamamlandi", "devam ediyor"), kategori, is grubu: adiyla.
            labelMatches(ctx.statuses, tokens, i).forEach((m) => add(m.phrase, { type: 'status', value: m.option.value, label: m.option.label, src: 'field' }));

            if (q.length >= 5) {
                (ctx.statuses || []).forEach((status) => {
                    if (fold(status.label).indexOf(q) === 0) { add(tok.raw, { type: 'status', value: status.value, label: status.label, src: 'field' }); }
                });
            }

            labelMatches(ctx.categories, tokens, i).forEach((m) => add(m.phrase, { type: 'category', value: m.option.value, label: ctx.t('qa_type_category') + ': ' + m.option.label, text: m.option.label, src: 'field' }));

            if (ctx.extended) {
                labelMatches(ctx.disciplines, tokens, i).forEach((m) => add(m.phrase, { type: 'discipline', value: m.option.value, label: ctx.t('qa_type_discipline') + ': ' + m.option.label, text: m.option.label, src: 'field' }));
            }

            if (ctx.canShare && ((q === 'tum' && next && next.q.indexOf('ekip') === 0) || q === 'herkes' || q === 'herkese')) {
                add(q === 'tum' ? tok.raw + ' ' + next.raw : tok.raw, { type: 'team', value: '1', label: ctx.t('qa_type_team'), src: 'field' });
            }

            // Kayitlar: iki kelimelik ad once ("Melih Kol", "Akfen Elektrik").
            let rows = [];
            let phrase = tok.raw;
            let used = 1;

            if (next) {
                rows = entityMatches(fold(tok.base + ' ' + next.base), ctx);

                if (rows.length) {
                    phrase = tok.raw + ' ' + next.raw;
                    used = 2;
                }
            }

            if (!rows.length && q.length >= 3 && VOICE_STOP.indexOf(q) === -1 && !/^\d/.test(q)) {
                rows = entityMatches(q, ctx);

                // Turkce ekler: "Melih'e" kesme isaretiyle ayrilir; "aksanin" gibi ekler denenir.
                for (let s = 0; !rows.length && tok.base === tok.raw && s < VOICE_SUFFIXES.length; s++) {
                    const suffix = VOICE_SUFFIXES[s];

                    if (q.length - suffix.length >= 4 && q.slice(-suffix.length) === suffix) {
                        rows = entityMatches(q.slice(0, -suffix.length), ctx);
                    }
                }
            }

            if (rows.length) {
                rows.forEach((row) => {
                    // "Ozgur bekletti" -> Bekletici; "Huseyin istedi" -> Talep eden (kisi olarak da).
                    if (row.type === 'person') {
                        ['waiting', 'requester'].forEach((role) => {
                            if (roleNear(tokens, i, role)) {
                                add(phrase, { type: role, kind: 'personnel', id: row.id, label: row.label, src: 'role' });
                            }
                        });
                    }

                    add(phrase, { ...row, src: 'entity' });
                });

                i += used - 1;
                continue;
            }

            // Kodlu kayit (SAT-2026-0012, TKLF...) ya da ozel ad: belge, talep, gorusme,
            // satin alma kayitlari ve bekletici / talep eden firma sunucuda aranir.
            const code = /^[a-zA-Z]{2,}[-/]?\d/.test(tok.base);
            const proper = i > 0 && /^[A-ZÇĞİÖŞÜ]/.test(tok.base) && q.length >= 4 && VOICE_STOP.indexOf(q) === -1;

            if ((code || proper) && remote.length < 3) {
                remote.push({ raw: tok.raw, base: tok.base, waiting: roleNear(tokens, i, 'waiting'), requester: roleNear(tokens, i, 'requester') });
            }
        }

        return { hits, remote };
    }

    /** Sunucudaki kayitlar (kodla ya da adda tam kelimeyle; cok sonuclu kelime atlanir). */
    function voiceRemote(candidates, ctx) {
        if (!ctx.searchUrl || !candidates.length) { return Promise.resolve([]); }

        return Promise.all(candidates.map((candidate) => remoteSearch(ctx.api, ctx.searchUrl, candidate.base).then((rows) => {
            const word = ' ' + fold(candidate.base) + ' ';
            const code = foldCode(candidate.base);
            const out = [];
            const matching = rows.filter((row) => words(fold(row.label)).indexOf(word) !== -1 || (row.code && foldCode(row.code).indexOf(code) !== -1));

            if (!matching.length || matching.length > 4) { return out; }

            matching.forEach((row) => {
                if (row.type === 'party') {
                    ['waiting', 'requester'].forEach((role) => {
                        if (candidate[role]) { out.push({ phrase: candidate.raw, item: { type: role, kind: 'party', id: row.id, label: row.label, src: 'role' } }); }
                    });
                } else if (ctx.linkTypes.indexOf(row.type) !== -1) {
                    out.push({ phrase: candidate.raw, item: { ...indexRow(row.type, row), src: 'entity' } });
                }
            });

            return out;
        }))).then((lists) => [].concat(...lists));
    }

        /** Duzenleyicide soylenen ifadenin yeri (sondan; rozet ve gecici yazi haric). */
    function findPhrase(root, phrase) {
        const nodes = [];
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => (node.parentNode.closest && node.parentNode.closest('.kq-badge, .kq-interim') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });

        while (walker.nextNode()) { nodes.push(walker.currentNode); }

        const needle = phrase.toLocaleLowerCase('tr-TR');

        for (let i = nodes.length - 1; i >= 0; i--) {
            const at = nodes[i].data.toLocaleLowerCase('tr-TR').lastIndexOf(needle);

            if (at !== -1) {
                return { node: nodes[i], start: at, prevStart: at, end: at + phrase.length, prev: '', trigger: '', query: phrase };
            }
        }

        return null;
    }

    const PHRASE_FIELDS = ['status', 'team', 'category', 'priority', 'discipline'];

    /**
     * Isin duz yazisi (D-240; kullanici 23 Ekim 2026: "Metnin duz yazi versiyonu
     * da gosterilmeli"): metin aynen, rozet okunus metniyle ("Termin: 23.10.2026"),
     * satirlar alt alta. Duzenleyici modeli ya da kayitli okunus (segments) alir.
     */
    function plainText(items, t) {
        return (items || []).map((item) => {
            if (item.t === 'text') { return item.v; }
            if (item.t === 'br') { return '\n'; }

            const badge = item.b;

            if (badge.type === 'owner' && (badge.implicit || !badge.label)) { return ' ' + t('qa_owner_me') + ' '; }
            if (badge.type === 'person' && badge.role === 'owner') { return ' ' + t('qa_owner', { name: badge.label }) + ' '; }

            return ' ' + badgeText(badge, t) + ' ';
        }).join('').replace(/[ \t ]+/g, ' ').replace(/ *\n */g, '\n').trim();
    }

    /** Panoya kopyala (guvenli baglantida tarayici panosu, yoksa secip kopyala). */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();

        return Promise.resolve();
    }

    /** Duz yazi kutusu: metin ve Kopyala (rapor denemesinin Duz yazi onizlemesi). */
    function PlainBox(props) {
        const { text } = props;
        const app = useApp();
        const { t } = app;

        return h('div', { className: 'kq-plain' },
            h('p', { className: 'kq-plain-text' }, text || '–'),
            h('button', {
                type: 'button',
                className: 'kq-plain-copy',
                onMouseDown: (event) => event.preventDefault(),
                onClick: () => copyText(text).then(() => app.toast({ text: t('qa_plain_copied'), tone: 'ok' })).catch(app.fail),
            }, h(Icon, { name: 'clipboard' }), t('qa_plain_copy')),
        );
    }

    /*
     * Okuma bicimi (D-241; kullanici 23 Ekim 2026: "ilgili satir istenirse duz yazi
     * veya badge'li hale gelebilecek. Altinda alan acilmasin. Duz yazi formatinda
     * duzenleme yapilamasin. kopyalama islemi her zaman sabit buton olarak kalabilir.
     * Tum liste duz yazi formatina donusturulebilir. Hangi formattaysa o formatta
     * kalabilir"): rozetli ya da duz yazi; kisinin son secimi tarayicida kalir.
     * D-246 (kullanici: "isler listesinde default olarak is detayi duz yazi seklinde
     * gelsin"): varsayilan duz yazi; anahtar yenilendi, onceki secimler bir kez
     * duz yaziyla baslar.
     */
    const FORMAT_KEY = 'konelsis.work.format.v2';

    const storedFormat = () => (KW.storage.get(FORMAT_KEY, 'plain') === 'badge' ? 'badge' : 'plain');

    function useFormat() {
        const [format, setFormat] = useState(storedFormat);
        const change = useCallback((next) => {
            setFormat(next);
            KW.storage.set(FORMAT_KEY, next);
        }, []);

        return [format, change];
    }

    /** Rozetli / Duz yazi secimi (liste ve okuma penceresi). */
    function FormatSwitch(props) {
        const { value, onChange } = props;
        const { t } = useApp();

        return h('div', { className: 'kw-seg kq-format', role: 'radiogroup', 'aria-label': t('qa_format') }, [['badge', t('qa_badged')], ['plain', t('qa_plain')]].map(([key, label]) => h('button', {
            key,
            type: 'button',
            role: 'radio',
            'aria-checked': String(value === key),
            className: cx(value === key && 'on'),
            onClick: () => onChange(key),
        }, h(Icon, { name: key === 'plain' ? 'text' : 'badge' }), label)));
    }

    /** Tek satirin bicim dugmesi (rozetli <-> duz yazi). */
    function FormatToggle(props) {
        const { plain, onToggle, disabled } = props;
        const { t } = useApp();
        const label = plain ? t('qa_badged') : t('qa_plain');

        return h('button', {
            type: 'button',
            className: cx('kq-row-btn', plain && 'on'),
            title: label,
            'aria-label': label,
            disabled,
            onMouseDown: (event) => event.preventDefault(),
            onClick: onToggle,
        }, h(Icon, { name: plain ? 'badge' : 'text' }));
    }

    /** Sabit Kopyala dugmesi: her zaman duz yaziyi panoya alir. */
    function CopyButton(props) {
        const { text, disabled } = props;
        const app = useApp();
        const { t } = app;

        return h('button', {
            type: 'button',
            className: 'kq-row-btn',
            title: t('qa_plain_copy'),
            'aria-label': t('qa_plain_copy'),
            disabled: disabled || !text,
            onMouseDown: (event) => event.preventDefault(),
            onClick: (event) => {
                event.stopPropagation();
                copyText(text).then(() => app.toast({ text: t('qa_plain_copied'), tone: 'ok' })).catch(app.fail);
            },
        }, h(Icon, { name: 'clipboard' }));
    }

    /** Duz yazi okunusu (duzenlenemez). */
    function PlainView(props) {
        return h('p', { className: 'kq-plain-view', 'aria-readonly': 'true' }, props.text || '–');
    }

    /** Kayitli isin okunusu, istenen bicimde (D-247: son satir her bicimde rozetli). */
    function WorkText(props) {
        return h(ReadingView, { segments: props.segments, plain: props.format === 'plain' });
    }

    /** Okuma gorunumu: rozetli ya da duz yazi (diger rozetler kalin); duzenlenemez. */
    function ReadingView(props) {
        const { segments, plain } = props;
        const { t } = useApp();
        const ref = useRef(null);

        useLayoutEffect(() => {
            if (ref.current) { fillReading(ref.current, readingSegments(segments || []), t, !!plain); }
        }, [segments, plain]);

        return h('div', { className: cx('kq-editor readonly kq-view', plain && 'kq-plain-mode'), 'aria-readonly': 'true', ref });
    }

    /**
     * Ifade onerileri (D-240; kullanici 23 Ekim 2026: "Pozanti res yazdigimda
     * 'pozanti res' onerisinden vazgecip res onerileri cikmaya basliyor. Ifadenin
     * butunundeki oneriyi onceliklendirip devamindaki onerilerde yine res'leri
     * onerebilir"): imlecin solundaki iki ya da uc kelime birlikte aranir; en
     * uzun eslesen ifade kazanir. Secilince ifadenin tamami rozet olur (span).
     */
    function phraseSuggestions(token, ctx) {
        const out = [];
        const words = token.words || [];

        if (token.trigger || !token.query) { return out; }

        for (let n = words.length; n >= 1 && !out.length; n--) {
            const parts = words.slice(words.length - n);
            const lead = parts[0].word;
            const onlyPerson = lead.charAt(0) === '@' ? 'person' : null;
            const phrase = [lead.replace(/^[#@]/, '')].concat(parts.slice(1).map((part) => part.word), [token.query]).join(' ').trim();
            const q = fold(phrase);

            if (q.length < 4) { continue; }

            const qCode = foldCode(phrase);
            const scored = [];

            (ctx.index || []).forEach((row) => {
                if (onlyPerson && row.type !== onlyPerson) { return; }

                const score = scoreOf(row, q, qCode);

                // Ifadenin tamami adda kelime basindan geciyor (ya da isveren).
                if (score >= 82 || (score === 72 && row.type !== 'project') || (score === 100 && row.type === 'project')) { scored.push([score, row]); }
            });

            scored.sort((a, b) => (b[0] - a[0]) || a[1].label.localeCompare(b[1].label, 'tr'));
            scored.slice(0, 6).forEach((pair) => out.push({ ...pair[1], src: 'entity', span: parts[0].start }));

            if (!onlyPerson) {
                searchFields({ query: phrase, prev: '', trigger: '' }, ctx)
                    .filter((item) => PHRASE_FIELDS.indexOf(item.type) !== -1 && item.value !== null && item.value !== undefined)
                    .forEach((item) => out.push({ ...item, src: 'field', span: parts[0].start }));
            }
        }

        return out;
    }

    /** Yerel oneriler: ifade once, sonra kelime; kayitlar once (tarih / efor yazildiysa alanlar once), en fazla 8. */
    function suggestionsFor(token, ctx) {
        const forced = token.trigger !== '';

        // "devam e": son kelime tek harf olsa da ifadenin tamami aranir.
        if (!forced && fold(token.query).length < 2) { return phraseSuggestions(token, ctx).slice(0, 8); }

        const onlyPerson = token.trigger === '@' ? 'person' : null;
        const entities = searchRows(ctx.index, token.query, onlyPerson, forced && !token.query ? 8 : 7).map((row) => ({ ...row, src: 'entity' }));
        const fields = onlyPerson ? [] : searchFields(token, ctx).map((row) => ({ ...row, src: 'field' }));
        const fieldFirst = !forced && (!!dateFromWord(token.query, ctx.today, token.prev) || hoursFromWord(token.query, token.prev) !== null);
        const seen = {};

        return phraseSuggestions(token, ctx)
            .concat(fieldFirst ? fields.concat(entities) : entities.concat(fields))
            .filter((item) => {
                const key = item.type + ':' + (item.id || item.value || item.label);

                if (seen[key]) { return false; }

                seen[key] = true;

                return true;
            })
            .slice(0, 8);
    }

    /* ------------------------------------------------------------------ */
    /* Duzenleyici DOM'u                                                    */
    /* ------------------------------------------------------------------ */

    function badgeText(item, t) {
        switch (item.type) {
            case 'start':
            case 'due':
                return t('qa_type_' + item.type) + ': ' + fmt.date(item.value);
            case 'hours':
                return t('qa_type_hours') + ': ' + hoursText(item.value) + ' ' + t('qa_hours_unit');
            case 'priority':
            case 'discipline':
            case 'category':
                return t('qa_type_' + item.type) + ': ' + (item.text || item.value);
            case 'requester':
            case 'waiting':
                return t('qa_type_' + item.type) + ': ' + item.label;
            case 'team':
                return t('qa_type_team');
            case 'owner':
                return t('qa_owner', { name: item.label });
            default:
                return item.label;
        }
    }

    function badgeClass(item) {
        return 'kq-badge kq-t-' + item.type
            + (item.type === 'priority' ? ' kq-p-' + item.value : '')
            + (item.type === 'status' ? ' kq-s-' + item.value : '')
            + (item.locked ? ' kq-locked' : '')
            + (item.pinned ? ' kq-pin' : '');
    }

    function makeBadge(item, t, readOnly) {
        const badge = document.createElement('span');
        badge.className = badgeClass(item);
        badge.setAttribute('contenteditable', 'false');
        badge.dataset.type = item.type;
        badge.dataset.label = item.label || '';

        ['id', 'value', 'text', 'kind', 'code'].forEach((key) => {
            if (item[key] !== undefined && item[key] !== null && item[key] !== '') { badge.dataset[key] = String(item[key]); }
        });

        if (item.locked) { badge.dataset.locked = '1'; }
        if (item.pinned) { badge.dataset.pinned = '1'; }
        if (item.implicit) { badge.dataset.implicit = '1'; }
        if (item.code) { badge.title = item.code + ' · ' + item.label; }

        badge.appendChild(iconNode(item.type === 'owner' ? 'person' : item.type));
        const text = document.createElement('span');
        text.className = 'kq-badge-text';
        text.textContent = badgeText(item, t);
        badge.appendChild(text);

        // Sabit durum ve Sorumlu kaldirilmaz; tiklayinca degistirilir.
        if (!readOnly && !item.locked && !item.pinned) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'kq-badge-x';
            remove.tabIndex = -1;
            remove.setAttribute('aria-label', t('qa_badge_remove'));
            remove.textContent = '×';
            badge.appendChild(remove);
        }

        return badge;
    }

    function badgeOf(node) {
        const d = node.dataset;

        return {
            type: d.type,
            id: d.id ? Number(d.id) : null,
            value: d.value || null,
            label: d.label || '',
            text: d.text || null,
            kind: d.kind || null,
            code: d.code || null,
            locked: d.locked === '1',
            pinned: d.pinned === '1',
            implicit: d.implicit === '1',
        };
    }

    /*
     * Sabit rozetler (D-235, kullanici 22 Ekim 2026: "Sabit isin durumu ve
     * Sorumlu alani varsa onu da textarea'nin 2. satirina sabitlestirip
     * koyabiliriz. Bosuna 2 kez ekranda gorulmesine gerek yok"): her isin
     * rozet satirinin basinda durum ve Sorumlu durur. Secilmemisse "ortuk"tur
     * (Planlandi / Sorumlu: Ben); yazilan ilk kisi Sorumlu'ya, yazilan durum
     * durum rozetine gecer. Kaldirilamaz, tiklayinca degistirilir.
     */
    const pinnedOf = (root, type) => root.querySelector('.kq-badge[data-pinned="1"][data-type="' + type + '"]');

    /** Kayitli okunusta durum ve sorumlu (role: owner) sabit rozete donusur. */
    function pinSegments(segments) {
        let status = false;
        let owner = false;
        const firstPerson = (segments || []).find((s) => s.t === 'badge' && s.b.type === 'person');
        const ownerBadge = (segments || []).find((s) => s.t === 'badge' && s.b.type === 'person' && s.b.role === 'owner') || firstPerson;

        return (segments || []).map((segment) => {
            if (segment.t !== 'badge') { return segment; }

            if (!status && segment.b.type === 'status') {
                status = true;

                return { t: 'badge', b: { ...segment.b, pinned: true, implicit: false } };
            }

            if (!owner && segment === ownerBadge) {
                owner = true;

                return { t: 'badge', b: { ...segment.b, type: 'owner', pinned: true, implicit: false } };
            }

            return segment;
        });
    }

    /**
     * Editordeki duzen (yeni isle ayni): baslik ve rozetleri ayni satirda (cumlenin
     * devami), not satirlari, en altta sabit durum ve Sorumlu.
     */
    function editorSegments(segments) {
        const pinned = pinSegments(segments);
        const fixed = pinned.filter((item) => item.t === 'badge' && item.b.pinned);
        const rest = pinned.filter((item) => !(item.t === 'badge' && item.b.pinned));

        while (rest.length && (rest[rest.length - 1].t === 'br' || (rest[rest.length - 1].t === 'text' && !rest[rest.length - 1].v.trim()))) {
            rest.pop();
        }

        return fixed.length ? rest.concat([{ t: 'br' }], fixed) : rest;
    }

    /*
     * Okuma duzeni (D-247; kullanici 23 Ekim 2026: "Sorumlu ve is'in durumu birde
     * tarih (efor, baslangic, termin) ve kritiklik onlar alta 2. satir olayi vardi
     * ya oraya dussun. Cunku metnin icerigini ilgilendirmeyen bir konu oluyor. O
     * yuzden duz yazi da olsa rozetli de olsa onlar gorulsun"): metin ve metindeki
     * rozetler ustte; son satirda sirayla durum, Sorumlu, baslangic, termin, efor,
     * kritiklik. Kayitli okunusu da duzenleyici modelini de alir.
     */
    const isMeta = (item) => item.t === 'badge' && (!!item.b.pinned || META_ORDER[item.b.type] !== undefined);

    const metaRank = (badge) => META_ORDER[badge.type];

    function readingSegments(segments) {
        const list = segments || [];
        const all = list.some((item) => item.t === 'badge' && (item.b.pinned || item.b.type === 'owner')) ? list : pinSegments(list);
        const meta = all.filter(isMeta).sort((a, b) => metaRank(a.b) - metaRank(b.b));
        const rest = [];

        all.forEach((item) => {
            if (isMeta(item)) { return; }

            const last = rest[rest.length - 1];

            if (item.t === 'text' && last && last.t === 'text') {
                last.v += item.v;
            } else {
                rest.push(item.t === 'text' ? { t: 'text', v: item.v } : item);
            }
        });

        // Cikan rozetlerin bosluklari ve bos kalan satirlar temizlenir.
        const clean = [];

        rest.forEach((item, index) => {
            if (item.t !== 'text') {
                if (item.t === 'br' && (!clean.length || clean[clean.length - 1].t === 'br')) { return; }
                clean.push(item);

                return;
            }

            let v = item.v.replace(/[ \t ]{2,}/g, ' ');
            const prev = rest[index - 1];
            const next = rest[index + 1];

            if (!prev || prev.t === 'br') { v = v.replace(/^[ \t ]+/, ''); }
            if (!next || next.t === 'br') { v = v.replace(/[ \t ]+$/, ''); }
            if (v) { clean.push({ t: 'text', v }); }
        });

        while (clean.length && clean[clean.length - 1].t === 'br') { clean.pop(); }

        if (!meta.length) { return clean; }

        return clean.length ? clean.concat([{ t: 'br' }], meta) : meta;
    }

    /** Kopyalanan duz yazi: metin, alt satirda durum · Sorumlu · tarihler · kritiklik. */
    function readingText(segments, t) {
        const items = readingSegments(segments);
        const at = items.findIndex(isMeta);
        const body = plainText(at === -1 ? items : items.slice(0, at), t);
        const meta = at === -1 ? [] : items.slice(at).filter(isMeta).map((item) => plainText([item], t)).filter(Boolean);

        return [body, meta.join(' · ')].filter(Boolean).join('\n');
    }

        /** Eksik sabit rozeti (yeni is ya da silinen) son bilinen haliyle rozet satirinin basina koyar. */
    function ensurePinned(root, t, last) {
        const status = pinnedOf(root, 'status');
        const owner = pinnedOf(root, 'owner');

        if (status && owner) { return; }

        const make = (type) => makeBadge((last && last[type]) || (type === 'status'
            ? { type: 'status', value: 'planned', label: '', pinned: true, implicit: true }
            : { type: 'owner', label: '', pinned: true, implicit: true }), t);

        if (!status && !owner) {
            root.appendChild(document.createElement('br'));
            root.appendChild(make('status'));
            root.appendChild(document.createTextNode(' '));
            root.appendChild(make('owner'));
            root.appendChild(document.createTextNode(' '));

            return;
        }

        if (!status) {
            owner.parentNode.insertBefore(make('status'), owner);
            owner.parentNode.insertBefore(document.createTextNode(' '), owner);
        } else {
            status.after(document.createTextNode(' '), make('owner'));
        }
    }

    /** Ortuk sabit rozetlerin yazisi: bekletici varsa Bekliyor; departman tek basinaysa Tum ekip. */
    function refreshPinned(root, ctx, keepStatus) {
        const status = pinnedOf(root, 'status');
        const owner = pinnedOf(root, 'owner');
        const setText = (badge, text) => {
            const node = badge.querySelector('.kq-badge-text');

            if (node && node.textContent !== text) { node.textContent = text; }
        };

        if (status && status.dataset.implicit === '1') {
            const value = root.querySelector('.kq-badge[data-type="waiting"]') ? 'waiting' : (keepStatus || 'planned');
            const label = ((ctx.statuses || []).find((s) => s.value === value) || {}).label || value;
            status.dataset.value = value;
            status.dataset.label = label;
            status.className = badgeClass({ type: 'status', value, pinned: true });
            setText(status, label);
        }

        if (owner && owner.dataset.implicit === '1') {
            const shared = ctx.canShare && root.querySelector('.kq-badge[data-type="unit"]') && !root.querySelector('.kq-badge[data-type="team"]');
            setText(owner, shared ? ctx.t('qa_owner', { name: ctx.t('qa_type_team') }) : ctx.t('qa_owner_me'));
        }
    }

    /** Duzenleyiciyi kayitli okunustan doldurur (duzenleme ve "Coglat"). */
    function fillEditor(root, segments, t, readOnly) {
        root.innerHTML = '';

        (segments || []).forEach((segment, index) => {
            if (segment.t === 'text') {
                root.appendChild(document.createTextNode(segment.v));
            } else if (segment.t === 'br') {
                root.appendChild(document.createElement('br'));
            } else if (segment.t === 'badge') {
                root.appendChild(makeBadge(segment.b, t, readOnly));

                const next = segments[index + 1];

                if (!next || next.t === 'badge') { root.appendChild(document.createTextNode(' ')); }
            }
        });
    }

    /**
     * Okunusu cizer (D-247): son satir (durum, Sorumlu, tarihler, kritiklik) her
     * bicimde rozettir; duz yazida diger rozetler kalin yaziya doner (kullanici:
     * "Baska rozetli eklenmis konularda ise duz yazi durumuna gecince bold yazilsin").
     */
    function fillReading(root, segments, t, plain) {
        root.innerHTML = '';

        segments.forEach((segment, index) => {
            if (segment.t === 'text') {
                root.appendChild(document.createTextNode(segment.v));

                return;
            }

            if (segment.t === 'br') {
                root.appendChild(document.createElement('br'));

                return;
            }

            const badge = segment.b;

            if (plain && !isMeta(segment)) {
                const strong = document.createElement('b');
                strong.className = 'kq-plain-strong';
                strong.textContent = badgeText(badge, t);

                if (badge.code) { strong.title = badge.code + ' · ' + badge.label; }

                root.appendChild(strong);
            } else {
                const node = makeBadge(badge, t, true);

                if (badge.type === 'owner' && (badge.implicit || !badge.label)) {
                    node.querySelector('.kq-badge-text').textContent = t('qa_owner_me');
                }

                root.appendChild(node);
            }

            const next = segments[index + 1];

            if (!next || next.t === 'badge') { root.appendChild(document.createTextNode(' ')); }
        });
    }

    /** D-247: tarih, efor ya da kritiklik rozeti sabit satira, sirasindaki yere konur. */
    function placeMeta(root, badge) {
        let start = pinnedOf(root, 'status') || pinnedOf(root, 'owner');

        while (start && start.nodeName !== 'BR') { start = start.previousSibling; }

        const space = document.createTextNode(' ');
        let before = null;

        for (let node = start ? start.nextSibling : null; node && !before; node = node.nextSibling) {
            if (node.nodeType === 1 && node.classList.contains('kq-badge') && node.dataset.pinned !== '1'
                && (META_ORDER[node.dataset.type] === undefined || META_ORDER[node.dataset.type] > META_ORDER[badge.dataset.type])) {
                before = node;
            }
        }

        if (before) {
            before.parentNode.insertBefore(badge, before);
            before.parentNode.insertBefore(space, before);

            return;
        }

        const last = root.lastChild;

        if (last && !(last.nodeType === 3 && /\s$/.test(last.data))) { root.appendChild(document.createTextNode(' ')); }

        root.appendChild(badge);
        root.appendChild(space);
    }

    function readItems(root) {
        const items = [];

        const walk = (parent) => {
            parent.childNodes.forEach((node) => {
                if (node.nodeType === 3) {
                    const text = node.data.replace(/ /g, ' ').replace(/​/g, '');

                    if (text) { items.push({ t: 'text', v: text }); }

                    return;
                }

                if (node.nodeType !== 1) { return; }

                if (node.classList.contains('kq-interim')) {
                    return;
                }

                if (node.classList.contains('kq-badge')) {
                    items.push({ t: 'badge', b: badgeOf(node) });

                    return;
                }

                if (node.nodeName === 'BR') {
                    items.push({ t: 'br' });

                    return;
                }

                const block = node.nodeName === 'DIV' || node.nodeName === 'P';

                if (block && items.length && items[items.length - 1].t !== 'br') {
                    items.push({ t: 'br' });
                }

                walk(node);
            });
        };

        walk(root);

        while (items.length && (items[items.length - 1].t === 'br' || (items[items.length - 1].t === 'text' && !items[items.length - 1].v.trim()))) {
            items.pop();
        }

        return items;
    }

    function paragraphsOf(items) {
        const paragraphs = [[]];

        items.forEach((item) => {
            if (item.t === 'br') {
                paragraphs.push([]);
            } else {
                paragraphs[paragraphs.length - 1].push(item);
            }
        });

        return paragraphs;
    }

    const squish = (text) => text.replace(/\s+/g, ' ').trim();

    // Cumle sonu sayilmayan kisaltmalar (katlanmis bicim).
    const ABBREVIATIONS = ['a.s.', 'ltd.', 'sti.', 'tic.', 'san.', 'vb.', 'vs.', 'dr.', 'muh.', 'no.', 'bkz.', 'prof.', 'doc.', 'mah.', 'cad.', 'sok.', 'tel.', 'yrd.', 'av.', 'op.', 'st.'];

    /** Paragrafin ilk cumlesi ve kalani; yalniz yazi icinde (rozet adlari bolunmez). */
    function firstSentence(paragraph) {
        let line = '';
        const breakable = [];

        paragraph.forEach((item) => {
            const piece = item.t === 'text' ? item.v : (isField(item.b.type) ? ' ' : ' ' + item.b.label + ' ');

            for (let k = 0; k < piece.length; k++) { breakable.push(item.t === 'text'); }

            line += piece;
        });

        for (let i = 0; i < line.length - 1; i++) {
            if (!breakable[i] || '.!?…'.indexOf(line[i]) === -1 || !/\s/.test(line[i + 1])) { continue; }

            const word = (line.slice(0, i).match(/(\S+)$/) || ['', ''])[1];

            if (!word || /^\d+$/.test(word) || /^\p{L}$/u.test(word) || ABBREVIATIONS.indexOf(fold(word + '.')) !== -1) { continue; }

            return [squish(line.slice(0, i + 1)), squish(line.slice(i + 1))];
        }

        return [squish(line), ''];
    }

    /**
     * Metin: alan rozetleri ve paragrafin sonundaki rozetler duser, cumlenin
     * icindeki kayit rozeti adiyla kalir. Baslik ilk cumledir (D-236, kullanici:
     * "Ilk cumle baslik kabul edilip, cumle 1 cumleden fazla ise 2. cumle aciklama
     * sayilabilir"); ilk paragrafin kalani ve diger satirlar aciklama. Baslik 200
     * karakteri gecerse kelime sinirinda kesilir, kalan aciklamaya gecer (ayni
     * metin iki kez yazilmaz).
     */
    function textParts(items) {
        const paragraphs = paragraphsOf(items).map((paragraph) => {
            let last = -1;

            paragraph.forEach((item, index) => {
                if (item.t === 'text' && item.v.trim()) { last = index; }
            });

            return last === -1 ? null : paragraph.slice(0, last + 1);
        }).filter(Boolean);

        if (!paragraphs.length) { return { title: '', note: null }; }

        let [title, remainder] = firstSentence(paragraphs[0]);

        if (title.length > 200) {
            const cut = title.slice(0, 200).replace(/\s+\S*$/, '') || title.slice(0, 200);
            remainder = squish(title.slice(cut.length) + ' ' + remainder);
            title = cut;
        }

        const rest = (remainder ? [remainder] : []).concat(paragraphs.slice(1).map((paragraph) => firstSentence(paragraph).filter(Boolean).join(' ')).filter(Boolean));

        return { title, note: rest.length ? rest.join('\n').slice(0, 2000) : null };
    }

        function placeCaretAfter(node) {
        const selection = window.getSelection();
        const range = document.createRange();
        range.setStartAfter(node);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    /** Yazmaya donus: sabit rozet satirindan onceki satirin sonu (yoksa en son). */
    function placeCaretForTyping(root) {
        let br = pinnedOf(root, 'status') || pinnedOf(root, 'owner');

        while (br && br.nodeName !== 'BR') { br = br.previousSibling; }

        if (!br) {
            placeCaretAtEnd(root);

            return;
        }

        const selection = window.getSelection();
        const range = document.createRange();
        range.setStartBefore(br);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function placeCaretAtEnd(root) {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(root);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    /** Imlecin solundaki kelime ve ondan onceki kelime ("gecen cuma", "2 saat"). */
    function currentToken(root) {
        const selection = window.getSelection();

        if (!selection || !selection.rangeCount || !selection.isCollapsed) { return null; }

        const node = selection.anchorNode;

        if (!node || node.nodeType !== 3 || !root.contains(node)) { return null; }

        const before = node.data.slice(0, selection.anchorOffset);
        const match = before.match(/(^|[\s (])([#@]?)([^\s #@()]*)$/);

        if (!match) { return null; }

        let start = selection.anchorOffset - match[2].length - match[3].length;
        const head = node.data.slice(0, start);
        const prevMatch = head.match(/([^\s ]+)[\s ]+$/);
        const prev = prevMatch ? prevMatch[1] : '';
        // D-240: onceki iki kelime; "Pozanti res", "devam e", "Tum ek" ifadesi butun olarak aranir.
        const words = [];
        let rest = head;

        for (let k = 0; k < 2; k++) {
            const found = rest.match(/(\S+)\s+$/);

            if (!found) { break; }

            const at = rest.length - found[0].length;
            words.unshift({ word: found[1], start: at });
            rest = rest.slice(0, at);
        }

        return { node, start, prevStart: prevMatch ? start - prevMatch[0].length : start, end: selection.anchorOffset, trigger: match[2], query: match[3], prev, words };
    }

    function caretRect(root) {
        const selection = window.getSelection();

        if (selection && selection.rangeCount) {
            const rects = selection.getRangeAt(0).getClientRects();

            if (rects.length) { return rects[rects.length - 1]; }
        }

        return root.getBoundingClientRect();
    }

    function removeBadge(node) {
        const next = node.nextSibling;

        if (next && next.nodeType === 3 && /^[  ]/.test(next.data)) {
            next.data = next.data.slice(1);

            if (!next.data) { next.remove(); }
        }

        node.remove();
    }

    /** Tek olabilen rozeti kaldirir; ayni kayit zaten varsa true. */
    function prepareFor(root, item) {
        const existing = Array.prototype.slice.call(root.querySelectorAll('.kq-badge[data-type="' + item.type + '"]:not([data-pinned="1"])'));
        const owner = pinnedOf(root, 'owner');

        if (item.type === 'person' && owner && String(owner.dataset.id) === String(item.id)) {
            return true;
        }

        if (SINGLE[item.type]) {
            existing.forEach((node) => removeBadge(node));

            return false;
        }

        return existing.some((node) => String(node.dataset.id) === String(item.id));
    }

    /* ------------------------------------------------------------------ */
    /* Oneri kutusu, secim pencereleri, menuler                             */
    /* ------------------------------------------------------------------ */

    const typeLabel = (t, type) => t('qa_type_' + type);

    function popStyle(rect, width, height) {
        const left = Math.max(8, Math.min(rect.left - 4, window.innerWidth - width - 8));
        const below = rect.bottom + 6;
        const up = below + (height || 300) > window.innerHeight && rect.top > (height || 300) + 20;

        return up ? { left, bottom: window.innerHeight - rect.top + 6, width } : { left, top: below, width };
    }

    function OptionRow(props) {
        const { item, active, onPick, onHover, sub } = props;

        return h('button', {
            type: 'button',
            role: 'option',
            className: cx('kq-opt', 'kq-t-' + item.type, item.type === 'status' && 'kq-s-' + item.value, item.type === 'priority' && 'kq-p-' + item.value, active && 'on'),
            'data-active': String(!!active),
            'aria-selected': String(!!active),
            onMouseEnter: onHover,
            onClick: () => onPick(item),
        },
        h('span', { className: 'kq-opt-ic' }, h(Icon, { name: item.type })),
        h('span', { className: 'kq-opt-main' },
            h('span', { className: 'kq-opt-label' }, item.text && item.type !== 'priority' && item.type !== 'discipline' && item.type !== 'category' ? item.text : item.label),
            sub ? h('span', { className: 'kq-opt-sub' }, sub) : null,
        ),
        active ? h('kbd', { className: 'kq-kbd' }, '↵') : null);
    }

    function subOf(item, t) {
        if (item.src === 'field') {
            return item.sub || typeLabel(t, item.type);
        }

        return [typeLabel(t, item.type), item.code, item.sub].filter(Boolean).filter((value, i, all) => all.indexOf(value) === i).join(' · ');
    }

    function SuggestList(props) {
        const { items, active, rect, onPick, onHover, loading } = props;
        const { t } = useApp();
        const listRef = useRef(null);

        useEffect(() => {
            const el = listRef.current && listRef.current.querySelector('[data-active="true"]');

            if (el) { el.scrollIntoView({ block: 'nearest' }); }
        }, [active]);

        if ((!items.length && !loading) || !rect) { return null; }

        return h(Portal, null, h('div', { className: 'kq-suggest', style: popStyle(rect, 360), role: 'listbox', ref: listRef, onMouseDown: (event) => event.preventDefault() },
            items.map((item, index) => h(OptionRow, {
                key: item.type + ':' + (item.id || item.value || item.label),
                item,
                active: index === active,
                sub: subOf(item, t),
                onPick,
                onHover: () => onHover(index),
            })),
            loading ? h('div', { className: 'kq-loading' }, h('span', { className: 'kw-spinner', 'aria-hidden': 'true' }), t('qa_searching')) : null,
        ));
    }

    /** Arama kutulu liste (kayitlar, durum, kritiklik, is grubu, kategori, uzak kayitlar). */
    function ListPicker(props) {
        const { type, ctx, onPick } = props;
        const { t } = useApp();
        const [query, setQuery] = useState('');
        const [active, setActive] = useState(0);
        const [remote, setRemote] = useState([]);
        const [loading, setLoading] = useState(false);
        const isRemote = REMOTE_TYPES.indexOf(type) !== -1;

        useEffect(() => {
            if (!isRemote) { return undefined; }

            let alive = true;
            const timer = window.setTimeout(() => {
                if (fold(query).trim().length < 2) { setRemote([]); setLoading(false); return; }

                setLoading(true);
                remoteSearch(ctx.api, ctx.searchUrl, query).then((rows) => {
                    if (alive) { setRemote(rows.filter((row) => row.type === type).map((row) => indexRow(row.type, row))); }
                }).finally(() => { if (alive) { setLoading(false); } });
            }, 220);

            return () => { alive = false; window.clearTimeout(timer); };
        }, [query, isRemote, type]);

        let list;

        if (isRemote) {
            list = remote;
        } else if (type === 'status') {
            list = (ctx.statuses || []).map((s) => ({ type, value: s.value, label: s.label }));
        } else if (type === 'priority') {
            list = (ctx.priorities || []).map((s) => ({ type, value: s.value, label: t('qa_type_priority') + ': ' + s.label, text: s.label }));
        } else if (type === 'discipline') {
            list = (ctx.disciplines || []).map((s) => ({ type, value: s.value, label: t('qa_type_discipline') + ': ' + s.label, text: s.label }));
        } else if (type === 'category') {
            list = (ctx.categories || []).map((s) => ({ type, value: s.value, label: t('qa_type_category') + ': ' + s.label, text: s.label }));
        } else {
            list = searchRows(ctx.index, query, type, 60);
        }

        if (!isRemote && ENTITY_TYPES.indexOf(type) === -1 && query) {
            list = list.filter((row) => fold(row.text || row.label).indexOf(fold(query)) !== -1);
        }

        return h(Fragment, null,
            h('label', { className: 'kq-pop-search' }, h(Icon, { name: 'search' }), h('input', {
                value: query,
                autoFocus: true,
                placeholder: isRemote ? t('qa_search_remote') : t('qa_search'),
                onChange: (event) => { setQuery(event.target.value); setActive(0); },
                onKeyDown: (event) => {
                    if (event.key === 'ArrowDown') { event.preventDefault(); setActive((value) => Math.min(list.length - 1, value + 1)); }
                    if (event.key === 'ArrowUp') { event.preventDefault(); setActive((value) => Math.max(0, value - 1)); }
                    if (event.key === 'Enter' && list[active]) { event.preventDefault(); onPick(list[active]); }
                },
            })),
            h('div', { className: 'kq-pop-list', role: 'listbox' },
                list.length ? list.map((item, i) => h(OptionRow, {
                    key: item.type + ':' + (item.id || item.value),
                    item,
                    active: i === active,
                    sub: [item.code, item.sub].filter(Boolean).join(' · ') || null,
                    onPick,
                    onHover: () => setActive(i),
                })) : h('div', { className: 'kq-empty' }, loading ? t('qa_searching') : (isRemote && fold(query).length < 2 ? t('qa_type_to_search') : t('qa_no_results'))),
            ),
        );
    }

    function DatePicker(props) {
        const { type, ctx, onPick } = props;
        const { t } = useApp();
        const [date, setDate] = useState(ctx.today);
        const pick = (value) => { if (value) { onPick({ type, value, label: typeLabel(t, type) }); } };
        const quick = [
            [t('qa_yesterday'), addDays(ctx.today, -1)],
            [t('qa_today'), ctx.today],
            [t('qa_tomorrow'), addDays(ctx.today, 1)],
            [fmt.dayName(weekday(ctx.today, 1, false)), weekday(ctx.today, 1, false)],
            [fmt.dayName(weekday(ctx.today, 5, false)), weekday(ctx.today, 5, false)],
            [t('qa_next_week'), addDays(ctx.today, 7)],
            [t('qa_last_week'), addDays(ctx.today, -7)],
            [t('qa_last_friday'), weekday(ctx.today, 5, true)],
        ];

        return h('div', { className: 'kq-pop-date' },
            h('div', { className: 'kq-pop-quick' }, quick.map(([label, value]) => h('button', {
                key: label + value,
                type: 'button',
                className: cx('kq-chip', value < ctx.today && 'past'),
                onClick: () => pick(value),
            }, h('b', null, label), h('span', null, fmt.dm(value))))),
            h('div', { className: 'kq-pop-row' },
                h('input', { type: 'date', className: 'kw-input', value: date, autoFocus: true, onChange: (event) => setDate(event.target.value), onKeyDown: (event) => { if (event.key === 'Enter') { event.preventDefault(); pick(date); } } }),
                h(Btn, { variant: 'primary', onClick: () => pick(date) }, t('qa_date_add')),
            ),
            h('p', { className: 'kq-pop-help' }, t('qa_date_help')),
        );
    }

    function HoursPicker(props) {
        const { onPick } = props;
        const { t } = useApp();
        const [value, setValue] = useState('');
        const pick = (hours) => { const n = Number(String(hours).replace(',', '.')); if (n > 0 && n <= 999) { onPick({ type: 'hours', value: n, label: t('qa_type_hours') }); } };

        return h('div', { className: 'kq-pop-date' },
            h('div', { className: 'kq-pop-quick' }, [0.5, 1, 2, 4, 8].map((hours) => h('button', { key: hours, type: 'button', className: 'kq-chip', onClick: () => pick(hours) },
                h('b', null, hoursText(hours)), h('span', null, t('qa_hours_unit'))))),
            h('div', { className: 'kq-pop-row' },
                h('input', { type: 'number', min: 0, max: 999, step: 0.25, className: 'kw-input', value, autoFocus: true, placeholder: '0,0', onChange: (event) => setValue(event.target.value), onKeyDown: (event) => { if (event.key === 'Enter') { event.preventDefault(); pick(value); } } }),
                h(Btn, { variant: 'primary', onClick: () => pick(value) }, t('qa_date_add')),
            ),
        );
    }

    /** Talep eden / Bekletici: personel, taraf ya da serbest metin. */
    function SourcePicker(props) {
        const { type, ctx, onPick } = props;
        const { t } = useApp();
        const [tab, setTab] = useState('personnel');
        const [query, setQuery] = useState('');
        const [text, setText] = useState('');
        const [remote, setRemote] = useState([]);
        const pickSource = (kind, row) => onPick({ type, kind, id: row && row.id ? row.id : null, value: kind === 'text' ? row.label : null, label: row.label });

        useEffect(() => {
            if (tab !== 'party') { return undefined; }

            let alive = true;
            const timer = window.setTimeout(() => {
                remoteSearch(ctx.api, ctx.searchUrl, query).then((rows) => { if (alive) { setRemote(rows.filter((row) => row.type === 'party')); } });
            }, 220);

            return () => { alive = false; window.clearTimeout(timer); };
        }, [tab, query]);

        const people = (ctx.people || []).filter((p) => !query || fold(p.label).indexOf(fold(query)) !== -1).slice(0, 40);
        const tabs = [['personnel', t('qa_source_person')], ['party', t('qa_source_party')], ['text', t('qa_source_text')]];

        return h(Fragment, null,
            h('div', { className: 'kq-pop-tabs', role: 'tablist' }, tabs.map(([key, label]) => h('button', {
                key,
                type: 'button',
                role: 'tab',
                className: cx('kq-pop-tab', tab === key && 'on'),
                'aria-selected': String(tab === key),
                onClick: () => setTab(key),
            }, label))),
            tab === 'text'
                ? h('div', { className: 'kq-pop-row' },
                    h('input', { className: 'kw-input', value: text, autoFocus: true, placeholder: t('qa_source_text_ph'), onChange: (event) => setText(event.target.value), onKeyDown: (event) => { if (event.key === 'Enter' && text.trim()) { event.preventDefault(); pickSource('text', { label: text.trim() }); } } }),
                    h(Btn, { variant: 'primary', disabled: !text.trim(), onClick: () => pickSource('text', { label: text.trim() }) }, t('qa_date_add')))
                : h(Fragment, null,
                    h('label', { className: 'kq-pop-search' }, h(Icon, { name: 'search' }), h('input', { value: query, autoFocus: true, placeholder: t('qa_search'), onChange: (event) => setQuery(event.target.value) })),
                    h('div', { className: 'kq-pop-list' },
                        (tab === 'personnel' ? people : remote).map((row) => h(OptionRow, {
                            key: tab + row.id,
                            item: { type: tab === 'personnel' ? 'person' : 'party', label: row.label },
                            sub: row.sub || null,
                            onPick: () => pickSource(tab, row),
                            onHover: () => {},
                        })),
                        tab === 'party' && !remote.length ? h('div', { className: 'kq-empty' }, fold(query).length < 2 ? t('qa_type_to_search') : t('qa_no_results')) : null,
                    )),
        );
    }

    /** Ustteki dugmelerin ve deger isteyen onerilerin secim penceresi. */
    function PickerPop(props) {
        const { type, rect, ctx, onPick, onClose } = props;
        const { t } = useApp();
        const ref = useRef(null);

        KW.useClickOutside(ref, onClose, true);
        KW.useEscape(onClose, true);

        let body;

        if (type === 'due' || type === 'start') {
            body = h(DatePicker, { type, ctx, onPick });
        } else if (type === 'hours') {
            body = h(HoursPicker, { onPick });
        } else if (type === 'requester' || type === 'waiting') {
            body = h(SourcePicker, { type, ctx, onPick });
        } else {
            body = h(ListPicker, { type, ctx, onPick });
        }

        return h(Portal, null, h('div', { className: 'kq-pop kq-t-' + type, style: popStyle(rect, 340, 360), ref, role: 'dialog', 'aria-label': typeLabel(t, type) },
            h('div', { className: 'kq-pop-head kq-t-' + type }, h(Icon, { name: type }), typeLabel(t, type)),
            body,
        ));
    }

    /** "Alanlar" / "Baglantilar" acilir menusu (portal: pencere govdesi kirpmaz). */
    function ToolMenu(props) {
        const { label, icon, types, onChoose } = props;
        const { t } = useApp();
        const [open, setOpen] = useState(null);
        const ref = useRef(null);
        const boxRef = useRef(null);
        const close = useCallback((event) => {
            if (event && event.target && boxRef.current && boxRef.current.contains(event.target)) { return; }
            setOpen(null);
        }, []);

        KW.useClickOutside(ref, close, !!open);
        KW.useEscape(() => setOpen(null), !!open);

        if (!types.length) { return null; }

        return h('div', { className: 'kq-menu', ref },
            h('button', {
                type: 'button',
                className: cx('kq-tool', 'kq-tool-menu', open && 'open'),
                'aria-expanded': String(!!open),
                'aria-haspopup': 'menu',
                onMouseDown: (event) => event.preventDefault(),
                onClick: (event) => setOpen(open ? null : event.currentTarget.getBoundingClientRect()),
            }, h(Icon, { name: icon }), label, h(Icon, { name: 'chevron', className: 'kq-caret' })),
            open ? h(Portal, null, h('div', { className: 'kq-menu-box', role: 'menu', ref: boxRef, style: popStyle(open, 220, types.length * 34 + 12) }, types.map((type) => h('button', {
                key: type,
                type: 'button',
                role: 'menuitem',
                className: 'kq-menu-item kq-t-' + type,
                onMouseDown: (event) => event.preventDefault(),
                onClick: () => { setOpen(null); onChoose(type, ref.current.getBoundingClientRect()); },
            }, h('span', { className: 'kq-opt-ic' }, h(Icon, { name: type })), typeLabel(t, type))))) : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Tek isin duzenleyicisi                                               */
    /* ------------------------------------------------------------------ */

    const VALUE_PICKERS = { due: true, start: true, hours: true, requester: true, waiting: true };
    // Bir iste bir kayitta en fazla belge (sunucu sinirlariyla ayni, WorkItemThreadController).
    const ATTACH_LIMIT = 20;

    /**
     * prose (D-240, rapor yazma denemesi): is degil duz rapor metni; sabit durum /
     * Sorumlu satiri yok, yalniz kayit onerileri (kisi, departman, taahhut ...).
     */
    function QuickEditor(props) {
        const { apiRef, ctx, autoFocus, placeholder, initial, readOnly, keepStatus, prose, onChange, onFocus, onNewRow, onSave, onRemoveEmpty } = props;
        const { t } = ctx;
        const pins = !readOnly && !prose;
        const rootRef = useRef(null);
        const lastPinned = useRef(null);
        const voiceAnchor = useRef(null);
        const [voiceFound, setVoiceFound] = useState([]);
        const [voiceOn, setVoiceOn] = useState(false);
        const [empty, setEmpty] = useState(true);
        const [token, setToken] = useState(null);
        const [items, setItems] = useState([]);
        const [active, setActive] = useState(-1);
        const [rect, setRect] = useState(null);
        const [remoteLoading, setRemoteLoading] = useState(false);
        const [picker, setPicker] = useState(null);
        const muted = useRef(null);
        const remoteTimer = useRef(null);
        const tokenKey = useRef('');

        const emit = useCallback(() => {
            const root = rootRef.current;

            if (!root) { return; }

            if (pins) {
                ensurePinned(root, t, lastPinned.current);
                refreshPinned(root, ctx, keepStatus);
                const status = pinnedOf(root, 'status');
                const owner = pinnedOf(root, 'owner');
                lastPinned.current = { status: status ? badgeOf(status) : null, owner: owner ? badgeOf(owner) : null };
            }

            const model = readItems(root);
            setEmpty(isEmptyModel(model));
            onChange(model);
        }, [onChange, pins, t, ctx, keepStatus]);

        /** Sabit rozeti degistirir (durum ya da Sorumlu; secilen artik ortuk degildir). */
        const setPinned = useCallback((type, item) => {
            const root = rootRef.current;

            if (!root) { return; }

            ensurePinned(root, t, lastPinned.current);
            const badge = makeBadge(type === 'owner'
                ? { type: 'owner', id: item.id, label: item.label, pinned: true }
                : { type: 'status', value: item.value, label: item.label, pinned: true }, t);
            pinnedOf(root, type).replaceWith(badge);
        }, [t]);

        /** Durum her zaman sabit rozete; ilk kisi (Sorumlu ortukken) Sorumlu'ya gider. */
        const route = useCallback((item) => {
            const root = rootRef.current;

            if (!root || !pins) { return false; }

            if (item.type === 'status') {
                setPinned('status', item);

                return true;
            }

            if (item.type === 'person') {
                const owner = pinnedOf(root, 'owner');

                if (!owner || owner.dataset.implicit === '1' || String(owner.dataset.id) === String(item.id)) {
                    setPinned('owner', item);

                    return true;
                }
            }

            return false;
        }, [setPinned, pins]);

        /** D-247: tarih, efor, kritiklik yazinin icine girmez; sabit satira (durum ve Sorumlu'nun ardina) gider. */
        const lineTwo = useCallback((item) => {
            const root = rootRef.current;

            if (!root || !pins || !isLineTwo(item.type)) { return false; }

            ensurePinned(root, t, lastPinned.current);
            prepareFor(root, item);
            placeMeta(root, makeBadge(item, t));

            return true;
        }, [pins, t]);

        const closeSuggest = useCallback(() => {
            setToken(null);
            setItems([]);
            setActive(-1);
            setRemoteLoading(false);
            tokenKey.current = '';
            window.clearTimeout(remoteTimer.current);
        }, []);

        const refreshToken = useCallback(() => {
            const root = rootRef.current;

            if (!root || !ctx.index || readOnly) { return; }

            const next = currentToken(root);

            if (!next || (muted.current && muted.current.node === next.node && muted.current.start === next.start)) {
                closeSuggest();

                return;
            }

            muted.current = null;
            const local = prose
                ? suggestionsFor(next, ctx).filter((item) => ENTITY_TYPES.indexOf(item.type) !== -1 || REMOTE_TYPES.indexOf(item.type) !== -1)
                : suggestionsFor(next, ctx);
            const key = next.trigger + next.query;
            const wantsRemote = ctx.searchUrl && ctx.linkTypes.length && fold(next.query).length >= (next.trigger ? 2 : 3);

            tokenKey.current = key;
            setToken(next);
            setItems(local);
            setActive(next.trigger && local.length ? 0 : -1);
            setRect(caretRect(root));
            window.clearTimeout(remoteTimer.current);

            if (!wantsRemote) {
                setRemoteLoading(false);

                if (!local.length) { closeSuggest(); }

                return;
            }

            setRemoteLoading(true);
            remoteTimer.current = window.setTimeout(() => {
                remoteSearch(ctx.api, ctx.searchUrl, next.query).then((rows) => {
                    if (tokenKey.current !== key) { return; }

                    const extra = rows.filter((row) => ctx.linkTypes.indexOf(row.type) !== -1).slice(0, 6).map((row) => ({ ...indexRow(row.type, row), src: 'entity' }));
                    setRemoteLoading(false);
                    setItems((list) => {
                        const merged = list.concat(extra).slice(0, 10);

                        if (!merged.length) { window.setTimeout(closeSuggest, 0); }

                        return merged;
                    });
                });
            }, 250);
        }, [ctx, closeSuggest, readOnly, prose]);

        /** Rozeti imlecteki kelimenin yerine ya da imlece koyar. */
        const insertAtToken = useCallback((item, at) => {
            const root = rootRef.current;

            if (!root) { return; }

            // "gecen cuma", "2 saat": onceki kelime de rozetin parcasidir.
            const usesPrev = at && at.prev && ((item.type === 'start' || item.type === 'due') ? dateUsesPrev(at.query || '', at.prev) : (item.type === 'hours' && /^\d+(?:[.,]\d+)?$/.test(at.prev)));
            const from = at ? (usesPrev ? at.prevStart : at.start) : 0;

            if (route(item) || lineTwo(item)) {
                if (at && at.node.isConnected) {
                    const head = at.node.data.slice(0, from);
                    const tail = at.node.data.slice(at.end);
                    // Ifade yazidan cikar; iki bosluk kalmaz.
                    at.node.data = head + (/\s$/.test(head) && /^\s/.test(tail) ? tail.slice(1) : tail);
                    root.focus();
                    const selection = window.getSelection();
                    const range = document.createRange();
                    range.setStart(at.node, Math.min(from, at.node.data.length));
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }

                closeSuggest();
                emit();

                return;
            }

            if (prepareFor(root, item)) {
                if (at && at.node.isConnected) {
                    at.node.data = at.node.data.slice(0, from) + at.node.data.slice(at.end);
                }

                closeSuggest();
                emit();

                return;
            }

            const badge = makeBadge(item, t);
            const space = document.createTextNode(' ');

            if (at && at.node.isConnected) {
                const range = document.createRange();
                range.setStart(at.node, Math.min(from, at.node.data.length));
                range.setEnd(at.node, Math.min(at.end, at.node.data.length));
                range.deleteContents();
                range.insertNode(space);
                range.insertNode(badge);
            } else {
                root.appendChild(badge);
                root.appendChild(space);
            }

            root.focus();
            placeCaretAfter(space);
            closeSuggest();
            emit();
        }, [t, emit, closeSuggest, route, lineTwo]);

        /**
         * Ustteki dugmeler: rozet cumlenin devamina, yazinin sonuna (sabit durum /
         * Sorumlu satirindan once) gelir; imlec rozetin arkasinda kalir (D-235,
         * kullanici: "ilgili cumlenin devamina eklemek yerine alttaki 2. satira
         * eklendiler. Aslinda cumlenin devamina eklenmis olmaliydi").
         */
        const appendTag = useCallback((item) => {
            const root = rootRef.current;

            if (!root) { return; }

            if (route(item) || lineTwo(item) || prepareFor(root, item)) {
                emit();

                return;
            }

            if (pins) { ensurePinned(root, t, lastPinned.current); }

            let br = pins ? (pinnedOf(root, 'status') || pinnedOf(root, 'owner')) : null;

            while (br && br.nodeName !== 'BR') { br = br.previousSibling; }

            const badge = makeBadge(item, t);
            const space = document.createTextNode(' ');

            if (br) {
                const before = br.previousSibling;

                if (before && before.nodeType === 3 && before.data && !/\s$/.test(before.data)) {
                    br.parentNode.insertBefore(document.createTextNode(' '), br);
                }

                br.parentNode.insertBefore(badge, br);
                br.parentNode.insertBefore(space, br);
            } else {
                root.appendChild(badge);
                root.appendChild(space);
            }

            root.focus();
            placeCaretAfter(space);
            emit();
        }, [t, emit, route, lineTwo, pins]);

        /** Konusma basladi: gecici yazi imlecte (sabit satirdaysa yazinin sonunda) durur. */
        const voiceStart = useCallback(() => {
            const root = rootRef.current;

            if (!root || readOnly || (voiceAnchor.current && voiceAnchor.current.isConnected)) { return; }

            if (pins) { ensurePinned(root, t, lastPinned.current); }

            const span = document.createElement('span');
            span.className = 'kq-interim';
            span.setAttribute('contenteditable', 'false');
            let br = pins ? (pinnedOf(root, 'status') || pinnedOf(root, 'owner')) : null;

            while (br && br.nodeName !== 'BR') { br = br.previousSibling; }

            const selection = window.getSelection();
            let placed = false;

            if (selection && selection.rangeCount && root.contains(selection.anchorNode)) {
                const range = selection.getRangeAt(0).cloneRange();
                range.collapse(true);

                if (!br || range.comparePoint(br, 0) >= 0) {
                    range.insertNode(span);
                    placed = true;
                }
            }

            if (!placed) {
                if (br) { br.parentNode.insertBefore(span, br); } else { root.appendChild(span); }
            }

            voiceAnchor.current = span;
            setVoiceOn(true);
        }, [readOnly, t, pins]);

        const voiceInterim = useCallback((text) => {
            if (voiceAnchor.current) { voiceAnchor.current.textContent = text ? ' ' + text : ''; }
        }, []);

        /** Kesinlesen cumle yaziya gecer; icindeki kayitlar oneri olur. */
        const voiceCommit = useCallback((text) => {
            const root = rootRef.current;
            let clean = String(text || '').trim();

            if (!root || !clean) { return; }

            if (!voiceAnchor.current || !voiceAnchor.current.isConnected) { voiceStart(); }

            const anchor = voiceAnchor.current;
            const before = anchor.previousSibling;
            const lineStart = !before || before.nodeName === 'BR' || (before.nodeType === 3 && !before.data.trim() && (!before.previousSibling || before.previousSibling.nodeName === 'BR'));
            const needSpace = before && before.nodeName !== 'BR' && !(before.nodeType === 3 && /\s$/.test(before.data));

            if (lineStart) { clean = clean.charAt(0).toLocaleUpperCase('tr-TR') + clean.slice(1); }

            anchor.parentNode.insertBefore(document.createTextNode((needSpace ? ' ' : '') + clean + ' '), anchor);
            anchor.textContent = '';
            emit();

            if (!ctx.index) { return; }

            const merge = (found) => {
                if (!found.length) { return; }

                setVoiceFound((list) => {
                    const keys = {};
                    list.forEach((hit) => { keys[hit.item.type + ':' + (hit.item.id || hit.item.value)] = true; });

                    return list.concat(found.filter((hit) => !keys[hit.item.type + ':' + (hit.item.id || hit.item.value)])).slice(-16);
                });
            };
            const scan = voiceHits(clean, ctx);
            merge(scan.hits);
            voiceRemote(scan.remote, ctx).then(merge);
        }, [emit, ctx, voiceStart]);

        const voiceStop = useCallback(() => {
            const root = rootRef.current;
            const anchor = voiceAnchor.current;
            voiceAnchor.current = null;
            setVoiceOn(false);

            if (!root || !anchor || !anchor.isConnected) { return; }

            const selection = window.getSelection();
            const range = document.createRange();
            range.setStartBefore(anchor);
            range.collapse(true);
            anchor.remove();
            root.focus();
            selection.removeAllRanges();
            selection.addRange(range);
            emit();
        }, [emit]);

        /** Oneri secildi: durum / kisi sabit rozete (soylenen yazi kalir), digerleri yazinin yerine rozet olur. */
        const applyVoice = useCallback((hit) => {
            const root = rootRef.current;

            const group = (item) => (item.src === 'entity' ? 'entity' : (item.type === 'start' || item.type === 'due' ? 'date' : item.type));

            // Ayni ifadenin ayni turden diger adaylari duser (ornegin iki Mehmet'ten biri secildi).
            setVoiceFound((list) => list.filter((other) => other !== hit && (other.phrase !== hit.phrase || group(other) !== group(hit))));

            if (!root) { return; }

            if (route(hit.item)) {
                emit();

                return;
            }

            const replaces = hit.item.src === 'entity' || ['start', 'due', 'hours'].indexOf(hit.item.type) !== -1;
            const at = replaces ? findPhrase(root, hit.phrase) : null;

            if (at) {
                insertAtToken(hit.item, at);
            } else {
                appendTag(hit.item);
            }
        }, [route, emit, insertAtToken, appendTag]);

        const pick = useCallback((item) => {
            if (item.src === 'field' && VALUE_PICKERS[item.type] && (item.value === null || item.value === undefined)) {
                setPicker({ type: item.type, token, rect: rect || caretRect(rootRef.current) });
                closeSuggest();

                return;
            }

            insertAtToken(item, item.span !== undefined && token ? { ...token, start: item.span, prevStart: item.span, prev: '' } : token);
        }, [token, rect, insertAtToken, closeSuggest]);

        useLayoutEffect(() => {
            apiRef.current = {
                appendTag,
                html: () => {
                    if (!rootRef.current) { return ''; }

                    const copy = rootRef.current.cloneNode(true);
                    copy.querySelectorAll('.kq-interim').forEach((node) => node.remove());

                    return copy.innerHTML;
                },
                voiceStart,
                voiceInterim,
                voiceCommit,
                voiceStop,
                focus: () => {
                    const root = rootRef.current;

                    if (root) {
                        root.focus();
                        placeCaretForTyping(root);
                    }
                },
                clear: () => {
                    if (rootRef.current) {
                        rootRef.current.innerHTML = '';
                        lastPinned.current = null;
                        emit();
                    }
                },
            };
        }, [apiRef, appendTag, emit, voiceStart, voiceInterim, voiceCommit, voiceStop]);

        // Ilk icerik: kayitli okunus (duzenleme) ya da kopyalanan satir (Coglat).
        useLayoutEffect(() => {
            const root = rootRef.current;

            if (!root) { return; }

            if (initial && initial.html !== undefined) {
                root.innerHTML = initial.html;
            } else if (initial) {
                fillEditor(root, readingSegments(initial.segments), t, readOnly);
            }

            emit();
        }, []);

        useEffect(() => {
            if (autoFocus && rootRef.current && !readOnly) {
                rootRef.current.focus();
                placeCaretForTyping(rootRef.current);
            }
        }, []);

        useEffect(() => {
            if (!token) { return undefined; }

            const update = () => { if (rootRef.current) { setRect(caretRect(rootRef.current)); } };
            window.addEventListener('scroll', update, true);
            window.addEventListener('resize', update);

            return () => {
                window.removeEventListener('scroll', update, true);
                window.removeEventListener('resize', update);
            };
        }, [token]);

        KW.useEscape(() => {
            if (token) { muted.current = { node: token.node, start: token.start }; }
            closeSuggest();
        }, !!(items.length || remoteLoading));

        const insertBreak = () => {
            if (document.execCommand && document.execCommand('insertLineBreak')) {
                return;
            }

            const selection = window.getSelection();

            if (!selection || !selection.rangeCount) { return; }

            const range = selection.getRangeAt(0);
            range.deleteContents();
            const br = document.createElement('br');
            range.insertNode(br);

            if (!br.nextSibling) {
                br.parentNode.appendChild(document.createElement('br'));
            }

            placeCaretAfter(br);
        };

        const onKeyDown = (event) => {
            const mod = event.ctrlKey || event.metaKey;

            if (items.length) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setActive((value) => (value + 1) % items.length);

                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    setActive((value) => (value <= 0 ? items.length - 1 : value - 1));

                    return;
                }

                if ((event.key === 'Enter' && !mod && active >= 0) || event.key === 'Tab') {
                    event.preventDefault();
                    pick(items[active >= 0 ? active : 0]);

                    return;
                }
            }

            if (mod && event.key === 'Enter') {
                event.preventDefault();
                closeSuggest();

                if (onNewRow) { onNewRow(); }

                return;
            }

            if (mod && (event.key === 's' || event.key === 'S')) {
                event.preventDefault();
                closeSuggest();
                onSave();

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                closeSuggest();
                insertBreak();
                emit();

                return;
            }

            if (event.key === 'Backspace' && empty && onRemoveEmpty) {
                if (onRemoveEmpty()) { event.preventDefault(); }
            }
        };

        const onPaste = (event) => {
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData).getData('text/plain') || '';

            if (!(document.execCommand && document.execCommand('insertText', false, text))) {
                const selection = window.getSelection();

                if (selection && selection.rangeCount) {
                    const range = selection.getRangeAt(0);
                    range.deleteContents();
                    const node = document.createTextNode(text);
                    range.insertNode(node);
                    placeCaretAfter(node);
                }
            }

            emit();
        };

        const onClick = (event) => {
            if (readOnly) { return; }

            const remove = event.target.closest && event.target.closest('.kq-badge-x');

            if (remove) {
                event.preventDefault();
                removeBadge(remove.parentNode);
                emit();
                rootRef.current.focus();

                return;
            }

            const badge = event.target.closest && event.target.closest('.kq-badge');

            if (badge && badge.dataset.pinned === '1') {
                setPicker({ type: badge.dataset.type === 'owner' ? 'person' : 'status', token: null, rect: badge.getBoundingClientRect(), pin: badge.dataset.type });

                return;
            }

            if (badge && VALUE_PICKERS[badge.dataset.type]) {
                setPicker({ type: badge.dataset.type, token: null, rect: badge.getBoundingClientRect() });

                return;
            }

            refreshToken();
        };

        return h(Fragment, null,
            h('div', {
                ref: rootRef,
                className: cx('kq-editor', empty && !voiceOn && 'empty', readOnly && 'readonly'),
                contentEditable: !readOnly,
                suppressContentEditableWarning: true,
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-readonly': readOnly ? 'true' : undefined,
                'aria-label': placeholder,
                'data-placeholder': placeholder,
                spellCheck: false,
                lang: 'tr',
                onInput: () => { emit(); refreshToken(); },
                onKeyDown,
                onKeyUp: (event) => { if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) !== -1) { refreshToken(); } },
                onPaste,
                onClick,
                onFocus,
                onBlur: () => window.setTimeout(() => { if (rootRef.current && document.activeElement !== rootRef.current) { closeSuggest(); } }, 150),
            }),
            voiceFound.length && !readOnly ? h('div', { className: 'kq-voice', role: 'group', 'aria-label': t('qa_voice_found') },
                h('span', { className: 'kq-voice-label' }, h(Icon, { name: 'mic' }), t('qa_voice_found')),
                voiceFound.map((hit) => h('button', {
                    key: hit.item.type + ':' + (hit.item.id || hit.item.value),
                    type: 'button',
                    className: cx('kq-voice-chip', 'kq-t-' + hit.item.type, hit.item.type === 'status' && 'kq-s-' + hit.item.value),
                    title: '«' + hit.phrase + '» · ' + typeLabel(t, hit.item.type),
                    onMouseDown: (event) => event.preventDefault(),
                    onClick: () => applyVoice(hit),
                }, h(Icon, { name: hit.item.type }), badgeText(hit.item, t), h('span', { className: 'kq-voice-said' }, (voiceFound.filter((other) => other.phrase === hit.phrase && other.item.src === 'entity').length > 1 && hit.item.src === 'entity' ? typeLabel(t, hit.item.type) + ' · ' : '') + '«' + hit.phrase + '»'))),
                voiceFound.length > 1 ? h('button', {
                    type: 'button',
                    className: 'kq-voice-all',
                    onMouseDown: (event) => event.preventDefault(),
                    onClick: () => {
                        const firsts = {};
                        voiceFound.forEach((hit) => { if (!firsts[hit.phrase]) { firsts[hit.phrase] = hit; } });
                        Object.keys(firsts).forEach((phrase) => applyVoice(firsts[phrase]));
                        setVoiceFound([]);
                    },
                }, t('qa_voice_add_all')) : null,
                h('button', { type: 'button', className: 'kq-voice-x', 'aria-label': t('qa_voice_dismiss'), title: t('qa_voice_dismiss'), onClick: () => setVoiceFound([]) }, '×'),
            ) : null,
            items.length || remoteLoading ? h(SuggestList, { items, active, rect, loading: remoteLoading, onPick: pick, onHover: setActive }) : null,
            picker ? h(PickerPop, {
                type: picker.type,
                rect: picker.rect,
                ctx,
                onClose: () => setPicker(null),
                onPick: (item) => {
                    const at = picker.token;
                    setPicker(null);

                    if (picker.pin) {
                        setPinned(picker.pin, item);
                        emit();
                    } else if (at) {
                        insertAtToken(item, at);
                    } else {
                        appendTag(item);
                    }
                },
            }) : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Plan: duzenleyici icerigi -> is kaydi                                */
    /* ------------------------------------------------------------------ */

    function sourcePayload(prefix, badge) {
        const empty = {};
        empty[prefix + '_kind'] = null;
        empty[prefix + '_personnel_id'] = null;
        empty[prefix + '_party_id'] = null;
        empty[prefix + '_text'] = null;

        if (!badge || !badge.kind) { return empty; }

        const out = { ...empty };
        out[prefix + '_kind'] = badge.kind;

        if (badge.kind === 'personnel') {
            out[prefix + '_personnel_id'] = badge.id;
        } else if (badge.kind === 'party') {
            out[prefix + '_party_id'] = badge.id;
            out[prefix + '_text'] = badge.label || null;
        } else {
            out[prefix + '_text'] = badge.value || badge.label || null;
        }

        return out;
    }

    /** keep: duzenlenen isin degerleri (rozeti silinen sahip / durum korunur; otomatik isin baglantisi degismez). */
    function planOf(model, ctx, keep) {
        const badges = model.filter((item) => item.t === 'badge').map((item) => item.b);
        const first = (type) => badges.find((badge) => badge.type === type) || null;
        // Sabit rozetler: durum ve Sorumlu (ortukse secilmemis sayilir).
        const pinnedStatus = badges.find((badge) => badge.pinned && badge.type === 'status') || null;
        const pinnedOwner = badges.find((badge) => badge.pinned && badge.type === 'owner') || null;
        const owner = pinnedOwner && !pinnedOwner.implicit && pinnedOwner.id ? pinnedOwner : null;
        const seen = owner ? { [owner.id]: true } : {};
        const unit = first('unit');
        const project = first('project');
        const linkSeen = {};
        const links = badges.filter((badge) => LINK_KIND[badge.type] && !linkSeen[badge.type + badge.id] && (linkSeen[badge.type + badge.id] = true));
        const status = pinnedStatus && !pinnedStatus.implicit ? pinnedStatus : null;
        const waiting = first('waiting');
        const team = first('team');
        const text = textParts(model);
        const me = ctx.me || {};
        const warnings = [];
        // Metindeki kisiler ek sorumludur (Sorumlu sabit rozettedir).
        let assignees = badges.filter((badge) => badge.type === 'person' && !seen[badge.id] && (seen[badge.id] = true));

        if (assignees.length && !ctx.extended) {
            warnings.push(ctx.t('qa_warn_people'));
            assignees = [];
        }

        // Her tur birden fazla baglanir (D-233, D-235). Ilk bagli kayit isin kendi
        // kolonunda, digerleri ek kayit tablolarinda; ek tablosu henuz olmayan tur
        // (sunucu multi_kinds'ta bildirmez) varsa ilk kayit o olur ki kaybolmasin.
        const canExtra = (badge) => ctx.multiLinks && ctx.multiKinds.indexOf(badge.type) !== -1;
        const primary = links.find((badge) => badge.locked) || links.find((badge) => !canExtra(badge)) || links[0] || null;
        const others = links.filter((badge) => badge !== primary);
        const extra = others.filter(canExtra);

        if (extra.length !== others.length) {
            warnings.push(ctx.t('qa_warn_links'));
        }

        let forEveryone = false;

        if (team) {
            if (ctx.canShare) { forEveryone = true; } else { warnings.push(ctx.t('qa_warn_team')); }
        } else if (!owner && unit && ctx.canShare) {
            forEveryone = true;
        } else if (!owner && unit && !ctx.canShare) {
            warnings.push(ctx.t('qa_warn_unit_own'));
        }

        const statusValue = status ? status.value : (waiting ? 'waiting' : ((keep && keep.status) || 'planned'));
        const payload = {
            title: text.title,
            note: text.note,
            status: statusValue,
            personnel_id: owner ? owner.id : (keep ? keep.personnelId : me.id),
            project_id: project ? project.id : null,
            due_on: first('due') ? first('due').value : null,
            work_hours: first('hours') ? Number(first('hours').value) : null,
            category_code: first('category') ? first('category').value : null,
            ...sourcePayload('requester', first('requester')),
            ...sourcePayload('waiting', waiting),
        };

        if (!(keep && keep.lockedLink) && (!primary || !primary.locked)) {
            payload.link_kind = primary ? LINK_KIND[primary.type] : null;
            payload.link_id = primary ? primary.id : null;
        }

        if (unit) { payload.org_unit_id = unit.id; }
        if (first('start')) { payload.work_on = first('start').value; }

        if (ctx.extended) {
            payload.assignee_ids = assignees.map((person) => person.id);
            payload.for_everyone = forEveryone;
            payload.priority = first('priority') ? first('priority').value : 'normal';
            // D-245: is grubu kapaliyken (secenek yok) alan hic gonderilmez; kayitli deger korunur.
            if ((ctx.disciplines || []).length) { payload.discipline = first('discipline') ? first('discipline').value : null; }
        }

        if (ctx.multiLinks) {
            payload.extra_links = extra.map((badge) => ({ kind: LINK_KIND[badge.type], id: badge.id }));
        }

        if (statusValue === 'waiting' && !waiting) {
            warnings.push(ctx.t('qa_warn_waiting'));
        }

        return {
            owner, assignees, unit, team: forEveryone, project, links: [primary].filter(Boolean).concat(extra), status: statusValue, waiting,
            start: first('start'), due: first('due'), hours: first('hours'), priority: first('priority'), discipline: first('discipline'),
            category: first('category'), requester: first('requester'), title: text.title, warnings, payload, editing: !!keep,
        };
    }

    /** Yalniz uyarilar (D-235: rozetler editorde; ayni bilgi ikinci kez gosterilmez). */
    function Warnings(props) {
        const { plan } = props;

        if (!plan.warnings.length) { return null; }

        return h('div', { className: 'kq-warns', role: 'status' }, plan.warnings.map((text, i) => h('span', { key: 'w' + i, className: 'kq-warn' }, h(Icon, { name: 'warn' }), text)));
    }

    /* ------------------------------------------------------------------ */
    /* Ortak yazma alani (pencere, pano, ust cubuk)                         */
    /* ------------------------------------------------------------------ */

    let rowSeq = 0;
    const newRow = (initial) => ({ key: 'r' + (++rowSeq), model: [], error: null, state: 'idle', initial: initial || null });
    // Bos is: yazi yok ve sabit rozetler disinda rozet yok.
    const isEmptyModel = (model) => !model.some((item) => (item.t === 'text' ? !!item.v.trim() : item.t === 'badge' && !item.b.pinned));

    function sentenceSegments(card) {
        return card && card.sentence ? card.sentence : [{ t: 'text', v: card ? card.title : '' }];
    }

    /**
     * Arac cubugu + is satirlari + kaydet. mode: create | edit | view.
     * Pencere ve panodaki acik alan ayni durumu kullanir.
     */
    /**
     * Oneri baglami (D-240): secenekler (kisi, departman, taahhut, teklif, is
     * gelistirme, ihale) bir kez yuklenir; yazma alani, Isler listesinin
     * suzgecleri ve rapor denemeleri ayni baglami kullanir.
     */
    function useQuickContext() {
        const app = useApp();
        const { t, config, api } = app;
        const [data, setData] = useState(null);
        const [loadError, setLoadError] = useState(null);

        const load = useCallback(() => {
            setLoadError(null);

            if (!config.endpoints.quick_options) { setLoadError(new Error('missing_endpoint')); return; }

            loadOptions(api, config.endpoints.quick_options).then(setData).catch((error) => setLoadError(error));
        }, [api, config]);

        useEffect(() => { load(); }, [load]);

        const features = config.features || {};
        const ctx = useMemo(() => {
            const categoryLabels = (config.options && config.options.category_labels) || {};

            return {
                t,
                api,
                today: config.today,
                me: config.me,
                index: data ? buildIndex(data) : null,
                people: data ? (data.people || []).map((p) => ({ id: p.id, label: p.label, sub: p.sub })) : [],
                extended: !!features.extended,
                multiLinks: !!features.multi_links,
                linkTypes: features.link_types || [],
                multiKinds: features.multi_kinds || [],
                searchUrl: config.endpoints.quick_search || null,
                canShare: !!features.extended && !!(config.can && (config.can.team || config.can.all)),
                statuses: (config.options && config.options.statuses) || [],
                priorities: (config.options && config.options.priorities) || [],
                disciplines: (config.options && config.options.disciplines) || [],
                categories: Object.keys(categoryLabels).map((code) => ({ value: code, label: categoryLabels[code] })).sort((a, b) => a.label.localeCompare(b.label, 'tr')),
            };
        }, [data, config, t, api]);

        return { data, loadError, load, ctx };
    }

    /*
     * options.seed (D-240, rapor yazma denemesi): yeni satirlarin ilk rozetleri
     * (raporun konusu, plan satirinin baslangic tarihi); options.placeholder: ilk
     * satirin yer tutucusu. Donen push(list): verilen okunuslari satir olarak ekler
     * ("Ise donustur"). config.features.demo: kayit yok, yalniz bildirim.
     */
    function useComposer(options) {
        const { mode, card, compact, onSaved, seed, placeholder } = options;
        const app = useApp();
        const { t, config, api } = app;
        const { data, loadError, load, ctx } = useQuickContext();
        const seeded = () => (seed && seed.length ? { segments: seed } : null);
        const [rows, setRows] = useState(() => [newRow(card ? { segments: sentenceSegments(card) } : seeded())]);
        const [activeKey, setActiveKey] = useState(null);
        const [picker, setPicker] = useState(null);
        const [saving, setSaving] = useState(false);
        const apis = useRef({});
        const focusKey = useRef(null);
        const editing = mode === 'edit';
        const readOnly = mode === 'view';
        const keep = useMemo(() => (editing && card ? {
            status: card.status || null,
            personnelId: card.personnel ? card.personnel.id : null,
            lockedLink: card.source === 'automatic' && !!card.link,
        } : null), [editing, card]);

        const features = config.features || {};

        /*
         * Belge (D-242; kullanici 23 Ekim 2026: "is olusturulurken kismina da belge
         * yuklenebilir olmalidir. Yani is panosu direk ilgili textareaya surukle
         * birakla belge eklenebilsin"): belge yazinin ustune birakilir ya da atac
         * dugmesiyle secilir; satirda bekler, is kaydedilince o ise yuklenir.
         */
        const canAttach = !readOnly && !features.demo && !!(config.endpoints && config.endpoints.files);
        const [dropKey, setDropKey] = useState(null);
        // Kayitta belge yukleme ilerlemesi: "satir:sira" => { state, loaded } (D-243).
        const [progress, setProgress] = useState({});
        const hasFiles = (event) => !!(event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'Files') !== -1);

        const attach = (key, list) => {
            const chosen = Array.prototype.slice.call(list || []).filter((file) => file && file.name);

            if (!chosen.length) { return; }

            setRows((all) => all.map((row) => {
                if (row.key !== key) { return row; }

                const files = (row.files || []).concat(chosen.filter((file) => !(row.files || []).some((other) => other.name === file.name && other.size === file.size)));

                if (files.length > ATTACH_LIMIT) { app.toast({ text: t('qa_attach_limit', { n: ATTACH_LIMIT }), tone: 'bad' }); }

                return { ...row, files: files.slice(0, ATTACH_LIMIT) };
            }));
            setActiveKey(key);
        };

        const detach = (key, index) => setRows((all) => all.map((row) => (row.key === key ? { ...row, files: (row.files || []).filter((_, i) => i !== index) } : row)));

        const apiOf = (key) => {
            if (!apis.current[key]) { apis.current[key] = { current: null }; }

            return apis.current[key];
        };

        const setRow = (key, patch) => setRows((list) => list.map((row) => (row.key === key ? { ...row, ...patch } : row)));

        const addRow = (afterKey, initial) => {
            const row = newRow(initial || seeded());
            focusKey.current = row.key;
            setRows((list) => {
                const at = afterKey ? list.findIndex((item) => item.key === afterKey) : list.length - 1;
                const next = list.slice();
                next.splice(at + 1, 0, row);

                return next;
            });
            setActiveKey(row.key);
        };

        const duplicateRow = (key) => {
            const source = apis.current[key];

            addRow(key, { html: source && source.current ? source.current.html() : '' });
        };

        const removeRow = (key) => {
            setRows((list) => {
                if (list.length === 1) {
                    const only = apis.current[key];

                    if (only && only.current) { only.current.clear(); }

                    return [{ ...list[0], model: [], error: null }];
                }

                const index = list.findIndex((row) => row.key === key);
                const next = list.filter((row) => row.key !== key);
                const target = next[Math.max(0, index - 1)];
                focusKey.current = target ? target.key : null;

                return next;
            });
            delete apis.current[key];
        };

        useLayoutEffect(() => {
            if (!focusKey.current) { return; }

            const target = apis.current[focusKey.current];
            focusKey.current = null;

            if (target && target.current) { target.current.focus(); }
        }, [rows]);

        const plans = useMemo(() => {
            const out = {};
            rows.forEach((row) => { out[row.key] = planOf(row.model, ctx, keep); });

            return out;
        }, [rows, ctx, keep]);

        const filled = rows.filter((row) => !isEmptyModel(row.model));

        const reset = () => {
            apis.current = {};
            setRows([newRow(seeded())]);
        };

        /** Okunuslari satir olarak ekler; yazisi olmayan satirlar (bos ya da yalniz ilk rozetler) yerini birakir. */
        const push = (list) => {
            const added = (list || []).filter((segments) => segments && segments.length).map((segments) => newRow({ segments }));

            if (!added.length) { return; }

            focusKey.current = added[added.length - 1].key;
            setRows((current) => current.filter((row) => row.model.some((item) => item.t === 'text' && item.v.trim() !== '')).concat(added));
            setActiveKey(added[added.length - 1].key);
        };

        const save = async () => {
            if (saving || readOnly) { return; }

            stopVoice();

            if (!filled.length) {
                app.toast({ text: t('qa_empty_rows'), tone: 'bad' });

                return;
            }

            if (features.demo) {
                app.toast({ text: t('qa_demo_saved', { n: filled.length }), tone: 'ok' });

                return 'demo';
            }

            setRows((list) => list.map((row) => (isEmptyModel(row.model) ? row : { ...row, error: plans[row.key].title ? null : t('qa_title_required') })));

            if (filled.some((row) => !plans[row.key].title)) { return; }

            setSaving(true);
            const saved = [];
            const failed = {};
            const uploads = { added: 0, errors: [] };

            for (const row of filled) {
                setRow(row.key, { state: 'saving', error: null });

                try {
                    const payload = plans[row.key].payload;
                    const json = editing
                        ? await api.post(app.url(config.endpoints.update, card.id), { ...payload, row_version: card.row_version })
                        : await api.post(config.endpoints.store, payload);
                    const files = row.files || [];

                    // Is kaydedildi; satirda bekleyen belgeler ona tek tek, ilerlemesiyle yuklenir
                    // (D-243); yuklenemeyen belge isi bozmaz.
                    if (canAttach && files.length && json.card && json.card.id) {
                        for (let index = 0; index < files.length; index++) {
                            const slot = row.key + ':' + index;
                            setProgress((map) => ({ ...map, [slot]: { state: 'up', loaded: 0 } }));

                            try {
                                const result = await uploadOne(app.url(config.endpoints.files, json.card.id), files[index], config.csrf, (loaded) => setProgress((map) => ({ ...map, [slot]: { state: 'up', loaded } })));
                                uploads.added += result.added || 0;
                                json.card.files_count = (json.card.files_count || 0) + (result.added || 0);
                                setProgress((map) => ({ ...map, [slot]: { state: 'done', loaded: files[index].size } }));
                            } catch (error) {
                                uploads.errors.push(files[index].name + ': ' + app.describe(error));
                                setProgress((map) => ({ ...map, [slot]: { state: 'fail', loaded: 0 } }));
                            }
                        }
                    }

                    saved.push(json.card);
                    setRow(row.key, { state: 'saved', files: [] });
                } catch (error) {
                    failed[row.key] = app.describe(error);
                    setRow(row.key, { state: 'idle', error: failed[row.key] });
                }
            }

            setSaving(false);

            setProgress({});

            if (uploads.added) { app.toast({ text: t('qa_th_uploaded', { n: uploads.added }), tone: 'ok' }); }
            if (uploads.errors.length) { app.toast({ text: t('qa_attach_failed', { reason: uploads.errors[0] }), tone: 'bad', duration: 8000 }); }

            const bad = Object.keys(failed).length;

            if (saved.length) {
                window.dispatchEvent(new CustomEvent('kq-saved', { detail: { cards: saved, editing } }));

                if (window.Livewire && typeof window.Livewire.dispatch === 'function') { window.Livewire.dispatch('kq-saved'); }

                if (onSaved) { onSaved(saved, editing); }
            }

            if (!bad) {
                app.toast({ text: editing ? t('saved') : t('qa_created', { n: saved.length }), tone: 'ok' });

                if (compact) { reset(); }

                return 'done';
            }

            setRows((list) => {
                const left = list.filter((row) => row.state !== 'saved');

                return left.length ? left : [newRow()];
            });
            app.toast({ text: saved.length ? t('qa_partial', { ok: saved.length, bad }) : t('qa_failed', { n: bad }), tone: 'bad', duration: 7000 });

            return 'partial';
        };

        const activeApi = () => {
            const key = activeKey && rows.some((row) => row.key === activeKey) ? activeKey : rows[rows.length - 1].key;

            return apiOf(key);
        };

        /*
         * Sesle yazma (D-236): tarayicinin konusma tanimasi (Web Speech API) surekli
         * dinler; ara sonuc editorde silik yazi, kesin sonuc yazi olur. Kisa sessizlikte
         * tarayici durursa dinleme surerken yeniden baslar. Kaydetmek dinlemeyi bitirir.
         */
        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
        const voiceFeature = !!features.voice && !readOnly;
        const [listening, setListening] = useState(false);
        const recognition = useRef(null);
        const wantVoice = useRef(false);
        const voiceTarget = useRef(null);
        const voiceApi = () => (voiceTarget.current && voiceTarget.current.current) || null;

        const stopVoice = useCallback(() => {
            wantVoice.current = false;

            if (recognition.current) {
                try { recognition.current.stop(); } catch (ignored) { /* zaten durmus */ }
            }
        }, []);

        const startVoice = () => {
            if (!Recognition) {
                app.toast({ text: t('qa_voice_unsupported'), tone: 'bad', duration: 6000 });

                return;
            }

            if (!window.isSecureContext) {
                app.toast({ text: t('qa_voice_insecure'), tone: 'bad', duration: 6000 });

                return;
            }

            const target = activeApi();

            if (!target.current) { return; }

            voiceTarget.current = target;
            target.current.voiceStart();

            const rec = new Recognition();
            rec.lang = config.locale === 'en' ? 'en-US' : 'tr-TR';
            rec.continuous = true;
            rec.interimResults = true;
            rec.maxAlternatives = 1;
            rec.onresult = (event) => {
                const api = voiceApi();
                let interim = '';

                if (!api) { return; }

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const result = event.results[i];

                    if (result.isFinal) {
                        api.voiceCommit(result[0].transcript);
                    } else {
                        interim += result[0].transcript;
                    }
                }

                api.voiceInterim(interim.trim());
            };
            rec.onerror = (event) => {
                const fatal = { 'not-allowed': 'qa_voice_denied', 'service-not-allowed': 'qa_voice_denied', 'audio-capture': 'qa_voice_no_mic', network: 'qa_voice_network' };

                if (fatal[event.error]) {
                    wantVoice.current = false;
                    app.toast({ text: t(fatal[event.error]), tone: 'bad', duration: 7000 });
                }
            };
            rec.onend = () => {
                if (wantVoice.current && recognition.current === rec) {
                    try {
                        rec.start();

                        return;
                    } catch (ignored) {
                        wantVoice.current = false;
                    }
                }

                if (recognition.current === rec) { recognition.current = null; }

                setListening(false);
                const api = voiceApi();

                if (api) { api.voiceStop(); }

                voiceTarget.current = null;
            };

            recognition.current = rec;
            wantVoice.current = true;

            try {
                rec.start();
                setListening(true);
            } catch (error) {
                wantVoice.current = false;
                recognition.current = null;
                target.current.voiceStop();
                app.fail(error);
            }
        };

        useEffect(() => () => {
            wantVoice.current = false;

            if (recognition.current) {
                try { recognition.current.abort(); } catch (ignored) { /* kapanirken */ }
            }
        }, []);

        const choose = (type, rect) => {
            if (type === 'team') {
                const target = activeApi();

                if (target.current) { target.current.appendTag({ type: 'team', value: '1', label: t('qa_type_team') }); }

                return;
            }

            setPicker(picker && picker.type === type ? null : { type, rect });
        };

        const mainTypes = ENTITY_TYPES;
        const fieldTypes = ['status', 'start', 'due', 'hours', 'category', 'requester', 'waiting']
            .concat(ctx.extended ? ['priority'] : [])
            .concat(ctx.extended && (ctx.disciplines || []).length ? ['discipline'] : [])
            .concat(ctx.canShare ? ['team'] : []);
        const linkTypes = ctx.linkTypes;

        const toolbar = readOnly || !data ? null : h('div', { className: cx('kq-toolbar', compact && 'compact'), role: 'toolbar', 'aria-label': t('qa_insert') },
            compact ? null : h('span', { className: 'kq-toolbar-label' }, t('qa_insert')),
            mainTypes.map((type) => h('button', {
                key: type,
                type: 'button',
                className: cx('kq-tool', 'kq-t-' + type, picker && picker.type === type && 'open'),
                onMouseDown: (event) => event.preventDefault(),
                onClick: (event) => choose(type, event.currentTarget.getBoundingClientRect()),
            }, h(Icon, { name: type }), typeLabel(t, type))),
            h('span', { className: 'kq-sep', 'aria-hidden': 'true' }),
            h(ToolMenu, { label: t('qa_menu_fields'), icon: 'fields', types: fieldTypes, onChoose: choose }),
            h(ToolMenu, { label: t('qa_menu_links'), icon: 'link', types: linkTypes, onChoose: choose }),
            voiceFeature ? h('button', {
                type: 'button',
                className: cx('kq-tool', 'kq-mic', listening && 'on'),
                'aria-pressed': String(listening),
                disabled: !Recognition,
                title: !Recognition ? t('qa_voice_unsupported') : (listening ? t('qa_voice_stop') : t('qa_voice_help')),
                onMouseDown: (event) => event.preventDefault(),
                onClick: () => (listening ? stopVoice() : startVoice()),
            }, listening ? h('span', { className: 'kq-mic-dot', 'aria-hidden': 'true' }) : h(Icon, { name: 'mic' }), listening ? t('qa_voice_listening') : t('qa_voice')) : null,
        );

        const rowsEl = h('ol', { className: cx('kq-rows', compact && 'compact') }, rows.map((row, i) => h('li', {
            key: row.key,
            className: cx('kq-row', row.key === activeKey && 'active', row.error && 'has-error', row.state === 'saved' && 'saved', row.state === 'saving' && 'saving', row.plain && 'plain', dropKey === row.key && 'drop'),
            'data-drop-label': canAttach ? t('qa_drop') : undefined,
            // D-242: belge yazinin ustune birakilir (tarayici dosyayi acmaz, editore yazmaz).
            onDragOver: canAttach ? (event) => {
                if (!hasFiles(event)) { return; }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                if (dropKey !== row.key) { setDropKey(row.key); }
            } : undefined,
            onDragLeave: canAttach ? (event) => { if (!event.currentTarget.contains(event.relatedTarget)) { setDropKey(null); } } : undefined,
            onDrop: canAttach ? (event) => {
                if (!hasFiles(event)) { return; }
                event.preventDefault();
                setDropKey(null);
                attach(row.key, event.dataTransfer.files);
            } : undefined,
        },
        rows.length > 1 ? h('span', { className: 'kq-num', 'aria-hidden': 'true' }, row.state === 'saved' ? h(Icon, { name: 'check' }) : i + 1) : null,
        h('div', { className: 'kq-main' },
            data || readOnly ? h(QuickEditor, {
                apiRef: apiOf(row.key),
                ctx,
                autoFocus: i === rows.length - 1 && !compact && !readOnly,
                readOnly,
                keepStatus: keep ? keep.status : null,
                initial: row.initial,
                placeholder: i === 0 ? (placeholder || t(compact ? 'qa_placeholder_inline' : 'qa_placeholder')) : t('qa_placeholder_next'),
                onChange: (model) => setRow(row.key, { model, error: null }),
                onFocus: () => setActiveKey(row.key),
                onNewRow: editing ? null : () => addRow(row.key),
                onSave: save,
                onRemoveEmpty: editing ? null : () => {
                    if (rows.length > 1) {
                        removeRow(row.key);

                        return true;
                    }

                    return false;
                },
            }) : null,
            // D-241: duz yazi satirin yerinde gorunur (duzenleyici gizli kalir, durumu korunur); duzenlenemez.
            row.plain ? h('div', { className: 'kq-plain-row' }, h(ReadingView, { segments: row.model, plain: true })) : null,
            (row.files || []).length ? h('ul', { className: 'kq-attach', 'aria-label': t('qa_attach') }, row.files.map((file, index) => {
                // D-243: kayitta her belge kendi ilerlemesiyle (dolan zemin, yuzde, bitince isaret).
                const step = progress[row.key + ':' + index] || null;
                const pct = step ? (step.state === 'done' ? 100 : percentOf(step.loaded, file.size)) : 0;

                return h('li', { key: file.name + file.size, className: cx('kq-attach-chip', step && step.state), style: step ? { '--kq-pct': pct + '%' } : undefined },
                    h(Icon, { name: step && step.state === 'done' ? 'check' : (step && step.state === 'fail' ? 'warn' : 'document') }),
                    h('span', { className: 'kq-attach-name' }, file.name),
                    h('span', { className: 'kq-attach-size' }, step ? (step.state === 'fail' ? t('qa_up_failed') : '%' + pct) : sizeText(file.size)),
                    step ? null : h('button', { type: 'button', className: 'kq-attach-x', title: t('qa_attach_remove'), 'aria-label': t('qa_attach_remove'), disabled: saving, onClick: () => detach(row.key, index) }, '×'));
            })) : null,
            !isEmptyModel(row.model) && !readOnly ? h(Warnings, { plan: plans[row.key] }) : null,
            row.error ? h('p', { className: 'kq-error', role: 'alert' }, h(Icon, { name: 'warn' }), row.error) : null,
        ),
        h('div', { className: 'kq-row-tools' },
            h(FormatToggle, { plain: !!row.plain, disabled: isEmptyModel(row.model) && !row.plain, onToggle: () => setRow(row.key, { plain: !row.plain }) }),
            h(CopyButton, { text: isEmptyModel(row.model) ? '' : readingText(row.model, t) }),
            canAttach ? h('label', { className: cx('kq-row-btn', saving && 'disabled'), title: t('qa_attach'), 'aria-label': t('qa_attach') },
                h('input', { type: 'file', multiple: true, className: 'kw-sr', disabled: saving, onChange: (event) => { attach(row.key, event.target.files); event.target.value = ''; } }),
                h(Icon, { name: 'document' })) : null,
            editing || readOnly ? null : h('button', {
                type: 'button',
                className: 'kq-row-btn',
                title: t('qa_duplicate'),
                'aria-label': t('qa_duplicate'),
                disabled: saving || isEmptyModel(row.model),
                onClick: () => duplicateRow(row.key),
            }, h(Icon, { name: 'copy' })),
            editing || readOnly ? null : h('button', {
                type: 'button',
                className: 'kq-row-btn danger',
                title: t('qa_remove_row'),
                'aria-label': t('qa_remove_row'),
                disabled: saving,
                onClick: () => removeRow(row.key),
            }, h(Icon, { name: 'close' })),
        ),
        )));

        const addButton = editing || readOnly ? null : h('button', { type: 'button', className: cx('kq-add', compact && 'compact'), disabled: saving || !data, onClick: () => addRow(rows[rows.length - 1].key) },
            h(Icon, { name: 'plus' }), t('qa_add_row'), compact ? null : h('kbd', { className: 'kq-kbd' }, 'Ctrl + Enter'));

        const pickerEl = picker ? h(PickerPop, {
            type: picker.type,
            rect: picker.rect,
            ctx,
            onClose: (event) => {
                if (event && event.target && event.target.closest && event.target.closest('.kq-tool, .kq-menu')) { return; }
                setPicker(null);
            },
            onPick: (item) => {
                setPicker(null);
                const target = activeApi();

                if (target.current) { target.current.appendTag(item); }
            },
        }) : null;

        return { data, loadError, load, ctx, toolbar, rowsEl, addButton, pickerEl, count: filled.length, models: filled.map((row) => row.model), push, saving, save, readOnly, editing, canAttach };
    }

    /* ------------------------------------------------------------------ */
    /* Okuma gorunumu, cevaplar ve belgeler (D-241)                         */
    /* ------------------------------------------------------------------ */

    function sizeText(bytes) {
        if (!bytes) { return ''; }
        if (bytes < 1024 * 1024) { return Math.max(1, Math.round(bytes / 1024)) + ' KB'; }

        return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' MB';
    }

    /**
     * Tek belge yukleme, ilerlemesiyle (D-243; kullanici 23 Ekim 2026: "Belgeler
     * eklendiginde yukleniyor seklinde bir progress dolmasini istiyorum. Ne kadari
     * yuklendigi gorulsun, ayrica birden cok belge yuklenmeye calisildiginda da
     * belgelerin ayri ayri yuklendigi bilinsin"): her belge ayri istekle gider;
     * onProgress(yuklenen, toplam) bayt.
     */
    function uploadOne(url, file, csrf, onProgress) {
        return new Promise((resolve, reject) => {
            if (!url) {
                const missing = new Error('missing_endpoint');
                missing.status = 0;
                reject(missing);

                return;
            }

            const xhr = new XMLHttpRequest();
            const form = new FormData();
            form.append('files[]', file);
            xhr.open('POST', url);
            xhr.withCredentials = true;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf || '');
            xhr.upload.onprogress = (event) => { if (onProgress) { onProgress(event.loaded, event.lengthComputable ? event.total : file.size); } };
            xhr.onload = () => {
                let data = null;

                try { data = JSON.parse(xhr.responseText); } catch (ignored) { data = null; }

                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(data || {});

                    return;
                }

                const error = new Error((data && data.message) || xhr.statusText || 'error');
                error.status = xhr.status;
                error.payload = data;
                reject(error);
            };
            xhr.onerror = () => {
                const network = new Error('network_error');
                network.status = 0;
                network.network = true;
                reject(network);
            };
            xhr.send(form);
        });
    }

    const percentOf = (loaded, total) => (total ? Math.min(100, Math.round((loaded / total) * 100)) : 0);

    /** Yukleme listesi: her belge kendi cubuguyla; ustte toplam (kac belge, yuzde kac). */
    function UploadList(props) {
        const { items, onDismiss } = props;
        const { t } = useApp();

        if (!items.length) { return null; }

        const total = items.reduce((sum, item) => sum + (item.size || 0), 0);
        const loaded = items.reduce((sum, item) => sum + (item.state === 'done' ? (item.size || 0) : Math.min(item.loaded || 0, item.size || 0)), 0);
        const done = items.filter((item) => item.state === 'done').length;
        const running = items.some((item) => item.state === 'up' || item.state === 'wait');

        return h('div', { className: 'kq-uploads', role: 'status', 'aria-live': 'polite' },
            h('div', { className: 'kq-uploads-head' },
                h('span', null, running ? t('qa_up_running', { done, n: items.length }) : t('qa_up_finished', { done, n: items.length })),
                h('b', null, '%' + percentOf(loaded, total))),
            h('div', { className: 'kq-bar' }, h('span', { style: { width: percentOf(loaded, total) + '%' } })),
            h('ul', { className: 'kq-upload-list' }, items.map((item) => {
                const pct = item.state === 'done' ? 100 : percentOf(item.loaded || 0, item.size || 0);

                return h('li', { key: item.key, className: cx('kq-upload-item', item.state) },
                    h(Icon, { name: item.state === 'done' ? 'check' : (item.state === 'fail' ? 'warn' : 'document') }),
                    h('span', { className: 'kq-upload-name', title: item.name }, item.name),
                    h('span', { className: 'kq-upload-pct' }, item.state === 'fail' ? t('qa_up_failed') : (item.state === 'wait' ? t('qa_up_waiting') : '%' + pct)),
                    item.state === 'fail' && onDismiss ? h('button', { type: 'button', className: 'kq-attach-x', title: t('close'), 'aria-label': t('close'), onClick: () => onDismiss(item.key) }, '×') : null,
                    h('div', { className: 'kq-bar small' }, h('span', { style: { width: pct + '%' } })),
                    item.state === 'fail' && item.error ? h('p', { className: 'kq-upload-error' }, item.error) : null);
            })));
    }

    /** Belgeyi indirmeden goruntuleme penceresi (gorsel, PDF, Excel, Word, metin). */
    function FilePreview(props) {
        const { file, onClose } = props;
        const { t } = useApp();
        const office = file.kind === 'sheet' || file.kind === 'word';
        const src = office && file.preview ? file.preview + (file.preview.indexOf('?') === -1 ? '?' : '&') + 'embed=1' : (file.preview || file.url);

        return h(Modal, {
            title: h('span', { className: 'kq-title kq-preview-title', title: file.name }, h(Icon, { name: 'document' }), file.name),
            sub: [sizeText(file.size), file.at].filter(Boolean).join(' · ') || null,
            size: 'preview',
            onClose,
            footer: [
                h('a', { key: 'd', className: 'kw-btn', href: file.download }, h(Icon, { name: 'download' }), t('qa_th_download')),
                h(Btn, { key: 'c', onClick: onClose }, t('close')),
            ],
        },
        file.kind === 'image'
            ? h('div', { className: 'kq-preview-image' }, h('img', { src: file.url, alt: file.name }))
            : h('iframe', { className: 'kq-preview-frame', src, title: file.name }));
    }

    const HISTORY_ICON = {
        created: 'plus', status: 'status', edit: 'edit', priority: 'priority', reply: 'chat', note: 'text',
        file: 'document', file_removed: 'document', archive: 'archive', restore: 'undo', late: 'report', warn: 'warn',
    };

    function HistoryValue(props) {
        const { value, tone, old } = props;
        const { t } = useApp();

        return h('span', { className: cx('kq-hist-val', old && 'old', tone && 'kq-hist-status kq-s-' + tone, value === null && 'empty') }, value === null ? t('qa_hist_cleared') : value);
    }

    /**
     * Isin gecmisi (D-248; kullanici 23 Ekim 2026: "ilgili iste personeller
     * neler yaptigini gormemiz lazim. adimlar kaybolmamali"): kim, ne zaman, ne
     * yapti; gun gun, eskiden yeniye (son adim altta, cevaplar gibi). Ayni
     * pencerede cevap / belge sonrasi `refresh` ile yenilenir. Adim sayisi
     * sekme rozetine `onCount` ile gider.
     */
    function WorkHistory(props) {
        const { card, active, refresh, onCount } = props;
        const app = useApp();
        const { t, config, api } = app;
        const [entries, setEntries] = useState(null);
        const [error, setError] = useState(null);
        const listRef = useRef(null);

        const load = useCallback(() => {
            setError(null);
            api.get(app.url(config.endpoints.history, card.id)).then((json) => {
                const list = (json && json.entries) || [];
                setEntries(list);
                if (onCount) { onCount(list.length); }
            }).catch(setError);
        }, [api, config, card.id]);

        useEffect(() => { load(); }, [load, refresh]);

        useEffect(() => {
            if (active && listRef.current) { listRef.current.scrollTop = listRef.current.scrollHeight; }
        }, [entries, active]);

        if (!active) { return null; }
        if (error) { return h(KW.State, { error, onRetry: load }); }
        if (!entries) { return h(KW.State, { loading: true }); }
        if (!entries.length) { return h('p', { className: 'kq-th-empty' }, t('qa_hist_empty')); }

        const days = [];

        entries.forEach((entry) => {
            const last = days[days.length - 1];

            if (last && last.day === entry.day) {
                last.items.push(entry);
            } else {
                days.push({ day: entry.day, label: entry.day_label, items: [entry] });
            }
        });

        return h('div', { className: 'kq-hist', ref: listRef, role: 'list', 'aria-label': t('qa_hist_label') }, days.map((group) => h('section', { key: group.day, className: 'kq-hist-day', role: 'listitem' },
            h('h4', { className: 'kq-hist-day-label' }, group.label),
            h('ol', { className: 'kq-hist-list' }, group.items.map((entry) => h('li', { key: entry.id, className: 'kq-hist-item kq-h-' + entry.kind },
                h('span', { className: 'kq-hist-dot', 'aria-hidden': 'true' }, h(Icon, { name: HISTORY_ICON[entry.kind] || 'edit' })),
                h('div', { className: 'kq-hist-body' },
                    h('div', { className: 'kq-hist-head' },
                        h('b', { className: cx('kq-hist-who', entry.actor.system && 'system') }, entry.actor.name),
                        h('span', { className: 'kq-hist-verb' }, entry.text),
                        h('time', { className: 'kq-hist-time', title: entry.at }, entry.time)),
                    entry.lines.length ? h('ul', { className: 'kq-hist-lines' }, entry.lines.map((line) => h('li', { key: line.field },
                        h('span', { className: 'kq-hist-label' }, line.label),
                        line.from !== null ? h(HistoryValue, { value: line.from, tone: line.from_tone, old: true }) : null,
                        line.from !== null ? h(Icon, { name: 'arrow', className: 'kq-hist-arrow' }) : null,
                        h(HistoryValue, { value: line.to, tone: line.to_tone })))) : null,
                    entry.files.length ? h('ul', { className: 'kq-hist-files' }, entry.files.map((name, index) => h('li', { key: index }, h(Icon, { name: 'document' }), name))) : null,
                    entry.quote ? h('p', { className: 'kq-hist-quote' }, entry.quote) : null)))))));
    }

    /** Cevaplar ve belgeler kapaliyken (D-244) yalniz Gecmis sekmesi. */
    function HistoryPanel(props) {
        const { card } = props;
        const { t } = useApp();
        const [count, setCount] = useState(null);

        return h('section', { className: 'kq-thread', 'aria-label': t('qa_hist_label') },
            h(KW.Tabs, { label: t('qa_hist_label'), value: 'history', onChange: () => {}, items: [{ value: 'history', label: t('qa_hist_tab'), badge: count || null }] }),
            h(WorkHistory, { card, active: true, onCount: setCount }));
    }

    /**
     * Isin cevaplari ve belgeleri (kullanici 23 Ekim 2026: "Ise karsi cevap
     * yazilabilir olmasi lazim. Belge yuklenebilir olmasi lazim. Bu ozelliklerde
     * gelsin yine ayni arayuzde temiz sekilde"). Isi goren cevap yazar ve belge
     * yukler; sorumlular zilde haber alir. Degisiklik "kq-thread" olayiyla
     * listeye bildirilir (sayilar). D-243: gorseller sekmelerin ustunde isin
     * icinde gorunur; belgeler pencerede onizlenir; yuklemeler belge belge
     * ilerleme cubuguyla. uploadRef: okuma penceresine birakilan belgeler.
     */
    function WorkThread(props) {
        // D-248: history acikken ucuncu sekme Gecmis (cevap / belge sonrasi yenilenir).
        const { card, uploadRef, history } = props;
        const app = useApp();
        const { t, config, api } = app;
        const [data, setData] = useState(null);
        const [error, setError] = useState(null);
        const [tab, setTab] = useState('replies');
        const [steps, setSteps] = useState(null);
        const [refresh, setRefresh] = useState(0);
        const [body, setBody] = useState('');
        const [busy, setBusy] = useState(false);
        const [drop, setDrop] = useState(false);
        const [queue, setQueue] = useState([]);
        const [preview, setPreview] = useState(null);
        const fileRef = useRef(null);
        const listRef = useRef(null);
        const seq = useRef(0);

        const load = useCallback(() => {
            setError(null);
            api.get(app.url(config.endpoints.thread, card.id)).then(setData).catch(setError);
        }, [api, config, card.id]);

        useEffect(() => { load(); }, [load]);

        const notes = data ? data.notes : [];
        const files = data ? data.files : [];
        // Gorseller galeride (isin icinde), digerleri belge listesinde; ayni belge iki kez gorunmez.
        const images = files.filter((file) => file.kind === 'image');
        const documents = files.filter((file) => file.kind !== 'image');

        useEffect(() => {
            if (listRef.current) { listRef.current.scrollTop = listRef.current.scrollHeight; }
        }, [notes.length, tab]);

        const changed = (json) => {
            setData(json);
            setRefresh((n) => n + 1);
            window.dispatchEvent(new CustomEvent('kq-thread', { detail: { id: card.id, notes: json.notes.length, files: json.files.length } }));
        };

        const send = () => {
            const text = body.trim();

            if (!text || busy) { return; }

            setBusy(true);
            api.post(app.url(config.endpoints.replies, card.id), { body: text })
                .then((json) => { changed(json); setBody(''); })
                .catch(app.fail)
                .finally(() => setBusy(false));
        };

        const patch = (key, values) => setQueue((list) => list.map((item) => (item.key === key ? { ...item, ...values } : item)));

        const upload = (list) => {
            const chosen = Array.prototype.slice.call(list || []).filter((file) => file && file.name);

            if (!chosen.length || !(data && data.can.upload)) { return; }

            const batch = chosen.map((file) => {
                seq.current += 1;

                return { key: 'u' + seq.current, file, name: file.name, size: file.size, loaded: 0, state: 'wait' };
            });

            setTab('files');
            // Biten eski yuklemeler yeni grubun yerini acar; suren / hatali olanlar kalir.
            setQueue((current) => current.filter((item) => item.state !== 'done').concat(batch));
            if (fileRef.current) { fileRef.current.value = ''; }

            (async () => {
                let added = 0;

                for (const item of batch) {
                    patch(item.key, { state: 'up' });

                    try {
                        const json = await uploadOne(app.url(config.endpoints.files, card.id), item.file, config.csrf, (loaded) => patch(item.key, { loaded }));
                        added += json.added || 0;
                        patch(item.key, { state: 'done', loaded: item.size });
                        changed(json);
                    } catch (failure) {
                        patch(item.key, { state: 'fail', error: app.describe(failure) });
                    }
                }

                if (added) { app.toast({ text: t('qa_th_uploaded', { n: added }), tone: 'ok' }); }

                // Tamamlananlar kisa sure sonra listeden kalkar (belge listesinde gorunur).
                window.setTimeout(() => setQueue((current) => current.filter((item) => !(item.state === 'done' && batch.some((one) => one.key === item.key)))), 2500);
            })();
        };

        if (uploadRef) { uploadRef.current = upload; }

        const remove = (file) => {
            app.confirm({ text: t('qa_th_remove_confirm', { name: file.name }), confirmLabel: t('qa_th_remove') }).then((yes) => {
                if (yes) { api.post(app.url(config.endpoints.file_remove, file.id), {}).then(changed).catch(app.fail); }
            });
        };

        const open = (file) => {
            if (file.kind === 'image' || file.preview) {
                setPreview(file);
            } else {
                window.location.assign(file.download);
            }
        };

        const uploading = queue.some((item) => item.state === 'up' || item.state === 'wait');
        let content;

        if (tab === 'history') {
            content = null;
        } else if (error) {
            content = h(KW.State, { error, onRetry: load });
        } else if (!data) {
            content = h(KW.State, { loading: true });
        } else if (tab === 'replies') {
            content = h('div', { className: 'kq-replies' },
                notes.length
                    ? h('ol', { className: 'kq-reply-list', ref: listRef }, notes.map((note) => h('li', { key: note.id, className: cx('kq-reply', note.mine && 'mine') },
                        h(KW.Avatar, { initials: note.author ? note.author.initials : '·' }),
                        h('div', { className: 'kq-reply-body' },
                            h('div', { className: 'kq-reply-head' },
                                h('b', null, note.author ? note.author.name : t('qa_th_imported')),
                                note.source ? h('span', { className: 'kq-reply-src' }, note.source) : null,
                                h('time', null, note.at)),
                            h('p', { className: 'kq-reply-text' }, note.body)))))
                    : h('p', { className: 'kq-th-empty' }, t('qa_th_no_replies')),
                data.can.reply ? h('div', { className: 'kq-reply-box' },
                    h('textarea', {
                        className: 'kw-input kq-reply-input',
                        rows: 2,
                        value: body,
                        placeholder: t('qa_th_reply_ph'),
                        'aria-label': t('qa_th_reply_label'),
                        onChange: (event) => setBody(event.target.value),
                        onKeyDown: (event) => { if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); send(); } },
                    }),
                    h(Btn, { className: 'kq-send-btn', disabled: busy || !body.trim(), onClick: send },
                        busy ? h('span', { className: 'kw-spinner', 'aria-hidden': 'true' }) : h(Icon, { name: 'send' }), t('qa_th_send'))) : null);
        } else {
            content = h('div', {
                className: cx('kq-files', drop && 'drop'),
                onDragOver: (event) => { if (data.can.upload) { event.preventDefault(); setDrop(true); } },
                onDragLeave: () => setDrop(false),
                onDrop: (event) => { event.preventDefault(); setDrop(false); if (data.can.upload) { upload(event.dataTransfer.files); } },
            },
            h(UploadList, { items: queue, onDismiss: (key) => setQueue((current) => current.filter((item) => item.key !== key)) }),
            documents.length
                ? h('ul', { className: 'kq-file-list' }, documents.map((file) => h('li', { key: file.id, className: 'kq-file' },
                    file.thumb
                        ? h('button', { type: 'button', className: 'kq-file-thumb', title: t('qa_th_preview'), onClick: () => open(file) }, h('img', { src: file.thumb, alt: '', loading: 'lazy' }))
                        : h('span', { className: 'kq-file-ic kq-k-' + file.kind }, h(Icon, { name: 'document' })),
                    h('button', { type: 'button', className: 'kq-file-name', title: file.preview || file.kind === 'image' ? t('qa_th_preview') : t('qa_th_download'), onClick: () => open(file) }, file.name),
                    h('span', { className: 'kq-file-meta' }, [sizeText(file.size), file.at].filter(Boolean).join(' · ')),
                    file.preview || file.kind === 'image' ? h('button', { type: 'button', className: 'kq-row-btn', title: t('qa_th_preview'), 'aria-label': t('qa_th_preview'), onClick: () => open(file) }, h(Icon, { name: 'eye' })) : h('span'),
                    h('a', { className: 'kq-row-btn', href: file.download, title: t('qa_th_download'), 'aria-label': t('qa_th_download') }, h(Icon, { name: 'download' })),
                    file.can_remove ? h('button', { type: 'button', className: 'kq-row-btn danger', title: t('qa_th_remove'), 'aria-label': t('qa_th_remove'), onClick: () => remove(file) }, h(Icon, { name: 'close' })) : h('span'))))
                : (queue.length || images.length ? null : h('p', { className: 'kq-th-empty' }, t('qa_th_no_files'))),
            data.can.upload ? h('label', { className: cx('kq-upload', uploading && 'busy') },
                h('input', { type: 'file', multiple: true, ref: fileRef, className: 'kw-sr', onChange: (event) => upload(event.target.files) }),
                h(Icon, { name: 'upload' }),
                h('span', null, t('qa_th_drop')),
                h('small', null, t('qa_th_max', { size: data.max_upload }))) : null);
        }

        return h('section', { className: 'kq-thread', 'aria-label': t('qa_th_label') },
            // Gorseller isin icinde (D-243): tiklayinca buyuk gorunur.
            images.length ? h('div', { className: 'kq-gallery', 'aria-label': t('qa_th_images') }, images.map((file) => h('div', { key: file.id, className: 'kq-gallery-cell' },
                h('button', { type: 'button', className: 'kq-gallery-item', title: file.name, onClick: () => setPreview(file) },
                    h('img', { src: file.thumb || file.url, alt: file.name, loading: 'lazy' })),
                h('div', { className: 'kq-gallery-tools' },
                    h('a', { className: 'kq-row-btn', href: file.download, title: t('qa_th_download'), 'aria-label': t('qa_th_download') }, h(Icon, { name: 'download' })),
                    file.can_remove ? h('button', { type: 'button', className: 'kq-row-btn', title: t('qa_th_remove'), 'aria-label': t('qa_th_remove'), onClick: () => remove(file) }, h(Icon, { name: 'close' })) : null)))) : null,
            h(KW.Tabs, {
                label: t('qa_th_label'),
                value: tab,
                onChange: setTab,
                items: [
                    { value: 'replies', label: t('qa_th_replies'), badge: notes.length || null },
                    { value: 'files', label: t('qa_th_files'), badge: files.length || null },
                    history ? { value: 'history', label: t('qa_hist_tab'), badge: steps || null } : null,
                ].filter(Boolean),
            }),
            content,
            history ? h(WorkHistory, { card, active: tab === 'history', refresh, onCount: setSteps }) : null,
            preview ? h(FilePreview, { file: preview, onClose: () => setPreview(null) }) : null);
    }

    /** Okuma penceresi: isin okunusu (rozetli / duz yazi), Kopyala, cevaplar, belgeler; Duzenle dugmesi yetkiliye. */
    function WorkView(props) {
        // D-246: bicim pencereyi acanin bicimidir (listede satir nasil gorunuyorsa); pencerede degistirmek listeyi etkilemez.
        const { card, onClose, onEdit, format, onFormat } = props;
        const { t, config } = useApp();
        const [dropping, setDropping] = useState(false);
        const uploadRef = useRef(null);
        const segments = sentenceSegments(card);
        const thread = !!(config.endpoints && config.endpoints.thread);
        const history = !!(config.endpoints && config.endpoints.history);
        const hasFiles = (event) => !!(event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'Files') !== -1);

        return h(Modal, {
            title: h('span', { className: 'kq-title' }, h(Icon, { name: 'check' }), t('qa_view_title')),
            sub: card.item_no || null,
            size: 'quick',
            onClose,
            footer: [
                h(Btn, { key: 'c', onClick: onClose }, t('close')),
                onEdit ? h(Btn, { key: 'e', className: 'kq-edit-btn', onClick: onEdit }, h(Icon, { name: 'edit' }), t('qa_edit')) : null,
            ].filter(Boolean),
        },
        h('div', {
            className: cx('kq kq-read', dropping && 'drop'),
            'data-drop-label': thread ? t('qa_drop_view') : undefined,
            // D-243: okuma penceresine birakilan belge de ise yuklenir.
            onDragOver: thread ? (event) => { if (hasFiles(event)) { event.preventDefault(); setDropping(true); } } : undefined,
            onDragLeave: thread ? (event) => { if (!event.currentTarget.contains(event.relatedTarget)) { setDropping(false); } } : undefined,
            onDrop: thread ? (event) => {
                if (!hasFiles(event)) { return; }
                event.preventDefault();
                setDropping(false);
                if (uploadRef.current) { uploadRef.current(event.dataTransfer.files); }
            } : undefined,
        },
            h('div', { className: 'kq-read-bar' },
                h(FormatSwitch, { value: format, onChange: onFormat }),
                h(CopyButton, { text: readingText(segments, t) })),
            h('div', { className: 'kq-read-text' }, h(WorkText, { segments, format })),
            thread ? h(WorkThread, { card, uploadRef, history }) : (history ? h(HistoryPanel, { card }) : null)));
    }

    /**
     * Pencere. D-241 (kullanici: "Satira direk tikladigimizda duzenlenmek icin
     * butona basilmali, direk duzenlemez. Ve her personel duzeltme yapamaz"):
     * kayitli is once okuma gorunumunde acilir; Duzenle (yetkiliye) duzenlemeye
     * gecer, Iptal ve Kaydet okumaya doner. mode 'edit' (menu / Duzenle dugmesi)
     * dogrudan duzenler, kapaninca pencere kapanir.
     * D-246 (kullanici: "Duz metinde modal acildiysa yine duz metin, rozetli ise
     * rozetli olarak acilacak modal. Ama duzenle butonuna basildigi anda ...
     * her halukarda rozetli duzenleme gelsin"): okuma bicimi `format` (acan
     * satirin bicimi; yoksa kisinin liste secimi); duzenleme her zaman rozetli,
     * duzenlemeden donunce okuma son bicimiyle acilir.
     */
    function QuickAddModal(props) {
        const { card: given, mode: wanted, format: opened, onClose, onSaved } = props;
        const [card, setCard] = useState(given || null);
        const canEdit = !!(card && card.can && card.can.update);
        const [mode, setMode] = useState(() => (!given ? 'create' : (wanted === 'edit' && canEdit ? 'edit' : 'view')));
        const [viewFormat, setViewFormat] = useState(() => (opened === 'badge' || opened === 'plain' ? opened : storedFormat()));
        const fromView = useRef(mode === 'view');

        if (mode === 'view') {
            return h(WorkView, { card, format: viewFormat, onFormat: setViewFormat, onClose, onEdit: canEdit ? () => { fromView.current = true; setMode('edit'); } : null });
        }

        const back = mode === 'edit' && fromView.current ? () => setMode('view') : onClose;

        return h(ComposerModal, {
            key: mode,
            mode,
            card,
            onClose: back,
            onDone: back,
            onSaved: (cards, editing) => {
                if (editing && cards[0]) { setCard(cards[0]); }
                if (onSaved) { onSaved(cards, editing); }
            },
        });
    }

    /** Yazma penceresi: olustur (birden fazla is) ya da duzenle (tek is). */
    function ComposerModal(props) {
        const { card, mode, onClose, onDone, onSaved } = props;
        const { t } = useApp();
        const c = useComposer({ mode, card, onSaved });

        const save = () => c.save().then((result) => { if (result === 'done') { onDone(); } });
        const title = mode === 'create' ? t('qa_title') : (mode === 'edit' ? t('qa_edit_title') : t('qa_view_title'));
        const sub = mode === 'create' ? t('qa_sub') : (card && card.item_no ? card.item_no : null);

        const footer = mode === 'view'
            ? [h(Btn, { key: 'c', onClick: onClose }, t('close'))]
            : [
                mode === 'create' ? h('span', { key: 'k', className: 'kq-keys' }, t('qa_keys')) : null,
                h(Btn, { key: 'c', onClick: onClose, disabled: c.saving }, t('cancel')),
                h(Btn, { key: 's', variant: 'primary', disabled: c.saving || !c.data || !c.count, onClick: save },
                    c.saving ? h('span', { className: 'kw-spinner', 'aria-hidden': 'true' }) : null,
                    mode === 'edit' ? t('save') : (c.count > 1 ? t('qa_save', { n: c.count }) : t('qa_save_one'))),
            ].filter(Boolean);

        return h(Modal, {
            title: h('span', { className: 'kq-title' }, h(Icon, { name: mode === 'create' ? 'bolt' : 'check' }), title),
            sub,
            size: 'quick',
            onClose: () => { if (!c.saving) { onClose(); } },
            footer,
        },
        c.loadError ? h(KW.State, { error: c.loadError, onRetry: c.load }) : null,
        !c.loadError && !c.data && mode !== 'view' ? h(KW.State, { loading: true }) : null,
        c.data || mode === 'view' ? h('div', { className: 'kq' },
            c.toolbar,
            mode === 'create' ? h('p', { className: 'kq-hint' }, t('qa_hint') + (c.canAttach ? ' ' + t('qa_hint_drop') : '')) : null,
            c.rowsEl,
            c.addButton,
            c.pickerEl,
        ) : null);
    }

    /** Is panosunda hep acik yazma alani (D-235: "Hizli is ekle alaninin yerini alsin"). */
    function InlineComposer(props) {
        const { onCreated } = props;
        const { t } = useApp();
        const c = useComposer({ mode: 'create', compact: true, onSaved: (cards) => { if (onCreated) { onCreated(cards); } } });

        if (c.loadError) { return h('div', { className: 'kq-inline' }, h(KW.State, { error: c.loadError, onRetry: c.load })); }

        return h('section', { className: 'kq kq-inline', 'aria-label': t('qa_title') },
            c.toolbar,
            c.rowsEl,
            h('div', { className: 'kq-inline-foot' },
                c.addButton,
                h(Btn, { variant: 'primary', disabled: c.saving || !c.data || !c.count, onClick: c.save },
                    c.saving ? h('span', { className: 'kw-spinner', 'aria-hidden': 'true' }) : h(Icon, { name: 'check' }),
                    c.count > 1 ? t('qa_save', { n: c.count }) : t('qa_save_one')),
            ),
            c.pickerEl,
        );
    }

    /** Isin rozetli okunusu (yalniz goruntu). */
    function SentenceView(props) {
        const { segments } = props;
        const { t } = useApp();
        const ref = useRef(null);

        useLayoutEffect(() => {
            if (ref.current) { fillEditor(ref.current, editorSegments(segments || []), t, true); }
        }, [segments]);

        return h('div', { className: 'kq-editor readonly kq-view', ref });
    }

    /* ------------------------------------------------------------------ */
    /* Ek bagli kayitlar (eski is penceresi)                                */
    /* ------------------------------------------------------------------ */

    const KIND_TYPE = Object.keys(LINK_KIND).reduce((map, type) => { map[LINK_KIND[type]] = type; return map; }, {});

    function ExtraLinks(props) {
        const { links, onRemove } = props;
        const { t } = useApp();

        return h('div', { className: 'kq-links' }, links.map((link) => h('span', { key: link.kind + link.id, className: 'kq-mini kq-t-' + (KIND_TYPE[link.kind] || 'proposal') },
            h(Icon, { name: KIND_TYPE[link.kind] || 'proposal' }),
            (link.no ? link.no + ' · ' : '') + link.label,
            onRemove ? h('button', { type: 'button', className: 'kq-mini-x', 'aria-label': t('qa_badge_remove'), onClick: () => onRemove(link) }, '×') : null,
        )));
    }

    /* ------------------------------------------------------------------ */
    /* Ust cubuk penceresi (her sayfa)                                      */
    /* ------------------------------------------------------------------ */

    function GlobalQuick() {
        const app = useApp();
        const { config, api } = app;
        const [state, setState] = useState(null);

        useEffect(() => {
            const open = () => {
                if (config.can && config.can.create === false) { return; }
                setState({ card: null });
            };
            // D-241: { id, mode }: 'view' (satir tiklamasi, varsayilan) ya da 'edit' (Duzenle dugmesi).
            const edit = (event) => {
                const detail = (event && event.detail) || {};
                const mode = detail.mode === 'edit' ? 'edit' : 'view';
                // D-246: listede satirin bicimi (duz yazi / rozetli) okuma penceresine gecer.
                const format = detail.format === 'badge' || detail.format === 'plain' ? detail.format : null;

                if (detail.card) {
                    setState({ card: detail.card, mode, format });

                    return;
                }

                if (!detail.id) { return; }

                api.get(app.url(config.endpoints.show, detail.id)).then((json) => setState({ card: json.card, mode, format })).catch(app.fail);
            };

            window.addEventListener('kq-open', open);
            window.addEventListener('kq-edit', edit);

            return () => {
                window.removeEventListener('kq-open', open);
                window.removeEventListener('kq-edit', edit);
            };
        }, [api, config]);

        return state ? h(QuickAddModal, { key: state.card ? 'e' + state.card.id + state.mode + (state.format || '') : 'new', card: state.card, mode: state.mode, format: state.format, onClose: () => setState(null) }) : null;
    }

    window.KonelsisWorkQuick = {
        QuickAddModal, InlineComposer, QuickEditor, SentenceView, ExtraLinks, Icon, PlainBox, PlainView, WorkText, ReadingView, FormatSwitch, FormatToggle, CopyButton, useFormat,
        plainText, readingText, readingSegments, copyText, editorSegments, paragraphsOf, isEmptyModel, useComposer, useQuickContext, ToolMenu, PickerPop, typeLabel, badgeText, makeBadge,
        ENTITY_TYPES, FIELD_TYPES,
    };

    KW.mount('work-quick', GlobalQuick);
}());
