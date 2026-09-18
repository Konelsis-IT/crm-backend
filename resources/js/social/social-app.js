/*
 * Konelsis Sosyal Medya modulu - UYGULAMA KABUGU (B31, D-106, 18 Eylul 2026).
 *
 * En SON yuklenen dosyadir (core, editor, feed, detail, composer, planner, insights,
 * manage, app). Gorevi:
 *   - #konelsis-social-root icine React kokunu baglamak (cift baglama korumali);
 *   - acilis: KS.actions.loadBoot() -> (hesap varsa) KS.actions.resolveDeepLink();
 *     yukleme iskeleti, hata durumu (+ tekrar dene), hesap yoksa engelleyici bos durum;
 *   - ust cubuk: modul basligi, hesap degistirici (iki buyuk hap), "Icerik ekle" tur menusu;
 *   - gorunum sekmeleri (Akis, Plan, Ilham ve Rakipler, Analiz, Ayarlar) + sayac rozetleri;
 *     sekme satiri Filament ust cubugunun altinda yapiskandir ve yapisinca hesap secimi ile
 *     "Icerik ekle" dugmesinin kucuk hallerini tasir (ust cubuk ekrandan ciktigi icin);
 *   - etkin gorunumu KS.views[...] icinden TEMBEL (cizim aninda) cozer; eksik ya da hata
 *     veren gorunum icin dostca bir yedek ekran gosterir (hata siniri);
 *   - kaplamalar: store.detailId -> KS.views.Detail, store.composer.open -> KS.views.Composer;
 *   - 60 sn'de bir sayac tazeleme (document.hidden korumali) + pencere odaginda tazeleme.
 *
 * Adres esitleme (gorunum, icerik, hesap), gecmis (pushState / popstate), son hesap
 * (localStorage ks_profile) ve derin baglanti tamamen cekirdektedir (KS.router / KS.actions);
 * bu dosya yalniz onlari cagirir. Bildirim ve onay pencerelerini cekirdek kendi kokunde
 * cizer; KS.ToastHost yalniz o kokun hazir olmasini saglar.
 *
 * Gorunum dosyalari icin not: .ks-app-view altinda --ks-sticky-top degiskeni yapiskan sekme
 * satirinin yuksekligini de icerir; gorunumlerin kendi yapiskan parcalari (arac cubugu, sag
 * sutun) `top: var(--ks-sticky-top)` kullandiginda sekmelerin ALTINDA durur.
 *
 * Disa acilanlar: KS.App (kok bilesen), KS.mountApp() (baglama; tekrar cagrilmasi zararsiz),
 * KS.appRoot (ReactDOM kok nesnesi; baglandiktan sonra).
 * Stiller: resources/css/filament/konelsis-social.css, bolum APP (sinif on eki ks-app-).
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;

    // Betik iki kez yuklenirse ikinci kopya calismaz.
    if (KS.__appLoaded) {
        return;
    }

    KS.__appLoaded = true;

    const { h, t, cx, fmt, useState, useEffect, useRef, useCallback, useStore } = KS;

    const COUNTS_INTERVAL = 60000;   // 60 sn'de bir sayac tazeleme
    const WAKE_THROTTLE = 15000;     // odak / gorunurluk tazelemeleri arasinda en az 15 sn

    const VIEW_LABELS = { akis: 'view_feed', plan: 'view_plan', ilham: 'view_insights', analiz: 'view_analytics', ayarlar: 'settings' };
    const VIEW_ICONS = { akis: 'grid', plan: 'calendar', ilham: 'sparkles', analiz: 'chart', ayarlar: 'settings' };

    // Tur menusu: simge + kisa aciklama. Etiketler sunucudan (boot.options.types) gelir.
    const TYPE_META = {
        photo: { icon: 'image', hint: 'type_hint_photo' },
        video: { icon: 'video', hint: 'type_hint_video' },
        short_text: { icon: 'text', hint: 'type_hint_short_text' },
        long_text: { icon: 'document', hint: 'type_hint_long_text' },
        blog: { icon: 'blog', hint: 'type_hint_blog' },
    };

    /* ================================================================== */
    /* 1. Yardimcilar                                                      */
    /* ================================================================== */

    /** Hesabin avatar verisi: kurumsal hesap -> logo, yonetici hesabi -> sahibin fotografi, yoksa monogram. */
    function profilePerson(profile) {
        const owner = profile.owner || null;
        let photo = null;

        if (profile.kind === 'corporate') {
            photo = KS.config.logo || null;
        } else if (owner && owner.photo) {
            photo = owner.photo;
        }

        return { id: profile.id, name: profile.name, initials: profile.initials || undefined, photo };
    }

    /** Kullanicinin ilgilenmesi gereken icerik sayisi: onay yetkilisi icin "onayimi bekleyen", digerleri icin "onay bekleyen". */
    function attentionOf(counts, approver) {
        if (!counts) {
            return 0;
        }

        const value = Number(approver ? counts.awaiting_my_approval : counts.pending);

        return value > 0 ? value : 0;
    }

    function attentionHint(count, approver) {
        return t(approver ? 'awaiting_my_approval_hint' : 'pending_hint', { count: fmt.number(count) });
    }

    /**
     * Butun hesaplarin sayaclarini sirayla tazeler (etkin hesap once). Cekirdekteki istek
     * sira numarasi tek oldugu icin istekler ayni anda degil art arda gonderilir.
     */
    function refreshAllCounts() {
        const state = KS.store.getState();
        const profiles = (state.boot && state.boot.profiles) || [];
        const activeId = state.profileId;
        let chain = KS.actions.refreshCounts(activeId || undefined);

        profiles.forEach((profile) => {
            if (Number(profile.id) !== Number(activeId)) {
                chain = chain.then(() => KS.actions.refreshCounts(profile.id));
            }
        });

        return chain.catch(() => null);
    }

    function reloadPage() {
        window.location.reload();
    }

    /* ================================================================== */
    /* 2. Hata siniri                                                      */
    /* ================================================================== */

    /**
     * Boundary({ fallback: (reset) => eleman | null, onError(error), children })
     * Bir gorunum ya da kaplama cizim sirasinda hata verirse kabuk ayakta kalir.
     */
    class Boundary extends window.React.Component {
        constructor(props) {
            super(props);
            this.state = { error: null };
            this.reset = this.reset.bind(this);
        }

        static getDerivedStateFromError(error) {
            return { error };
        }

        componentDidCatch(error) {
            console.error('KonelsisSocial: bilesen cizilirken hata olustu', error);

            if (typeof this.props.onError === 'function') {
                this.props.onError(error);
            }
        }

        reset() {
            this.setState({ error: null });
        }

        render() {
            if (this.state.error) {
                return typeof this.props.fallback === 'function' ? this.props.fallback(this.reset) : null;
            }

            return this.props.children;
        }
    }

    /* ================================================================== */
    /* 3. Durum ekranlari: iskelet, hata, hesap yok                        */
    /* ================================================================== */

    function StateCard(props) {
        return h('div', { className: 'ks-root ks-app-shell' },
            h('div', { className: 'ks-boot' },
                h('div', { className: 'ks-app-state' },
                    h(KS.Empty, {
                        icon: props.icon,
                        title: props.title,
                        text: props.text,
                        className: props.tone === 'error' ? 'ks-empty--error' : undefined,
                        action: props.actions,
                    }))));
    }

    function BootSkeleton() {
        const cards = [];
        const tabs = [];

        for (let index = 0; index < 8; index += 1) {
            cards.push(h(KS.Skeleton, { key: index, variant: 'card' }));
        }

        for (let index = 0; index < 4; index += 1) {
            tabs.push(h(KS.Skeleton, { key: index, variant: 'rect', width: '5.5rem', height: '0.875rem', radius: '9999px' }));
        }

        return h('div', { className: 'ks-root ks-app-shell ks-app-skel', role: 'status', 'aria-busy': 'true' },
            h('span', { className: 'ks-sr-only' }, t('loading')),
            h('div', { className: 'ks-app-skel__top' },
                h('div', { className: 'ks-app-skel__brand' },
                    h(KS.Skeleton, { variant: 'rect', width: '2.75rem', height: '2.75rem', radius: '0.875rem' }),
                    h('div', { className: 'ks-app-skel__titles' },
                        h(KS.Skeleton, { variant: 'rect', width: '11rem', height: '1.5rem', radius: '0.5rem' }),
                        h(KS.Skeleton, { variant: 'text', width: '17rem' }))),
                h('div', { className: 'ks-app-skel__pills' },
                    h(KS.Skeleton, { variant: 'rect', width: '13rem', height: '3rem', radius: '9999px' }),
                    h(KS.Skeleton, { variant: 'rect', width: '13rem', height: '3rem', radius: '9999px' }))),
            h('div', { className: 'ks-app-skel__tabs' }, tabs),
            h('div', { className: 'ks-grid' }, cards));
    }

    function BootError(props) {
        return h(StateCard, {
            icon: 'warning',
            tone: 'error',
            title: t('boot_failed_title'),
            text: KS.describeError(props.error) || t('error_generic'),
            actions: [
                h(KS.Button, { key: 'retry', variant: 'primary', icon: 'refresh', onClick: props.onRetry }, t('retry')),
                h(KS.Button, { key: 'reload', variant: 'ghost', onClick: reloadPage }, t('reload_page')),
            ],
        });
    }

    function ProfilesMissing(props) {
        return h(StateCard, {
            icon: 'users',
            title: t('profiles_missing'),
            text: t('profiles_missing_text'),
            actions: h(KS.Button, { variant: 'soft', icon: 'refresh', onClick: props.onRetry }, t('retry')),
        });
    }

    /* ================================================================== */
    /* 4. Ust cubuk parcalari                                              */
    /* ================================================================== */

    function ProfileAvatar(props) {
        const profile = props.profile;

        // Hesap adi dugmenin metninde zaten var; avatar ekran okuyucuya ikinci kez okunmasin.
        return h('span', { className: 'ks-app-avatar-wrap', 'aria-hidden': 'true' },
            h(KS.Avatar, {
                person: profilePerson(profile),
                size: props.size || 'md',
                title: false,
                className: cx('ks-app-avatar', profile.kind === 'corporate' && 'ks-app-avatar--logo'),
            }));
    }

    /** Hesap degistirici: buyuk haplar (avatar / monogram + ad + hesap turu + bekleyen sayisi). */
    function ProfilePills(props) {
        return h('div', { className: 'ks-app-pills', role: 'group', 'aria-label': t('profile_switcher') },
            props.profiles.map((profile) => {
                const active = Number(profile.id) === Number(props.profileId);
                const waiting = attentionOf(props.counts[profile.id], props.approver);
                const hint = waiting > 0 ? attentionHint(waiting, props.approver) : null;

                return h('button', {
                    key: profile.id,
                    type: 'button',
                    className: cx('ks-pill', 'ks-app-pill', active && 'is-active'),
                    'aria-pressed': active ? 'true' : 'false',
                    title: profile.name,
                    onClick: () => KS.actions.setProfile(profile.id),
                },
                    h(ProfileAvatar, { profile }),
                    h('span', { className: 'ks-pill__text' },
                        h('span', { className: 'ks-pill__name' }, profile.name),
                        profile.kind_label ? h('span', { className: 'ks-pill__meta' }, profile.kind_label) : null),
                    hint ? h('span', { className: 'ks-app-pill__count ks-c-amber', title: hint },
                        h('span', { 'aria-hidden': 'true' }, fmt.number(waiting)),
                        h('span', { className: 'ks-sr-only' }, hint)) : null,
                );
            }));
    }

    /** Yapiskan sekme satirindaki kucuk hesap secimi (ust cubuk ekrandan ciktiginda). */
    function ProfileMenu(props) {
        const current = props.profiles.find((profile) => Number(profile.id) === Number(props.profileId)) || null;

        if (!current) {
            return null;
        }

        if (props.profiles.length < 2) {
            return h('span', { className: 'ks-app-switch ks-app-switch--static', title: current.name },
                h(ProfileAvatar, { profile: current, size: 'sm' }),
                h('span', { className: 'ks-app-switch__name' }, current.name));
        }

        return h(KS.Menu, {
            label: t('switch_profile'),
            align: 'end',
            width: 272,
            trigger: (triggerProps) => h('button', Object.assign({
                type: 'button',
                className: 'ks-app-switch',
                title: t('switch_profile'),
                'aria-label': t('switch_profile_current', { name: current.name }),
            }, triggerProps),
                h(ProfileAvatar, { profile: current, size: 'sm' }),
                h('span', { className: 'ks-app-switch__name' }, current.name),
                h(KS.Icon, { name: 'chevron-down', className: 'ks-app-switch__caret' })),
            items: props.profiles.map((profile) => ({
                key: 'profile-' + profile.id,
                label: profile.name,
                hint: profile.kind_label || undefined,
                active: Number(profile.id) === Number(current.id),
                onSelect: () => KS.actions.setProfile(profile.id),
            })),
        });
    }

    /** "Icerik ekle": bes turun menusu. Tur listesi bos gelirse olusturucu tur seciciyle acilir. */
    function AddMenu(props) {
        const types = props.types || [];
        const open = (type) => KS.actions.openComposer({ type: type || null, defaults: { profile_id: props.profileId } });

        if (!types.length) {
            return props.iconOnly
                ? h(KS.IconButton, { icon: 'plus', label: t('add_content'), variant: 'solid', onClick: () => open(null) })
                : h(KS.Button, { variant: 'primary', icon: 'plus', size: props.compact ? 'sm' : 'md', onClick: () => open(null) }, t('add_content'));
        }

        const items = [{ heading: t('add_content_heading') }].concat(types.map((type) => {
            const meta = TYPE_META[type.value] || null;

            return {
                key: 'type-' + type.value,
                label: type.label,
                icon: meta ? meta.icon : 'plus',
                hint: meta ? t(meta.hint) : undefined,
                onSelect: () => open(type.value),
            };
        }));

        return h(KS.Menu, {
            label: t('add_content'),
            align: 'end',
            width: 304,
            items,
            trigger: props.iconOnly
                ? { iconOnly: true, icon: 'plus', label: t('add_content'), variant: 'solid' }
                : { label: t('add_content'), icon: 'plus', variant: 'primary', size: props.compact ? 'sm' : 'md' },
        });
    }

    /* ================================================================== */
    /* 5. Gorunum alani                                                    */
    /* ================================================================== */

    function ViewMissing(props) {
        const canGoFeed = props.view !== 'akis' && !!KS.viewComponent('akis');

        return h('div', { className: 'ks-app-state ks-app-state--inline' },
            h(KS.Empty, {
                icon: 'sparkles',
                title: t('view_unavailable_title'),
                text: t('view_unavailable_text'),
                action: [
                    h(KS.Button, { key: 'reload', variant: 'soft', icon: 'refresh', onClick: reloadPage }, t('reload_page')),
                    canGoFeed ? h(KS.Button, { key: 'feed', variant: 'ghost', icon: 'grid', onClick: () => KS.actions.setView('akis') }, t('back_to_feed')) : null,
                ],
            }));
    }

    function ViewCrashed(props) {
        return h('div', { className: 'ks-app-state ks-app-state--inline' },
            h(KS.Empty, {
                icon: 'warning',
                className: 'ks-empty--error',
                title: t('view_crashed_title'),
                text: t('view_crashed_text'),
                action: [
                    h(KS.Button, { key: 'retry', variant: 'soft', icon: 'refresh', onClick: props.onRetry }, t('retry')),
                    h(KS.Button, { key: 'reload', variant: 'ghost', onClick: reloadPage }, t('reload_page')),
                ],
            }));
    }

    /** Etkin gorunum: bilesen cizim aninda cozulur (dosya sirasi ve eksik dosya kabugu bozmaz). */
    function ViewHost(props) {
        const Component = KS.viewComponent(props.view);

        return h('div', { className: 'ks-app-view', role: 'tabpanel', 'aria-label': t(VIEW_LABELS[props.view] || 'view_feed') },
            Component
                ? h(Boundary, { fallback: (reset) => h(ViewCrashed, { onRetry: reset }) }, h(Component, null))
                : h(ViewMissing, { view: props.view }));
    }

    /** Kaplama bileseni kayitli degilse: bilgi ver ve durumu kapat (ekran kilitli kalmasin). */
    function MissingOverlay(props) {
        useEffect(() => {
            KS.toast.error(t('overlay_unavailable'));
            props.onClose();
        }, []);

        return null;
    }

    /* ================================================================== */
    /* 6. Yapiskan sekme satiri: "yapisti mi" bilgisi                      */
    /* ================================================================== */

    /**
     * Sekme satirinin hemen ustundeki 1 px'lik isaret ogesi gorus alanindan (yapiskan ust sinirin
     * uzerinden) ciktiginda satir yapismis demektir. Satirin yuksekligi iki durumda da aynidir;
     * bu yuzden durum degisimi sayfayi oynatmaz.
     */
    function useStuck(sentinelRef, navRef) {
        const [stuck, setStuck] = useState(false);

        useEffect(() => {
            const sentinel = sentinelRef.current;
            const nav = navRef.current;

            if (!sentinel || !nav || typeof window.IntersectionObserver !== 'function') {
                return undefined;
            }

            let observer = null;

            const build = () => {
                if (observer) {
                    observer.disconnect();
                }

                const top = Math.max(0, Math.round(parseFloat(window.getComputedStyle(nav).top) || 0));

                observer = new window.IntersectionObserver((entries) => {
                    const entry = entries[entries.length - 1];

                    if (entry) {
                        setStuck(!entry.isIntersecting && entry.boundingClientRect.top < top + 2);
                    }
                }, { rootMargin: '-' + (top + 1) + 'px 0px 0px 0px', threshold: 0 });

                observer.observe(sentinel);
            };

            const onResize = KS.debounce(build, 200);

            build();
            window.addEventListener('resize', onResize);

            return () => {
                window.removeEventListener('resize', onResize);
                onResize.cancel();

                if (observer) {
                    observer.disconnect();
                }
            };
        }, []);

        return stuck;
    }

    /* ================================================================== */
    /* 7. Kabuk                                                            */
    /* ================================================================== */

    function Shell() {
        const boot = KS.useBoot();
        const abilities = KS.useAbilities();
        const profileId = useStore((state) => state.profileId);
        const view = useStore((state) => state.view);
        const detailId = useStore((state) => state.detailId);
        const composer = useStore((state) => state.composer);
        const allCounts = useStore((state) => state.counts);
        const isMobile = KS.useIsMobile();

        const sentinelRef = useRef(null);
        const navRef = useRef(null);
        const lastRefresh = useRef(Date.now());
        const stuck = useStuck(sentinelRef, navRef);

        const profiles = (boot && boot.profiles) || [];
        const types = (boot && boot.options && boot.options.types) || [];
        const canCreate = !!abilities.create;
        const canManage = !!abilities.manage_data;
        const approver = !!abilities.approve_any;
        const counts = allCounts[profileId] || {};

        // Ayarlar yalniz manage_data ile gorunur; yetkisiz derin baglanti akisa duser.
        const shownView = view === 'ayarlar' && !canManage ? 'akis' : view;

        useEffect(() => {
            if (view === 'ayarlar' && !canManage) {
                KS.actions.setView('akis');
            }
        }, [view, canManage]);

        // Sayaclar: 60 sn'de bir + pencere odaga / sekme gorunur hale geldiginde.
        const refresh = useCallback(() => {
            if (document.hidden) {
                return;
            }

            lastRefresh.current = Date.now();
            refreshAllCounts();
        }, []);

        KS.useInterval(refresh, COUNTS_INTERVAL, true);

        useEffect(() => {
            const onWake = () => {
                if (document.hidden || Date.now() - lastRefresh.current < WAKE_THROTTLE) {
                    return;
                }

                refresh();
            };

            window.addEventListener('focus', onWake);
            document.addEventListener('visibilitychange', onWake);

            return () => {
                window.removeEventListener('focus', onWake);
                document.removeEventListener('visibilitychange', onWake);
            };
        }, [refresh]);

        // Dar ekranda sekmeler yatay kayar: etkin sekme gorunur alana getirilir.
        useEffect(() => {
            const nav = navRef.current;
            const list = nav ? nav.querySelector('[role="tablist"]') : null;
            const active = list ? list.querySelector('[aria-selected="true"]') : null;

            if (!list || !active || list.scrollWidth <= list.clientWidth) {
                return;
            }

            const listBox = list.getBoundingClientRect();
            const activeBox = active.getBoundingClientRect();

            list.scrollLeft += (activeBox.left - listBox.left) - (listBox.width - activeBox.width) / 2;
        }, [shownView, stuck, isMobile]);

        const waiting = attentionOf(counts, approver);
        const today = Number(counts.today) > 0 ? Number(counts.today) : 0;
        const missed = Number(counts.missed) > 0 ? Number(counts.missed) : 0;
        const due = today + missed;

        const tabs = [
            {
                value: 'akis',
                label: t(VIEW_LABELS.akis),
                icon: VIEW_ICONS.akis,
                count: waiting > 0 ? waiting : undefined,
                countTone: 'amber',
                title: waiting > 0 ? attentionHint(waiting, approver) : undefined,
            },
            {
                value: 'plan',
                label: t(VIEW_LABELS.plan),
                icon: VIEW_ICONS.plan,
                count: due > 0 ? due : undefined,
                countTone: today > 0 ? 'red' : 'rose',
                title: due > 0 ? t('plan_due_hint', { today: fmt.number(today), missed: fmt.number(missed) }) : undefined,
            },
            { value: 'ilham', label: t(VIEW_LABELS.ilham), icon: VIEW_ICONS.ilham },
            { value: 'analiz', label: t(VIEW_LABELS.analiz), icon: VIEW_ICONS.analiz },
            canManage ? { value: 'ayarlar', label: t(VIEW_LABELS.ayarlar), icon: VIEW_ICONS.ayarlar } : null,
        ];

        // Kaplamalar da cizim aninda cozulur.
        const Detail = KS.views.Detail || null;
        const Composer = KS.views.Composer || null;

        const onDetailCrash = () => {
            KS.toast.error(t('view_crashed_title'));
            KS.actions.closeDetail();
        };

        const onComposerCrash = () => {
            KS.toast.error(t('view_crashed_title'));
            KS.actions.closeComposer();
        };

        let detailLayer = null;

        if (detailId) {
            detailLayer = Detail
                ? h(Boundary, { key: 'detail-' + detailId, onError: onDetailCrash }, h(Detail, { id: detailId, onClose: KS.actions.closeDetail }))
                : h(MissingOverlay, { key: 'detail-missing', onClose: KS.actions.closeDetail });
        }

        let composerLayer = null;

        if (composer && composer.open) {
            composerLayer = Composer
                ? h(Boundary, { key: 'composer', onError: onComposerCrash }, h(Composer, { state: composer, onClose: KS.actions.closeComposer }))
                : h(MissingOverlay, { key: 'composer-missing', onClose: KS.actions.closeComposer });
        }

        return h('div', { className: 'ks-root ks-app-shell' },
            h('header', { className: 'ks-app-top' },
                h('div', { className: 'ks-app-top__grid' },
                    h('div', { className: 'ks-app-brand' },
                        h('span', { className: 'ks-app-brand__mark', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'hash' })),
                        h('div', { className: 'ks-app-brand__text' },
                            h('h1', { className: 'ks-app-title' }, t('title')),
                            h('p', { className: 'ks-app-subtitle' }, t('app_subtitle')))),
                    h(ProfilePills, { profiles, profileId, counts: allCounts, approver }),
                    canCreate ? h('div', { className: 'ks-app-actions' }, h(AddMenu, { types, profileId })) : null)),

            h('div', { ref: sentinelRef, className: 'ks-app-sentinel', 'aria-hidden': 'true' }),

            h('div', { ref: navRef, className: cx('ks-app-nav', stuck && 'is-stuck') },
                h(KS.Tabs, { items: tabs, value: shownView, onChange: KS.actions.setView, label: t('views_label'), className: 'ks-app-tabs' }),
                stuck ? h('div', { className: 'ks-app-nav__tools' },
                    h(ProfileMenu, { profiles, profileId }),
                    canCreate ? h(AddMenu, { types, profileId, compact: true, iconOnly: isMobile }) : null) : null),

            // Hesap degisince gorunum bastan kurulur: liste, sayfalama ve kaydirma o hesaba gore sifirlanir.
            h(ViewHost, { key: shownView + ':' + profileId, view: shownView }),

            detailLayer,
            composerLayer,
            h(KS.ToastHost, null));
    }

    /* ================================================================== */
    /* 8. Kok bilesen: acilis akisi                                        */
    /* ================================================================== */

    function App() {
        const bootStatus = useStore((state) => state.bootStatus);
        const bootError = useStore((state) => state.bootError);
        const boot = useStore((state) => state.boot);
        const [linkReady, setLinkReady] = useState(false);
        const runRef = useRef(0);

        /*
         * bootstrap -> (hesap varsa) derin baglanti (H4). Derin baglanti cozulene kadar iskelet
         * gosterilir; boylece akis once yanlis hesapla, sonra icerigin hesabiyla iki kez cekilmez.
         */
        const start = useCallback((force) => {
            runRef.current += 1;

            const run = runRef.current;

            setLinkReady(false);

            KS.actions.loadBoot(force)
                .then((payload) => {
                    const hasProfiles = !!(payload && payload.profiles && payload.profiles.length);

                    return hasProfiles ? KS.actions.resolveDeepLink() : false;
                })
                .catch(() => false)
                .then(() => {
                    if (runRef.current === run) {
                        setLinkReady(true);
                    }
                });
        }, []);

        useEffect(() => {
            start(false);

            return () => {
                runRef.current = -1;
            };
        }, [start]);

        if (bootStatus === 'error') {
            return h(BootError, { error: bootError, onRetry: () => start(true) });
        }

        if (bootStatus !== 'ready' || !boot) {
            return h(BootSkeleton, null);
        }

        if (!boot.profiles || !boot.profiles.length) {
            return h(ProfilesMissing, { onRetry: () => start(true) });
        }

        if (!linkReady) {
            return h(BootSkeleton, null);
        }

        return h(Shell, null);
    }

    /* ================================================================== */
    /* 9. Baglama                                                          */
    /* ================================================================== */

    function mountApp() {
        const rootEl = KS.root || document.getElementById('konelsis-social-root');

        if (!rootEl || KS.appRoot || rootEl.getAttribute('data-ks-mounted') === '1') {
            return;
        }

        rootEl.setAttribute('data-ks-mounted', '1');

        try {
            const root = window.ReactDOM.createRoot(rootEl);

            KS.appRoot = root;
            root.render(h(App, null));
        } catch (error) {
            KS.appRoot = null;
            rootEl.removeAttribute('data-ks-mounted');
            console.error('KonelsisSocial: uygulama baglanamadi', error);
        }
    }

    KS.App = App;
    KS.mountApp = mountApp;

    if (document.readyState !== 'loading') {
        mountApp();
    } else {
        document.addEventListener('DOMContentLoaded', mountApp, { once: true });
    }
}());
