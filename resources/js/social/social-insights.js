/*
 * Konelsis Sosyal Medya modulu - "Ilham ve Rakipler" gorunumu (B31, D-106, 18 Eylul 2026).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement), dis kutuphane yok.
 * social-core.js'ten SONRA yuklenir ve yalniz onun API'sini kullanir (window.KonelsisSocial = KS).
 * Stiller: resources/css/filament/konelsis-social.css, bolum INSIGHTS (sinif on eki ks-insights-).
 *
 * Kaydettigi gorunum: KS.views.Insights (ozellik almaz; etkin hesabi depodan okur).
 *
 * Icerik:
 * - Izlenen hesaplar (GET watch?profile): rakip firmalar / rakip yoneticiler / resmi kurumlar.
 *   Sunucu yalniz etkin hesabin turune uyan gruplari dondurur. Tablo degil, kapakli hesap kartlari:
 *   addan turetilen yumusak gecisli monogram, ad, alt baslik, not ve marka renginde platform
 *   dugmeleri (KS.PlatformLink: KS.safeUrl + yeni sekme + rel noopener noreferrer).
 *   Tur secimi (Segmented), arama kutusu, pasifleri goster (yalniz manage_data; include_inactive=1).
 * - Ekle / duzenle penceresi (yalniz me.abilities.manage_data): tur, ad, alt baslik, not, durum,
 *   sira ve her platform icin bir adres alani -> watch.store / watch.update. Silme yoktur; hesap
 *   pasife alinir. `links` her kayitta TAM liste olarak gider (E6: listede olmayan baglanti kalkar);
 *   hizli "pasife al / etkinlestir" isleminde `links` hic gonderilmez (baglantilara dokunulmaz).
 * - Katalog paneli (GET catalog): onizlenebilir dosya <iframe> (gorsel ise <img>) icinde okunur,
 *   "Genis ekranda oku", "Yeni sekmede ac" ve "Indir" dugmeleri; onizlenemeyen dosya yalniz indirme
 *   karti; katalog yoksa Dokumanlar'a (Genel katalog) yonlendiren bos durum.
 * - Duzen: kap genisligi >= 1040px iken hesap izgarasi + yapiskan katalog karti; daha darda sekmeler
 *   (Hesaplar | Katalog). Genislik ResizeObserver ile KABIN kendisinden olculur; Filament kenar
 *   cubugunun acik/kapali olmasi boylece dogru sonuc verir.
 *
 * Olay: hesap eklenince / guncellenince KS.events.emit('watch', account) (akis sayfasindaki
 * "Rakipler ve kurumlar" seridi isterse dinler).
 *
 * Gorunen hicbir metin koda gomulu degildir; hepsi KS.t('anahtar') ile lang `ui` dizisinden gelir.
 */
