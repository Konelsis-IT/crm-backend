/*
 * Konelsis Sosyal Medya modulu - React CEKIRDEGI (B31, D-106, 18 Eylul 2026).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement), dis kutuphane
 * yok. Bu dosya ILK yuklenir ve window.KonelsisSocial (kisa adi KS) ad alanini kurar;
 * diger butun social-*.js dosyalari yalniz buradaki API'yi kullanir. Stiller:
 * resources/css/filament/konelsis-social.css, bolum CORE (sinif on eki ks-, degiskenler --ks-*).
 * Baglanti: app.blade.php icindeki #konelsis-social-root data-config
 * { endpoints, csrf, locale, labels, logo, params? }.
 *
 * Her gorunum dosyasi soyle baslar / every view file starts like this:
 *     (function () {
 *         'use strict';
 *         if (!window.KonelsisSocial) return;
 *         const KS = window.KonelsisSocial;
 *         const { h, t, api, url, fmt, cx, Icon, Button } = KS;
 *         const { useState, useEffect } = KS;
 *         KS.views.Feed = function Feed() { ... };
 *     }());
 *
 * =====================================================================
 * API BASVURUSU / API REFERENCE
 * =====================================================================
 *
 * --- Temel / Basics ----------------------------------------------------
 * KS.config            data-config nesnesi (endpoints, csrf, locale, labels, logo)
 * KS.root              #konelsis-social-root elemani (yoksa null)
 * KS.lang / KS.locale  'tr' | 'en'  /  'tr-TR' | 'en-GB'
 * KS.h, KS.Fragment    React.createElement, React.Fragment
 * KS.hooks             { useState, useEffect, useRef, useCallback, useMemo, useReducer, useLayoutEffect }
 *                      (ayni kancalar dogrudan KS.useState ... olarak da vardir)
 * KS.t(key, params)    arayuz metni; ":ad" yer tutuculari params ile dolar. Kaynak lang `ui`
 *                      dizisi; cekirdek anahtarlari icin gomulu yedek sozluk vardir.
 * KS.hasLabel(key)     anahtar lang dosyasindan geldi mi
 *
 * --- HTTP ---------------------------------------------------------------
 * KS.url(name, params) uc sablonu cozer. name = rota adinin 'filament.admin.social.' sonrasi
 *                      ('contents.show', 'media.file', 'uploads.chunk' ...; 'contents_show' ve
 *                      'contentsShow' yazimlari da bulunur). params: sayi/metin = id kisayolu ya da
 *                      { id, token, query: { ... } }; __ID__ ve __TOKEN__ degistirilir. Bilinmeyen uc -> null.
 *                      Ornek: KS.url('contents.show', 12) | KS.url('uploads.chunk', { token }) |
 *                             KS.url('media.file', { id: 5, query: { variant: 'thumbnail' } })
 * KS.hasEndpoint(name) uc tanimli mi (null gelen yetenekler icin dugmeyi gizleyin)
 * KS.api.get(url, params?, { signal }?)   -> Promise<json>   (params sorgu dizgisine; diziler ad[]=)
 * KS.api.post(url, body?, { signal }?)    -> Promise<json>   (JSON govde)
 * KS.api.form(url, formData, { signal }?) -> Promise<json>   (FormData; ilerleme gerekmeyen yuklemeler)
 * KS.api.upload(url, formData, onProgress?, { signal, timeout }?) -> Promise<json> + .abort()
 *                      XMLHttpRequest; onProgress(oran 0..1, { loaded, total }). Donen Promise'in
 *                      abort() islevi vardir (then() zinciri yeni Promise uretir; ilk nesneyi saklayin).
 *                      Her istek X-CSRF-TOKEN, X-Requested-With, Accept: application/json gonderir.
 * Hata nesnesi (hepsinde ayni): Error { status, code (F8: 'stale_record' ...), message, serverMessage,
 *                      errors (Laravel alan hatalari), payload, network?, aborted?, missingEndpoint? }.
 * KS.handleError(e, { silent?, onReload?, title? }) -> gosterilen metin | null. Duruma gore esler (H2):
 *                      401/419 session_expired (+Sayfayi yenile) | 403 forbidden | 404 not_found |
 *                      409 payload.message || stale_reload (+Yeniden yukle -> onReload) | 413 file_too_large |
 *                      422 payload.message || invalid_input | ag hatasi network_error | diger error_generic.
 *                      e.aborted sessizce gecilir. silent: bildirim gostermeden metni dondurur.
 * KS.describeError(e)  ayni eslemenin yalniz metni (ErrorState icin).
 *
 * --- Bicimlendirme / fmt --------------------------------------------------
 * KS.fmt.date(v) dd.mm.yyyy | dateTime(v) dd.mm.yyyy HH:MM | time(v) HH:MM | dateShort(v) "20 Eyl" |
 * dateLong(v) "20 Eylul 2026 Pazar" | planned(tarih, saat) "20.09.2026 | 10:30" | relative(v) "5 dk once" |
 * bytes(n) "1,5 KB" | number(n, ondalik?) | compact(n) "12,3 B" | percent(oran 0..1, ondalik?) |
 * monthLabel('2026-09' | Date) "Eylul 2026" | weekdays('short'|'long') Pazartesi ile baslar |
 * duration(sn) "01:15" / "1:02:05" | parse(v) -> Date|null ('Y-m-d' YEREL gun olarak cozulur) |
 * toYmd(v) 'Y-m-d' | todayYmd() (kurum saat dilimi: config.timezone) | personName(person|null) (null -> t('system')) |
 * names(people, max = 3) -> { shown: [adlar], rest: sayi }  ("A, B ve 2 kisi begendi" icin)
 *
 * --- Yardimcilar / Helpers ------------------------------------------------
 * KS.cx(...siniflar)            metin | dizi | { sinif: kosul }
 * KS.safeUrl(u)                 yalniz http(s):// ya da tek "/" ile baslayan yol; degilse null (H1).
 *                               API'den gelen HER href/src bundan gecmelidir.
 * KS.isExternalUrl(u)           panel disi adres mi (target=_blank rel="noopener noreferrer" icin)
 * KS.copyText(metin)            -> Promise<boolean> (clipboard API, olmazsa gizli textarea)
 * KS.htmlToPlainText(html)      body_html -> duz metin (bloklar satir sonu, li "- " (madde imi), baglanti "metin (adres)")
 * KS.platformClass(deger)       'ks-platform--instagram' ... (bilinmeyen -> website); renk satir ici VERILMEZ (H5)
 * KS.paletteColor(ad)           8 palet adindan biri (red amber emerald sky violet stone rose teal); bilinmeyen -> stone
 * KS.statusColor(durum) | KS.stageColor(asama: today red, tomorrow amber, approaching sky, missed rose)
 * KS.PALETTE, KS.PLATFORMS, KS.ICON_NAMES   sabit listeler
 * KS.omit(nesne, anahtarlar) | KS.clamp(v, min, max) | KS.uid(onEk) | KS.debounce(fn, ms) | KS.shallowEqual(a, b)
 * KS.options(grup) -> boot.options[grup] | KS.optionLabel(grup, deger) | KS.limits() -> boot.limits
 *
 * --- Kancalar / Hooks -----------------------------------------------------
 * KS.useStore(secici, esitlik?)     depo dilimi; secici her seferinde yeni nesne uretiyorsa esitlik = KS.shallowEqual
 * KS.useStoreOf(depo, secici, esitlik?)  KS.createStore ile kurulan baska depolar icin
 * KS.useBoot() | useMe() | useAbilities() | useLimits() | useProfile() (etkin hesap) | useCounts() (etkin hesabin F6 sayaclari)
 * KS.useInterval(fn, ms, etkin?)    yoklama; fn icinde `if (document.hidden) return;` korumasi sizin isiniz
 * KS.useDebounced(deger, ms = 300)  gecikmeli deger (arama kutusu)
 * KS.useResource(() => Promise, [bagimliliklar]) -> { data, error, loading, reload(), setData(next|fn) }
 * KS.useMediaQuery(sorgu) | KS.useIsMobile() (< 768px) | KS.useClickOutside(ref, fn, etkin?)
 * KS.useOverlay(ref, { onClose(reason), dismissible, modal = true, lockScroll, restoreFocus, initialFocus, active })
 *                                   kendi tam ekran kaplamaniz icin: odak tuzagi, Escape, kaydirma kilidi, odak iadesi
 *
 * --- Durum / State ---------------------------------------------------------
 * KS.store = { getState(), setState(yama | fn(state) -> yama), subscribe(fn) -> iptal }. Anahtarlar:
 *   boot, bootStatus ('idle'|'loading'|'ready'|'error'), bootError, profileId, view, filters, feedVersion,
 *   counts ({ <profile_id>: F6 }), storage, detailId, composer ({ open, content|null, type|null, defaults|null }),
 *   contents ({ <id>: detail } onbellek), lastContent, contentVersion.
 * KS.DEFAULT_FILTERS = { status: 'all', type, platform, category, creator, q, from, to, scope: 'all', published, stage } (bos = '')
 * KS.actions:
 *   loadBoot(force?) -> Promise<boot>      bootstrap; profil onceligi: adres `hesap` > localStorage ks_profile > ilk profil
 *   resolveDeepLink() -> Promise<boolean>  H4: `icerik` varsa once ayrinti cekilir, hesap ona gore secilir, ayrinti replaceState ile acilir
 *   setProfile(id, { silent }?) | setView('akis'|'plan'|'ilham'|'analiz'|'ayarlar') | setFilters(yama) | resetFilters(koru?)
 *   openDetail(id, { replace }?) | closeDetail()
 *   openComposer(arg) | closeComposer()    arg: ayrinti nesnesi (duzenleme) | 'photo' gibi tur | { type?, defaults?: { planned_on?,
 *                                          planned_time?, profile_id?, category_id?, platforms? } }; aciksa yalniz durum guncellenir
 *   refreshFeed() (feedVersion artar) | refreshCounts(profileId?) | refreshStorage() | fetchContent(id) -> Promise<detail>
 *   applyContent(detail, { silent }?)      HER { content } yanitindan sonra cagirin: onbellek + lastContent/contentVersion +
 *                                          acik olusturucunun icerigi (row_version devri) + 'content' olayi + sayac tazeleme
 * KS.events.on(ad, fn) -> iptal | KS.events.emit(ad, veri). Cekirdek olayi: 'content' (applyContent ile gelen ayrinti).
 * KS.router = { params, read() -> { view, contentId, profileCode }, href(yama), write(yama) (yalniz replaceState), sync(),
 *               setGuard('composer'|'detail', () => boolean|Promise<boolean>) }  Turkce parametreler: gorunum, icerik, hesap.
 *   openDetail/openComposer pushState kullanir; Geri tusu en ustteki kaplamayi kapatir (H3). Kaydedilmemis degisiklik icin
 *   setGuard ile Geri tusuna onay koyun; arayuzdeki Kapat dugmesinin onayi bilesenin kendi isidir.
 * KS.views = {}  -> KS.views.Feed | Detail | Composer | Planner | Insights | Analytics | Settings = Bilesen
 * KS.VIEWS = ['akis','plan','ilham','analiz','ayarlar'] | KS.VIEW_COMPONENTS (akis -> 'Feed' ...) | KS.viewComponent(view) -> Bilesen|null
 *
 * --- Geri bildirim / Feedback -------------------------------------------------
 * KS.toast.success(metin, secenek?) | .error | .info | .warning | .show({ type, text, ... }) | .dismiss(id) | .clear()
 *                    secenek: { title, duration (ms; 0 = kalici), action: { label, onClick } }
 * KS.confirm({ title, text, confirmLabel, cancelLabel, danger, icon, confirmIcon, hideCancel, body }) -> Promise<boolean>
 * KS.askNote({ title, text, label, placeholder, hint, required, requiredMessage, confirmLabel, cancelLabel, danger,
 *              initial, maxLength }) -> Promise<string|null>   (null = vazgecildi)   [KS.Confirm / KS.prompt ayni islevlerdir]
 * Bildirim ve pencere kokunu cekirdek kendisi baglar; KS.ToastHost baglamak istege baglidir (bir sey cizmez).
 *
 * --- Bilesenler / Components (hepsi h(KS.X, props, cocuklar) ile) ------------------
 * Icon({ name, size (px | CSS), className, strokeWidth, title })
 *   Adlar: plus minus close back check check-circle x-mark search filter sort calendar clock more menu chevron-left
 *   chevron-right chevron-down chevron-up arrow-right arrow-up arrow-down image images layers video play pause text document
 *   blog book crop sparkles hdd format-original format-square format-portrait format-story format-landscape heart heart-solid
 *   thumb-up thumb-down thumb-down-solid comment reply pin square hand star star-solid share megaphone bell zoom-in zoom-out
 *   expand collapse download upload link link-off external copy eye eye-off edit save trash-none drag archive refresh restore
 *   history alert warning info bolt send lock flag tag hash grid list chart trend settings user users building institution undo
 *   redo table bold italic underline strike list-ul list-ol quote code heading paragraph clear-format instagram facebook
 *   linkedin x youtube tiktok globe website.  (trash-none = "galeriden cikar"; silme yoktur.)
 * PlatformIcon({ platform, variant: 'plain'|'badge'|'solid', size: 'sm'|'lg'|px, label })
 * PlatformLink({ platform, label, url, handle, compact })          marka renginde dis baglanti (yeni sekme)
 * ExternalLink({ href, children, className, title })               safeUrl + yeni sekme; gecersiz adres duz metin olur
 * Button({ variant: 'primary'|'ghost'|'soft'|'danger'|'success'|'link' (varsayilan ghost), size: 'sm'|'md'|'lg', icon, iconRight,
 *          loading, disabled, block, active, pressed, onClick, title, type, href, target, download, ariaLabel, buttonRef, ...button })
 * IconButton({ icon, label (ZORUNLU: aria-label), variant: 'ghost'|'plain'|'soft'|'solid'|'danger'|'inverse' (koyu sahne ustu),
 *              size, active, pressed, count, loading, disabled, onClick, href, title, buttonRef })
 * Badge({ color (palet), icon, dot, solid, size: 'sm'|'md', title }) | StatusBadge({ status, label, color?, size })
 * StageBadge({ stage, label?, size }) | Chip({ label|children, color, active, onClick, onRemove, icon, platform, disabled, title, size })
 * Avatar({ person|null, size: 'xs'|'sm'|'md'|'lg'|'xl', title }) | AvatarStack({ people, max = 4, size, total }) |
 * PersonLine({ person, meta?, size, trailing })
 * Modal({ title, subtitle, icon, onClose(reason), size: 'sm'|'md'|'lg'|'xl'|'full', footer, children, dismissible = true,
 *         closeOnBackdrop = true, hideClose, initialFocus (ref|secici), padded = true, label, headerActions, className, bodyClassName })
 * Drawer({ ...Modal ile ayni, side: 'right'|'left'|'bottom', size: 'sm'|'md'|'lg'|'xl'|'full' })
 * Overlay({ title, subtitle, leading, actions, onClose, footer, children, label, closeLabel, closeIcon, dismissible, bar = true,
 *           className, bodyClassName })  tam ekran; govde icin ks-overlay__body--split + .ks-overlay__stage / .ks-overlay__side,
 *           ya da ks-overlay__body--padded (+ --narrow)
 *   Uc kaplama da: role=dialog, aria-modal, odak tuzagi, Escape (yalniz en ustteki), govde kaydirma kilidi, odagi acana iade;
 *   body sonundaki #ks-portal-root icine cizilir; < 768px tam ekran sayfa olur ('sm' Modal alttan acilan sayfa).
 *   Acilista odak: [data-autofocus] tasiyan eleman > initialFocus > pencere kabi.
 * Popover({ trigger: (triggerProps, { open, close }) => eleman, children | (close) => eleman, align: 'start'|'end',
 *           placement: 'bottom'|'top', width, label, block, className, panelClassName, onOpenChange })
 * Menu({ trigger: { label, icon, variant, size, iconOnly, iconRight, disabled } | islev, items, align = 'end', placement, label, onSelect, width })
 *   items: [{ key, label, hint, icon | platform, onSelect(item), danger, disabled, active, title } | { divider: true } | { heading: 'Baslik' }]
 * Tabs({ items: [{ value, label, icon, count, countTone, disabled, title }], value, onChange(value), variant: 'underline'|'pill', label, stretch })
 * Segmented({ items: [{ value, label, icon, title, disabled }], value, onChange(value), size, label, block, iconOnly })
 * Field({ label, hint, error, required, htmlFor, group, counter, children })   tek cocuga id/aria-* aktarir; group: cip kumeleri
 * TextInput({ value, onChange(value, event), type, placeholder, icon, clearable, invalid, size, disabled, onEnter(value), inputRef, trailing, ...input })
 * TextArea({ value, onChange(value, event), rows, autoGrow, maxRows, maxLength, softLimit, counter, invalid, inputRef, ...textarea })
 * Select({ options: [{ value, label, disabled }] | [{ label, options }], value, onChange(value, event), placeholder (false = yok),
 *          clearable, icon, invalid, size, disabled })   onChange secenegin OZGUN degerini (sayi/metin) verir; bos secim ''
 * DateInput({ value: 'Y-m-d'|'', onChange(value), min, max, clearable = true, placeholder, invalid, disabled, size })   gorunen bicim dd.mm.yyyy
 * TimeInput({ value: 'HH:MM'|'', onChange(value), step = 300, clearable, invalid, disabled, size })
 * Checkbox({ checked, onChange(checked, event), label, hint, disabled, indeterminate }) | Switch({ checked, onChange(checked), label, hint, disabled })
 * Dropzone({ accept, multiple, onFiles(File[]), disabled, icon, title, text (false = yok), hint, compact, paste, children })
 * Empty({ icon, title, text, action, compact }) | ErrorState({ error | text, onRetry, compact }) |
 * Notice({ tone: 'info'|'warning'|'danger'|'success'|'neutral', icon, title, children, action, compact })
 * Card({ as, interactive, selected, padded = true, onClick, ... }) | Section({ title, description, icon, actions, flush, children }) |
 * Stat({ label, value, hint, icon, tone (palet), onClick }) | Divider({ label? }) | LoadMore({ onClick, loading, hasMore, label })
 * CopyButton({ text | getText(), label, variant, size, icon, successText }) | Spinner({ size, label, center }) |
 * Skeleton({ variant: 'text'|'rect'|'circle'|'card', width, height, lines, radius }) |
 * ProgressBar({ value, max = 100, label, tone: 'accent'|'success'|'warning'|'danger'|'neutral', size, indeterminate, showValue }) |
 * Tooltip({ text, placement: 'top'|'bottom', children }) | Portal({ children })
 *
 * --- CSS siniflari (core.css) / layout helpers ------------------------------------
 * Kok: .ks-root (kabuk kokune verin) | .ks-app | .ks-topbar (+ --sticky, __brand, __logo, __title, __subtitle, __center, __actions)
 * .ks-content (+ --with-rail) | .ks-main | .ks-rail (>= 1280px yapiskan 300px) | .ks-grid (1-5 sutun; --sm --lg --tight --2 --3 --4;
 * --ks-grid-min / --ks-grid-max-cols / --ks-grid-gap ile ayarlanir) | .ks-toolbar (+ --boxed, __group, __spacer) |
 * .ks-form | .ks-form-row (+ --2 --3) | .ks-form-actions | .ks-card | .ks-panel | .ks-sheet | .ks-overlay__* | .ks-pills / .ks-pill
 * (+ is-active, __text, __name, __meta) | .ks-meta (__label, __value) | .ks-boot | .ks-stack (+ --sm --lg) | .ks-row (+ --wrap
 * --between --top) | .ks-spacer | .ks-scroll | .ks-text-12/13/14/16/20/28 | .ks-title | .ks-subtitle | .ks-eyebrow | .ks-muted |
 * .ks-faint | .ks-strong | .ks-nowrap | .ks-truncate | .ks-clamp-2/3/4 | .ks-link | .ks-sr-only | .ks-hidden |
 * .ks-only-mobile / .ks-only-desktop | .ks-c-<palet> (--ks-c-bg/-fg/-solid/-border) | .ks-platform.ks-platform--<deger> (--ks-platform,
 * --ks-platform-on). Koyu kip yalniz .dark altindaki degiskenlerle gelir; gorunum CSS'i renk icin --ks-* degiskenlerini kullanir.
 * Katmanlar: --ks-z-sticky 10, --ks-z-pop 20, --ks-z-overlay 32, --ks-z-modal 33, --ks-z-dialog 34, --ks-z-toast 35 (35 asilmaz).
 */
(function () {
    'use strict';

    if (!window.React || !window.ReactDOM) { console.error('KonelsisSocial: React yok'); return; }

    // Betik iki kez yuklenirse (ornegin yinelenen script etiketi) ikinci kopya calismaz.
    if (window.KonelsisSocial && window.KonelsisSocial.__core) {
        return;
    }

    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const h = React.createElement;
    const Fragment = React.Fragment;
    const { useState, useEffect, useRef, useCallback, useMemo, useReducer, useLayoutEffect } = React;

    /* ================================================================== */
    /* 1. Yapilandirma (config)                                            */
    /* ================================================================== */

    // Sosyal medya sayfasi #konelsis-social-root; cekirdegi kullanan diger ekranlar
    // (gorusme plani takvimi, B34) kok ogeyi [data-ks-root] ile isaretler.
    const rootEl = document.getElementById('konelsis-social-root') || document.querySelector('[data-ks-root]');
    let config = {};

    if (rootEl) {
        try {
            config = JSON.parse(rootEl.dataset.config || '{}') || {};
        } catch (error) {
            console.error('KonelsisSocial: data-config okunamadi', error);
            config = {};
        }
    }

    // __('social_content.ui') cozulmezse dizi yerine metin doner; o durumda bos sozluk kullanilir.
    const labels = (config.labels && typeof config.labels === 'object' && !Array.isArray(config.labels)) ? config.labels : {};
    const endpoints = (config.endpoints && typeof config.endpoints === 'object') ? config.endpoints : {};
    const lang = String(config.locale || 'tr').toLowerCase().indexOf('en') === 0 ? 'en' : 'tr';
    const locale = lang === 'en' ? 'en-GB' : 'tr-TR';

    /*
     * Cekirdek bilesenlerin kendi etiketleri icin emniyet sozlugu. Asil kaynak
     * lang/{tr,en}/social_content.php `ui` dizisidir (config.labels); buradaki
     * metinler yalniz anahtar orada eksikse devreye girer ki ekranda ham anahtar
     * (snake_case) hic gorunmesin.
     */
    const FALLBACK_LABELS = {
        tr: {
            system: 'Sistem',
            close: 'Kapat',
            cancel: 'Vazgeç',
            confirm: 'Evet',
            confirm_title: 'Emin misiniz?',
            send: 'Gönder',
            clear: 'Temizle',
            retry: 'Tekrar dene',
            reload: 'Yeniden yükle',
            reload_page: 'Sayfayı yenile',
            loading: 'Yükleniyor…',
            load_more: 'Daha fazla',
            required: 'Zorunlu',
            select_placeholder: 'Seçiniz',
            pick_date: 'Tarih seç',
            more_people: ':n kişi daha',
            character_count: ':count / :max karakter',
            note: 'Not',
            note_placeholder: 'Notunuzu yazın',
            note_required: 'Bu işlem için not yazmanız gerekir.',
            copy: 'Kopyala',
            copied: 'Kopyalandı.',
            copy_failed: 'Kopyalanamadı. Metni elle seçip kopyalayın.',
            opens_new_tab: 'Yeni sekmede açılır',
            dropzone_title: 'Dosyaları buraya bırakın',
            dropzone_text: 'veya bilgisayarınızdan seçmek için tıklayın',
            load_failed: 'Veriler yüklenemedi.',
            error_generic: 'İşlem tamamlanamadı. Lütfen tekrar deneyin.',
            network_error: 'Sunucuya ulaşılamadı. Bağlantınızı kontrol edip tekrar deneyin.',
            session_expired: 'Oturum süreniz doldu. Sayfayı yenileyip tekrar giriş yapın.',
            forbidden: 'Bu işlem için yetkiniz yok.',
            not_found: 'Kayıt bulunamadı veya artık erişilebilir değil.',
            stale_reload: 'Bu içerik siz düzenlerken başka biri tarafından değiştirildi. Güncel hâlini yükleyin.',
            file_too_large: 'Dosya sunucunun kabul ettiği boyuttan büyük.',
            invalid_input: 'Girilen bilgiler kabul edilmedi. Alanları kontrol edin.',
            content_not_accessible: 'İçerik bulunamadı veya görüntüleme yetkiniz yok.',
            today: 'Bugün',
            tomorrow: 'Yarın',
            yesterday: 'Dün',
            rel_now: 'az önce',
            rel_minutes: ':n dk önce',
            rel_hours: ':n sa önce',
            rel_days: ':n gün önce',
            rel_in_minutes: ':n dk sonra',
            rel_in_hours: ':n sa sonra',
            rel_in_days: ':n gün sonra',
            stage_today: 'Bugün',
            stage_tomorrow: 'Yarın',
            stage_approaching: 'Yaklaşıyor',
            stage_missed: 'Gecikti',
        },
        en: {
            system: 'System',
            close: 'Close',
            cancel: 'Cancel',
            confirm: 'Yes',
            confirm_title: 'Are you sure?',
            send: 'Send',
            clear: 'Clear',
            retry: 'Try again',
            reload: 'Reload',
            reload_page: 'Refresh the page',
            loading: 'Loading…',
            load_more: 'Load more',
            required: 'Required',
            select_placeholder: 'Select',
            pick_date: 'Pick a date',
            more_people: ':n more',
            character_count: ':count / :max characters',
            note: 'Note',
            note_placeholder: 'Write your note',
            note_required: 'A note is required for this action.',
            copy: 'Copy',
            copied: 'Copied.',
            copy_failed: 'Could not copy. Select the text and copy it manually.',
            opens_new_tab: 'Opens in a new tab',
            dropzone_title: 'Drop files here',
            dropzone_text: 'or click to choose from your computer',
            load_failed: 'The data could not be loaded.',
            error_generic: 'The action could not be completed. Please try again.',
            network_error: 'The server could not be reached. Check your connection and try again.',
            session_expired: 'Your session has expired. Refresh the page and sign in again.',
            forbidden: 'You are not allowed to do this.',
            not_found: 'The record was not found or is no longer accessible.',
            stale_reload: 'This content was changed by someone else while you were editing. Load the current version.',
            file_too_large: 'The file is larger than the server accepts.',
            invalid_input: 'The information was not accepted. Check the fields.',
            content_not_accessible: 'The content was not found or you are not allowed to view it.',
            today: 'Today',
            tomorrow: 'Tomorrow',
            yesterday: 'Yesterday',
            rel_now: 'just now',
            rel_minutes: ':n min ago',
            rel_hours: ':n h ago',
            rel_days: ':n days ago',
            rel_in_minutes: 'in :n min',
            rel_in_hours: 'in :n h',
            rel_in_days: 'in :n days',
            stage_today: 'Today',
            stage_tomorrow: 'Tomorrow',
            stage_approaching: 'Approaching',
            stage_missed: 'Overdue',
        },
    };

    function hasLabel(key) {
        return typeof labels[key] === 'string' && labels[key] !== '';
    }

    /** Arayuz metni: t('anahtar', { ad: 'deger' }) -> ":ad" yer tutuculari degistirilir. */
    function t(key, params) {
        let text = hasLabel(key) ? labels[key] : (FALLBACK_LABELS[lang][key] || key);

        if (params) {
            // Uzun adlar once: ":name" varken ":n" onu bozmasin.
            Object.keys(params).sort((a, b) => b.length - a.length).forEach((name) => {
                text = text.split(':' + name).join(String(params[name]));
            });
        }

        return text;
    }

    /* ================================================================== */
    /* 2. Kucuk yardimcilar                                                */
    /* ================================================================== */

    /** Sinif adlarini birlestirir: cx('a', kosul && 'b', { c: true }, ['d']). */
    function cx() {
        const out = [];

        for (let index = 0; index < arguments.length; index += 1) {
            const item = arguments[index];

            if (!item) {
                continue;
            }

            if (typeof item === 'string' || typeof item === 'number') {
                out.push(String(item));
            } else if (Array.isArray(item)) {
                const nested = cx.apply(null, item);

                if (nested) {
                    out.push(nested);
                }
            } else if (typeof item === 'object') {
                Object.keys(item).forEach((name) => {
                    if (item[name]) {
                        out.push(name);
                    }
                });
            }
        }

        return out.join(' ');
    }

    /** props icinden verilen anahtarlari cikarir (nesne "rest" sozdizimi yerine). */
    function omit(source, keys) {
        const out = {};

        Object.keys(source || {}).forEach((name) => {
            if (keys.indexOf(name) === -1) {
                out[name] = source[name];
            }
        });

        return out;
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    let uidSeq = 0;

    function uid(prefix) {
        uidSeq += 1;

        return (prefix || 'ks') + '-' + uidSeq.toString(36) + '-' + Math.random().toString(36).slice(2, 7);
    }

    function shallowEqual(a, b) {
        if (Object.is(a, b)) {
            return true;
        }

        if (!a || !b || typeof a !== 'object' || typeof b !== 'object') {
            return false;
        }

        const keysA = Object.keys(a);
        const keysB = Object.keys(b);

        if (keysA.length !== keysB.length) {
            return false;
        }

        return keysA.every((name) => Object.prototype.hasOwnProperty.call(b, name) && Object.is(a[name], b[name]));
    }

    function debounce(fn, delay) {
        let timer = null;

        const wrapped = function () {
            const args = arguments;

            window.clearTimeout(timer);
            timer = window.setTimeout(() => fn.apply(null, args), delay);
        };

        wrapped.cancel = () => window.clearTimeout(timer);

        return wrapped;
    }

    /**
     * API verisinden gelen adresi dogrular (H1): yalniz http(s) ya da tek "/" ile
     * baslayan kok-goreli yol kabul edilir; digerleri (javascript:, data:, "//host") null.
     */
    function hasUnsafeUrlChars(text) {
        // Denetim karakteri (0-31, 127), bosluk ve ters bolu adreste kabul edilmez.
        for (let index = 0; index < text.length; index += 1) {
            const code = text.charCodeAt(index);

            if (code <= 32 || code === 127 || code === 92) {
                return true;
            }
        }

        return /\s/.test(text);
    }

    function safeUrl(value) {
        if (typeof value !== 'string') {
            return null;
        }

        const text = value.trim();

        if (text === '' || hasUnsafeUrlChars(text)) {
            return null;
        }

        if (/^https?:\/\//i.test(text)) {
            return text;
        }

        if (text.charAt(0) === '/' && text.charAt(1) !== '/') {
            return text;
        }

        return null;
    }

    /** Adres bu panelin disina mi gidiyor? (yeni sekme + rel icin) */
    function isExternalUrl(value) {
        const url = safeUrl(value);

        if (!url || url.charAt(0) === '/') {
            return false;
        }

        try {
            return new URL(url).origin !== window.location.origin;
        } catch (error) {
            return true;
        }
    }

    const PALETTE = ['red', 'amber', 'emerald', 'sky', 'violet', 'stone', 'rose', 'teal'];
    // Filament renk adlari gelirse (widget tarafinin dili) ks paletine cevrilir.
    const PALETTE_ALIASES = { warning: 'amber', success: 'emerald', danger: 'red', info: 'sky', gray: 'stone', grey: 'stone', primary: 'red' };
    const STATUS_COLORS = { pending: 'amber', approved: 'emerald', rejected: 'red', revision_requested: 'violet', archived: 'stone' };
    const STAGE_COLORS = { today: 'red', tomorrow: 'amber', approaching: 'sky', missed: 'rose' };
    const PLATFORMS = ['instagram', 'facebook', 'linkedin', 'x', 'youtube', 'tiktok', 'website'];

    /** 8 palet adindan biri; bilinmeyen -> 'stone' (H5). */
    function paletteColor(name) {
        const value = String(name || '').toLowerCase();

        if (PALETTE.indexOf(value) !== -1) {
            return value;
        }

        return PALETTE_ALIASES[value] || 'stone';
    }

    function statusColor(status) {
        return STATUS_COLORS[status] || 'stone';
    }

    function stageColor(stage) {
        return STAGE_COLORS[stage] || 'stone';
    }

    /** Platform rengi sinifi (H5): ks-platform--instagram ... bilinmeyen -> website. */
    function platformClass(value) {
        const name = String(value || '').toLowerCase();

        return 'ks-platform--' + (PLATFORMS.indexOf(name) !== -1 ? name : 'website');
    }

    /** Panoya kopyalar; guvenli baglam yoksa gizli textarea + execCommand ile dener. Promise<boolean>. */
    function copyText(text) {
        const value = String(text === undefined || text === null ? '' : text);

        const legacy = () => {
            const area = document.createElement('textarea');
            const active = document.activeElement;
            let ok = false;

            area.value = value;
            area.setAttribute('readonly', '');
            area.setAttribute('aria-hidden', 'true');
            area.style.position = 'fixed';
            area.style.top = '0';
            area.style.left = '-9999px';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            area.setSelectionRange(0, value.length);

            try {
                ok = document.execCommand('copy');
            } catch (error) {
                ok = false;
            }

            document.body.removeChild(area);

            if (active && typeof active.focus === 'function') {
                try {
                    active.focus({ preventScroll: true });
                } catch (error) {
                    // Odak geri verilemezse sorun degil.
                }
            }

            return ok;
        };

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
            return navigator.clipboard.writeText(value).then(() => true).catch(() => legacy());
        }

        return Promise.resolve(legacy());
    }

    /**
     * Sunucunun temizledigi body_html'i duz metne cevirir (H7 "Metni kopyala"):
     * bloklar -> satir sonu, li -> "- " (madde imi) / "1. ", baglanti -> "metin (adres)",
     * tablo hucreleri sekme ile ayrilir. HTML yalniz DOMParser'in etkisiz belgesinde
     * ayristirilir; canli DOM'a hic yazilmaz.
     */
    function htmlToPlainText(html) {
        if (!html) {
            return '';
        }

        let doc = null;

        try {
            doc = new DOMParser().parseFromString(String(html), 'text/html');
        } catch (error) {
            doc = null;
        }

        if (!doc || !doc.body) {
            return '';
        }

        const BLOCKS = ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'FIGURE', 'FIGCAPTION', 'UL', 'OL', 'TABLE', 'SECTION', 'ARTICLE'];
        const out = [];

        const walk = (node, context) => {
            if (node.nodeType === 3) {
                out.push(context.pre ? node.nodeValue : node.nodeValue.replace(/\s+/g, ' '));

                return;
            }

            if (node.nodeType !== 1) {
                return;
            }

            const tag = node.nodeName.toUpperCase();

            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEMPLATE') {
                return;
            }

            if (tag === 'BR') {
                out.push('\n');

                return;
            }

            if (tag === 'HR') {
                out.push('\n\n———\n\n');

                return;
            }

            if (tag === 'IMG') {
                const alt = (node.getAttribute('alt') || '').trim();

                if (alt) {
                    out.push('[' + alt + ']');
                }

                return;
            }

            const childContext = { pre: context.pre || tag === 'PRE', list: context.list, index: context.index };

            if (tag === 'UL' || tag === 'OL') {
                childContext.list = tag;
                childContext.index = { value: 0 };
            }

            if (tag === 'LI') {
                let bullet = '• ';

                if (context.list === 'OL' && context.index) {
                    context.index.value += 1;
                    bullet = context.index.value + '. ';
                }

                out.push('\n' + bullet);
                Array.prototype.forEach.call(node.childNodes, (child) => walk(child, childContext));

                return;
            }

            if (tag === 'TR') {
                out.push('\n');
                Array.prototype.forEach.call(node.children, (cell, cellIndex) => {
                    if (cellIndex > 0) {
                        out.push('\t');
                    }

                    walk(cell, childContext);
                });

                return;
            }

            if (tag === 'A') {
                const before = out.length;
                const href = safeUrl(node.getAttribute('href') || '') || ((node.getAttribute('href') || '').indexOf('mailto:') === 0 ? node.getAttribute('href').slice(7) : null);

                Array.prototype.forEach.call(node.childNodes, (child) => walk(child, childContext));

                const text = out.slice(before).join('').trim();

                if (href && href !== text) {
                    out.push(text ? ' (' + href + ')' : href);
                }

                return;
            }

            const isBlock = BLOCKS.indexOf(tag) !== -1;

            if (isBlock) {
                out.push('\n\n');
            }

            Array.prototype.forEach.call(node.childNodes, (child) => walk(child, childContext));

            if (isBlock) {
                out.push('\n\n');
            }
        };

        walk(doc.body, { pre: false, list: null, index: null });

        return out.join('')
            .split(String.fromCharCode(160)).join(' ')
            .replace(/[ \t]+\n/g, '\n')
            .replace(/\n[ \t]+(?=\S)/g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .replace(/[ ]{2,}/g, ' ')
            .trim();
    }

    /* ================================================================== */
    /* 3. HTTP                                                             */
    /* ================================================================== */

    /**
     * Uc adi -> adres. params: sayi/metin (id kisayolu) ya da { id, token, query }.
     * Sablonlardaki __ID__ ve __TOKEN__ yer tutuculari degistirilir; query nesnesi
     * sorgu dizgisine eklenir. Bilinmeyen uc -> null (konsola uyari).
     */
    function findEndpoint(name) {
        const key = String(name || '');
        const candidates = [
            key,
            key.replace(/\./g, '_'),
            key.replace(/[._-]+(\w)/g, (match, letter) => letter.toUpperCase()),
            key.replace(/_/g, '.'),
        ];

        for (let index = 0; index < candidates.length; index += 1) {
            if (typeof endpoints[candidates[index]] === 'string' && endpoints[candidates[index]] !== '') {
                return endpoints[candidates[index]];
            }
        }

        return null;
    }

    function hasEndpoint(name) {
        return findEndpoint(name) !== null;
    }

    function buildQuery(params) {
        const search = new URLSearchParams();

        Object.keys(params || {}).forEach((name) => {
            const value = params[name];

            if (value === undefined || value === null || value === '') {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => {
                    if (item !== undefined && item !== null && item !== '') {
                        search.append(name + '[]', String(item));
                    }
                });

                return;
            }

            if (typeof value === 'boolean') {
                search.append(name, value ? '1' : '0');

                return;
            }

            search.append(name, String(value));
        });

        return search.toString();
    }

    function appendQuery(address, params) {
        const query = typeof params === 'string' ? params.replace(/^\?/, '') : buildQuery(params);

        if (!query) {
            return address;
        }

        return address + (address.indexOf('?') === -1 ? '?' : '&') + query;
    }

    function url(name, params) {
        const template = findEndpoint(name);

        if (template === null) {
            console.warn('KonelsisSocial: bilinmeyen uc "' + name + '"');

            return null;
        }

        let options = params;

        if (options === undefined || options === null) {
            options = {};
        } else if (typeof options !== 'object') {
            options = { id: options };
        }

        let address = template;

        if (options.id !== undefined && options.id !== null) {
            address = address.split('__ID__').join(encodeURIComponent(String(options.id)));
        }

        if (options.token !== undefined && options.token !== null) {
            address = address.split('__TOKEN__').join(encodeURIComponent(String(options.token)));
        }

        if (address.indexOf('__ID__') !== -1 || address.indexOf('__TOKEN__') !== -1) {
            console.warn('KonelsisSocial: "' + name + '" ucu icin id/token verilmedi');
        }

        return options.query ? appendQuery(address, options.query) : address;
    }

    /** Laravel dogrulama hatalarindan (errors) ilk ucunu tek metne indirger. */
    function flattenErrors(errors) {
        if (!errors || typeof errors !== 'object') {
            return '';
        }

        const seen = [];

        Object.keys(errors).forEach((field) => {
            const list = Array.isArray(errors[field]) ? errors[field] : [errors[field]];

            if (list.length && seen.indexOf(String(list[0])) === -1) {
                seen.push(String(list[0]));
            }
        });

        return seen.slice(0, 3).join(' ');
    }

    function makeError(status, payload, extra) {
        const data = payload && typeof payload === 'object' ? payload : null;
        // Dogrulama yanitinda `message` "(and 2 more errors)" ekini Ingilizce tasir; alan mesajlari tercih edilir.
        const message = (data && (flattenErrors(data.errors) || data.message)) || '';
        const failure = new Error(message || t('error_generic'));

        failure.status = status || 0;
        failure.payload = data;
        failure.code = data && typeof data.code === 'string' ? data.code : null;
        failure.errors = data && data.errors && typeof data.errors === 'object' ? data.errors : null;
        failure.serverMessage = message || null;

        return Object.assign(failure, extra || {});
    }

    function baseHeaders() {
        return {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': config.csrf || '',
        };
    }

    async function request(method, address, body, isForm, options) {
        if (!address) {
            throw makeError(0, null, { missingEndpoint: true });
        }

        const headers = baseHeaders();
        const init = { method, headers, credentials: 'same-origin' };

        if (options && options.signal) {
            init.signal = options.signal;
        }

        if (body !== undefined && body !== null) {
            if (isForm) {
                init.body = body;
            } else {
                headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(body);
            }
        }

        let response = null;

        try {
            response = await fetch(address, init);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw makeError(0, null, { aborted: true });
            }

            throw makeError(0, null, { network: true });
        }

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (!response.ok) {
            throw makeError(response.status, payload);
        }

        return payload;
    }

    /**
     * XMLHttpRequest ile yukleme: ilerleme + iptal. Donen Promise'in uzerinde
     * abort() vardir: const job = api.upload(...); job.abort(); await job;
     * (then() ile zincirlenen yeni Promise'te abort yoktur; ilk donen nesneyi saklayin.)
     */
    function upload(address, formData, onProgress, options) {
        const xhr = new XMLHttpRequest();

        const promise = new Promise((resolve, reject) => {
            if (!address) {
                reject(makeError(0, null, { missingEndpoint: true }));

                return;
            }

            const parse = () => {
                try {
                    return JSON.parse(xhr.responseText || 'null');
                } catch (error) {
                    return null;
                }
            };

            xhr.open((options && options.method) || 'POST', address, true);

            const headers = baseHeaders();

            Object.keys(headers).forEach((name) => xhr.setRequestHeader(name, headers[name]));

            if (options && options.timeout) {
                xhr.timeout = options.timeout;
            }

            xhr.upload.onprogress = (event) => {
                if (typeof onProgress === 'function') {
                    const ratio = event.lengthComputable && event.total > 0 ? clamp(event.loaded / event.total, 0, 1) : 0;

                    onProgress(ratio, { loaded: event.loaded, total: event.lengthComputable ? event.total : null });
                }
            };

            xhr.onload = () => {
                const payload = parse();

                if (xhr.status >= 200 && xhr.status < 300) {
                    if (typeof onProgress === 'function') {
                        onProgress(1, { loaded: null, total: null });
                    }

                    resolve(payload);
                } else {
                    reject(makeError(xhr.status, payload));
                }
            };

            xhr.onerror = () => reject(makeError(0, null, { network: true }));
            xhr.ontimeout = () => reject(makeError(0, null, { network: true, timeout: true }));
            xhr.onabort = () => reject(makeError(0, null, { aborted: true }));

            if (options && options.signal) {
                if (options.signal.aborted) {
                    xhr.abort();

                    return;
                }

                options.signal.addEventListener('abort', () => xhr.abort(), { once: true });
            }

            xhr.send(formData);
        });

        promise.abort = () => {
            try {
                xhr.abort();
            } catch (error) {
                // Zaten bitmis istek.
            }
        };

        return promise;
    }

    const api = {
        request,
        get: (address, params, options) => request('GET', address ? appendQuery(address, params || {}) : address, undefined, false, options),
        post: (address, body, options) => request('POST', address, body === undefined ? {} : body, false, options),
        form: (address, formData, options) => request('POST', address, formData, true, options),
        upload,
    };

    /* ================================================================== */
    /* 4. Bicimlendirme (fmt)                                              */
    /* ================================================================== */

    function pad2(value) {
        return (value < 10 ? '0' : '') + value;
    }

    /** 'Y-m-d' metnini YEREL takvim gunu olarak, digerlerini ISO olarak cozer; gecersiz -> null. */
    function parseDate(value) {
        if (value === undefined || value === null || value === '') {
            return null;
        }

        if (value instanceof Date) {
            return Number.isNaN(value.getTime()) ? null : value;
        }

        if (typeof value === 'number') {
            return new Date(value);
        }

        const text = String(value);
        const plain = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);

        if (plain) {
            return new Date(Number(plain[1]), Number(plain[2]) - 1, Number(plain[3]));
        }

        const month = /^(\d{4})-(\d{2})$/.exec(text);

        if (month) {
            return new Date(Number(month[1]), Number(month[2]) - 1, 1);
        }

        const date = new Date(text);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    function toYmd(value) {
        const date = parseDate(value);

        return date ? date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate()) : '';
    }

    function dayStart(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    const fmt = {
        parse: parseDate,
        toYmd,
        /** Bugun 'Y-m-d': kurum saat dilimi (config.timezone, sunucudaki SocialClock ile ayni) biliniyorsa ona gore. */
        todayYmd() {
            if (typeof config.timezone === 'string' && config.timezone !== '') {
                try {
                    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: config.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
                    const pick = (type) => (parts.find((part) => part.type === type) || {}).value;

                    if (pick('year') && pick('month') && pick('day')) {
                        return pick('year') + '-' + pick('month') + '-' + pick('day');
                    }
                } catch (error) {
                    // Taninmayan saat dilimi: tarayicinin yerel gunune dusulur.
                }
            }

            return toYmd(new Date());
        },

        /** dd.mm.yyyy (proje standardi d.m.Y). */
        date(value) {
            const date = parseDate(value);

            return date ? pad2(date.getDate()) + '.' + pad2(date.getMonth() + 1) + '.' + date.getFullYear() : '';
        },

        /** dd.mm.yyyy HH:MM */
        dateTime(value) {
            const date = parseDate(value);

            return date ? fmt.date(date) + ' ' + pad2(date.getHours()) + ':' + pad2(date.getMinutes()) : '';
        },

        /** HH:MM; 'HH:MM' ya da 'HH:MM:SS' metni oldugu gibi kisaltilir. */
        time(value) {
            if (typeof value === 'string' && /^\d{2}:\d{2}(:\d{2})?$/.test(value)) {
                return value.slice(0, 5);
            }

            const date = parseDate(value);

            return date ? pad2(date.getHours()) + ':' + pad2(date.getMinutes()) : '';
        },

        /** "20 Eyl" */
        dateShort(value) {
            const date = parseDate(value);

            return date ? date.toLocaleDateString(locale, { day: 'numeric', month: 'short' }) : '';
        },

        /** "20 Eylul 2026 Pazar" */
        dateLong(value) {
            const date = parseDate(value);

            return date ? date.toLocaleDateString(locale, { day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' }) : '';
        },

        /** Plan tarihi + saat: "20.09.2026 - 10:30" */
        planned(dateValue, timeValue) {
            const date = fmt.date(dateValue);
            const time = fmt.time(timeValue);

            return date && time ? date + ' · ' + time : date;
        },

        /** Goreli zaman (t() ile tr/en): az once, 5 dk once, 3 sa once, Dun, 4 gun once, sonra tarih. */
        relative(value) {
            const date = parseDate(value);

            if (!date) {
                return '';
            }

            const diff = Date.now() - date.getTime();
            const future = diff < 0;
            const minutes = Math.round(Math.abs(diff) / 60000);

            if (minutes < 1) {
                return t('rel_now');
            }

            if (minutes < 60) {
                return t(future ? 'rel_in_minutes' : 'rel_minutes', { n: minutes });
            }

            const hours = Math.round(minutes / 60);

            if (hours < 24) {
                return t(future ? 'rel_in_hours' : 'rel_hours', { n: hours });
            }

            const days = Math.round(Math.abs(dayStart(new Date()).getTime() - dayStart(date).getTime()) / 86400000);

            if (days <= 1) {
                return t(future ? 'tomorrow' : 'yesterday');
            }

            if (days < 7) {
                return t(future ? 'rel_in_days' : 'rel_days', { n: days });
            }

            return fmt.date(date);
        },

        /** 1536 -> "1,5 KB" */
        bytes(value) {
            const size = Number(value);

            if (!Number.isFinite(size) || size <= 0) {
                return '0 B';
            }

            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const power = Math.min(units.length - 1, Math.floor(Math.log(size) / Math.log(1024)));
            const scaled = size / Math.pow(1024, power);
            const digits = power === 0 || scaled >= 100 ? 0 : (scaled >= 10 ? 1 : 2);

            return scaled.toLocaleString(locale, { maximumFractionDigits: digits }) + ' ' + units[power];
        },

        /** 12345 -> "12.345" */
        number(value, digits) {
            const number = Number(value);

            if (value === null || value === undefined || value === '' || !Number.isFinite(number)) {
                return '—';
            }

            return number.toLocaleString(locale, { maximumFractionDigits: digits === undefined ? 0 : digits });
        },

        /** 12345 -> "12,3 B" (kisa gosterim) */
        compact(value) {
            const number = Number(value);

            if (value === null || value === undefined || value === '' || !Number.isFinite(number)) {
                return '—';
            }

            try {
                return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(number);
            } catch (error) {
                return fmt.number(number);
            }
        },

        /** 0.1234 -> "%12,3" / "12.3%" */
        percent(ratio, digits) {
            const number = Number(ratio);

            if (ratio === null || ratio === undefined || !Number.isFinite(number)) {
                return '—';
            }

            return number.toLocaleString(locale, { style: 'percent', maximumFractionDigits: digits === undefined ? 1 : digits });
        },

        /** '2026-09' ya da Date -> "Eylul 2026" */
        monthLabel(value) {
            const date = parseDate(value);

            if (!date) {
                return '';
            }

            const text = date.toLocaleDateString(locale, { month: 'long', year: 'numeric' });

            return text.charAt(0).toLocaleUpperCase(locale) + text.slice(1);
        },

        /** Pazartesi ile baslayan gun adlari: ['Pzt','Sal',...] ('long' da verilebilir). */
        weekdays(style) {
            const monday = new Date(2024, 0, 1);
            const names = [];

            for (let index = 0; index < 7; index += 1) {
                names.push(new Date(2024, 0, monday.getDate() + index).toLocaleDateString(locale, { weekday: style || 'short' }));
            }

            return names;
        },

        /** 75 -> "01:15", 3725 -> "1:02:05" */
        duration(seconds) {
            const total = Math.max(0, Math.round(Number(seconds) || 0));
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const rest = total % 60;

            return hours > 0 ? hours + ':' + pad2(minutes) + ':' + pad2(rest) : pad2(minutes) + ':' + pad2(rest);
        },

        /** Kisi adi; null kisi -> t('system'). */
        personName(person) {
            return person && person.name ? person.name : t('system');
        },

        /** "A, B ve 2 kisi" tarzi listeler icin adlar: en cok `max` ad + kalan sayi. */
        names(people, max) {
            const list = (people || []).map((person) => fmt.personName(person));
            const limit = max || 3;

            return { shown: list.slice(0, limit), rest: Math.max(0, list.length - limit) };
        },
    };

    /* ================================================================== */
    /* 5. Simgeler                                                         */
    /* ================================================================== */

    /** Disli cark yolu hesapla uretilir (elle yazilan uzun yol yerine kesin geometri). */
    function gearPath() {
        const teeth = 8;
        const step = (Math.PI * 2) / teeth;
        const point = (radius, angle) => (12 + radius * Math.cos(angle)).toFixed(2) + ' ' + (12 + radius * Math.sin(angle)).toFixed(2);
        let d = '';

        for (let index = 0; index < teeth; index += 1) {
            const angle = index * step - Math.PI / 2;

            d += (index === 0 ? 'M' : 'L') + point(7.1, angle - step * 0.3)
                + 'L' + point(9.7, angle - step * 0.17)
                + 'L' + point(9.7, angle + step * 0.17)
                + 'L' + point(7.1, angle + step * 0.3);
        }

        return d + 'zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z';
    }

    /*
     * 24x24, tek yol (path), cizgi simgeleri. Deger metin ise cizgi; nesne ise
     * { d, solid: true } dolgulu, { d, w: 2.6 } kalin cizgili (nokta simgeleri).
     */
    const ICONS = {
        // Genel
        'plus': 'M12 5v14M5 12h14',
        'minus': 'M5 12h14',
        'close': 'M6 6l12 12M18 6L6 18',
        'back': 'M19 12H5M11 6l-6 6 6 6',
        'check': 'M5 13l4 4L19 7',
        'check-circle': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM8 12.5l2.7 2.7L16 9.5',
        'x-mark': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM9 9l6 6M15 9l-6 6',
        'search': 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4-4',
        'filter': 'M3 5h18l-7 8.5V19l-4 2v-7.5z',
        'sort': 'M7 4v16M3.5 16.5L7 20l3.5-3.5M17 20V4M13.5 7.5L17 4l3.5 3.5',
        'calendar': 'M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM3 10h18M8 3v4M16 3v4',
        'clock': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2',
        'more': { d: 'M5 12h.01M12 12h.01M19 12h.01', w: 2.8 },
        'menu': 'M4 6h16M4 12h16M4 18h16',
        'chevron-left': 'M15 18l-6-6 6-6',
        'chevron-right': 'M9 6l6 6-6 6',
        'chevron-down': 'M6 9l6 6 6-6',
        'chevron-up': 'M6 15l6-6 6 6',
        'arrow-right': 'M5 12h14M13 6l6 6-6 6',
        'arrow-up': 'M12 19V5M6 11l6-6 6 6',
        'arrow-down': 'M12 5v14M6 13l6 6 6-6',

        // Icerik turleri ve medya
        'image': 'M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM3 16l5-5 4 4 3-3 6 6M15.5 8.5h.01',
        'images': 'M8 7h11a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2zM6 17l4.5-4.5 3.5 3.5 2.5-2.5L21 18M3 15V6a2 2 0 0 1 2-2h11',
        'layers': 'M12 2.5l9 4.5-9 4.5-9-4.5zM3 12l9 4.5 9-4.5M3 16.5l9 4.5 9-4.5',
        'video': 'M4 6h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zM16 10.5l6-3.5v10l-6-3.5',
        'play': { d: 'M7 4.5v15l12-7.5z', solid: true },
        'pause': 'M8 5v14M16 5v14',
        'text': 'M5 6V5h14v1M12 5v14M9 19h6',
        'document': 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5M9 13h6M9 17h6',
        'blog': 'M4 5h13v14.5a1.5 1.5 0 0 0 3 0V9h-3M8 9h5v4H8zM8 17h5M4 5v14a2 2 0 0 0 2 2h12.5',
        'book': 'M4 19.5V5a2 2 0 0 1 2-2h13v14H6.5A2.5 2.5 0 0 0 4 19.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H19v-5M9 7.5h6',
        'crop': 'M6 2v14a2 2 0 0 0 2 2h14M2 6h14a2 2 0 0 1 2 2v14',
        'sparkles': 'M10 3l1.8 4.7 4.7 1.8-4.7 1.8L10 16l-1.8-4.7-4.7-1.8 4.7-1.8zM18 14l.9 2.1L21 17l-2.1.9L18 20l-.9-2.1L15 17l2.1-.9z',
        'hdd': 'M3 14h18M5.5 5h13L21 14v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4zM7 16.5h.01M10.5 16.5h.01',
        'format-original': 'M4 6h16v12H4zM4 15l4-4 3 3 3-3 6 6',
        'format-square': 'M5 5h14v14H5z',
        'format-portrait': 'M6 4.5h12v15H6z',
        'format-story': 'M7.5 3h9v18h-9z',
        'format-landscape': 'M3 7h18v10H3z',

        // Etkilesim
        'heart': 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z',
        'heart-solid': { d: 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z', solid: true },
        'thumb-up': 'M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.3a2 2 0 0 0 2-1.7l1.4-9a2 2 0 0 0-2-2.3zM7 22H4.3A2.3 2.3 0 0 1 2 20v-7a2.3 2.3 0 0 1 2.3-2H7',
        'thumb-down': 'M10 15v4a3 3 0 0 0 3 3l4-9V2H5.7a2 2 0 0 0-2 1.7l-1.4 9a2 2 0 0 0 2 2.3zM17 2h2.7A2.3 2.3 0 0 1 22 4v7a2.3 2.3 0 0 1-2.3 2H17',
        'thumb-down-solid': { d: 'M10 15v4a3 3 0 0 0 3 3l4-9V2H5.7a2 2 0 0 0-2 1.7l-1.4 9a2 2 0 0 0 2 2.3zM17 2h2.7A2.3 2.3 0 0 1 22 4v7a2.3 2.3 0 0 1-2.3 2H17', solid: true },
        'comment': 'M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.2A8 8 0 1 1 21 12z',
        'reply': 'M9 14L4 9l5-5M4 9h10a6 6 0 0 1 6 6v5',
        'pin': 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        'square': 'M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
        'hand': 'M8 13V5.5a1.5 1.5 0 0 1 3 0V11M11 10.5v-6a1.5 1.5 0 0 1 3 0V11M14 6.5a1.5 1.5 0 0 1 3 0V12M17 9a1.5 1.5 0 0 1 3 0v6a7 7 0 0 1-7 7h-1.5a7 7 0 0 1-5.6-2.8L3.5 15a1.6 1.6 0 0 1 2.6-1.9L8 15.5',
        'star': 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z',
        'star-solid': { d: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z', solid: true },
        'share': 'M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM8.6 13.5l6.8 4M15.4 6.5l-6.8 4',
        'megaphone': 'M3 10v4a1 1 0 0 0 1 1h3l8 4V5L7 9H4a1 1 0 0 0-1 1zM18.5 9a4 4 0 0 1 0 6M8 15v4a1 1 0 0 0 1 1h1.5a1 1 0 0 0 1-1v-2.4',
        'bell': 'M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9zM10 20a2 2 0 0 0 4 0',
        // Gorusme plani kanallari (B34): telefon ve e-posta.
        'phone': 'M5 4h3l1.5 4-2 1.2a11 11 0 0 0 5.3 5.3L14 12.5l4 1.5v3a2 2 0 0 1-2 2A13 13 0 0 1 3 6a2 2 0 0 1 2-2z',
        'mail': 'M4 6h16v12H4zM4 7l8 6 8-6',

        // Gorsel araclari
        'zoom-in': 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4-4M11 8v6M8 11h6',
        'zoom-out': 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4-4M8 11h6',
        'expand': 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
        'collapse': 'M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5',
        'download': 'M12 3v12M6 11l6 6 6-6M4 21h16',
        'upload': 'M12 16V3M6 9l6-6 6 6M4 21h16',
        'link': 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7',
        'link-off': 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1-1M3 3l18 18',
        'external': 'M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5',
        'copy': 'M9 9h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2zM5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1',
        'eye': 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
        'eye-off': 'M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 6.6C3.6 8.5 2 12 2 12s3.6 7 10 7a9.8 9.8 0 0 0 4.3-1M9.9 9.9a3 3 0 0 0 4.2 4.2',
        'edit': 'M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19zM14.5 6.5l3 3',
        'save': 'M5 3h11l4 4v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM8 3v5h7V3M8 21v-7h8v7',
        // Silme yok (kullanici kurali 10): "galeriden cikar" icin cop kutusu degil, eksi-daire.
        'trash-none': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM8 12h8',
        'drag': { d: 'M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01', w: 2.8 },

        // Durum ve akis
        'archive': 'M3 4h18v5H3zM5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4',
        'refresh': 'M20 11a8 8 0 0 0-14.3-4.4L4 8.5M4 4v4.5h4.5M4 13a8 8 0 0 0 14.3 4.4l1.7-1.9M20 20v-4.5h-4.5',
        'restore': 'M4 5v5h5M4.5 10A8 8 0 1 1 4 12.5M12 8v4.5l3 1.5',
        'alert': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 8v5M12 16.5h.01',
        'warning': 'M12 3.5L2.5 20h19zM12 10v4.5M12 17.5h.01',
        'info': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11v5.5M12 7.5h.01',
        'bolt': 'M13 2L4 14h7l-1 8 9-12h-7z',
        'send': 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',
        'lock': 'M6 11V8a6 6 0 0 1 12 0v3M5 11h14v10H5z',
        'flag': 'M5 21V4M5 4h11l-1.5 4L16 12H5',
        'tag': 'M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9zM7.5 7.5h.01',
        'hash': 'M5 9h15M4 15h15M10 3L8 21M16 3l-2 18',

        // Gorunumler
        'grid': 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'list': 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01',
        'chart': 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3',
        'trend': 'M3 17l6-6 4 4 8-8M15 7h6v6',
        'settings': gearPath(),
        'user': 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1',
        'users': 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'building': 'M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 10h4a1 1 0 0 1 1 1v10M2 21h20M8 8h3M8 12h3M8 16h3',
        'institution': 'M3 21h18M5 21V10M9 21V10M15 21V10M19 21V10M2.5 10h19L12 3.5z',

        // Metin duzenleyici
        'undo': 'M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11',
        'redo': 'M15 14l5-5-5-5M20 9H9.5a5.5 5.5 0 0 0 0 11H13',
        'table': 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM3 10h18M3 14.5h18M9 5v14M15 5v14',
        'bold': 'M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z',
        'italic': 'M19 4h-9M14 20H5M15 4L9 20',
        'underline': 'M7 4v7a5 5 0 0 0 10 0V4M5 20h14',
        'strike': 'M4 12h16M16 7.5C15.6 5.9 14.1 5 12 5 9.6 5 8 6.2 8 8c0 1 .5 1.8 1.5 2.4M8 16.5c.4 1.6 1.9 2.5 4 2.5 2.4 0 4-1.2 4-3 0-.7-.2-1.3-.7-1.8',
        'list-ul': 'M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01',
        'list-ol': 'M10 6h11M10 12h11M10 18h11M4 4.5L5.5 4v5M4 9h3M4 14.5a1.5 1.5 0 1 1 2.6 1L4 18.5h3',
        'quote': 'M10 7H6a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h3v1a3 3 0 0 1-3 3M19 7h-4a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h3v1a3 3 0 0 1-3 3',
        'code': 'M16 18l6-6-6-6M8 6l-6 6 6 6',
        'heading': 'M6 4v16M18 4v16M6 12h12',
        'paragraph': 'M13 4v16M17 4v16M19 4H9.5a4.5 4.5 0 0 0 0 9H13',
        'clear-format': 'M4 7V5h12M10 5L8 19M6 19h5M15 14l5 5M20 14l-5 5',

        // Platformlar (cizgi uslubunda marka isaretleri)
        'instagram': 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zM16 11.4a4 4 0 1 1-7.9 1.2 4 4 0 0 1 7.9-1.2zM17.5 6.5h.01',
        'facebook': 'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z',
        'linkedin': 'M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2zM4 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4z',
        'x': 'M4 3.5h4.2l11.8 17h-4.2zM19.5 3.5l-6.2 7.1M10.7 13.4l-6.2 7.1',
        'youtube': 'M22.5 6.4a2.8 2.8 0 0 0-1.9-2C18.9 4 12 4 12 4s-6.9 0-8.6.5a2.8 2.8 0 0 0-1.9 2A29 29 0 0 0 1 11.8a29 29 0 0 0 .5 5.3 2.8 2.8 0 0 0 1.9 1.9c1.7.5 8.6.5 8.6.5s6.9 0 8.6-.5a2.8 2.8 0 0 0 1.9-1.9 29 29 0 0 0 .5-5.3 29 29 0 0 0-.5-5.4zM9.75 15l5.75-3.2-5.75-3.3z',
        'tiktok': 'M14 3v12.5a4 4 0 1 1-4-4M14 3c.4 3 2.4 5 5.5 5.3',
        'globe': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z',
    };

    // Ayni cizimin ikinci adi.
    ICONS.website = ICONS.globe;
    ICONS.history = ICONS.restore;

    const warnedIcons = {};

    /** Icon({ name, size, className, strokeWidth, title }) - size: sayi (px) ya da CSS uzunlugu. */
    function Icon(props) {
        const spec = ICONS[props.name];

        if (!spec && !warnedIcons[props.name]) {
            warnedIcons[props.name] = true;
            console.warn('KonelsisSocial: bilinmeyen simge "' + props.name + '"');
        }

        const d = !spec ? 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z' : (typeof spec === 'string' ? spec : spec.d);
        const solid = !!(spec && spec.solid);
        const width = props.strokeWidth || (spec && spec.w) || 1.8;
        const size = props.size === undefined || props.size === null ? null : (typeof props.size === 'number' ? props.size + 'px' : props.size);
        const attrs = {
            className: cx('ks-icon', props.className),
            viewBox: '0 0 24 24',
            fill: solid ? 'currentColor' : 'none',
            stroke: 'currentColor',
            strokeWidth: solid ? 1.4 : width,
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            focusable: 'false',
            style: size ? { width: size, height: size } : undefined,
        };

        if (props.title) {
            attrs.role = 'img';
            attrs['aria-label'] = props.title;
        } else {
            attrs['aria-hidden'] = 'true';
        }

        return h('svg', attrs, h('path', { d }));
    }

    function platformIconName(platform) {
        const name = String(platform || '').toLowerCase();

        return PLATFORMS.indexOf(name) !== -1 && name !== 'website' ? name : 'globe';
    }

    /**
     * PlatformIcon({ platform, size, variant, label, className })
     * variant: 'plain' (marka renginde simge) | 'badge' (yuvarlak, yumusak zemin) | 'solid' (marka zemini, beyaz simge).
     * Renk satir ici verilmez; ks-platform--<deger> sinifi --ks-platform-* degiskenini baglar (H5).
     */
    function PlatformIcon(props) {
        const variant = props.variant || 'plain';

        return h('span', {
            className: cx('ks-platform', platformClass(props.platform), 'ks-platform-icon', 'ks-platform-icon--' + variant, props.size && typeof props.size === 'string' && 'ks-platform-icon--' + props.size, props.className),
            title: props.label || undefined,
            role: props.label ? 'img' : undefined,
            'aria-label': props.label || undefined,
            'aria-hidden': props.label ? undefined : 'true',
        }, h(Icon, { name: platformIconName(props.platform), size: typeof props.size === 'number' ? props.size : undefined }));
    }

    /* ================================================================== */
    /* 6. Depo (store), olaylar, genel kancalar                            */
    /* ================================================================== */

    /** Kucuk gozlemlenebilir depo: getState / setState(yama | fn(state) -> yama) / subscribe(fn) -> iptal. */
    function createStore(initial) {
        let state = initial;
        const listeners = new Set();

        return {
            getState: () => state,
            setState(patch) {
                const partial = typeof patch === 'function' ? patch(state) : patch;

                if (!partial || partial === state) {
                    return;
                }

                const next = Object.assign({}, state, partial);

                if (shallowEqual(next, state)) {
                    return;
                }

                state = next;
                Array.from(listeners).forEach((listener) => listener(state));
            },
            subscribe(listener) {
                listeners.add(listener);

                return () => listeners.delete(listener);
            },
        };
    }

    /** Depo kancasi: useStoreOf(depo, secici, esitlik). Secici her cagrida yeni nesne uretiyorsa esitlik olarak KS.shallowEqual verin. */
    function useStoreOf(target, selector, isEqual) {
        const select = selector || ((state) => state);
        const equal = isEqual || Object.is;
        const selectRef = useRef(select);
        const valueRef = useRef(undefined);
        const [, rerender] = useReducer((count) => count + 1, 0);

        let value = select(target.getState());

        // Secici yeni ama esit bir nesne urettiyse onceki referans korunur (gereksiz efekt tetiklenmez).
        if (valueRef.current !== undefined && equal(valueRef.current, value)) {
            value = valueRef.current;
        }

        selectRef.current = select;
        valueRef.current = value;

        useEffect(() => {
            const check = () => {
                if (!equal(valueRef.current, selectRef.current(target.getState()))) {
                    rerender();
                }
            };

            const unsubscribe = target.subscribe(check);

            // Cizim ile abonelik arasinda kacan degisiklik.
            check();

            return unsubscribe;
        }, [target]);

        return value;
    }

    const VIEWS = ['akis', 'plan', 'ilham', 'analiz', 'ayarlar'];
    const VIEW_COMPONENTS = { akis: 'Feed', plan: 'Planner', ilham: 'Insights', analiz: 'Analytics', ayarlar: 'Settings' };
    const DEFAULT_VIEW = 'akis';
    const DEFAULT_FILTERS = { status: 'all', type: '', platform: '', category: '', creator: '', q: '', from: '', to: '', scope: 'all', published: '', stage: '' };
    const CLOSED_COMPOSER = { open: false, content: null, type: null, defaults: null };

    const store = createStore({
        boot: null,
        bootStatus: 'idle',          // idle | loading | ready | error
        bootError: null,
        profileId: null,
        view: DEFAULT_VIEW,
        filters: Object.assign({}, DEFAULT_FILTERS),
        feedVersion: 0,              // artinca akis yeniden cekilir
        counts: {},                  // { <profile_id>: counts (F6) }
        storage: null,
        detailId: null,
        composer: CLOSED_COMPOSER,
        contents: {},                // { <id>: detail } son uygulanan ayrintilar (onbellek)
        lastContent: null,           // applyContent ile gelen son ayrinti
        contentVersion: 0,
    });

    function useStore(selector, isEqual) {
        return useStoreOf(store, selector, isEqual);
    }

    /** Basit olay yolu: events.on('content', fn) -> iptal; events.emit('content', detail). */
    const events = (function () {
        const map = {};

        return {
            on(name, handler) {
                (map[name] = map[name] || new Set()).add(handler);

                return () => map[name].delete(handler);
            },
            emit(name, payload) {
                Array.from(map[name] || []).forEach((handler) => {
                    try {
                        handler(payload);
                    } catch (error) {
                        console.error('KonelsisSocial: olay dinleyicisi hata verdi (' + name + ')', error);
                    }
                });
            },
        };
    }());

    function useInterval(callback, delay, enabled) {
        const saved = useRef(callback);

        saved.current = callback;

        useEffect(() => {
            if (enabled === false || !delay) {
                return undefined;
            }

            const id = window.setInterval(() => saved.current(), delay);

            return () => window.clearInterval(id);
        }, [delay, enabled]);
    }

    /** Degerin gecikmeli kopyasi (arama kutulari icin): const q = useDebounced(term, 300). */
    function useDebounced(value, delay) {
        const [debounced, setDebounced] = useState(value);

        useEffect(() => {
            const timer = window.setTimeout(() => setDebounced(value), delay === undefined ? 300 : delay);

            return () => window.clearTimeout(timer);
        }, [value, delay]);

        return debounced;
    }

    function useMediaQuery(query) {
        const read = () => (typeof window.matchMedia === 'function' ? window.matchMedia(query).matches : false);
        const [matches, setMatches] = useState(read);

        useEffect(() => {
            if (typeof window.matchMedia !== 'function') {
                return undefined;
            }

            const list = window.matchMedia(query);
            const onChange = () => setMatches(list.matches);

            onChange();

            if (list.addEventListener) {
                list.addEventListener('change', onChange);

                return () => list.removeEventListener('change', onChange);
            }

            list.addListener(onChange);

            return () => list.removeListener(onChange);
        }, [query]);

        return matches;
    }

    /** < 768px: kaplamalar tam ekran sayfaya doner. */
    function useIsMobile() {
        return useMediaQuery('(max-width: 767.98px)');
    }

    function useClickOutside(ref, handler, enabled) {
        const saved = useRef(handler);

        saved.current = handler;

        useEffect(() => {
            if (enabled === false) {
                return undefined;
            }

            const onDown = (event) => {
                const node = ref.current;

                if (node && !node.contains(event.target)) {
                    saved.current(event);
                }
            };

            document.addEventListener('mousedown', onDown, true);
            document.addEventListener('touchstart', onDown, true);

            return () => {
                document.removeEventListener('mousedown', onDown, true);
                document.removeEventListener('touchstart', onDown, true);
            };
        }, [ref, enabled]);
    }

    /**
     * Veri yukleme kancasi: const { data, error, loading, reload, setData } = useResource(() => api.get(...), [bagimliliklar]).
     * Yarisan istekleri ve kaldirilmis bileseni korur; hata icin handleError CAGIRMAZ (ekranda ErrorState gosterin).
     */
    function useResource(loader, deps) {
        const [state, setState] = useState({ data: null, error: null, loading: true });
        const [version, setVersion] = useState(0);
        const loaderRef = useRef(loader);

        loaderRef.current = loader;

        useEffect(() => {
            let cancelled = false;

            setState((current) => ({ data: current.data, error: null, loading: true }));

            Promise.resolve()
                .then(() => loaderRef.current())
                .then((data) => {
                    if (!cancelled) {
                        setState({ data, error: null, loading: false });
                    }
                })
                .catch((error) => {
                    if (!cancelled && !(error && error.aborted)) {
                        setState((current) => ({ data: current.data, error, loading: false }));
                    }
                });

            return () => {
                cancelled = true;
            };
        }, (deps || []).concat([version]));

        const reload = useCallback(() => setVersion((current) => current + 1), []);
        const setData = useCallback((next) => setState((current) => ({
            data: typeof next === 'function' ? next(current.data) : next,
            error: current.error,
            loading: current.loading,
        })), []);

        return { data: state.data, error: state.error, loading: state.loading, reload, setData };
    }

    /* ================================================================== */
    /* 7. Adres esitleme (router) ve eylemler                              */
    /* ================================================================== */

    const PARAMS = Object.assign({ view: 'gorunum', content: 'icerik', profile: 'hesap' }, (config.params && typeof config.params === 'object') ? config.params : {});

    /*
     * Tarayici gecmisi (H3): ayrinti ve olusturucu acilirken pushState ile bir girdi
     * eklenir; Geri tusu en ustteki kaplamayi kapatir. nav.stack acik kaplamalari,
     * `pushed` o kaplama icin gecmis girdisi eklenip eklenmedigini tutar (derin
     * baglantiyla acilan ayrinti replaceState kullanir, girdisi yoktur).
     * history.state icindeki ksDepth = o girdiye kadar bize ait ardisik girdi sayisi;
     * replaceState bu degeri hic degistirmez. Kararli durumda ksDepth === pushedCount().
     * Arayuzden kapatmada fazla girdi bir sonraki dongude history.go(-n) ile geri
     * verilir (settleHistory); arada yeni bir kaplama acilirsa o girdi yeniden
     * kullanilir (pushState yerine replaceState), boylece "olusturucuyu kapat + ayrintiyi
     * ac" ayni anda cagrildiginda gecmis bozulmaz.
     */
    const nav = { stack: [], guards: {}, ignoreUntil: 0, timer: 0 };

    function pushedCount() {
        return nav.stack.filter((item) => item.pushed).length;
    }

    function depthOf(state) {
        return state && typeof state.ksDepth === 'number' ? state.ksDepth : 0;
    }

    function profileById(id) {
        const boot = store.getState().boot;

        return ((boot && boot.profiles) || []).find((profile) => Number(profile.id) === Number(id)) || null;
    }

    function profileByCode(code) {
        const boot = store.getState().boot;
        const wanted = String(code || '').toLowerCase();

        return ((boot && boot.profiles) || []).find((profile) => String(profile.code || '').toLowerCase() === wanted) || null;
    }

    const router = {
        params: PARAMS,

        /** Adresteki Turkce parametreler -> { view, contentId, profileCode } (gecersizler null). */
        read() {
            const query = new URLSearchParams(window.location.search);
            const view = query.get(PARAMS.view);
            const contentId = parseInt(query.get(PARAMS.content) || '', 10);

            return {
                view: VIEWS.indexOf(view) !== -1 ? view : null,
                contentId: contentId > 0 ? contentId : null,
                profileCode: query.get(PARAMS.profile) || null,
            };
        },

        /** Yama uygulanmis adres: { view, contentId, profileCode }; null/'' parametreyi siler. */
        href(patch) {
            const address = new URL(window.location.href);
            const apply = (name, value) => {
                if (value === undefined) {
                    return;
                }

                if (value === null || value === '' || value === false) {
                    address.searchParams.delete(name);
                } else {
                    address.searchParams.set(name, String(value));
                }
            };

            if (patch) {
                apply(PARAMS.view, patch.view === DEFAULT_VIEW ? null : patch.view);
                apply(PARAMS.content, patch.contentId);
                apply(PARAMS.profile, patch.profileCode);
            }

            return address.pathname + address.search + address.hash;
        },

        /** Adresi gunceller (her zaman replaceState; gecmise girdi eklemez, ksDepth degerini korur). */
        write(patch) {
            try {
                window.history.replaceState(window.history.state, '', router.href(patch));
            } catch (error) {
                // Gecmis API'si kisitliysa (ornegin gomulu onizleme) adres esitlenmez; uygulama calismaya devam eder.
            }
        },

        /** Adresi depodaki duruma esitler (replaceState). */
        sync() {
            const state = store.getState();
            const profile = profileById(state.profileId);

            router.write({ view: state.view, contentId: state.detailId, profileCode: profile ? profile.code : undefined });
        },

        /**
         * Geri tusuyla kapanmadan once sorulacak koruma: setGuard('composer', () => Promise<boolean>|boolean).
         * true = kapanabilir. null vererek kaldirin. (Arayuzdeki Kapat dugmesi icin onay bilesenin kendi isidir.)
         */
        setGuard(kind, guard) {
            if (typeof guard === 'function') {
                nav.guards[kind] = guard;
            } else {
                delete nav.guards[kind];
            }
        },
    };

    function closeInStore(kind) {
        if (kind === 'detail') {
            store.setState({ detailId: null });
        } else if (kind === 'composer') {
            store.setState({ composer: CLOSED_COMPOSER });
        }
    }

    function setDepth(depth) {
        try {
            window.history.replaceState(Object.assign({}, window.history.state || {}, { ksDepth: depth }), '', window.location.href);
        } catch (error) {
            // yok sayilir
        }
    }

    /** Fazla gecmis girdilerini geri verir ya da yalniz adresi esitler (bir sonraki dongude calisir). */
    function settleHistory() {
        nav.timer = 0;

        const extra = depthOf(window.history.state) - pushedCount();

        if (extra > 0) {
            // Bu gecis bizim; gelen popstate yok sayilir. Sure siniri takili kalmayi onler.
            nav.ignoreUntil = Date.now() + 1500;

            try {
                window.history.go(-extra);
            } catch (error) {
                nav.ignoreUntil = 0;
            }

            return;
        }

        if (extra < 0) {
            // Olmamasi gereken durum (ornegin gecmis disaridan degistirildi): sayac gercege cekilir.
            setDepth(pushedCount());
        }

        router.sync();
    }

    function scheduleSettle() {
        if (!nav.timer) {
            nav.timer = window.setTimeout(settleHistory, 0);
        }
    }

    function openOverlay(kind, replace) {
        if (nav.stack.some((item) => item.kind === kind)) {
            return false;
        }

        nav.stack.push({ kind, pushed: !replace });

        return true;
    }

    /** Yeni acilan (pushed) kaplama icin gecmis girdisi: bosta bir girdimiz varsa o kullanilir, yoksa eklenir. */
    function claimEntry(patch) {
        const wanted = pushedCount();

        try {
            if (depthOf(window.history.state) >= wanted) {
                window.history.replaceState(Object.assign({}, window.history.state || {}, { ksDepth: wanted }), '', router.href(patch));
            } else {
                window.history.pushState({ ksDepth: wanted }, '', router.href(patch));
            }
        } catch (error) {
            // yok sayilir
        }
    }

    function leaveOverlay(kind) {
        const index = nav.stack.map((item) => item.kind).lastIndexOf(kind);

        closeInStore(kind);
        delete nav.guards[kind];

        if (index !== -1) {
            nav.stack.splice(index, 1);
        }

        scheduleSettle();
    }

    function onPopState(event) {
        if (nav.ignoreUntil) {
            const ours = Date.now() < nav.ignoreUntil;

            nav.ignoreUntil = 0;

            if (ours) {
                scheduleSettle();

                return;
            }
        }

        const depth = depthOf(event.state);
        const surplus = pushedCount() - depth;

        if (surplus > 0) {
            const top = nav.stack[nav.stack.length - 1];
            const guard = surplus === 1 && top && top.pushed ? nav.guards[top.kind] : null;

            if (guard) {
                // Girdi geri konur, sonra kullaniciya sorulur; onaylarsa normal kapatma yolu calisir.
                try {
                    window.history.pushState({ ksDepth: depth + 1 }, '', router.href({ contentId: store.getState().detailId }));
                } catch (error) {
                    // yok sayilir
                }

                Promise.resolve().then(guard).then((allowed) => {
                    if (allowed) {
                        leaveOverlay(top.kind);
                    }
                }).catch(() => undefined);

                return;
            }

            while (pushedCount() > depth) {
                let index = nav.stack.length - 1;

                while (index > 0 && !nav.stack[index].pushed) {
                    index -= 1;
                }

                const closed = nav.stack.splice(index, 1)[0];

                delete nav.guards[closed.kind];
                closeInStore(closed.kind);
            }
        }

        const location = router.read();

        // Ileri tusu: ayrinti girdisine donulduyse ayrinti yeniden acilir. Geri getirilemeyen
        // girdiler (kapatilmis olusturucu) settleHistory tarafindan geri sarilir.
        if (depth > pushedCount() && location.contentId && !store.getState().detailId && openOverlay('detail', false)) {
            store.setState({ detailId: location.contentId });
        }

        if (location.view && location.view !== store.getState().view && !nav.stack.length) {
            store.setState({ view: location.view });
        }

        scheduleSettle();
    }

    window.addEventListener('popstate', onPopState);

    // Sayfa yenilendiyse onceki belgeden kalan ksDepth sifirlanir (bellekteki yigin bos).
    if (depthOf(window.history.state) !== 0) {
        setDepth(0);
    }

    function rememberProfile(profile) {
        try {
            window.localStorage.setItem('ks_profile', profile ? String(profile.code) : '');
        } catch (error) {
            // Gizli sekme ya da kapali depolama: hatirlama olmadan devam.
        }
    }

    function rememberedProfileCode() {
        try {
            return window.localStorage.getItem('ks_profile') || null;
        } catch (error) {
            return null;
        }
    }

    const countsSeq = {};
    let bootPromise = null;

    const actions = {
        /**
         * bootstrap ucunu ceker; boot/counts/storage/profileId/view degerlerini kurar.
         * Profil onceligi: adresteki `hesap` -> son kullanilan (localStorage ks_profile) -> ilk profil.
         * Promise<boot>; hata durumunda bootStatus 'error' olur ve hata yeniden firlatilir.
         */
        loadBoot(force) {
            if (bootPromise && !force) {
                return bootPromise;
            }

            store.setState({ bootStatus: 'loading', bootError: null });

            bootPromise = api.get(url('bootstrap')).then((payload) => {
                const boot = payload || {};
                const profiles = boot.profiles || [];
                const location = router.read();
                const current = store.getState();

                store.setState({ boot });

                const wanted = profileById(current.profileId)
                    || profileByCode(location.profileCode)
                    || profileByCode(rememberedProfileCode())
                    || profiles[0]
                    || null;

                store.setState({
                    bootStatus: 'ready',
                    counts: boot.counts && typeof boot.counts === 'object' ? boot.counts : {},
                    storage: boot.storage || null,
                    profileId: wanted ? wanted.id : null,
                    view: location.view || current.view || DEFAULT_VIEW,
                });

                return boot;
            }).catch((error) => {
                bootPromise = null;
                store.setState({ bootStatus: 'error', bootError: error });

                throw error;
            });

            return bootPromise;
        },

        /** Etkin hesabi degistirir: adres (`hesap`), localStorage, akis ve sayaclar tazelenir. */
        setProfile(id, options) {
            const profile = profileById(id);

            if (!profile) {
                return;
            }

            const changed = Number(store.getState().profileId) !== Number(profile.id);

            store.setState({ profileId: profile.id });
            rememberProfile(profile);
            router.sync();

            if (changed && !(options && options.silent)) {
                store.setState((state) => ({ feedVersion: state.feedVersion + 1 }));
                actions.refreshCounts();
            }
        },

        /** Gorunum: 'akis' | 'plan' | 'ilham' | 'analiz' | 'ayarlar' (adres `gorunum`). */
        setView(view) {
            if (VIEWS.indexOf(view) === -1) {
                return;
            }

            store.setState({ view });
            router.sync();

            try {
                window.scrollTo({ top: 0, behavior: 'auto' });
            } catch (error) {
                window.scrollTo(0, 0);
            }
        },

        setFilters(patch) {
            store.setState((state) => ({ filters: Object.assign({}, state.filters, patch || {}) }));
        },

        resetFilters(keep) {
            store.setState({ filters: Object.assign({}, DEFAULT_FILTERS, keep || {}) });
        },

        /** Ayrintiyi acar (pushState). options.replace = true: derin baglanti acilisi (replaceState). */
        openDetail(id, options) {
            const contentId = Number(id);

            if (!(contentId > 0)) {
                return;
            }

            const replace = !!(options && options.replace);
            const opened = openOverlay('detail', replace);

            store.setState({ detailId: contentId });

            if (opened && !replace) {
                claimEntry({ contentId });
            } else {
                router.sync();
            }
        },

        closeDetail() {
            if (store.getState().detailId === null && !nav.stack.some((item) => item.kind === 'detail')) {
                return;
            }

            leaveOverlay('detail');
        },

        /**
         * Olusturucu (H3). arg: icerik ayrintisi (duzenleme) | tur metni | { type?, defaults?: { planned_on?,
         * planned_time?, profile_id?, category_id?, platforms? } }. Zaten aciksa yalniz durum guncellenir
         * (ornegin "once kaydet" sonrasi duzenleme kipine gecis), yeni gecmis girdisi eklenmez.
         */
        openComposer(arg) {
            let next = { open: true, content: null, type: null, defaults: null };

            if (typeof arg === 'string') {
                next.type = arg;
            } else if (arg && typeof arg === 'object' && arg.id && arg.content_type) {
                next = { open: true, content: arg, type: arg.content_type, defaults: null };
            } else if (arg && typeof arg === 'object') {
                next.type = arg.type || null;
                next.defaults = arg.defaults || null;
            }

            const opened = openOverlay('composer', false);

            store.setState({ composer: next });

            if (opened) {
                claimEntry({});
            }
        },

        closeComposer() {
            if (!store.getState().composer.open && !nav.stack.some((item) => item.kind === 'composer')) {
                return;
            }

            leaveOverlay('composer');
        },

        refreshFeed() {
            store.setState((state) => ({ feedVersion: state.feedVersion + 1 }));
        },

        /** Etkin (ya da verilen) hesabin sayaclarini ceker; hata sessizdir (bir sonraki yoklamada denenir). */
        refreshCounts(profileId) {
            const id = profileId || store.getState().profileId;

            if (!id || !hasEndpoint('counts')) {
                return Promise.resolve(null);
            }

            // Sira korumasi HESAP BASINADIR: iki hesabin es zamanli tazelemesi birbirinin yanitini dusurmez.
            countsSeq[id] = (countsSeq[id] || 0) + 1;

            const seq = countsSeq[id];

            return api.get(url('counts'), { profile: id }).then((payload) => {
                if (seq !== countsSeq[id] || !payload || !payload.counts) {
                    return null;
                }

                store.setState((state) => {
                    const counts = Object.assign({}, state.counts);

                    counts[id] = payload.counts;

                    return { counts };
                });

                return payload.counts;
            }).catch(() => null);
        },

        refreshStorage() {
            if (!hasEndpoint('storage')) {
                return Promise.resolve(null);
            }

            return api.get(url('storage')).then((payload) => {
                const storage = payload && payload.storage ? payload.storage : null;

                if (storage) {
                    store.setState({ storage });
                }

                return storage;
            }).catch(() => null);
        },

        /** Ayrintiyi sunucudan ceker, onbellege uygular ve dondurur (hata firlatir). */
        fetchContent(id) {
            return api.get(url('contents.show', id)).then((payload) => {
                const detail = payload && payload.content ? payload.content : payload;

                if (detail && detail.id) {
                    actions.applyContent(detail, { silent: true });
                }

                return detail;
            });
        },

        /**
         * Her icerik degisikliginden sonra { content } yanitindaki ayrintiyi uygular: onbellek (contents),
         * lastContent/contentVersion, acik olusturucunun icerigi (row_version devralinir, H2), 'content'
         * olayi ve sayac tazeleme. options.silent = true: yalniz onbellek (sayac/olay yok).
         */
        applyContent(detail, options) {
            if (!detail || !detail.id) {
                return;
            }

            store.setState((state) => {
                const contents = Object.assign({}, state.contents);
                const keys = Object.keys(contents);

                // Onbellek sinirli tutulur (en eski kayitlar dusurulur).
                if (keys.length >= 40 && !contents[detail.id]) {
                    keys.slice(0, keys.length - 39).forEach((key) => delete contents[key]);
                }

                contents[detail.id] = detail;

                const patch = { contents };

                if (!(options && options.silent)) {
                    patch.lastContent = detail;
                    patch.contentVersion = state.contentVersion + 1;
                }

                if (state.composer.open && state.composer.content && Number(state.composer.content.id) === Number(detail.id)) {
                    patch.composer = Object.assign({}, state.composer, { content: detail });
                }

                return patch;
            });

            if (!(options && options.silent)) {
                events.emit('content', detail);
                scheduleCountsRefresh();
            }
        },

        /**
         * Derin baglanti (H4): adreste `icerik` varsa once ayrinti cekilir, hesap o icerigin hesabina
         * alinir, sonra ayrinti replaceState ile acilir. 403/404 -> content_not_accessible bildirimi,
         * parametre silinir. loadBoot tamamlandiktan sonra cagirin. Promise<boolean> (acildi mi).
         */
        resolveDeepLink() {
            const location = router.read();

            if (!location.contentId) {
                router.sync();

                return Promise.resolve(false);
            }

            return actions.fetchContent(location.contentId).then((detail) => {
                if (detail && detail.profile_id) {
                    actions.setProfile(detail.profile_id, { silent: true });
                }

                actions.openDetail(location.contentId, { replace: true });

                return true;
            }).catch((error) => {
                if (error && (error.status === 403 || error.status === 404)) {
                    toast.error(t('content_not_accessible'));
                } else {
                    handleError(error);
                }

                router.sync();

                return false;
            });
        },
    };

    const scheduleCountsRefresh = debounce(() => actions.refreshCounts(), 400);

    /* Depo secicileri icin kisa kancalar. */
    const EMPTY_OBJECT = Object.freeze({});
    const EMPTY_LIST = Object.freeze([]);

    const useBoot = () => useStore((state) => state.boot);
    const useMe = () => useStore((state) => (state.boot && state.boot.me) || null);
    const useAbilities = () => useStore((state) => (state.boot && state.boot.me && state.boot.me.abilities) || EMPTY_OBJECT);
    const useLimits = () => useStore((state) => (state.boot && state.boot.limits) || EMPTY_OBJECT);
    const useProfile = () => useStore((state) => ((state.boot && state.boot.profiles) || EMPTY_LIST).find((profile) => Number(profile.id) === Number(state.profileId)) || null);
    const useCounts = () => useStore((state) => state.counts[state.profileId] || EMPTY_OBJECT);

    /** boot.options icinden liste: options('platforms') -> [{ value, label, ... }]. */
    function options(group) {
        const boot = store.getState().boot;

        return (boot && boot.options && boot.options[group]) || EMPTY_LIST;
    }

    /** Secenek etiketi: optionLabel('types', 'photo') -> "Fotograf"; bulunamazsa ''. */
    function optionLabel(group, value) {
        const found = options(group).find((item) => item && String(item.value) === String(value));

        return found ? found.label : '';
    }

    function limits() {
        const boot = store.getState().boot;

        return (boot && boot.limits) || EMPTY_OBJECT;
    }

    /* ================================================================== */
    /* 8. Kaplama altyapisi: portal, odak tuzagi, Escape, kaydirma kilidi   */
    /* ================================================================== */

    let portalNode = null;

    /** Kaplamalar Livewire bileseninin disina, body sonundaki #ks-portal-root icine cizilir. */
    function portalRoot() {
        if (!portalNode || !document.body.contains(portalNode)) {
            portalNode = document.getElementById('ks-portal-root');

            if (!portalNode) {
                portalNode = document.createElement('div');
                portalNode.id = 'ks-portal-root';
                portalNode.className = 'ks-root ks-portal-root';
                document.body.appendChild(portalNode);
            }
        }

        return portalNode;
    }

    function Portal(props) {
        return ReactDOM.createPortal(props.children, portalRoot());
    }

    const overlayStack = [];
    let scrollLocks = 0;

    function lockScroll() {
        scrollLocks += 1;

        if (scrollLocks === 1) {
            const html = document.documentElement;
            const gap = window.innerWidth - html.clientWidth;

            html.style.setProperty('--ks-scrollbar-gap', (gap > 0 ? gap : 0) + 'px');
            html.classList.add('ks-scroll-lock');
        }
    }

    function unlockScroll() {
        scrollLocks = Math.max(0, scrollLocks - 1);

        if (scrollLocks === 0) {
            document.documentElement.classList.remove('ks-scroll-lock');
            document.documentElement.style.removeProperty('--ks-scrollbar-gap');
        }
    }

    const FOCUSABLE = [
        'a[href]', 'area[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])', 'textarea:not([disabled])', 'iframe', 'video[controls]', 'audio[controls]',
        'summary', '[contenteditable="true"]', '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    function focusableIn(node) {
        return Array.prototype.filter.call(node.querySelectorAll(FOCUSABLE), (element) => element.getClientRects().length > 0 && element.getAttribute('aria-hidden') !== 'true');
    }

    function focusQuietly(element) {
        if (!element || typeof element.focus !== 'function') {
            return;
        }

        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    }

    function onOverlayKeyDown(event) {
        if (!overlayStack.length || event.isComposing) {
            return;
        }

        if (event.key === 'Escape' || event.key === 'Esc') {
            const top = overlayStack[overlayStack.length - 1];

            // Escape yalniz en ustteki katmana gider; Filament ve sohbet paneli dinleyicilerine ulasmaz.
            event.stopPropagation();

            if (top.dismissible()) {
                event.preventDefault();
                top.close('escape');
            }

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        let entry = null;

        for (let index = overlayStack.length - 1; index >= 0; index -= 1) {
            if (overlayStack[index].modal) {
                entry = overlayStack[index];

                break;
            }
        }

        const node = entry && entry.ref.current;

        if (!node) {
            return;
        }

        const items = focusableIn(node);
        const active = document.activeElement;

        if (!items.length) {
            event.preventDefault();
            focusQuietly(node);

            return;
        }

        if (!node.contains(active)) {
            event.preventDefault();
            focusQuietly(items[0]);

            return;
        }

        if (event.shiftKey && (active === items[0] || active === node)) {
            event.preventDefault();
            focusQuietly(items[items.length - 1]);
        } else if (!event.shiftKey && active === items[items.length - 1]) {
            event.preventDefault();
            focusQuietly(items[0]);
        }
    }

    document.addEventListener('keydown', onOverlayKeyDown, true);

    /**
     * Kaplama davranisi (Modal, Drawer, Overlay ve Popover bunu kullanir; kendi tam ekran
     * kaplamanizi yaziyorsaniz siz de kullanin):
     *   useOverlay(ref, { onClose(reason), dismissible = true, modal = true, lockScroll = true,
     *                     restoreFocus = true, initialFocus: ref | secici, active = true })
     * modal: govde kaydirmasi kilitlenir, Tab odagi iceride doner, acilista odak iceri alinir
     * ([data-autofocus] > initialFocus > kap), kapanista odak acana geri verilir. Escape en ustteki
     * katmanin onClose('escape') islevini cagirir.
     */
    function useOverlay(ref, options) {
        const saved = useRef(options || {});

        saved.current = options || {};

        const active = saved.current.active !== false;

        useEffect(() => {
            if (!active) {
                return undefined;
            }

            const modal = saved.current.modal !== false;
            const locks = modal && saved.current.lockScroll !== false;
            const opener = document.activeElement;
            const entry = {
                ref,
                modal,
                dismissible: () => saved.current.dismissible !== false && typeof saved.current.onClose === 'function',
                close: (reason) => saved.current.onClose(reason),
            };

            overlayStack.push(entry);

            if (locks) {
                lockScroll();
            }

            let frame = 0;

            if (modal) {
                frame = window.requestAnimationFrame(() => {
                    const node = ref.current;

                    if (!node || node.contains(document.activeElement)) {
                        return;
                    }

                    const wanted = saved.current.initialFocus;
                    let target = null;

                    if (wanted && typeof wanted === 'object' && wanted.current) {
                        target = wanted.current;
                    } else if (typeof wanted === 'string') {
                        target = node.querySelector(wanted);
                    }

                    focusQuietly(target || node.querySelector('[data-autofocus]') || node);
                });
            }

            return () => {
                window.cancelAnimationFrame(frame);

                const index = overlayStack.indexOf(entry);

                if (index !== -1) {
                    overlayStack.splice(index, 1);
                }

                if (locks) {
                    unlockScroll();
                }

                if (modal && saved.current.restoreFocus !== false && opener && opener !== document.body && document.contains(opener)) {
                    focusQuietly(opener);
                }
            };
        }, [active, ref]);
    }

    /* ================================================================== */
    /* 9. Temel bilesenler                                                 */
    /* ================================================================== */

    const BUTTON_OWN_PROPS = ['variant', 'size', 'icon', 'iconRight', 'loading', 'block', 'active', 'pressed', 'href', 'target', 'download', 'className', 'children', 'ariaLabel', 'buttonRef', 'label', 'count'];

    /**
     * Button({ variant: 'primary'|'ghost'|'soft'|'danger'|'success'|'link', size: 'sm'|'md'|'lg', icon, iconRight,
     *          loading, disabled, block, active, pressed, onClick, title, type, href, target, download, ariaLabel })
     * href verilirse <a> cizilir (KS.safeUrl'den gecer; dis adres yeni sekmede, rel noopener noreferrer).
     */
    function Button(props) {
        const variant = props.variant || 'ghost';
        const size = props.size || 'md';
        const withLabel = props.children !== undefined && props.children !== null && props.children !== false;
        const className = cx('ks-btn', 'ks-btn--' + variant, 'ks-btn--' + size, props.block && 'ks-btn--block', props.loading && 'is-loading', props.active && 'is-active', !withLabel && 'ks-btn--icon-only', props.className);
        const content = [
            props.loading ? h('span', { key: 'spin', className: 'ks-btn__spinner', 'aria-hidden': 'true' }) : (props.icon ? h(Icon, { key: 'icon', name: props.icon }) : null),
            withLabel ? h('span', { key: 'label', className: 'ks-btn__label' }, props.children) : null,
            props.iconRight ? h(Icon, { key: 'right', name: props.iconRight }) : null,
        ];
        const href = props.href !== undefined ? safeUrl(props.href) : null;

        if (href && !props.disabled && !props.loading) {
            const external = props.target ? props.target === '_blank' : isExternalUrl(href);

            return h('a', {
                href,
                className,
                target: external ? '_blank' : (props.target || undefined),
                rel: external ? 'noopener noreferrer' : undefined,
                download: props.download,
                title: props.title,
                'aria-label': props.ariaLabel,
                onClick: props.onClick,
            }, content);
        }

        const native = omit(props, BUTTON_OWN_PROPS);

        return h('button', Object.assign({}, native, {
            type: props.type || 'button',
            ref: props.buttonRef,
            className,
            disabled: !!(props.disabled || props.loading || (props.href !== undefined && !href)),
            'aria-busy': props.loading ? 'true' : undefined,
            'aria-pressed': props.pressed === undefined ? undefined : (props.pressed ? 'true' : 'false'),
            'aria-label': props.ariaLabel || native['aria-label'],
        }), content);
    }

    /**
     * IconButton({ icon, label (zorunlu: aria-label + ipucu), variant: 'ghost'|'plain'|'soft'|'solid'|'danger'|'inverse',
     *              size: 'sm'|'md'|'lg', active, pressed, count, loading, disabled, onClick, href, title })
     */
    function IconButton(props) {
        const variant = props.variant || 'ghost';
        const size = props.size || 'md';
        const className = cx('ks-iconbtn', 'ks-iconbtn--' + variant, 'ks-iconbtn--' + size, props.active && 'is-active', props.loading && 'is-loading', props.className);
        const content = [
            props.loading ? h('span', { key: 'spin', className: 'ks-btn__spinner', 'aria-hidden': 'true' }) : h(Icon, { key: 'icon', name: props.icon }),
            props.count !== undefined && props.count !== null && props.count !== 0 ? h('span', { key: 'count', className: 'ks-iconbtn__count' }, props.count) : null,
        ];
        const href = props.href !== undefined ? safeUrl(props.href) : null;

        if (href && !props.disabled) {
            const external = props.target ? props.target === '_blank' : isExternalUrl(href);

            return h('a', {
                href,
                className,
                target: external ? '_blank' : (props.target || undefined),
                rel: external ? 'noopener noreferrer' : undefined,
                download: props.download,
                title: props.title || props.label,
                'aria-label': props.label,
                onClick: props.onClick,
            }, content);
        }

        const native = omit(props, BUTTON_OWN_PROPS);

        return h('button', Object.assign({}, native, {
            type: 'button',
            ref: props.buttonRef,
            className,
            disabled: !!(props.disabled || props.loading || (props.href !== undefined && !href)),
            title: props.title || props.label,
            'aria-label': props.label,
            'aria-pressed': props.pressed === undefined ? undefined : (props.pressed ? 'true' : 'false'),
        }), content);
    }

    /** Badge({ color: 8 palet adindan biri, icon, dot, solid, size: 'sm'|'md', title }) */
    function Badge(props) {
        return h('span', {
            className: cx('ks-badge', 'ks-c-' + paletteColor(props.color), props.solid && 'ks-badge--solid', props.size === 'sm' && 'ks-badge--sm', props.className),
            title: props.title,
        },
            props.dot ? h('span', { className: 'ks-badge__dot', 'aria-hidden': 'true' }) : null,
            props.icon ? h(Icon, { name: props.icon }) : null,
            props.children,
        );
    }

    /** StatusBadge({ status, label, color }) - color yoksa durum degerinden turetilir (B2 paleti). */
    function StatusBadge(props) {
        return h(Badge, { color: props.color || statusColor(props.status), dot: true, size: props.size, className: cx('ks-badge--status', props.className) }, props.label || props.status_label || '');
    }

    /** StageBadge({ stage: today|tomorrow|approaching|missed, label }) - renkler istemcide sabit (B3). */
    function StageBadge(props) {
        if (!props.stage) {
            return null;
        }

        return h(Badge, { color: stageColor(props.stage), icon: props.stage === 'missed' ? 'alert' : 'clock', size: props.size, className: cx('ks-badge--stage', props.className) }, props.label || t('stage_' + props.stage));
    }

    /**
     * Chip({ label | children, color, active, onClick, onRemove, icon, platform, disabled, title, size: 'sm'|'md' })
     * onClick varsa secilebilir dugmedir (aria-pressed = active); onRemove varsa kucuk kaldir dugmesi eklenir.
     */
    function Chip(props) {
        const body = [
            props.platform ? h(PlatformIcon, { key: 'platform', platform: props.platform }) : (props.icon ? h(Icon, { key: 'icon', name: props.icon }) : null),
            h('span', { key: 'label', className: 'ks-chip__label' }, props.label !== undefined ? props.label : props.children),
        ];
        const className = cx('ks-chip', props.color && 'ks-c-' + paletteColor(props.color), props.color && 'ks-chip--tinted', props.active && 'is-active', props.size === 'sm' && 'ks-chip--sm', props.disabled && 'is-disabled', props.className);

        if (!props.onClick && !props.onRemove) {
            return h('span', { className, title: props.title }, body);
        }

        return h('span', { className: cx(className, 'ks-chip--split'), title: props.title },
            props.onClick
                ? h('button', { type: 'button', className: 'ks-chip__main', disabled: props.disabled, 'aria-pressed': props.active ? 'true' : 'false', onClick: props.onClick }, body)
                : h('span', { className: 'ks-chip__main' }, body),
            props.onRemove
                ? h('button', { type: 'button', className: 'ks-chip__remove', disabled: props.disabled, 'aria-label': t('clear'), onClick: props.onRemove }, h(Icon, { name: 'close' }))
                : null,
        );
    }

    function initialsOf(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

        if (!parts.length) {
            return '?';
        }

        const first = parts[0].charAt(0);
        const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

        return (first + last).toLocaleUpperCase(locale);
    }

    /** Avatar({ person | null, size: 'xs'|'sm'|'md'|'lg'|'xl', title }) - null kisi "Sistem" olarak cizilir. */
    function Avatar(props) {
        const person = props.person || null;
        const source = person ? safeUrl(person.photo) : null;
        const [broken, setBroken] = useState(false);
        const name = fmt.personName(person);

        useEffect(() => setBroken(false), [source]);

        return h('span', {
            className: cx('ks-avatar', 'ks-avatar--' + (props.size || 'md'), !person && 'ks-avatar--system', props.className),
            title: props.title === false ? undefined : (props.title || name),
            role: 'img',
            'aria-label': name,
        },
            source && !broken
                ? h('img', { src: source, alt: '', loading: 'lazy', decoding: 'async', onError: () => setBroken(true) })
                : (person
                    ? h('span', { className: 'ks-avatar__initials', 'aria-hidden': 'true' }, person.initials || initialsOf(name))
                    : h(Icon, { name: 'settings' })),
        );
    }

    /** AvatarStack({ people, max = 4, size = 'sm', total }) - fazlasi "+N" rozetiyle. */
    function AvatarStack(props) {
        const people = props.people || [];
        const max = props.max || 4;
        const total = props.total !== undefined ? props.total : people.length;
        const shown = people.slice(0, max);
        const rest = Math.max(0, total - shown.length);

        if (!shown.length) {
            return null;
        }

        return h('span', { className: cx('ks-avatar-stack', props.className) },
            shown.map((person, index) => h(Avatar, { key: (person && person.id) || 'p' + index, person, size: props.size || 'sm' })),
            rest > 0 ? h('span', { className: cx('ks-avatar', 'ks-avatar--' + (props.size || 'sm'), 'ks-avatar--more'), title: t('more_people', { n: rest }) }, '+' + rest) : null,
        );
    }

    /** PersonLine({ person, meta, size, trailing }) - avatar + ad + ikinci satir (meta ya da gorev / departman). */
    function PersonLine(props) {
        const person = props.person || null;
        const meta = props.meta !== undefined ? props.meta : (person ? [person.job_title, person.department].filter(Boolean).join(' · ') : '');

        return h('span', { className: cx('ks-person', props.className) },
            h(Avatar, { person, size: props.size || 'sm', title: false }),
            h('span', { className: 'ks-person__text' },
                h('span', { className: 'ks-person__name' }, fmt.personName(person)),
                meta ? h('span', { className: 'ks-person__meta' }, meta) : null,
            ),
            props.trailing || null,
        );
    }

    /** ExternalLink({ href, children, className, title }) - adres guvenli degilse duz metin cizer. */
    function ExternalLink(props) {
        const href = safeUrl(props.href);

        if (!href) {
            return h('span', { className: props.className }, props.children);
        }

        const external = isExternalUrl(href);

        return h('a', {
            href,
            className: cx('ks-link', props.className),
            target: external ? '_blank' : undefined,
            rel: external ? 'noopener noreferrer' : undefined,
            title: props.title || (external ? t('opens_new_tab') : undefined),
            onClick: props.onClick,
        }, props.children);
    }

    /**
     * PlatformLink({ platform, label, url, handle, compact }) - marka renginde baglanti dugmesi (yeni sekme).
     * compact: yalniz simge (yuvarlak). Adres gecersizse hic cizilmez.
     */
    function PlatformLink(props) {
        const href = safeUrl(props.url);

        if (!href) {
            return null;
        }

        const label = props.label || props.platform_label || props.platform;

        return h('a', {
            href,
            className: cx('ks-platform', platformClass(props.platform), 'ks-platform-link', props.compact && 'ks-platform-link--compact', props.className),
            target: '_blank',
            rel: 'noopener noreferrer',
            title: props.compact ? label + (props.handle ? ' · ' + props.handle : '') : t('opens_new_tab'),
            'aria-label': props.compact ? label : undefined,
        },
            h(Icon, { name: platformIconName(props.platform) }),
            props.compact ? null : h('span', { className: 'ks-platform-link__text' },
                h('span', { className: 'ks-platform-link__name' }, label),
                props.handle ? h('span', { className: 'ks-platform-link__handle' }, props.handle) : null,
            ),
            props.compact ? null : h(Icon, { name: 'external', className: 'ks-platform-link__out' }),
        );
    }

    /** Spinner({ size: 'sm'|'md'|'lg', label, center }) */
    function Spinner(props) {
        return h('span', { className: cx('ks-spinner-wrap', props.center && 'ks-spinner-wrap--center', props.className), role: 'status' },
            h('span', { className: cx('ks-spinner', 'ks-spinner--' + (props.size || 'md')), 'aria-hidden': 'true' }),
            h('span', { className: props.label ? 'ks-spinner__label' : 'ks-sr-only' }, props.label || t('loading')),
        );
    }

    /** Skeleton({ variant: 'text'|'rect'|'circle'|'card', width, height, lines, radius }) */
    function Skeleton(props) {
        const variant = props.variant || 'text';
        const style = {};

        if (props.width !== undefined) {
            style.width = typeof props.width === 'number' ? props.width + 'px' : props.width;
        }

        if (props.height !== undefined) {
            style.height = typeof props.height === 'number' ? props.height + 'px' : props.height;
        }

        if (props.radius !== undefined) {
            style.borderRadius = typeof props.radius === 'number' ? props.radius + 'px' : props.radius;
        }

        if (variant === 'card') {
            return h('div', { className: cx('ks-skeleton-card', props.className), 'aria-hidden': 'true' },
                h('span', { className: 'ks-skeleton ks-skeleton--rect ks-skeleton-card__media', style: props.height !== undefined ? { height: style.height } : undefined }),
                h('span', { className: 'ks-skeleton ks-skeleton--text', style: { width: '70%' } }),
                h('span', { className: 'ks-skeleton ks-skeleton--text', style: { width: '45%' } }),
            );
        }

        if (variant === 'text' && props.lines > 1) {
            const lines = [];

            for (let index = 0; index < props.lines; index += 1) {
                lines.push(h('span', { key: index, className: 'ks-skeleton ks-skeleton--text', style: { width: index === props.lines - 1 ? '60%' : '100%' } }));
            }

            return h('span', { className: cx('ks-skeleton-lines', props.className), 'aria-hidden': 'true' }, lines);
        }

        return h('span', { className: cx('ks-skeleton', 'ks-skeleton--' + variant, props.className), style, 'aria-hidden': 'true' });
    }

    /** ProgressBar({ value, max = 100, label, tone: 'accent'|'success'|'warning'|'danger'|'neutral', size: 'sm'|'md', indeterminate, showValue }) */
    function ProgressBar(props) {
        const max = props.max || 100;
        const value = clamp(Number(props.value) || 0, 0, max);
        const percent = Math.round((value / max) * 100);

        return h('div', { className: cx('ks-progress', 'ks-progress--' + (props.tone || 'accent'), props.size === 'sm' && 'ks-progress--sm', props.indeterminate && 'is-indeterminate', props.className) },
            props.label || props.showValue ? h('div', { className: 'ks-progress__head' },
                h('span', { className: 'ks-progress__label' }, props.label || ''),
                props.showValue && !props.indeterminate ? h('span', { className: 'ks-progress__value' }, fmt.percent(percent / 100, 0)) : null,
            ) : null,
            h('div', {
                className: 'ks-progress__track',
                role: 'progressbar',
                'aria-valuemin': 0,
                'aria-valuemax': 100,
                'aria-valuenow': props.indeterminate ? undefined : percent,
                'aria-label': typeof props.label === 'string' ? props.label : (props.ariaLabel || t('loading')),
            }, h('div', { className: 'ks-progress__bar', style: props.indeterminate ? undefined : { width: percent + '%' } })),
        );
    }

    /** Tooltip({ text, placement: 'top'|'bottom', children }) - CSS ipucu; dokunmatik ekranda gizlidir (title ozelligi de yeterlidir). */
    function Tooltip(props) {
        if (!props.text) {
            return props.children || null;
        }

        return h('span', { className: cx('ks-tip', 'ks-tip--' + (props.placement || 'top'), props.className), 'data-tip': props.text }, props.children);
    }

    /** Empty({ icon, title, text, action, compact }) - bos durum. */
    function Empty(props) {
        return h('div', { className: cx('ks-empty', props.compact && 'ks-empty--compact', props.className) },
            props.icon ? h('span', { className: 'ks-empty__icon', 'aria-hidden': 'true' }, h(Icon, { name: props.icon })) : null,
            props.title ? h('p', { className: 'ks-empty__title' }, props.title) : null,
            props.text ? h('p', { className: 'ks-empty__text' }, props.text) : null,
            props.action ? h('div', { className: 'ks-empty__action' }, props.action) : null,
        );
    }

    /** ErrorState({ error | text, onRetry, compact }) - yukleme hatasi + "Tekrar dene". */
    function ErrorState(props) {
        const text = props.text || (props.error ? describeError(props.error) : null) || t('load_failed');

        return h(Empty, {
            icon: 'warning',
            title: text,
            compact: props.compact,
            className: cx('ks-empty--error', props.className),
            action: props.onRetry ? h(Button, { variant: 'soft', icon: 'refresh', onClick: props.onRetry }, t('retry')) : null,
        });
    }

    /** Notice({ tone: 'info'|'warning'|'danger'|'success'|'neutral', icon, title, children, action, compact }) - satir ici bilgi kutusu. */
    function Notice(props) {
        const tone = props.tone || 'info';
        const icons = { info: 'info', warning: 'warning', danger: 'alert', success: 'check-circle', neutral: 'info' };

        return h('div', { className: cx('ks-notice', 'ks-notice--' + tone, props.compact && 'ks-notice--compact', props.className), role: tone === 'danger' ? 'alert' : 'note' },
            h(Icon, { name: props.icon || icons[tone] || 'info', className: 'ks-notice__icon' }),
            h('div', { className: 'ks-notice__body' },
                props.title ? h('p', { className: 'ks-notice__title' }, props.title) : null,
                props.children ? h('div', { className: 'ks-notice__text' }, props.children) : null,
            ),
            props.action ? h('div', { className: 'ks-notice__action' }, props.action) : null,
        );
    }

    /** Card({ as = 'div', interactive, selected, padded = true, onClick, className, children }) */
    function Card(props) {
        const native = omit(props, ['as', 'interactive', 'selected', 'padded', 'className', 'children']);

        return h(props.as || 'div', Object.assign({}, native, {
            className: cx('ks-card', props.padded !== false && 'ks-card--padded', (props.interactive || props.onClick) && 'ks-card--interactive', props.selected && 'is-selected', props.className),
        }), props.children);
    }

    /** Section({ title, description, icon, actions, children, flush }) - baslikli panel. */
    function Section(props) {
        return h('section', { className: cx('ks-panel', props.flush && 'ks-panel--flush', props.className) },
            props.title || props.actions ? h('header', { className: 'ks-panel__head' },
                props.icon ? h('span', { className: 'ks-panel__icon', 'aria-hidden': 'true' }, h(Icon, { name: props.icon })) : null,
                h('div', { className: 'ks-panel__titles' },
                    props.title ? h('h3', { className: 'ks-panel__title' }, props.title) : null,
                    props.description ? h('p', { className: 'ks-panel__desc' }, props.description) : null,
                ),
                props.actions ? h('div', { className: 'ks-panel__actions' }, props.actions) : null,
            ) : null,
            h('div', { className: 'ks-panel__body' }, props.children),
        );
    }

    /** Stat({ label, value, hint, icon, tone: palet adi, onClick, trend }) - KPI kutusu. */
    function Stat(props) {
        const tag = props.onClick ? 'button' : 'div';

        return h(tag, {
            type: props.onClick ? 'button' : undefined,
            onClick: props.onClick,
            className: cx('ks-stat', props.tone && 'ks-c-' + paletteColor(props.tone), props.onClick && 'ks-stat--interactive', props.className),
        },
            props.icon ? h('span', { className: 'ks-stat__icon', 'aria-hidden': 'true' }, h(Icon, { name: props.icon })) : null,
            h('span', { className: 'ks-stat__body' },
                h('span', { className: 'ks-stat__label' }, props.label),
                h('span', { className: 'ks-stat__value' }, props.value),
                props.hint ? h('span', { className: 'ks-stat__hint' }, props.hint) : null,
            ),
        );
    }

    function Divider(props) {
        return props && props.label
            ? h('div', { className: 'ks-divider ks-divider--label', role: 'separator' }, h('span', null, props.label))
            : h('hr', { className: 'ks-divider' });
    }

    /** LoadMore({ onClick, loading, hasMore, label }) - "Daha fazla" sayfalama dugmesi. */
    function LoadMore(props) {
        if (!props.hasMore) {
            return null;
        }

        return h('div', { className: 'ks-load-more' },
            h(Button, { variant: 'soft', loading: props.loading, onClick: props.onClick, iconRight: 'chevron-down' }, props.label || t('load_more')));
    }

    /** CopyButton({ text | getText(), label, variant, size, icon, successText }) - panoya kopyalar ve bildirim gosterir. */
    function CopyButton(props) {
        const [done, setDone] = useState(false);
        const timer = useRef(0);

        useEffect(() => () => window.clearTimeout(timer.current), []);

        const onClick = () => {
            const value = typeof props.getText === 'function' ? props.getText() : props.text;

            copyText(value).then((ok) => {
                if (ok) {
                    setDone(true);
                    window.clearTimeout(timer.current);
                    timer.current = window.setTimeout(() => setDone(false), 1600);
                    toast.success(props.successText || t('copied'));
                } else {
                    toast.error(t('copy_failed'));
                }
            });
        };

        return h(Button, { variant: props.variant || 'ghost', size: props.size || 'sm', icon: done ? 'check' : (props.icon || 'copy'), onClick, disabled: props.disabled, className: props.className }, props.label === undefined ? t('copy') : props.label);
    }

    /* ================================================================== */
    /* 10. Kaplamalar: Modal, Drawer, Overlay, Popover, Menu               */
    /* ================================================================== */

    function useBackdropClose(enabled, onClose) {
        const pressed = useRef(false);

        return {
            // Metin secerken disariya surukleme kaplamayi kapatmasin: basma ve birakma ayni yerde olmali.
            onMouseDown: (event) => {
                pressed.current = event.target === event.currentTarget;
            },
            onClick: (event) => {
                if (enabled && pressed.current && event.target === event.currentTarget) {
                    onClose('backdrop');
                }

                pressed.current = false;
            },
        };
    }

    function OverlayHead(props) {
        if (!props.title && !props.onClose && !props.actions) {
            return null;
        }

        return h('header', { className: props.block + '__head' },
            props.icon ? h('span', { className: props.block + '__icon', 'aria-hidden': 'true' }, h(Icon, { name: props.icon })) : null,
            h('div', { className: props.block + '__titles' },
                props.title ? h('h2', { id: props.titleId, className: props.block + '__title' }, props.title) : null,
                props.subtitle ? h('p', { className: props.block + '__subtitle' }, props.subtitle) : null,
            ),
            props.actions ? h('div', { className: props.block + '__actions' }, props.actions) : null,
            props.onClose ? h(IconButton, { icon: 'close', label: t('close'), variant: 'plain', onClick: () => props.onClose('button') }) : null,
        );
    }

    /**
     * Modal({ title, subtitle, icon, onClose(reason), size: 'sm'|'md'|'lg'|'xl'|'full', footer, children,
     *         dismissible = true, closeOnBackdrop = true, initialFocus, padded = true, label, role, className })
     * role=dialog + aria-modal, odak tuzagi, Escape, govde kaydirma kilidi, odagi acana geri verme.
     * < 768px: 'sm' alt sayfa (bottom sheet), digerleri tam ekran sayfa olur.
     */
    function Modal(props) {
        const ref = useRef(null);
        const titleId = useMemo(() => uid('ks-modal-title'), []);
        const dismissible = props.dismissible !== false && typeof props.onClose === 'function';
        const backdrop = useBackdropClose(dismissible && props.closeOnBackdrop !== false, props.onClose);

        useOverlay(ref, { onClose: props.onClose, dismissible, initialFocus: props.initialFocus });

        return h(Portal, null,
            h('div', Object.assign({ className: cx('ks-modal-layer', props.layerClassName) }, backdrop),
                h('div', {
                    ref,
                    className: cx('ks-modal', 'ks-modal--' + (props.size || 'md'), props.className),
                    role: props.role || 'dialog',
                    'aria-modal': 'true',
                    'aria-labelledby': props.title ? titleId : undefined,
                    'aria-label': props.title ? undefined : props.label,
                    tabIndex: -1,
                },
                    h(OverlayHead, { block: 'ks-modal', title: props.title, subtitle: props.subtitle, icon: props.icon, titleId, actions: props.headerActions, onClose: dismissible && props.hideClose !== true ? props.onClose : null }),
                    h('div', { className: cx('ks-modal__body', props.padded === false && 'ks-modal__body--flush', props.bodyClassName) }, props.children),
                    props.footer ? h('footer', { className: 'ks-modal__foot' }, props.footer) : null,
                ),
            ),
        );
    }

    /**
     * Drawer({ title, subtitle, icon, onClose, side: 'right'|'left'|'bottom', size: 'sm'|'md'|'lg', footer, children,
     *          dismissible, closeOnBackdrop, initialFocus, padded, label, className }) - Modal ile ayni davranis; < 768px tam ekran.
     */
    function Drawer(props) {
        const ref = useRef(null);
        const titleId = useMemo(() => uid('ks-drawer-title'), []);
        const dismissible = props.dismissible !== false && typeof props.onClose === 'function';
        const backdrop = useBackdropClose(dismissible && props.closeOnBackdrop !== false, props.onClose);
        const side = props.side || 'right';

        useOverlay(ref, { onClose: props.onClose, dismissible, initialFocus: props.initialFocus });

        return h(Portal, null,
            h('div', Object.assign({ className: cx('ks-drawer-layer', 'ks-drawer-layer--' + side) }, backdrop),
                h('aside', {
                    ref,
                    className: cx('ks-drawer', 'ks-drawer--' + side, 'ks-drawer--' + (props.size || 'md'), side === 'bottom' && 'ks-sheet', props.className),
                    role: 'dialog',
                    'aria-modal': 'true',
                    'aria-labelledby': props.title ? titleId : undefined,
                    'aria-label': props.title ? undefined : props.label,
                    tabIndex: -1,
                },
                    side === 'bottom' ? h('span', { className: 'ks-sheet__grip', 'aria-hidden': 'true' }) : null,
                    h(OverlayHead, { block: 'ks-drawer', title: props.title, subtitle: props.subtitle, icon: props.icon, titleId, actions: props.headerActions, onClose: dismissible ? props.onClose : null }),
                    h('div', { className: cx('ks-drawer__body', props.padded === false && 'ks-drawer__body--flush', props.bodyClassName) }, props.children),
                    props.footer ? h('footer', { className: 'ks-drawer__foot' }, props.footer) : null,
                ),
            ),
        );
    }

    /**
     * Overlay({ title, subtitle, leading, actions, onClose, footer, children, label, closeLabel, closeIcon = 'back',
     *           dismissible = true, bar = true, className, bodyClassName }) - tam ekran kaplama (Ayrinti, Olusturucu).
     * Govde duzeni icin: bodyClassName 'ks-overlay__body--split' + cocuklarda .ks-overlay__stage ve .ks-overlay__side.
     */
    function Overlay(props) {
        const ref = useRef(null);
        const dismissible = props.dismissible !== false && typeof props.onClose === 'function';

        useOverlay(ref, { onClose: props.onClose, dismissible, initialFocus: props.initialFocus });

        return h(Portal, null,
            h('div', {
                ref,
                className: cx('ks-overlay', props.className),
                role: 'dialog',
                'aria-modal': 'true',
                'aria-label': props.label || (typeof props.title === 'string' ? props.title : undefined),
                tabIndex: -1,
            },
                props.bar === false ? null : h('header', { className: 'ks-overlay__bar' },
                    typeof props.onClose === 'function' ? h(IconButton, { icon: props.closeIcon || 'back', label: props.closeLabel || t('close'), onClick: () => props.onClose('button'), className: 'ks-overlay__close' }) : null,
                    props.leading || null,
                    h('div', { className: 'ks-overlay__titles' },
                        props.title ? h('h2', { className: 'ks-overlay__title' }, props.title) : null,
                        props.subtitle ? h('p', { className: 'ks-overlay__subtitle' }, props.subtitle) : null,
                    ),
                    props.actions ? h('div', { className: 'ks-overlay__actions' }, props.actions) : null,
                ),
                h('div', { className: cx('ks-overlay__body', props.bodyClassName) }, props.children),
                props.footer ? h('footer', { className: 'ks-overlay__footer' }, props.footer) : null,
            ),
        );
    }

    /**
     * Popover({ trigger: (triggerProps, { open, close }) => eleman, children | (close) => eleman,
     *           align: 'start'|'end', placement: 'bottom'|'top', width, label, role, className, onOpenChange })
     * triggerProps = { onClick, 'aria-expanded', 'aria-haspopup' } -> tetikleyici dugmeye yayin.
     * Disari tiklama ve Escape kapatir; ekrana sigmazsa yon/hiza kendiliginden cevrilir.
     */
    function Popover(props) {
        const [open, setOpen] = useState(false);
        const [flip, setFlip] = useState({ placement: null, align: null });
        const wrapRef = useRef(null);
        const panelRef = useRef(null);

        const change = useCallback((next) => {
            setOpen(next);

            if (typeof props.onOpenChange === 'function') {
                props.onOpenChange(next);
            }
        }, [props.onOpenChange]);

        const close = useCallback((options) => {
            change(false);

            if (!(options && options.keepFocus === false) && wrapRef.current) {
                focusQuietly(wrapRef.current.querySelector('button, a[href], [tabindex]'));
            }
        }, [change]);

        useClickOutside(wrapRef, () => change(false), open);
        useOverlay(panelRef, { active: open, modal: false, onClose: () => close() });

        useLayoutEffect(() => {
            if (!open || !panelRef.current || !wrapRef.current) {
                setFlip((current) => (current.placement || current.align ? { placement: null, align: null } : current));

                return;
            }

            const panel = panelRef.current.getBoundingClientRect();
            const anchor = wrapRef.current.getBoundingClientRect();
            const next = { placement: null, align: null };

            if (panel.bottom > window.innerHeight - 8 && anchor.top > window.innerHeight - anchor.bottom) {
                next.placement = 'top';
            } else if (panel.top < 8) {
                next.placement = 'bottom';
            }

            if (panel.right > window.innerWidth - 8) {
                next.align = 'end';
            } else if (panel.left < 8) {
                next.align = 'start';
            }

            if (next.placement || next.align) {
                setFlip(next);
            }
        }, [open]);

        const triggerProps = {
            onClick: () => change(!open),
            'aria-expanded': open ? 'true' : 'false',
            'aria-haspopup': props.role === 'menu' ? 'menu' : 'dialog',
        };
        const placement = flip.placement || props.placement || 'bottom';
        const align = flip.align || props.align || 'start';

        return h('span', { ref: wrapRef, className: cx('ks-pop', props.block && 'ks-pop--block', props.className) },
            props.trigger(triggerProps, { open, close }),
            open ? h('div', {
                ref: panelRef,
                className: cx('ks-pop__panel', 'ks-pop__panel--' + placement, 'ks-pop__panel--' + align, props.panelClassName),
                role: props.role || 'dialog',
                'aria-label': props.label,
                style: props.width ? { width: typeof props.width === 'number' ? props.width + 'px' : props.width } : undefined,
            }, typeof props.children === 'function' ? props.children(close) : props.children) : null,
        );
    }

    function MenuList(props) {
        const ref = useRef(null);

        const buttons = () => Array.prototype.slice.call(ref.current ? ref.current.querySelectorAll('[role="menuitem"]:not([disabled])') : []);

        useEffect(() => {
            focusQuietly(buttons()[0]);
        }, []);

        const onKeyDown = (event) => {
            const list = buttons();
            const index = list.indexOf(document.activeElement);
            let next = null;

            if (event.key === 'ArrowDown') {
                next = list[(index + 1) % list.length];
            } else if (event.key === 'ArrowUp') {
                next = list[(index - 1 + list.length) % list.length];
            } else if (event.key === 'Home') {
                next = list[0];
            } else if (event.key === 'End') {
                next = list[list.length - 1];
            } else if (event.key === 'Tab') {
                props.close({ keepFocus: false });

                return;
            }

            if (next) {
                event.preventDefault();
                focusQuietly(next);
            }
        };

        return h('div', { ref, className: 'ks-menu', onKeyDown },
            props.items.filter(Boolean).map((item, index) => {
                if (item.divider) {
                    return h('div', { key: 'd' + index, className: 'ks-menu__divider', role: 'separator' });
                }

                if (item.heading) {
                    return h('div', { key: 'h' + index, className: 'ks-menu__heading' }, item.heading);
                }

                return h('button', {
                    key: item.key || index,
                    type: 'button',
                    role: 'menuitem',
                    className: cx('ks-menu__item', item.danger && 'ks-menu__item--danger', item.active && 'is-active'),
                    disabled: item.disabled,
                    title: item.title,
                    onClick: () => {
                        props.close();

                        if (typeof item.onSelect === 'function') {
                            item.onSelect(item);
                        }

                        if (typeof props.onSelect === 'function') {
                            props.onSelect(item);
                        }
                    },
                },
                    item.platform ? h(PlatformIcon, { platform: item.platform }) : (item.icon ? h(Icon, { name: item.icon }) : null),
                    h('span', { className: 'ks-menu__text' },
                        h('span', { className: 'ks-menu__label' }, item.label),
                        item.hint ? h('span', { className: 'ks-menu__hint' }, item.hint) : null,
                    ),
                    item.active ? h(Icon, { name: 'check', className: 'ks-menu__check' }) : null,
                );
            }),
        );
    }

    /**
     * Menu({ trigger, items, align = 'end', placement, label, onSelect, width })
     * trigger: Button ozellikleri nesnesi ({ label, icon, variant, size, iconOnly, iconRight }) ya da Popover'daki gibi islev.
     * items: [{ key, label, hint, icon | platform, onSelect(item), danger, disabled, active, title } | { divider: true } | { heading: 'Baslik' }]
     * role=menu; ok tuslari, Home/End, Escape.
     */
    function Menu(props) {
        const trigger = typeof props.trigger === 'function'
            ? props.trigger
            : (triggerProps) => {
                const spec = props.trigger || {};

                if (spec.iconOnly) {
                    return h(IconButton, Object.assign({ icon: spec.icon || 'more', label: spec.label || props.label, variant: spec.variant, size: spec.size, disabled: spec.disabled }, triggerProps));
                }

                return h(Button, Object.assign({ icon: spec.icon, iconRight: spec.iconRight === undefined ? 'chevron-down' : spec.iconRight, variant: spec.variant, size: spec.size, disabled: spec.disabled }, triggerProps), spec.label);
            };

        return h(Popover, { trigger, role: 'menu', label: props.label, align: props.align || 'end', placement: props.placement, width: props.width, className: props.className, panelClassName: 'ks-pop__panel--menu' },
            (close) => h(MenuList, { items: props.items || [], close, onSelect: props.onSelect }));
    }

    /* ================================================================== */
    /* 11. Sekmeler ve secim denetimleri                                   */
    /* ================================================================== */

    function rovingKeyDown(event, items, value, onChange, container) {
        const enabled = items.filter((item) => !item.disabled);
        const index = enabled.findIndex((item) => String(item.value) === String(value));
        let next = null;

        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            next = enabled[(index + 1) % enabled.length];
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            next = enabled[(index - 1 + enabled.length) % enabled.length];
        } else if (event.key === 'Home') {
            next = enabled[0];
        } else if (event.key === 'End') {
            next = enabled[enabled.length - 1];
        }

        if (!next) {
            return;
        }

        event.preventDefault();

        if (String(next.value) !== String(value) && typeof onChange === 'function') {
            onChange(next.value);
        }

        if (container) {
            const target = Array.prototype.find.call(container.querySelectorAll('[data-value]'), (node) => node.getAttribute('data-value') === String(next.value));

            focusQuietly(target);
        }
    }

    /**
     * Tabs({ items: [{ value, label, icon, count, disabled, title }], value, onChange(value), variant: 'underline'|'pill',
     *        label (aria), stretch, className }) - role=tablist, ok tuslari ile gezinme.
     */
    function Tabs(props) {
        const ref = useRef(null);
        const items = (props.items || []).filter(Boolean);
        const hasActive = items.some((item) => String(item.value) === String(props.value));

        return h('div', {
            ref,
            className: cx('ks-tabs', 'ks-tabs--' + (props.variant || 'underline'), props.stretch && 'ks-tabs--stretch', props.className),
            role: 'tablist',
            'aria-label': props.label,
            onKeyDown: (event) => rovingKeyDown(event, items, props.value, props.onChange, ref.current),
        }, items.map((item, index) => {
            const active = String(item.value) === String(props.value);

            return h('button', {
                key: String(item.value),
                type: 'button',
                role: 'tab',
                'aria-selected': active ? 'true' : 'false',
                tabIndex: active || (!hasActive && index === 0) ? 0 : -1,
                disabled: item.disabled,
                title: item.title,
                'data-value': String(item.value),
                className: cx('ks-tab', active && 'is-active'),
                onClick: () => props.onChange && props.onChange(item.value),
            },
                item.icon ? h(Icon, { name: item.icon }) : null,
                h('span', { className: 'ks-tab__label' }, item.label),
                item.count !== undefined && item.count !== null ? h('span', { className: cx('ks-tab__count', item.countTone && 'ks-c-' + paletteColor(item.countTone)) }, fmt.number(item.count)) : null,
            );
        }));
    }

    /**
     * Segmented({ items: [{ value, label, icon, title, disabled }], value, onChange(value), size: 'sm'|'md', label (aria),
     *             block, iconOnly }) - role=radiogroup.
     */
    function Segmented(props) {
        const ref = useRef(null);
        const items = (props.items || []).filter(Boolean);
        const hasActive = items.some((item) => String(item.value) === String(props.value));

        return h('div', {
            ref,
            className: cx('ks-segmented', props.size === 'sm' && 'ks-segmented--sm', props.block && 'ks-segmented--block', props.className),
            role: 'radiogroup',
            'aria-label': props.label,
            onKeyDown: (event) => rovingKeyDown(event, items, props.value, props.onChange, ref.current),
        }, items.map((item, index) => {
            const active = String(item.value) === String(props.value);

            return h('button', {
                key: String(item.value),
                type: 'button',
                role: 'radio',
                'aria-checked': active ? 'true' : 'false',
                'aria-label': props.iconOnly ? item.label : undefined,
                tabIndex: active || (!hasActive && index === 0) ? 0 : -1,
                disabled: item.disabled,
                title: item.title || (props.iconOnly ? item.label : undefined),
                'data-value': String(item.value),
                className: cx('ks-segmented__item', active && 'is-active'),
                onClick: () => props.onChange && props.onChange(item.value),
            },
                item.icon ? h(Icon, { name: item.icon }) : null,
                props.iconOnly ? null : h('span', null, item.label),
            );
        }));
    }

    /* ================================================================== */
    /* 12. Form denetimleri                                                */
    /* ================================================================== */

    /**
     * Field({ label, hint, error, required, htmlFor, group, counter, children, className })
     * Tek cocuk varsa id / aria-describedby / aria-invalid ona aktarilir ve <label for> baglanir.
     * group = true: cip kumeleri gibi coklu denetimler icin role=group + aria-labelledby.
     */
    function Field(props) {
        const autoId = useMemo(() => uid('ks-field'), []);
        const only = React.Children.count(props.children) === 1 && React.isValidElement(props.children) && props.children.type !== Fragment ? props.children : null;
        const controlId = props.htmlFor || (only && only.props.id) || autoId;
        const labelId = controlId + '-label';
        const hintId = controlId + '-hint';
        const errorId = controlId + '-error';
        const describedBy = cx(props.error && errorId, props.hint && hintId) || undefined;
        let control = props.children;

        if (only && !props.group) {
            control = React.cloneElement(only, {
                id: controlId,
                'aria-describedby': cx(only.props['aria-describedby'], describedBy) || undefined,
                'aria-invalid': props.error ? 'true' : only.props['aria-invalid'],
                'aria-required': props.required ? 'true' : only.props['aria-required'],
            });
        }

        const labelContent = props.label ? [
            props.label,
            props.required ? h('span', { key: 'req', className: 'ks-field__required', title: t('required'), 'aria-hidden': 'true' }, ' *') : null,
        ] : null;

        return h('div', {
            className: cx('ks-field', props.error && 'has-error', props.className),
            role: props.group ? 'group' : undefined,
            'aria-labelledby': props.group && props.label ? labelId : undefined,
        },
            props.label || props.counter ? h('div', { className: 'ks-field__head' },
                props.label ? (props.group
                    ? h('span', { id: labelId, className: 'ks-field__label' }, labelContent)
                    : h('label', { htmlFor: controlId, className: 'ks-field__label' }, labelContent)) : h('span', null),
                props.counter ? h('span', { className: 'ks-field__counter' }, props.counter) : null,
            ) : null,
            h('div', { className: 'ks-field__control' }, control),
            props.error ? h('p', { id: errorId, className: 'ks-field__error', role: 'alert' }, h(Icon, { name: 'alert' }), h('span', null, props.error)) : null,
            props.hint ? h('p', { id: hintId, className: 'ks-field__hint' }, props.hint) : null,
        );
    }

    const INPUT_OWN_PROPS = ['value', 'onChange', 'icon', 'clearable', 'invalid', 'size', 'onEnter', 'inputRef', 'className', 'wrapClassName', 'trailing', 'autoGrow', 'maxRows', 'counter', 'softLimit', 'options', 'placeholder'];

    function isInvalid(props) {
        return !!props.invalid || props['aria-invalid'] === 'true' || props['aria-invalid'] === true;
    }

    /**
     * TextInput({ value, onChange(value, event), type = 'text', placeholder, icon, clearable, invalid, size: 'sm'|'md'|'lg',
     *             disabled, onEnter(value, event), inputRef, trailing, ...input ozellikleri })
     */
    function TextInput(props) {
        const native = omit(props, INPUT_OWN_PROPS);
        const value = props.value === undefined || props.value === null ? '' : props.value;
        const invalid = isInvalid(props);

        return h('span', { className: cx('ks-input-wrap', 'ks-input-wrap--' + (props.size || 'md'), props.icon && 'has-icon', invalid && 'is-invalid', props.disabled && 'is-disabled', props.wrapClassName) },
            props.icon ? h(Icon, { name: props.icon, className: 'ks-input-wrap__icon' }) : null,
            h('input', Object.assign({ type: 'text', autoComplete: 'off' }, native, {
                ref: props.inputRef,
                className: cx('ks-input', props.className),
                value,
                placeholder: props.placeholder,
                'aria-invalid': invalid ? 'true' : undefined,
                onChange: (event) => props.onChange && props.onChange(event.target.value, event),
                onKeyDown: (event) => {
                    if (typeof native.onKeyDown === 'function') {
                        native.onKeyDown(event);
                    }

                    if (event.key === 'Enter' && !event.isComposing && typeof props.onEnter === 'function') {
                        props.onEnter(event.target.value, event);
                    }
                },
            })),
            props.clearable && value !== '' && !props.disabled
                ? h('button', { type: 'button', className: 'ks-input-wrap__clear', 'aria-label': t('clear'), title: t('clear'), onClick: () => props.onChange && props.onChange('', null) }, h(Icon, { name: 'close' }))
                : null,
            props.trailing || null,
        );
    }

    /**
     * TextArea({ value, onChange(value, event), rows = 3, autoGrow, maxRows = 16, maxLength, softLimit, counter, invalid,
     *            inputRef, ...textarea ozellikleri }) - counter: "12 / 280"; softLimit asilinca uyari, maxLength'te kirmizi.
     */
    function TextArea(props) {
        const native = omit(props, INPUT_OWN_PROPS);
        const innerRef = useRef(null);
        const value = props.value === undefined || props.value === null ? '' : String(props.value);
        const invalid = isInvalid(props);
        const limit = props.softLimit || props.maxLength || null;
        const over = props.softLimit ? value.length > props.softLimit : false;
        const full = props.maxLength ? value.length >= props.maxLength : false;

        const setRef = (node) => {
            innerRef.current = node;

            if (typeof props.inputRef === 'function') {
                props.inputRef(node);
            } else if (props.inputRef && typeof props.inputRef === 'object') {
                props.inputRef.current = node;
            }
        };

        useLayoutEffect(() => {
            const node = innerRef.current;

            if (!props.autoGrow || !node) {
                return;
            }

            const style = window.getComputedStyle(node);
            const line = parseFloat(style.lineHeight) || 20;
            const extra = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom) + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
            const max = line * (props.maxRows || 16) + extra;

            node.style.height = 'auto';
            node.style.height = Math.min(node.scrollHeight + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth), max) + 'px';
            node.style.overflowY = node.scrollHeight > max ? 'auto' : 'hidden';
        }, [value, props.autoGrow, props.maxRows]);

        return h('span', { className: cx('ks-textarea-wrap', invalid && 'is-invalid', props.wrapClassName) },
            h('textarea', Object.assign({ rows: 3 }, native, {
                ref: setRef,
                className: cx('ks-textarea', props.autoGrow && 'ks-textarea--grow', props.className),
                value,
                placeholder: props.placeholder,
                'aria-invalid': invalid ? 'true' : undefined,
                onChange: (event) => props.onChange && props.onChange(event.target.value, event),
            })),
            props.counter && limit
                ? h('span', { className: cx('ks-textarea-wrap__counter', over && 'is-over', full && 'is-full'), 'aria-live': 'off', title: t('character_count', { count: fmt.number(value.length), max: fmt.number(limit) }) },
                    fmt.number(value.length) + ' / ' + fmt.number(limit))
                : (props.counter ? h('span', { className: 'ks-textarea-wrap__counter' }, fmt.number(value.length)) : null),
        );
    }

    /**
     * Select({ options: [{ value, label, disabled }] | [{ label, options: [...] }], value, onChange(value, event), placeholder,
     *          clearable = true, invalid, size, disabled, ...select ozellikleri }) - yerel <select>; onChange secenegin ozgun
     * (sayi/metin) degerini verir, bos secim ''.
     */
    function Select(props) {
        const native = omit(props, INPUT_OWN_PROPS.concat(['clearable']));
        const groups = props.options || [];
        const flat = [];

        groups.forEach((item) => {
            if (item && Array.isArray(item.options)) {
                item.options.forEach((child) => flat.push(child));
            } else if (item) {
                flat.push(item);
            }
        });

        const current = props.value === undefined || props.value === null ? '' : String(props.value);
        const invalid = isInvalid(props);
        const option = (item) => h('option', { key: String(item.value), value: String(item.value), disabled: item.disabled }, item.label);

        return h('span', { className: cx('ks-select-wrap', 'ks-input-wrap--' + (props.size || 'md'), invalid && 'is-invalid', props.disabled && 'is-disabled', current === '' && 'is-empty', props.wrapClassName) },
            props.icon ? h(Icon, { name: props.icon, className: 'ks-input-wrap__icon' }) : null,
            h('select', Object.assign({}, native, {
                ref: props.inputRef,
                className: cx('ks-select', props.icon && 'has-icon', props.className),
                value: current,
                'aria-invalid': invalid ? 'true' : undefined,
                onChange: (event) => {
                    const found = flat.find((item) => String(item.value) === event.target.value);

                    if (props.onChange) {
                        props.onChange(found ? found.value : '', event);
                    }
                },
            }),
                props.placeholder !== false ? h('option', { value: '', disabled: props.clearable === false && current !== '' }, props.placeholder || t('select_placeholder')) : null,
                groups.map((item, index) => (item && Array.isArray(item.options)
                    ? h('optgroup', { key: 'g' + index, label: item.label }, item.options.map(option))
                    : (item ? option(item) : null))),
            ),
            h(Icon, { name: 'chevron-down', className: 'ks-select-wrap__chevron' }),
        );
    }

    /**
     * DateInput({ value: 'Y-m-d' | '', onChange(value), min, max, clearable = true, placeholder, invalid, disabled, size })
     * Yerel tarih secici acilir; gorunen metin her tarayicida dd.mm.yyyy'dir (yerel giris seffaf olarak ustte durur).
     */
    function DateInput(props) {
        const native = omit(props, INPUT_OWN_PROPS.concat(['clearable', 'min', 'max']));
        const ref = useRef(null);
        const value = /^\d{4}-\d{2}-\d{2}$/.test(props.value || '') ? props.value : '';
        const invalid = isInvalid(props);

        const openPicker = () => {
            const node = ref.current;

            if (!node || props.disabled) {
                return;
            }

            try {
                if (typeof node.showPicker === 'function') {
                    node.showPicker();
                } else {
                    node.focus();
                }
            } catch (error) {
                node.focus();
            }
        };

        return h('span', { className: cx('ks-date', 'ks-input-wrap--' + (props.size || 'md'), value && 'has-value', invalid && 'is-invalid', props.disabled && 'is-disabled', props.wrapClassName) },
            h(Icon, { name: 'calendar', className: 'ks-date__icon' }),
            h('span', { className: cx('ks-date__text', !value && 'is-placeholder'), 'aria-hidden': 'true' }, value ? fmt.date(value) : (props.placeholder || t('pick_date'))),
            h('input', Object.assign({}, native, {
                ref,
                type: 'date',
                className: 'ks-date__native',
                value,
                min: props.min || undefined,
                max: props.max || undefined,
                'aria-invalid': invalid ? 'true' : undefined,
                onChange: (event) => props.onChange && props.onChange(event.target.value || '', event),
                onClick: openPicker,
                onKeyDown: (event) => {
                    if (event.key === ' ' || event.key === 'Enter') {
                        event.preventDefault();
                        openPicker();
                    }
                },
            })),
            value && props.clearable !== false && !props.disabled
                ? h('button', { type: 'button', className: 'ks-date__clear', 'aria-label': t('clear'), title: t('clear'), onClick: () => props.onChange && props.onChange('', null) }, h(Icon, { name: 'close' }))
                : null,
        );
    }

    /** TimeInput({ value: 'HH:MM' | '', onChange(value), step, clearable = true, invalid, disabled, size }) - yerel saat girisi. */
    function TimeInput(props) {
        const native = omit(props, INPUT_OWN_PROPS.concat(['clearable']));
        const value = /^\d{2}:\d{2}/.test(props.value || '') ? String(props.value).slice(0, 5) : '';
        const invalid = isInvalid(props);

        return h('span', { className: cx('ks-input-wrap', 'ks-input-wrap--' + (props.size || 'md'), 'ks-time', 'has-icon', invalid && 'is-invalid', props.disabled && 'is-disabled', props.wrapClassName) },
            h(Icon, { name: 'clock', className: 'ks-input-wrap__icon' }),
            h('input', Object.assign({ step: 300 }, native, {
                type: 'time',
                className: cx('ks-input', props.className),
                value,
                'aria-invalid': invalid ? 'true' : undefined,
                onChange: (event) => props.onChange && props.onChange((event.target.value || '').slice(0, 5), event),
            })),
            value && props.clearable !== false && !props.disabled
                ? h('button', { type: 'button', className: 'ks-input-wrap__clear', 'aria-label': t('clear'), title: t('clear'), onClick: () => props.onChange && props.onChange('', null) }, h(Icon, { name: 'close' }))
                : null,
        );
    }

    /** Checkbox({ checked, onChange(checked, event), label, hint, disabled, indeterminate }) */
    function Checkbox(props) {
        const ref = useRef(null);
        const native = omit(props, ['checked', 'onChange', 'label', 'hint', 'indeterminate', 'className', 'children']);

        useEffect(() => {
            if (ref.current) {
                ref.current.indeterminate = !!props.indeterminate;
            }
        }, [props.indeterminate]);

        return h('label', { className: cx('ks-check', props.disabled && 'is-disabled', props.className) },
            h('input', Object.assign({}, native, {
                ref,
                type: 'checkbox',
                className: 'ks-check__input',
                checked: !!props.checked,
                onChange: (event) => props.onChange && props.onChange(event.target.checked, event),
            })),
            h('span', { className: 'ks-check__box', 'aria-hidden': 'true' }, h(Icon, { name: props.indeterminate ? 'minus' : 'check' })),
            props.label || props.children || props.hint ? h('span', { className: 'ks-check__text' },
                h('span', { className: 'ks-check__label' }, props.label || props.children),
                props.hint ? h('span', { className: 'ks-check__hint' }, props.hint) : null,
            ) : null,
        );
    }

    /** Switch({ checked, onChange(checked), label, hint, disabled }) - role=switch. */
    function Switch(props) {
        const id = useMemo(() => uid('ks-switch'), []);

        return h('span', { className: cx('ks-switch', props.disabled && 'is-disabled', props.className) },
            h('button', {
                type: 'button',
                id: props.id || id,
                role: 'switch',
                'aria-checked': props.checked ? 'true' : 'false',
                'aria-label': props.label ? undefined : props.ariaLabel,
                'aria-describedby': props['aria-describedby'],
                disabled: props.disabled,
                className: cx('ks-switch__track', props.checked && 'is-on'),
                onClick: () => props.onChange && props.onChange(!props.checked),
            }, h('span', { className: 'ks-switch__thumb', 'aria-hidden': 'true' })),
            props.label || props.hint ? h('label', { htmlFor: props.id || id, className: 'ks-switch__text' },
                h('span', { className: 'ks-switch__label' }, props.label),
                props.hint ? h('span', { className: 'ks-switch__hint' }, props.hint) : null,
            ) : null,
        );
    }

    /**
     * Dropzone({ accept, multiple, onFiles(File[]), disabled, icon = 'upload', title, text, hint, compact, paste, children })
     * Surukle-birak + tikla-sec (+ paste = true iken panodan yapistirma). Dosya denetimi (tur/boyut) cagiranin isidir.
     */
    function Dropzone(props) {
        const inputRef = useRef(null);
        const [over, setOver] = useState(false);
        const depth = useRef(0);
        const onFilesRef = useRef(props.onFiles);

        onFilesRef.current = props.onFiles;

        const emit = (list) => {
            const files = Array.prototype.slice.call(list || []);

            if (files.length && typeof onFilesRef.current === 'function') {
                onFilesRef.current(props.multiple ? files : files.slice(0, 1));
            }
        };

        useEffect(() => {
            if (!props.paste || props.disabled) {
                return undefined;
            }

            const onPaste = (event) => {
                const target = event.target;

                // Metin alanina yapistirma dosya yuklemesi sayilmaz.
                if (target && (target.isContentEditable || /^(INPUT|TEXTAREA)$/.test(target.nodeName))) {
                    return;
                }

                const files = event.clipboardData ? event.clipboardData.files : null;

                if (files && files.length) {
                    event.preventDefault();
                    emit(files);
                }
            };

            document.addEventListener('paste', onPaste);

            return () => document.removeEventListener('paste', onPaste);
        }, [props.paste, props.disabled, props.multiple]);

        const stop = (event) => {
            event.preventDefault();
            event.stopPropagation();
        };

        return h('div', {
            className: cx('ks-dropzone', over && 'is-over', props.compact && 'ks-dropzone--compact', props.disabled && 'is-disabled', props.className),
            onDragEnter: (event) => {
                stop(event);

                if (!props.disabled) {
                    depth.current += 1;
                    setOver(true);
                }
            },
            onDragOver: stop,
            onDragLeave: (event) => {
                stop(event);
                depth.current = Math.max(0, depth.current - 1);

                if (depth.current === 0) {
                    setOver(false);
                }
            },
            onDrop: (event) => {
                stop(event);
                depth.current = 0;
                setOver(false);

                if (!props.disabled && event.dataTransfer) {
                    emit(event.dataTransfer.files);
                }
            },
        },
            h('input', {
                ref: inputRef,
                type: 'file',
                className: 'ks-sr-only',
                tabIndex: -1,
                'aria-hidden': 'true',
                accept: Array.isArray(props.accept) ? props.accept.join(',') : props.accept,
                multiple: !!props.multiple,
                disabled: props.disabled,
                onChange: (event) => {
                    emit(event.target.files);
                    event.target.value = '';
                },
            }),
            h('button', { type: 'button', className: 'ks-dropzone__button', disabled: props.disabled, onClick: () => inputRef.current && inputRef.current.click() },
                h('span', { className: 'ks-dropzone__icon', 'aria-hidden': 'true' }, h(Icon, { name: props.icon || 'upload' })),
                h('span', { className: 'ks-dropzone__title' }, props.title || t('dropzone_title')),
                props.text === false ? null : h('span', { className: 'ks-dropzone__text' }, props.text || t('dropzone_text')),
                props.hint ? h('span', { className: 'ks-dropzone__hint' }, props.hint) : null,
            ),
            props.children || null,
        );
    }

    /* ================================================================== */
    /* 13. Bildirimler (toast), soz veren pencereler, hata esleme          */
    /* ================================================================== */

    const toastStore = createStore({ items: [] });
    const dialogStore = createStore({ items: [] });
    let serviceMounted = false;

    /*
     * Bildirim ve onay pencereleri cekirdegin kendi kokunde (body sonu, #ks-service-root)
     * cizilir; boylece gorunum dosyalarinin bir "host" baglamasi gerekmez ve uygulama
     * kabugu hataya dusse bile mesajlar gorunur.
     */
    function ensureServiceRoot() {
        if (serviceMounted) {
            return;
        }

        serviceMounted = true;

        const node = document.createElement('div');

        node.id = 'ks-service-root';
        node.className = 'ks-root';
        document.body.appendChild(node);
        ReactDOM.createRoot(node).render(h(ServiceHost));
    }

    const TOAST_ICONS = { success: 'check-circle', error: 'alert', info: 'info', warning: 'warning' };

    function showToast(type, text, options) {
        const opts = options || {};
        const item = {
            id: uid('toast'),
            type: TOAST_ICONS[type] ? type : 'info',
            text: String(text === undefined || text === null ? '' : text),
            title: opts.title || null,
            action: opts.action && typeof opts.action.onClick === 'function' ? opts.action : null,
            duration: opts.duration !== undefined ? opts.duration : (type === 'error' || type === 'warning' ? 7000 : 4000),
        };

        if (item.text === '' && !item.title) {
            return null;
        }

        ensureServiceRoot();

        // Ayni mesaj ust uste gelirse tek bildirim kalir; en cok dort bildirim gorunur.
        toastStore.setState((state) => ({
            items: state.items.filter((other) => !(other.type === item.type && other.text === item.text)).concat([item]).slice(-4),
        }));

        return item.id;
    }

    /**
     * KS.toast.success(metin, secenek) / .error / .info / .warning / .show({ type, text, ... }) -> id
     * secenek: { title, duration (ms; 0 = kalici), action: { label, onClick } }. KS.toast.dismiss(id), KS.toast.clear().
     */
    const toast = {
        show: (options) => showToast((options && options.type) || 'info', options && options.text, options),
        success: (text, options) => showToast('success', text, options),
        error: (text, options) => showToast('error', text, options),
        info: (text, options) => showToast('info', text, options),
        warning: (text, options) => showToast('warning', text, options),
        dismiss: (id) => toastStore.setState((state) => ({ items: state.items.filter((item) => item.id !== id) })),
        clear: () => toastStore.setState({ items: [] }),
    };

    function ToastItem(props) {
        const item = props.item;
        const [paused, setPaused] = useState(false);

        useEffect(() => {
            if (!item.duration || paused) {
                return undefined;
            }

            const timer = window.setTimeout(() => toast.dismiss(item.id), item.duration);

            return () => window.clearTimeout(timer);
        }, [item.id, item.duration, paused]);

        return h('div', {
            className: cx('ks-toast', 'ks-toast--' + item.type),
            role: item.type === 'error' ? 'alert' : 'status',
            onMouseEnter: () => setPaused(true),
            onMouseLeave: () => setPaused(false),
            onFocus: () => setPaused(true),
            onBlur: () => setPaused(false),
        },
            h(Icon, { name: TOAST_ICONS[item.type], className: 'ks-toast__icon' }),
            h('div', { className: 'ks-toast__body' },
                item.title ? h('p', { className: 'ks-toast__title' }, item.title) : null,
                item.text ? h('p', { className: 'ks-toast__text' }, item.text) : null,
            ),
            item.action ? h('button', {
                type: 'button',
                className: 'ks-toast__action',
                onClick: () => {
                    toast.dismiss(item.id);
                    item.action.onClick();
                },
            }, item.action.label) : null,
            h('button', { type: 'button', className: 'ks-toast__close', 'aria-label': t('close'), onClick: () => toast.dismiss(item.id) }, h(Icon, { name: 'close' })),
        );
    }

    function ToastViewport() {
        const items = useStoreOf(toastStore, (state) => state.items);

        return h('div', { className: 'ks-toasts', 'aria-live': 'polite', 'aria-relevant': 'additions' },
            items.map((item) => h(ToastItem, { key: item.id, item })));
    }

    /** Geriye uyumluluk: bildirimler cekirdek kokunde cizildigi icin bu bilesen bir sey cizmez; baglamak zararsizdir. */
    function ToastHost() {
        useEffect(() => ensureServiceRoot(), []);

        return null;
    }

    function openDialog(kind, options) {
        ensureServiceRoot();

        return new Promise((resolve) => {
            const item = { id: uid('dialog'), kind, options: options || {}, resolve };

            dialogStore.setState((state) => ({ items: state.items.concat([item]) }));
        });
    }

    function settleDialog(item, value) {
        dialogStore.setState((state) => ({ items: state.items.filter((other) => other.id !== item.id) }));
        item.resolve(value);
    }

    function ConfirmDialog(props) {
        const item = props.item;
        const options = item.options;
        const danger = !!options.danger;

        return h(Modal, {
            title: options.title || t('confirm_title'),
            icon: options.icon || (danger ? 'warning' : undefined),
            size: 'sm',
            role: 'alertdialog',
            layerClassName: 'ks-modal-layer--dialog',
            className: cx('ks-dialog', danger && 'ks-dialog--danger'),
            onClose: () => settleDialog(item, false),
            footer: [
                options.hideCancel ? null : h(Button, Object.assign({ key: 'cancel', variant: 'ghost', onClick: () => settleDialog(item, false) }, danger ? { 'data-autofocus': '' } : {}), options.cancelLabel || t('cancel')),
                h(Button, Object.assign({ key: 'ok', variant: danger ? 'danger' : 'primary', icon: options.confirmIcon, onClick: () => settleDialog(item, true) }, danger ? {} : { 'data-autofocus': '' }), options.confirmLabel || t('confirm')),
            ],
        }, options.text ? h('p', { className: 'ks-dialog__text' }, options.text) : null, options.body || null);
    }

    function NoteDialog(props) {
        const item = props.item;
        const options = item.options;
        const max = options.maxLength || limits().note_max || 4000;
        const [value, setValue] = useState(options.initial || '');
        const [error, setError] = useState(null);

        const submit = () => {
            const text = value.trim();

            if (options.required && text === '') {
                setError(options.requiredMessage || t('note_required'));

                return;
            }

            settleDialog(item, text);
        };

        return h(Modal, {
            title: options.title || t('note'),
            icon: options.icon,
            size: 'sm',
            layerClassName: 'ks-modal-layer--dialog',
            className: cx('ks-dialog', options.danger && 'ks-dialog--danger'),
            onClose: () => settleDialog(item, null),
            footer: [
                h(Button, { key: 'cancel', variant: 'ghost', onClick: () => settleDialog(item, null) }, options.cancelLabel || t('cancel')),
                h(Button, { key: 'ok', variant: options.danger ? 'danger' : 'primary', icon: options.confirmIcon, onClick: submit }, options.confirmLabel || t('send')),
            ],
        },
            options.text ? h('p', { className: 'ks-dialog__text' }, options.text) : null,
            h(Field, { label: options.label || t('note'), required: !!options.required, error, hint: options.hint },
                h(TextArea, {
                    value,
                    rows: 4,
                    autoGrow: true,
                    maxRows: 10,
                    maxLength: max,
                    counter: true,
                    placeholder: options.placeholder || t('note_placeholder'),
                    'data-autofocus': '',
                    onChange: (next) => {
                        setValue(next);

                        if (error) {
                            setError(null);
                        }
                    },
                    onKeyDown: (event) => {
                        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                            event.preventDefault();
                            submit();
                        }
                    },
                })),
        );
    }

    function DialogHost() {
        const items = useStoreOf(dialogStore, (state) => state.items);

        return items.map((item) => (item.kind === 'note' ? h(NoteDialog, { key: item.id, item }) : h(ConfirmDialog, { key: item.id, item })));
    }

    function ServiceHost() {
        return h(Fragment, null, h(ToastViewport), h(DialogHost));
    }

    /**
     * KS.confirm({ title, text, confirmLabel, cancelLabel, danger, icon, confirmIcon, hideCancel, body }) -> Promise<boolean>
     * (duz metin de verilebilir: KS.confirm('Emin misiniz?')).
     */
    function confirm(options) {
        return openDialog('confirm', typeof options === 'string' ? { text: options } : options);
    }

    /**
     * KS.askNote({ title, text, label, placeholder, hint, required, requiredMessage, confirmLabel, cancelLabel, danger,
     *              initial, maxLength }) -> Promise<string|null>  (null = vazgecildi; zorunlu degilse '' donebilir).
     */
    function askNote(options) {
        return openDialog('note', options);
    }

    /** Hata -> kullaniciya gosterilecek metin (duruma gore, H2). Iptal edilen istek icin null. */
    function describeError(error) {
        if (!error) {
            return t('error_generic');
        }

        if (error.aborted) {
            return null;
        }

        const status = Number(error.status) || 0;

        if (status === 401 || status === 419) {
            return t('session_expired');
        }

        if (status === 403) {
            return t('forbidden');
        }

        if (status === 404) {
            return t('not_found');
        }

        if (status === 409) {
            return error.serverMessage || t('stale_reload');
        }

        if (status === 413) {
            return t('file_too_large');
        }

        if (status === 422) {
            return error.serverMessage || t('invalid_input');
        }

        if (error.network) {
            return t('network_error');
        }

        return t('error_generic');
    }

    /**
     * KS.handleError(error, { silent, onReload, title }) -> gosterilen metin | null
     * 401/419 oturum (sayfayi yenile eylemi), 403, 404, 409 stale_reload ("Yeniden yukle" eylemi; onReload verilmezse
     * acik ayrinti ve akis tazelenir), 413, 422 payload.message || invalid_input, ag hatasi, digerleri error_generic.
     * Sunucu mesaji yalniz 422/409'da gosterilir. Iptal edilen istekler (error.aborted) sessizce gecilir.
     */
    function handleError(error, options) {
        const opts = options || {};
        const message = describeError(error);

        if (message === null) {
            return null;
        }

        const status = Number(error && error.status) || 0;

        if (error && !status && !error.network && !error.missingEndpoint) {
            // Sunucu disi (kod) hatasi: gelistirici icin konsola da yazilir.
            console.error('KonelsisSocial:', error);
        }

        if (opts.silent) {
            return message;
        }

        if (status === 401 || status === 419) {
            toast.error(message, { title: opts.title, duration: 0, action: { label: t('reload_page'), onClick: () => window.location.reload() } });
        } else if (status === 409) {
            toast.warning(message, {
                title: opts.title,
                duration: 12000,
                action: {
                    label: t('reload'),
                    onClick: typeof opts.onReload === 'function' ? opts.onReload : () => {
                        const detailId = store.getState().detailId;

                        if (detailId) {
                            actions.fetchContent(detailId).then((detail) => actions.applyContent(detail)).catch(() => undefined);
                        }

                        actions.refreshFeed();
                    },
                },
            });
        } else {
            toast.error(message, { title: opts.title });
        }

        return message;
    }

    /* ================================================================== */
    /* 14. Disa acilan ad alani                                            */
    /* ================================================================== */

    const hooks = { useState, useEffect, useRef, useCallback, useMemo, useReducer, useLayoutEffect };

    const KS = {
        __core: true,
        version: '1.0.0',

        // Yapilandirma ve React
        config,
        root: rootEl,
        lang,
        locale,
        h,
        Fragment,
        hooks,
        useState,
        useEffect,
        useRef,
        useCallback,
        useMemo,
        useReducer,
        useLayoutEffect,

        // Metin, HTTP, bicim
        t,
        hasLabel,
        api,
        url,
        hasEndpoint,
        fmt,

        // Yardimcilar
        cx,
        omit,
        clamp,
        uid,
        debounce,
        shallowEqual,
        safeUrl,
        isExternalUrl,
        copyText,
        htmlToPlainText,
        platformClass,
        paletteColor,
        statusColor,
        stageColor,
        PALETTE,
        ICON_NAMES: Object.keys(ICONS),
        PLATFORMS,

        // Kancalar
        useStore,
        useStoreOf,
        useInterval,
        useDebounced,
        useResource,
        useMediaQuery,
        useIsMobile,
        useClickOutside,
        useOverlay,
        useBoot,
        useMe,
        useAbilities,
        useLimits,
        useProfile,
        useCounts,

        // Durum
        createStore,
        store,
        actions,
        router,
        events,
        options,
        optionLabel,
        limits,
        VIEWS,
        VIEW_COMPONENTS,
        DEFAULT_FILTERS,
        views: {},
        /** Gorunumler arasi paylasilan parcalar (KS.parts.ContentCard ...); social-feed.js doldurur. */
        parts: {},
        /** Gorunum degeri -> kayitli bilesen (KS.views.Feed ...) ya da null. */
        viewComponent: (view) => KS.views[VIEW_COMPONENTS[view]] || null,

        // Bilesenler
        Icon,
        PlatformIcon,
        PlatformLink,
        ExternalLink,
        Button,
        IconButton,
        Badge,
        StatusBadge,
        StageBadge,
        Chip,
        Avatar,
        AvatarStack,
        PersonLine,
        Portal,
        Modal,
        Drawer,
        Overlay,
        Popover,
        Menu,
        Tabs,
        Segmented,
        Field,
        TextInput,
        TextArea,
        Select,
        DateInput,
        TimeInput,
        Checkbox,
        Switch,
        Dropzone,
        Empty,
        ErrorState,
        Notice,
        Card,
        Section,
        Stat,
        Divider,
        LoadMore,
        CopyButton,
        Spinner,
        Skeleton,
        ProgressBar,
        Tooltip,
        ToastHost,

        // Geri bildirim
        toast,
        confirm,
        askNote,
        Confirm: confirm,
        prompt: askNote,
        handleError,
        describeError,
    };

    window.KonelsisSocial = KS;
    window.KS = window.KS || KS;
}());