(function () {
    'use strict';

    if (!window.KonelsisSocial) { return; }

    const KS = window.KonelsisSocial;
    const { h, Fragment, t, api, url, fmt, cx } = KS;
    const { useState, useEffect, useRef, useMemo } = KS;

    /* ================================================================== */
    /* 1. Sabitler ve kucuk yardimcilar                                    */
    /* ================================================================== */

    const WIDE_MIN = 1040;          // kap genisligi (px): ustunde yan yana, altinda sekmeli duzen
    const NAME_MAX = 160;
    const URL_MAX = 500;
    const SORT_MAX = 65535;
    const NOTE_PREVIEW = 150;       // bundan uzun notlar uc satira kisaltilir
    const FRAME_TIMEOUT = 8000;     // iframe `load` olayi gelmezse bekleme gostergesi bu surede kalkar
    const EMPTY = Object.freeze([]);
    const KIND_ICONS = { competitor_company: 'building', competitor_executive: 'user', official_institution: 'institution' };
    const FORM_FIELDS = ['kind', 'name', 'subtitle', 'note', 'status', 'sort_order'];

    /** Arama icin sadelestirme: yerel kucuk harf + aksan ayirma (s-cedilla -> s, dotless i -> i ...). */
    function fold(value) {
        let text = String(value === undefined || value === null ? '' : value).toLocaleLowerCase(KS.locale);

        if (typeof text.normalize === 'function') {
            text = text.normalize('NFD').replace(/[̀-ͯ]/g, '');
        }

        return text.replace(/ı/g, 'i').replace(/\s+/g, ' ').trim();
    }

    function matches(account, needle) {
        if (!needle) {
            return true;
        }

        const haystack = [account.name, account.subtitle, account.note]
            .concat((account.links || []).map((link) => (link ? link.platform_label : '')))
            .map(fold)
            .join(' ');

        return needle.split(' ').every((word) => haystack.indexOf(word) !== -1);
    }

    /** Addan turetilen renk tonu (20-339): kirmizi bolge vurgu rengine ayrildigi icin atlanir. */
    function hueOf(name) {
        const text = String(name || '');
        let hash = 7;

        for (let index = 0; index < text.length; index += 1) {
            hash = (hash * 31 + text.charCodeAt(index)) % 3600;
        }

        return 20 + (hash % 320);
    }

    function hueStyle(name) {
        const hue = hueOf(name);

        return { '--ks-insights-hue': String(hue), '--ks-insights-hue-2': String(Math.min(345, hue + 38)) };
    }

    function initialsOf(account) {
        if (account && account.initials) {
            return String(account.initials);
        }

        const parts = String((account && account.name) || '').trim().split(/\s+/).filter(Boolean);

        if (!parts.length) {
            return '?';
        }

        return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toLocaleUpperCase(KS.locale);
    }

    function compareAccounts(a, b) {
        const orderA = Number(a.sort_order) || 0;
        const orderB = Number(b.sort_order) || 0;

        if (orderA !== orderB) {
            return orderA - orderB;
        }

        const byName = String(a.name || '').localeCompare(String(b.name || ''), KS.locale);

        return byName !== 0 ? byName : (Number(a.id) || 0) - (Number(b.id) || 0);
    }

    /** Kaydedilen hesabi eldeki gruplara isler (sunucudaki sirayla: sira, ad, kayit). */
    function applyAccount(data, account, includeInactive) {
        if (!data || !Array.isArray(data.groups)) {
            return data;
        }

        const visible = account.status === 'active' || includeInactive;
        const groups = data.groups.map((group) => {
            let accounts = (group.accounts || []).filter((item) => Number(item.id) !== Number(account.id));

            if (visible && group.kind === account.kind) {
                accounts = accounts.concat([account]).sort(compareAccounts);
            }

            return Object.assign({}, group, { accounts });
        });

        return Object.assign({}, data, { groups });
    }

    function countAccounts(groups) {
        return groups.reduce((sum, group) => sum + (group.accounts ? group.accounts.length : 0), 0);
    }

    /** Platform secenekleri: bootstrap.options.platforms; yoksa cekirdegin sabit listesi. */
    function platformOptions() {
        const list = KS.options('platforms');

        if (list && list.length) {
            return list.map((item) => ({ value: String(item.value), label: item.label || '' }));
        }

        return KS.PLATFORMS.map((value) => ({ value, label: '' }));
    }

    /** "instagram.com/konelsis" -> "https://instagram.com/konelsis"; baska semalar oldugu gibi kalir (dogrulama reddeder). */
    function normalizeUrl(value) {
        const text = String(value === undefined || value === null ? '' : value).trim();

        if (text === '' || /^https?:\/\//i.test(text) || /^[a-z][a-z0-9+.-]*:/i.test(text)) {
            return text;
        }

        return 'https://' + text.replace(/^\/+/, '');
    }

    function isValidUrl(value) {
        if (value.length > URL_MAX || !/^https?:\/\//i.test(value) || !KS.safeUrl(value)) {
            return false;
        }

        try {
            const parsed = new URL(value);

            return parsed.hostname.indexOf('.') > 0 && parsed.hostname.charAt(parsed.hostname.length - 1) !== '.';
        } catch (error) {
            return false;
        }
    }

    /** Laravel alan hatalari -> form alanlari; `links.2.url` gonderilen listedeki platforma eslenir. */
    function mapServerErrors(error, sentLinks) {
        const out = {};
        const source = error && error.errors;

        if (!source || typeof source !== 'object') {
            return out;
        }

        Object.keys(source).forEach((field) => {
            const list = Array.isArray(source[field]) ? source[field] : [source[field]];
            const message = list.length && list[0] ? String(list[0]) : '';

            if (!message) {
                return;
            }

            const link = /^links\.(\d+)(\.|$)/.exec(field);

            if (link) {
                const sent = sentLinks[Number(link[1])];

                if (sent) {
                    out['link_' + sent.platform] = message;
                }

                return;
            }

            if (FORM_FIELDS.indexOf(field) !== -1) {
                out[field] = message;
            }
        });

        return out;
    }

    function formOf(account, defaultKind) {
        const links = {};

        ((account && account.links) || []).forEach((link) => {
            if (link && link.platform) {
                links[String(link.platform)] = String(link.url || '');
            }
        });

        return {
            kind: account ? String(account.kind || '') : String(defaultKind || ''),
            name: account ? String(account.name || '') : '',
            subtitle: account ? String(account.subtitle || '') : '',
            note: account ? String(account.note || '') : '',
            active: account ? account.status !== 'inactive' : true,
            sortOrder: account && account.sort_order !== undefined && account.sort_order !== null ? String(account.sort_order) : '',
            links,
        };
    }

    /** Kaydedilmemis degisiklik denetimi icin bicimden bagimsiz ozet (bos baglantilar sayilmaz). */
    function snapshot(form) {
        const links = Object.keys(form.links || {}).sort()
            .map((platform) => [platform, String(form.links[platform] || '').trim()])
            .filter((pair) => pair[1] !== '');

        return JSON.stringify([form.kind, form.name.trim(), form.subtitle.trim(), form.note.trim(), !!form.active, form.sortOrder, links]);
    }

    /** Tarayici PDF'i sayfa icinde gosterebiliyor mu (mobil tarayicilarin cogu gosteremez). */
    function canEmbed(info) {
        const mime = String((info && info.mime) || '').toLowerCase();

        if (mime === 'application/pdf' && typeof navigator !== 'undefined' && navigator.pdfViewerEnabled === false) {
            return false;
        }

        return true;
    }

    /** Kabin kendi genisligine gore duzen secimi (kenar cubugu acik/kapali fark etmez). */
    function useContainerWide(ref, min) {
        const [wide, setWide] = useState(() => window.innerWidth >= min + 336);

        useEffect(() => {
            const node = ref.current;

            if (!node) {
                return undefined;
            }

            const measure = () => {
                const width = node.getBoundingClientRect().width;

                // Gizli kapta (genislik 0) son bilinen duzen korunur.
                if (width > 0) {
                    setWide(width >= min);
                }
            };

            measure();

            if (typeof window.ResizeObserver === 'function') {
                const observer = new window.ResizeObserver(measure);

                observer.observe(node);

                return () => observer.disconnect();
            }

            window.addEventListener('resize', measure);

            return () => window.removeEventListener('resize', measure);
        }, [ref, min]);

        return wide;
    }

    /* ================================================================== */
    /* 2. Hesap karti                                                      */
    /* ================================================================== */

    function AccountCard(props) {
        const account = props.account;
        const [expanded, setExpanded] = useState(false);
        const noteId = useMemo(() => KS.uid('ks-insights-note'), []);
        const inactive = !!account.status && account.status !== 'active';
        const note = String(account.note || '').trim();
        const long = note.length > NOTE_PREVIEW || note.split('\n').length > 3;
        const links = (account.links || []).filter((link) => link && KS.safeUrl(link.url));

        const menuItems = props.canManage ? [
            { key: 'edit', label: t('edit'), icon: 'edit', onSelect: () => props.onEdit(account) },
            inactive
                ? { key: 'activate', label: t('insights_activate'), icon: 'eye', onSelect: () => props.onToggle(account) }
                : { key: 'deactivate', label: t('insights_deactivate'), hint: t('insights_deactivate_hint'), icon: 'eye-off', onSelect: () => props.onToggle(account) },
        ] : null;

        return h('article', {
            className: cx('ks-insights-card', inactive && 'is-inactive', props.busy && 'is-busy'),
            style: hueStyle(account.name),
            'aria-label': account.name,
            'aria-busy': props.busy ? 'true' : undefined,
        },
            h('div', { className: 'ks-insights-card__cover', 'aria-hidden': 'true' }),
            h('div', { className: 'ks-insights-card__body' },
                h('div', { className: 'ks-insights-card__top' },
                    h('span', {
                        className: cx('ks-insights-mono', account.kind === 'competitor_executive' && 'ks-insights-mono--round'),
                        'aria-hidden': 'true',
                    }, initialsOf(account)),
                    h('div', { className: 'ks-insights-card__tools' },
                        inactive ? h(KS.Badge, { color: 'stone', size: 'sm', icon: 'eye-off' }, account.status_label || t('inactive')) : null,
                        menuItems ? h(KS.Menu, {
                            trigger: { iconOnly: true, icon: 'more', variant: 'plain', size: 'sm', disabled: !!props.busy, label: t('insights_account_actions', { name: account.name }) },
                            label: t('insights_account_actions', { name: account.name }),
                            align: 'end',
                            items: menuItems,
                        }) : null,
                    ),
                ),
                h('div', { className: 'ks-insights-card__titles' },
                    h('h4', { className: 'ks-insights-card__name' }, account.name),
                    account.subtitle ? h('p', { className: 'ks-insights-card__subtitle' }, account.subtitle) : null,
                ),
                note ? h('div', { className: 'ks-insights-card__notewrap' },
                    h('p', { id: noteId, className: cx('ks-insights-card__note', long && !expanded && 'ks-clamp-3') }, note),
                    long ? h(KS.Button, {
                        variant: 'link',
                        size: 'sm',
                        className: 'ks-insights-card__more',
                        'aria-expanded': expanded ? 'true' : 'false',
                        'aria-controls': noteId,
                        onClick: () => setExpanded(!expanded),
                    }, t(expanded ? 'show_less' : 'show_more')) : null,
                ) : null,
                h('div', { className: 'ks-insights-links' },
                    links.length
                        ? links.map((link) => h(KS.PlatformLink, {
                            key: link.platform,
                            platform: link.platform,
                            label: link.platform_label || KS.optionLabel('platforms', link.platform),
                            url: link.url,
                            className: 'ks-insights-link',
                        }))
                        : h('span', { className: 'ks-insights-links__empty' }, t('insights_no_links')),
                ),
            ),
        );
    }

    function CardSkeleton() {
        return h('div', { className: 'ks-insights-card ks-insights-card--skeleton', 'aria-hidden': 'true' },
            h('div', { className: 'ks-insights-card__cover' }),
            h('div', { className: 'ks-insights-card__body' },
                h('div', { className: 'ks-insights-card__top' },
                    h(KS.Skeleton, { variant: 'rect', width: '3.5rem', height: '3.5rem', radius: '1rem', className: 'ks-insights-mono-skeleton' }),
                ),
                h(KS.Skeleton, { variant: 'text', width: '62%', height: '0.9375rem' }),
                h(KS.Skeleton, { variant: 'text', lines: 2 }),
                h('div', { className: 'ks-insights-links' },
                    h(KS.Skeleton, { variant: 'rect', width: '6.5rem', height: '2.25rem', radius: 9999 }),
                    h(KS.Skeleton, { variant: 'rect', width: '5.5rem', height: '2.25rem', radius: 9999 }),
                    h(KS.Skeleton, { variant: 'rect', width: '6rem', height: '2.25rem', radius: 9999 }),
                ),
            ),
        );
    }

    function AccountsSkeleton() {
        const cards = [];

        for (let index = 0; index < 6; index += 1) {
            cards.push(h(CardSkeleton, { key: index }));
        }

        return h('div', { className: 'ks-insights-group', role: 'status', 'aria-label': t('loading') },
            h('div', { className: 'ks-insights-group__head' },
                h(KS.Skeleton, { variant: 'rect', width: '2rem', height: '2rem', radius: 10 }),
                h(KS.Skeleton, { variant: 'text', width: '9rem', height: '0.9375rem' }),
            ),
            h('div', { className: 'ks-grid ks-insights-grid' }, cards),
        );
    }

    function GroupSection(props) {
        const group = props.group;
        const accounts = group.accounts || [];
        const icon = KIND_ICONS[group.kind] || 'users';

        return h('section', { className: 'ks-insights-group', 'aria-label': group.kind_label },
            h('header', { className: 'ks-insights-group__head' },
                h('span', { className: 'ks-insights-group__icon', 'aria-hidden': 'true' }, h(KS.Icon, { name: icon })),
                h('h3', { className: 'ks-insights-group__title' }, group.kind_label),
                h(KS.Badge, { color: 'stone', size: 'sm' }, fmt.number(accounts.length)),
                h('span', { className: 'ks-spacer' }),
                props.canManage ? h(KS.Button, {
                    variant: 'soft',
                    size: 'sm',
                    icon: 'plus',
                    ariaLabel: t('insights_add_to_group', { group: group.kind_label }),
                    onClick: () => props.onAdd(group.kind),
                }, t('add')) : null,
            ),
            accounts.length
                ? h('div', { className: 'ks-grid ks-insights-grid' }, accounts.map((account) => h(AccountCard, {
                    key: account.id,
                    account,
                    canManage: props.canManage,
                    busy: Number(props.busyId) === Number(account.id),
                    onEdit: props.onEdit,
                    onToggle: props.onToggle,
                })))
                : h('div', { className: 'ks-insights-group__empty' },
                    h(KS.Empty, {
                        compact: true,
                        icon,
                        title: t('insights_group_empty', { group: group.kind_label }),
                        text: props.canManage ? t('insights_group_empty_manage') : null,
                        action: props.canManage ? h(KS.Button, { variant: 'soft', size: 'sm', icon: 'plus', onClick: () => props.onAdd(group.kind) }, t('insights_add_account')) : null,
                    })),
        );
    }

    /* ================================================================== */
    /* 3. Ekle / duzenle penceresi                                         */
    /* ================================================================== */

    function AccountModal(props) {
        const account = props.account || null;
        const platforms = useMemo(platformOptions, []);
        const initial = useMemo(() => formOf(account, props.defaultKind), []);
        const formId = useMemo(() => KS.uid('ks-insights-form'), []);
        const [form, setForm] = useState(initial);
        const [errors, setErrors] = useState({});
        const [saving, setSaving] = useState(false);
        const formRef = useRef(null);
        const alive = useRef(true);
        const focusInvalid = useRef(false);
        const noteMax = Number(KS.limits().note_max) || 4000;
        const dirty = snapshot(form) !== snapshot(initial);

        useEffect(() => {
            alive.current = true;

            return () => {
                alive.current = false;
            };
        }, []);

        // Dogrulama sonrasi ilk hatali alana odaklanilir.
        useEffect(() => {
            if (!focusInvalid.current) {
                return;
            }

            focusInvalid.current = false;

            const node = formRef.current ? formRef.current.querySelector('[aria-invalid="true"]') : null;

            if (node && typeof node.focus === 'function') {
                node.focus();
            }
        }, [errors]);

        const clearError = (name) => {
            if (errors[name]) {
                setErrors((current) => KS.omit(current, [name]));
            }
        };

        const setField = (name, value, errorKey) => {
            setForm((current) => Object.assign({}, current, { [name]: value }));
            clearError(errorKey || name);
        };

        const setLink = (platform, value) => {
            setForm((current) => Object.assign({}, current, { links: Object.assign({}, current.links, { [platform]: value }) }));
            clearError('link_' + platform);
        };

        const requestClose = () => {
            if (saving) {
                return;
            }

            if (!dirty) {
                props.onClose();

                return;
            }

            KS.confirm({
                text: t('unsaved_changes_confirm'),
                confirmLabel: t('discard'),
                cancelLabel: t('keep_editing'),
                danger: true,
            }).then((ok) => {
                if (ok && alive.current) {
                    props.onClose();
                }
            });
        };

        const validate = (draft) => {
            const found = {};

            if (!draft.kind) {
                found.kind = t('required_field');
            }

            if (draft.name.trim() === '') {
                found.name = t('required_field');
            }

            if (draft.sortOrder !== '') {
                const order = Number(draft.sortOrder);

                if (!Number.isInteger(order) || order < 0 || order > SORT_MAX) {
                    found.sort_order = t('insights_sort_order_invalid', { max: fmt.number(SORT_MAX) });
                }
            }

            platforms.forEach((platform) => {
                const value = draft.links[platform.value] || '';

                if (value !== '' && !isValidUrl(value)) {
                    found['link_' + platform.value] = t('insights_url_invalid');
                }
            });

            return found;
        };

        const submit = (event) => {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (saving) {
                return;
            }

            const links = {};

            platforms.forEach((platform) => {
                links[platform.value] = normalizeUrl(form.links[platform.value]);
            });

            const draft = Object.assign({}, form, { links });
            const found = validate(draft);

            setForm(draft);

            if (Object.keys(found).length) {
                focusInvalid.current = true;
                setErrors(found);

                return;
            }

            const sentLinks = platforms
                .map((platform) => ({ platform: platform.value, url: links[platform.value] }))
                .filter((link) => link.url !== '');
            const subtitle = draft.subtitle.trim();
            const note = draft.note.trim();
            const body = {
                kind: draft.kind,
                name: draft.name.trim(),
                subtitle: subtitle === '' ? null : subtitle,
                note: note === '' ? null : note,
                status: draft.active ? 'active' : 'inactive',
                sort_order: draft.sortOrder === '' ? null : Number(draft.sortOrder),
                links: sentLinks,
            };

            setErrors({});
            setSaving(true);

            api.post(account ? url('watch.update', account.id) : url('watch.store'), body).then((payload) => {
                KS.toast.success(t(account ? 'insights_account_updated' : 'insights_account_added'));
                props.onSaved(payload && payload.account ? payload.account : null);
            }).catch((error) => {
                if (!alive.current) {
                    return;
                }

                setSaving(false);

                const mapped = mapServerErrors(error, sentLinks);

                if (Object.keys(mapped).length) {
                    focusInvalid.current = true;
                    setErrors(mapped);
                    KS.toast.error(t('invalid_input'));
                } else {
                    KS.handleError(error);
                }
            });
        };

        const linkField = (platform) => {
            const key = 'link_' + platform.value;
            const label = platform.label || KS.optionLabel('platforms', platform.value);

            return h(KS.Field, {
                key: platform.value,
                error: errors[key],
                // Field etiketi bir dizi icinde cizer; anahtar React uyarisini onler.
                label: h('span', { key: 'label', className: 'ks-insights-linklabel' },
                    h(KS.PlatformIcon, { platform: platform.value, variant: 'badge', size: 'sm' }),
                    h('span', null, label),
                ),
            }, h(KS.TextInput, {
                type: 'url',
                inputMode: 'url',
                autoCapitalize: 'none',
                spellCheck: false,
                maxLength: URL_MAX,
                clearable: true,
                disabled: saving,
                placeholder: t('insights_url_placeholder'),
                value: form.links[platform.value] || '',
                onChange: (value) => setLink(platform.value, value),
                onBlur: (event) => {
                    const fixed = normalizeUrl(event.target.value);

                    if (fixed !== event.target.value) {
                        setLink(platform.value, fixed);
                    }
                },
            }));
        };

        return h(KS.Modal, {
            title: account ? t('insights_edit_account') : t('insights_add_account'),
            subtitle: t('insights_account_modal_subtitle'),
            icon: account ? 'edit' : 'plus',
            size: 'lg',
            closeOnBackdrop: false,
            onClose: requestClose,
            footer: [
                h(KS.Button, { key: 'cancel', variant: 'ghost', disabled: saving, onClick: requestClose }, t('cancel')),
                h(KS.Button, { key: 'save', variant: 'primary', icon: 'check', type: 'submit', form: formId, loading: saving }, t('save')),
            ],
        },
            h('form', { id: formId, ref: formRef, className: 'ks-form ks-insights-form', noValidate: true, onSubmit: submit },
                h('div', { className: 'ks-form-row ks-form-row--2' },
                    h(KS.Field, { label: t('insights_field_name'), required: true, error: errors.name },
                        h(KS.TextInput, {
                            value: form.name,
                            maxLength: NAME_MAX,
                            disabled: saving,
                            placeholder: t('insights_field_name_placeholder'),
                            'data-autofocus': '',
                            onChange: (value) => setField('name', value),
                        })),
                    h(KS.Field, { label: t('insights_field_subtitle'), error: errors.subtitle, hint: t('insights_field_subtitle_hint') },
                        h(KS.TextInput, {
                            value: form.subtitle,
                            maxLength: NAME_MAX,
                            disabled: saving,
                            onChange: (value) => setField('subtitle', value),
                        })),
                ),
                h('div', { className: 'ks-form-row ks-form-row--2' },
                    h(KS.Field, { label: t('insights_field_kind'), required: true, error: errors.kind },
                        h(KS.Select, {
                            options: props.kinds,
                            value: form.kind,
                            placeholder: props.kinds.some((item) => String(item.value) === form.kind) ? false : undefined,
                            disabled: saving,
                            onChange: (value) => setField('kind', String(value)),
                        })),
                    h(KS.Field, { label: t('insights_field_sort_order'), error: errors.sort_order, hint: t('insights_field_sort_order_hint') },
                        h(KS.TextInput, {
                            value: form.sortOrder,
                            inputMode: 'numeric',
                            maxLength: 5,
                            disabled: saving,
                            onChange: (value) => setField('sortOrder', String(value).replace(/[^\d]/g, '').slice(0, 5), 'sort_order'),
                        })),
                ),
                h(KS.Field, { label: t('note'), error: errors.note, hint: t('insights_field_note_hint') },
                    h(KS.TextArea, {
                        value: form.note,
                        rows: 3,
                        autoGrow: true,
                        maxRows: 10,
                        maxLength: noteMax,
                        counter: true,
                        disabled: saving,
                        onChange: (value) => setField('note', value),
                    })),
                h('div', { className: 'ks-insights-form__status' },
                    h(KS.Switch, {
                        checked: form.active,
                        disabled: saving,
                        label: t('insights_field_active'),
                        hint: t('insights_field_active_hint'),
                        onChange: (checked) => setField('active', checked, 'status'),
                    }),
                    errors.status ? h('p', { className: 'ks-field__error', role: 'alert' }, h(KS.Icon, { name: 'alert' }), h('span', null, errors.status)) : null,
                ),
                h(KS.Divider, { label: t('insights_links_section') }),
                h('p', { className: 'ks-insights-form__hint' }, t('insights_links_hint')),
                h('div', { className: 'ks-form-row ks-insights-form__links' }, platforms.map(linkField)),
            ),
        );
    }

    /* ================================================================== */
    /* 4. Katalog                                                          */
    /* ================================================================== */

    function catalogMeta(info) {
        return [
            info.revision_label,
            info.size_human,
            info.updated_at ? t('insights_catalog_updated', { date: fmt.date(info.updated_at) }) : null,
        ].filter(Boolean).join(' · ');
    }

    /** Onizleme: PDF / duz metin <iframe>, gorsel <img>. variant: 'aside' | 'pane' | 'reader'. */
    function CatalogViewer(props) {
        const info = props.info;
        const mime = String(info.mime || '').toLowerCase();
        const base = KS.safeUrl(info.preview_url);
        const isImage = mime.indexOf('image/') === 0;
        const src = base && mime === 'application/pdf' && base.indexOf('#') === -1 ? base + '#view=FitH' : base;
        const title = info.title || t('insights_catalog');
        const [phase, setPhase] = useState('loading');   // loading | ready | failed

        useEffect(() => {
            setPhase('loading');

            if (isImage) {
                return undefined;
            }

            // Bazi tarayicilar gomulu PDF icin `load` olayi uretmez; gosterge sonsuza kadar donmesin.
            const timer = window.setTimeout(() => setPhase((current) => (current === 'loading' ? 'ready' : current)), FRAME_TIMEOUT);

            return () => window.clearTimeout(timer);
        }, [src, isImage]);

        if (!src) {
            return null;
        }

        let content = null;

        if (phase === 'failed') {
            content = h(KS.Empty, { compact: true, icon: 'warning', title: t('insights_catalog_preview_failed'), text: t('insights_catalog_preview_failed_text') });
        } else if (isImage) {
            content = h('img', {
                className: 'ks-insights-viewer__image',
                src,
                alt: title,
                decoding: 'async',
                onLoad: () => setPhase('ready'),
                onError: () => setPhase('failed'),
            });
        } else {
            content = h('iframe', {
                className: 'ks-insights-viewer__frame',
                src,
                title,
                onLoad: () => setPhase('ready'),
            });
        }

        return h('div', {
            className: cx('ks-insights-viewer', 'ks-insights-viewer--' + (props.variant || 'aside'), isImage && 'ks-insights-viewer--image'),
            'aria-busy': phase === 'loading' ? 'true' : undefined,
        },
            content,
            phase === 'loading' ? h('div', { className: 'ks-insights-viewer__loading' }, h(KS.Spinner, { size: 'lg', label: t('insights_catalog_loading') })) : null,
        );
    }

    function CatalogSkeleton() {
        return h('div', { className: 'ks-insights-catalog__skeleton', role: 'status', 'aria-label': t('loading') },
            h('div', { className: 'ks-row' },
                h(KS.Skeleton, { variant: 'rect', width: '7.5rem', height: '2.25rem', radius: 10 }),
                h(KS.Skeleton, { variant: 'rect', width: '6rem', height: '2.25rem', radius: 10 }),
            ),
            h(KS.Skeleton, { variant: 'rect', className: 'ks-insights-viewer-skeleton' }),
        );
    }

    function CatalogFile(props) {
        const info = props.info;
        const meta = [info.size_human, info.revision_label].filter(Boolean).join(' · ');

        return h('div', { className: 'ks-insights-file' },
            h('div', { className: 'ks-insights-file__row' },
                h('span', { className: 'ks-insights-file__icon', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'document' })),
                h('div', { className: 'ks-insights-file__text' },
                    h('p', { className: 'ks-insights-file__name' }, info.file_name || info.title || t('insights_catalog')),
                    meta ? h('p', { className: 'ks-insights-file__meta' }, meta) : null,
                ),
            ),
            h('p', { className: 'ks-insights-file__hint' }, props.hint),
        );
    }

    function CatalogPanel(props) {
        const variant = props.variant || 'aside';
        const hasEndpoint = KS.hasEndpoint('catalog');
        const catalog = KS.useResource(() => (hasEndpoint ? api.get(url('catalog')) : Promise.resolve({ available: false })), [hasEndpoint]);
        const [reader, setReader] = useState(false);
        const info = catalog.data || null;
        const available = !!(info && info.available);
        const previewUrl = available ? KS.safeUrl(info.preview_url) : null;
        const downloadUrl = available ? KS.safeUrl(info.download_url) : null;
        const embeddable = !!(available && info.previewable && previewUrl && canEmbed(info));
        const meta = available ? catalogMeta(info) : '';
        let body = null;

        if (!info && catalog.loading) {
            body = h(CatalogSkeleton);
        } else if (!info && catalog.error) {
            body = h(KS.ErrorState, { compact: true, error: catalog.error, onRetry: catalog.reload });
        } else if (!available) {
            body = h(KS.Empty, {
                icon: 'book',
                title: t('insights_catalog_empty_title'),
                text: t('insights_catalog_empty_text'),
                className: 'ks-insights-catalog__empty',
            });
        } else {
            body = h(Fragment, null,
                h('div', { className: 'ks-insights-catalog__actions' },
                    embeddable ? h(KS.Button, { variant: 'primary', size: 'sm', icon: 'expand', onClick: () => setReader(true) }, t('insights_catalog_read')) : null,
                    previewUrl ? h(KS.Button, { variant: 'ghost', size: 'sm', icon: 'external', href: previewUrl, target: '_blank' }, t('open_in_new_tab')) : null,
                    downloadUrl ? h(KS.Button, { variant: embeddable || previewUrl ? 'ghost' : 'primary', size: 'sm', icon: 'download', href: downloadUrl, download: info.file_name || true }, t('download')) : null,
                ),
                catalog.error ? h(KS.Notice, { tone: 'warning', compact: true, className: 'ks-insights-catalog__notice' }, KS.describeError(catalog.error)) : null,
                embeddable
                    ? h(CatalogViewer, { info, variant })
                    : h(CatalogFile, { info, hint: info.previewable && previewUrl ? t('insights_catalog_no_inline') : t('insights_catalog_download_only') }),
            );
        }

        return h('section', { className: cx('ks-insights-catalog', 'ks-insights-catalog--' + variant), 'aria-label': t('insights_catalog') },
            h('header', { className: 'ks-insights-catalog__head' },
                h('span', { className: 'ks-insights-catalog__icon', 'aria-hidden': 'true' }, h(KS.Icon, { name: 'book' })),
                h('div', { className: 'ks-insights-catalog__titles' },
                    h('p', { className: 'ks-eyebrow' }, t('insights_catalog')),
                    h('h3', { className: 'ks-insights-catalog__title' }, (info && info.title) || t('insights_catalog_title')),
                    meta ? h('p', { className: 'ks-insights-catalog__meta' }, meta) : null,
                ),
                h(KS.IconButton, { icon: 'refresh', label: t('refresh'), variant: 'plain', size: 'sm', loading: catalog.loading && !!info, onClick: catalog.reload }),
            ),
            h('div', { className: 'ks-insights-catalog__body' }, body),
            reader && embeddable ? h(KS.Modal, {
                title: info.title || t('insights_catalog'),
                subtitle: meta || undefined,
                icon: 'book',
                size: 'full',
                padded: false,
                className: 'ks-insights-reader',
                bodyClassName: 'ks-insights-reader__body',
                onClose: () => setReader(false),
                headerActions: [
                    h(KS.IconButton, { key: 'open', icon: 'external', label: t('open_in_new_tab'), variant: 'plain', href: previewUrl, target: '_blank' }),
                    downloadUrl ? h(KS.IconButton, { key: 'download', icon: 'download', label: t('download'), variant: 'plain', href: downloadUrl, download: info.file_name || true }) : null,
                ],
            }, h(CatalogViewer, { info, variant: 'reader' })) : null,
        );
    }

    /* ================================================================== */
    /* 5. Gorunum                                                          */
    /* ================================================================== */

    function segmentLabel(label, count) {
        return h('span', { className: 'ks-insights-seg' },
            h('span', null, label),
            h('span', { className: 'ks-insights-seg__count' }, fmt.number(count)),
        );
    }

    KS.views.Insights = function Insights() {
        const profile = KS.useProfile();
        const abilities = KS.useAbilities();
        const profileId = profile ? profile.id : null;
        const canManage = !!abilities.manage_data && KS.hasEndpoint('watch.store') && KS.hasEndpoint('watch.update');
        const rootRef = useRef(null);
        const alive = useRef(true);
        const wide = useContainerWide(rootRef, WIDE_MIN);
        const [tab, setTab] = useState('accounts');
        const [catalogSeen, setCatalogSeen] = useState(false);
        const [kind, setKind] = useState('all');
        const [query, setQuery] = useState('');
        const [showInactive, setShowInactive] = useState(false);
        const [editor, setEditor] = useState(null);      // { account | null, kind }
        const [busyId, setBusyId] = useState(null);
        const needle = fold(KS.useDebounced(query, 200));
        const includeInactive = canManage && showInactive;

        const watch = KS.useResource(() => {
            if (!profileId) {
                return Promise.resolve({ profileId: null, groups: [] });
            }

            return api.get(url('watch'), { profile: profileId, include_inactive: includeInactive ? 1 : undefined })
                .then((payload) => ({ profileId, groups: payload && Array.isArray(payload.groups) ? payload.groups : [] }));
        }, [profileId, includeInactive]);

        useEffect(() => {
            alive.current = true;

            return () => {
                alive.current = false;
            };
        }, []);

        // Genis duzende katalog bir kez baglaninca dar duzene donuste de bagli kalir (PDF yeniden yuklenmez).
        useEffect(() => {
            if (wide) {
                setCatalogSeen(true);
            }
        }, [wide]);

        // Onceki hesabin verisi yeni hesap yuklenirken gosterilmez.
        const fresh = !!watch.data && watch.data.profileId === profileId;
        const groups = fresh ? watch.data.groups : EMPTY;

        const filtered = useMemo(() => groups.map((group) => Object.assign({}, group, {
            accounts: (group.accounts || []).filter((account) => matches(account, needle)),
        })), [groups, needle]);

        const total = countAccounts(groups);
        const activeKind = kind !== 'all' && groups.some((group) => group.kind === kind) ? kind : 'all';
        const visibleGroups = activeKind === 'all' ? filtered : filtered.filter((group) => group.kind === activeKind);
        const visibleCount = countAccounts(visibleGroups);

        const kindOptions = groups.length
            ? groups.map((group) => ({ value: group.kind, label: group.kind_label }))
            : KS.options('watch_kinds').map((item) => ({ value: item.value, label: item.label }));

        const openAdd = (kindValue) => {
            const fallback = activeKind !== 'all' ? activeKind : (kindOptions[0] ? kindOptions[0].value : '');

            setEditor({ account: null, kind: typeof kindValue === 'string' && kindValue ? kindValue : fallback });
        };

        const openEdit = (account) => setEditor({ account, kind: account.kind });

        const onSaved = (account) => {
            setEditor(null);

            if (!account || !account.id) {
                watch.reload();

                return;
            }

            watch.setData((data) => applyAccount(data, account, includeInactive));
            KS.events.emit('watch', account);

            // Kaydedilen hesap secili turun disindaysa gorunur kalmasi icin o ture gecilir.
            if (activeKind !== 'all' && account.kind !== activeKind) {
                setKind(groups.some((group) => group.kind === account.kind) ? account.kind : 'all');
            }
        };

        const toggleStatus = (account) => {
            const activate = account.status !== 'active';

            const run = () => {
                setBusyId(account.id);

                // `links` gonderilmez: baglantilara dokunulmaz (SocialWatchAccountService::update).
                api.post(url('watch.update', account.id), { kind: account.kind, name: account.name, status: activate ? 'active' : 'inactive' })
                    .then((payload) => {
                        const saved = payload && payload.account ? payload.account : null;

                        if (saved && saved.id) {
                            watch.setData((data) => applyAccount(data, saved, includeInactive));
                            KS.events.emit('watch', saved);
                        } else {
                            watch.reload();
                        }

                        KS.toast.success(t(activate ? 'insights_account_activated' : 'insights_account_deactivated'));
                    })
                    .catch((error) => KS.handleError(error))
                    .then(() => {
                        if (alive.current) {
                            setBusyId(null);
                        }
                    });
            };

            if (activate) {
                run();

                return;
            }

            KS.confirm({
                title: t('insights_deactivate_title'),
                text: t('insights_deactivate_text', { name: account.name }),
                confirmLabel: t('insights_deactivate'),
                icon: 'eye-off',
            }).then((ok) => {
                if (ok) {
                    run();
                }
            });
        };

        if (!profile) {
            return h('div', { ref: rootRef, className: 'ks-insights' },
                h(KS.Empty, { icon: 'users', title: t('insights_no_profile_title'), text: t('insights_no_profile_text') }));
        }

        const showAccounts = wide || tab === 'accounts';
        const showCatalog = wide || tab === 'catalog';
        const mountCatalog = showCatalog || catalogSeen;

        const segments = [{ value: 'all', label: segmentLabel(t('all'), countAccounts(filtered)) }].concat(filtered.map((group) => ({
            value: group.kind,
            icon: KIND_ICONS[group.kind] || 'users',
            label: segmentLabel(group.kind_label, group.accounts.length),
        })));

        let accountsBody = null;

        if (!fresh && watch.error) {
            accountsBody = h(KS.ErrorState, { error: watch.error, onRetry: watch.reload });
        } else if (!fresh) {
            accountsBody = h(AccountsSkeleton);
        } else if (needle && visibleCount === 0) {
            accountsBody = h(KS.Empty, {
                icon: 'search',
                title: t('no_results'),
                text: t('insights_no_match', { query: query.trim() }),
                action: h(KS.Button, { variant: 'soft', icon: 'close', onClick: () => setQuery('') }, t('insights_clear_search')),
            });
        } else if (!needle && total === 0) {
            accountsBody = h('div', { className: 'ks-insights-blank' },
                h(KS.Empty, {
                    icon: 'sparkles',
                    title: t('insights_empty_title'),
                    text: canManage ? t('insights_empty_text_manage') : t('insights_empty_text'),
                    action: canManage ? h(KS.Button, { variant: 'primary', icon: 'plus', onClick: () => openAdd() }, t('insights_add_account')) : null,
                }));
        } else {
            accountsBody = visibleGroups
                .filter((group) => !needle || group.accounts.length > 0)
                .map((group) => h(GroupSection, {
                    key: group.kind,
                    group,
                    canManage,
                    busyId,
                    onAdd: openAdd,
                    onEdit: openEdit,
                    onToggle: toggleStatus,
                }));
        }

        return h('div', { ref: rootRef, className: cx('ks-insights', wide && 'ks-insights--wide') },
            h('header', { className: 'ks-insights-head' },
                h('div', { className: 'ks-insights-head__text' },
                    h('h2', { className: 'ks-title' }, t('insights_title')),
                    h('p', { className: 'ks-subtitle' }, t('insights_intro', { profile: profile.name })),
                ),
                canManage && showAccounts ? h('div', { className: 'ks-insights-head__actions' },
                    h(KS.Button, { variant: 'primary', icon: 'plus', onClick: () => openAdd() }, t('insights_add_account')),
                ) : null,
            ),
            wide ? null : h(KS.Tabs, {
                label: t('insights_title'),
                stretch: true,
                value: tab,
                onChange: (value) => {
                    setTab(value);

                    if (value === 'catalog') {
                        setCatalogSeen(true);
                    }
                },
                items: [
                    { value: 'accounts', label: t('insights_accounts'), icon: 'users', count: fresh ? total : undefined },
                    { value: 'catalog', label: t('insights_catalog'), icon: 'book' },
                ],
            }),
            h('div', { className: 'ks-insights-layout' },
                h('div', { className: cx('ks-insights-main', !showAccounts && 'ks-hidden'), role: wide ? undefined : 'tabpanel', 'aria-label': t('insights_accounts') },
                    h('div', { className: 'ks-toolbar ks-toolbar--boxed ks-insights-toolbar' },
                        groups.length > 1 ? h('div', { className: 'ks-insights-kinds' },
                            h(KS.Segmented, { label: t('insights_kind_filter'), items: segments, value: activeKind, onChange: (value) => setKind(String(value)) }),
                        ) : null,
                        h(KS.TextInput, {
                            type: 'search',
                            icon: 'search',
                            clearable: true,
                            value: query,
                            placeholder: t('insights_search_placeholder'),
                            'aria-label': t('insights_search_placeholder'),
                            onChange: (value) => setQuery(value),
                        }),
                        canManage ? h('div', { className: 'ks-insights-toolbar__switch' },
                            h(KS.Switch, { checked: showInactive, label: t('insights_show_inactive'), onChange: (checked) => setShowInactive(checked) }),
                        ) : null,
                        h(KS.IconButton, { icon: 'refresh', label: t('refresh'), variant: 'plain', loading: watch.loading && fresh, onClick: watch.reload }),
                    ),
                    fresh && watch.error ? h(KS.Notice, {
                        tone: 'warning',
                        compact: true,
                        action: h(KS.Button, { variant: 'soft', size: 'sm', icon: 'refresh', onClick: watch.reload }, t('retry')),
                    }, KS.describeError(watch.error)) : null,
                    accountsBody,
                ),
                mountCatalog ? h('aside', { className: cx('ks-insights-aside', !showCatalog && 'ks-hidden'), role: wide ? undefined : 'tabpanel', 'aria-label': t('insights_catalog') },
                    h(CatalogPanel, { variant: wide ? 'aside' : 'pane' }),
                ) : null,
            ),
            editor ? h(AccountModal, {
                key: editor.account ? 'edit-' + editor.account.id : 'add-' + editor.kind,
                account: editor.account,
                defaultKind: editor.kind,
                kinds: kindOptions,
                onClose: () => setEditor(null),
                onSaved,
            }) : null,
        );
    };
}());
