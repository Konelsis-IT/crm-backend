/*
 * Konelsis kurum ici sohbet arayuzu (D-83, 11 Eylul 2026).
 *
 * React 18 (UMD, derleme adimi yok; JSX yerine React.createElement) ile
 * yazilmistir. Sunucuyla JSON uclari uzerinden konusur (ChatController);
 * canli veri kisa aralikli yoklamayla gelir: liste/cevrimici 4 sn, acik
 * sohbetin mesajlari 2,5 sn. Baglanti: launcher.blade.php icindeki
 * #konelsis-chat-root data-config.
 */
(function () {
    'use strict';

    const root = document.getElementById('konelsis-chat-root');

    if (! root || ! window.React || ! window.ReactDOM) {
        return;
    }

    const config = JSON.parse(root.dataset.config || '{}');
    const labels = config.labels || {};
    const { useState, useEffect, useRef, useCallback, useMemo } = React;
    const h = React.createElement;

    const t = (key, params) => {
        let text = labels[key] || key;

        if (params) {
            Object.keys(params).forEach((name) => {
                text = text.replace(new RegExp(':' + name, 'g'), String(params[name]));
            });
        }

        return text;
    };

    /* ------------------------------------------------------------------ */
    /* HTTP                                                                 */
    /* ------------------------------------------------------------------ */

    const endpoints = config.endpoints || {};
    const conversationUrl = (id, suffix) => endpoints.conversation.replace('__ID__', String(id)).replace(/\/messages$/, '') + (suffix || '');
    const messageUrl = (id) => endpoints.message.replace('__ID__', String(id));

    async function request(method, url, body, isForm) {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': config.csrf,
        };

        const init = { method, headers, credentials: 'same-origin' };

        if (body !== undefined && body !== null) {
            if (isForm) {
                init.body = body;
            } else {
                headers['Content-Type'] = 'application/json';
                init.body = JSON.stringify(body);
            }
        }

        const response = await fetch(url, init);
        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (! response.ok) {
            const message = (payload && (payload.message || (payload.errors && Object.values(payload.errors).flat().join(' ')))) || t('error_generic');
            const failure = new Error(message);
            failure.status = response.status;
            throw failure;
        }

        return payload;
    }

    const api = {
        get: (url, params) => {
            const query = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')).toString() : '';

            return request('GET', url + query);
        },
        post: (url, body) => request('POST', url, body),
        form: (url, formData) => request('POST', url, formData, true),
        del: (url) => request('DELETE', url),
    };

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    const locale = config.locale === 'en' ? 'en-GB' : 'tr-TR';

    function sameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function formatTime(iso) {
        if (! iso) {
            return '';
        }

        return new Date(iso).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
    }

    function formatListTime(iso) {
        if (! iso) {
            return '';
        }

        const date = new Date(iso);
        const now = new Date();

        if (sameDay(date, now)) {
            return formatTime(iso);
        }

        return date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });
    }

    function formatDayLabel(iso) {
        const date = new Date(iso);
        const now = new Date();
        const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);

        if (sameDay(date, now)) {
            return t('today');
        }

        if (sameDay(date, yesterday)) {
            return t('yesterday');
        }

        return date.toLocaleDateString(locale, { day: '2-digit', month: 'long', year: 'numeric' });
    }

    const URL_PATTERN = /((?:https?:\/\/|www\.)[^\s<]+)/gi;

    function isInAppUrl(url) {
        try {
            const parsed = new URL(url, window.location.origin);

            return parsed.origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    /** Metindeki adresleri tiklanabilir yapar. */
    function linkify(text) {
        if (! text) {
            return null;
        }

        const parts = text.split(URL_PATTERN);

        return parts.map((part, index) => {
            if (index % 2 === 1) {
                const href = part.startsWith('www.') ? 'https://' + part : part;
                const inApp = isInAppUrl(href);

                return h('a', {
                    key: index,
                    href,
                    className: 'kc-inline-link',
                    target: inApp ? '_self' : '_blank',
                    rel: inApp ? undefined : 'noopener noreferrer',
                }, part);
            }

            return part;
        });
    }

    function Avatar({ person, size, online, className }) {
        const classes = ['kc-avatar', size ? 'kc-avatar-' + size : '', className || ''].join(' ');

        return h('span', { className: classes, title: person && person.name },
            person && person.photo
                ? h('img', { src: person.photo, alt: person.name || '' })
                : h('span', { className: 'kc-avatar-initials' }, (person && person.initials) || '?'),
            online === true ? h('span', { className: 'kc-presence kc-presence-on', title: t('online') }) : null,
            online === false ? h('span', { className: 'kc-presence kc-presence-off', title: t('offline') }) : null,
        );
    }

    function Icon({ name }) {
        const paths = {
            chat: 'M8 10h8M8 14h5M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.2A8 8 0 1 1 21 12z',
            close: 'M6 6l12 12M18 6L6 18',
            back: 'M15 18l-6-6 6-6',
            send: 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',
            clip: 'M21 11.5l-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l9-9a3.5 3.5 0 0 1 5 5l-9 9a1.5 1.5 0 0 1-2.1-2.1L16 7',
            doc: 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5M9 13h6M9 17h6',
            link: 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7',
            pin: 'M12 17v5M5 17h14l-1.5-5H6.5zM9 3h6v9H9z',
            trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
            lock: 'M6 11V8a6 6 0 0 1 12 0v3M5 11h14v10H5z',
            external: 'M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5',
            search: 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4-4',
            users: 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
            download: 'M12 3v12M6 11l6 6 6-6M4 21h16',
            check: 'M5 13l4 4L19 7',
            plus: 'M12 5v14M5 12h14',
            ticket: 'M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4zM13 6v2M13 11v2M13 16v2',
        };

        return h('svg', { className: 'kc-icon', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': 'true' },
            h('path', { d: paths[name] || '' }));
    }

    function useInterval(callback, delay, enabled) {
        const saved = useRef(callback);
        saved.current = callback;

        useEffect(() => {
            if (! enabled || ! delay) {
                return undefined;
            }

            const id = window.setInterval(() => saved.current(), delay);

            return () => window.clearInterval(id);
        }, [delay, enabled]);
    }

    /* ------------------------------------------------------------------ */
    /* Uygulama                                                             */
    /* ------------------------------------------------------------------ */

    function App() {
        const [open, setOpen] = useState(false);
        const [tab, setTab] = useState('chats');
        const [me, setMe] = useState(null);
        const [conversations, setConversations] = useState([]);
        const [activeId, setActiveId] = useState(null);
        const [profile, setProfile] = useState(null);
        const [groupInfo, setGroupInfo] = useState(null);
        const [error, setError] = useState(null);
        const [poll, setPoll] = useState({ sync: 4000, messages: 2500 });
        const [maxUploadKb, setMaxUploadKb] = useState(20480);
        const [ready, setReady] = useState(false);

        const unreadTotal = useMemo(() => conversations.reduce((sum, item) => sum + (item.unread || 0), 0), [conversations]);
        const active = useMemo(() => conversations.find((item) => item.id === activeId) || null, [conversations, activeId]);

        const showError = useCallback((failure) => {
            setError(failure && failure.message ? failure.message : t('error_generic'));
            window.setTimeout(() => setError(null), 4000);
        }, []);

        const refresh = useCallback(async () => {
            if (document.hidden) {
                return;
            }

            try {
                const payload = await api.get(endpoints.sync);
                const next = payload.conversations || [];

                // Hic mesaj yazilmamis sohbet sunucu listesinde gorunmez
                // (11 Eylul 2026, kullanici bildirimi). Su an acik olan
                // sohbet ilk mesaj atilana kadar bu yuzden kaybolabilir;
                // yoklama onu listeden dusurmesin diye korunur.
                setConversations((current) => {
                    if (! activeId || next.some((item) => item.id === activeId)) {
                        return next;
                    }

                    const active = current.find((item) => item.id === activeId);

                    return active ? [active, ...next] : next;
                });
            } catch (failure) {
                // Sessiz: bir sonraki yoklamada tekrar denenir.
            }
        }, [activeId]);

        useEffect(() => {
            let cancelled = false;

            api.get(endpoints.bootstrap).then((payload) => {
                if (cancelled) {
                    return;
                }

                setMe(payload.me);
                setConversations(payload.conversations || []);
                setPoll(payload.poll || { sync: 4000, messages: 2500 });
                setMaxUploadKb(payload.max_upload_kb || 20480);
                setReady(true);

                // Talep kartindaki "Sohbeti ac" (?sohbet=ID) paneli o sohbette acar.
                const wanted = Number(new URLSearchParams(window.location.search).get('sohbet') || 0);

                if (wanted && (payload.conversations || []).some((item) => item.id === wanted)) {
                    setActiveId(wanted);
                    setOpen(true);
                }
            }).catch(() => {
                // Sohbet kapaliysa (B12A yok) dugme hic gorunmez.
            });

            return () => {
                cancelled = true;
            };
        }, []);

        useInterval(refresh, poll.sync, ready);

        useEffect(() => {
            const onKey = (event) => {
                if (event.key === 'Escape' && open) {
                    if (profile) {
                        setProfile(null);
                    } else if (groupInfo) {
                        setGroupInfo(null);
                    } else if (activeId) {
                        setActiveId(null);
                    } else {
                        setOpen(false);
                    }
                }
            };

            window.addEventListener('keydown', onKey);

            return () => window.removeEventListener('keydown', onKey);
        }, [open, profile, groupInfo, activeId]);

        const openConversation = useCallback((conversation) => {
            setConversations((current) => current.some((item) => item.id === conversation.id) ? current : [conversation, ...current]);
            setActiveId(conversation.id);
            setTab('chats');
            setProfile(null);
            setGroupInfo(null);
        }, []);

        const startDirect = useCallback(async (person) => {
            try {
                const payload = await api.post(endpoints.conversations, { personnel_id: person.id });

                if (payload.conversation) {
                    openConversation(payload.conversation);
                }
            } catch (failure) {
                showError(failure);
            }
        }, [openConversation, showError]);

        useEffect(() => {
            if (! ready) {
                return;
            }

            // Personel kartindaki "Sohbet baslat" (?sohbet_kisi=ID) o kisiyle
            // birebir sohbeti acar; yoksa olusturur (direct() find-or-create).
            const wantedPerson = Number(new URLSearchParams(window.location.search).get('sohbet_kisi') || 0);

            if (wantedPerson) {
                setOpen(true);
                startDirect({ id: wantedPerson });
            }
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [ready]);

        const createGroup = useCallback(async (title, ids) => {
            try {
                const payload = await api.post(endpoints.conversations, { title, personnel_ids: ids });

                if (payload.conversation) {
                    openConversation(payload.conversation);
                }
            } catch (failure) {
                showError(failure);
            }
        }, [openConversation, showError]);

        const togglePin = useCallback(async (conversation) => {
            try {
                const payload = await api.post(conversationUrl(conversation.id, '/pin'));
                setConversations((current) => current.map((item) => item.id === conversation.id ? { ...item, pinned: payload.pinned } : item));
            } catch (failure) {
                showError(failure);
            }
        }, [showError]);

        const deleteConversation = useCallback(async (conversation) => {
            try {
                await api.del(conversationUrl(conversation.id));
                setConversations((current) => current.filter((item) => item.id !== conversation.id));

                if (activeId === conversation.id) {
                    setActiveId(null);
                }
            } catch (failure) {
                showError(failure);
            }
        }, [activeId, showError]);

        const markLocalRead = useCallback((conversationId, sequence) => {
            setConversations((current) => current.map((item) => item.id === conversationId ? { ...item, unread: 0, last_sequence: Math.max(item.last_sequence || 0, sequence || 0) } : item));
        }, []);

        if (! ready || ! me) {
            return null;
        }

        return h(React.Fragment, null,
            h('button', {
                type: 'button',
                className: 'kc-launcher' + (open ? ' kc-launcher-open' : ''),
                onClick: () => setOpen(! open),
                'aria-label': open ? t('close') : t('open'),
                title: open ? t('close') : t('open'),
            },
                h(Icon, { name: open ? 'close' : 'chat' }),
                unreadTotal > 0 && ! open ? h('span', { className: 'kc-badge', 'aria-label': unreadTotal + ' ' + t('unread_badge') }, unreadTotal > 99 ? '99+' : unreadTotal) : null,
            ),
            open ? h('section', { className: 'kc-panel', role: 'dialog', 'aria-label': t('title') },
                error ? h('div', { className: 'kc-toast', role: 'alert' }, error) : null,
                profile
                    ? h(ProfileCard, { person: profile, onBack: () => setProfile(null), onMessage: (person) => { setProfile(null); startDirect(person); } })
                    : groupInfo
                        ? h(GroupInfo, { conversation: groupInfo, onBack: () => setGroupInfo(null), onPerson: (person) => setProfile(person) })
                        : active
                        ? h(ChatView, {
                            key: active.id,
                            conversation: active,
                            me,
                            poll,
                            maxUploadKb,
                            onBack: () => setActiveId(null),
                            onPin: () => togglePin(active),
                            onDelete: () => deleteConversation(active),
                            onProfile: (person) => setProfile(person),
                            onGroupInfo: (conversation) => setGroupInfo(conversation),
                            onRead: (sequence) => markLocalRead(active.id, sequence),
                            onError: showError,
                        })
                        : h(Home, {
                            tab,
                            setTab,
                            conversations,
                            onOpen: (conversation) => setActiveId(conversation.id),
                            onPin: togglePin,
                            onDelete: deleteConversation,
                            onClose: () => setOpen(false),
                            onPerson: (person) => setProfile(person),
                            onDirect: startDirect,
                            onGroup: createGroup,
                        }),
            ) : null,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Ana gorunum: sekmeler                                                */
    /* ------------------------------------------------------------------ */

    function Home({ tab, setTab, conversations, onOpen, onPin, onDelete, onClose, onPerson, onDirect, onGroup }) {
        return h('div', { className: 'kc-home' },
            h('header', { className: 'kc-header' },
                h('img', { className: 'kc-logo', src: config.logo, alt: '' }),
                h('h2', { className: 'kc-title' }, t('title')),
                h('button', { type: 'button', className: 'kc-icon-btn', onClick: onClose, 'aria-label': t('close'), title: t('close') }, h(Icon, { name: 'close' })),
            ),
            h('nav', { className: 'kc-tabs', role: 'tablist' },
                h('button', { type: 'button', role: 'tab', 'aria-selected': tab === 'chats', className: 'kc-tab' + (tab === 'chats' ? ' kc-tab-active' : ''), onClick: () => setTab('chats') }, h(Icon, { name: 'chat' }), t('tab_chats')),
                h('button', { type: 'button', role: 'tab', 'aria-selected': tab === 'people', className: 'kc-tab' + (tab === 'people' ? ' kc-tab-active' : ''), onClick: () => setTab('people') }, h(Icon, { name: 'users' }), t('tab_people')),
            ),
            tab === 'chats'
                ? h(ChatList, { conversations, onOpen, onPin, onDelete })
                : h(People, { onPerson, onDirect, onGroup }),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Sohbet listesi                                                       */
    /* ------------------------------------------------------------------ */

    function ChatList({ conversations, onOpen, onPin, onDelete }) {
        const [term, setTerm] = useState('');
        const [confirming, setConfirming] = useState(null);

        const filtered = useMemo(() => {
            const needle = term.trim().toLocaleLowerCase(locale);

            if (! needle) {
                return conversations;
            }

            return conversations.filter((item) => (item.title || '').toLocaleLowerCase(locale).includes(needle)
                || (item.subtitle || '').toLocaleLowerCase(locale).includes(needle)
                || (item.last_message && (item.last_message.preview || '').toLocaleLowerCase(locale).includes(needle)));
        }, [conversations, term]);

        const pinned = filtered.filter((item) => item.pinned);
        const others = filtered.filter((item) => ! item.pinned);

        const renderItem = (item) => h('div', { key: item.id, className: 'kc-row' + (item.unread ? ' kc-row-unread' : '') },
            h('button', { type: 'button', className: 'kc-row-main', onClick: () => onOpen(item) },
                h(Avatar, { person: { photo: item.photo, initials: item.initials, name: item.title }, online: item.online }),
                h('span', { className: 'kc-row-text' },
                    h('span', { className: 'kc-row-top' },
                        h('span', { className: 'kc-row-title' }, item.pinned ? h(Icon, { name: 'pin' }) : null, item.title),
                        h('span', { className: 'kc-row-time' }, formatListTime(item.last_message_at)),
                    ),
                    h('span', { className: 'kc-row-bottom' },
                        h('span', { className: 'kc-row-preview' },
                            item.typing && item.typing.length
                                ? h('em', { className: 'kc-typing' }, t('typing'))
                                : item.last_message
                                    ? (item.last_message.mine ? t('you') + ': ' : '') + item.last_message.preview
                                    : item.subtitle),
                        item.unread ? h('span', { className: 'kc-unread' }, item.unread) : null,
                    ),
                ),
            ),
            h('span', { className: 'kc-row-actions' },
                h('button', { type: 'button', className: 'kc-icon-btn kc-icon-btn-sm', title: item.pinned ? t('unpin') : t('pin'), 'aria-label': item.pinned ? t('unpin') : t('pin'), onClick: () => onPin(item) }, h(Icon, { name: 'pin' })),
                h('button', { type: 'button', className: 'kc-icon-btn kc-icon-btn-sm kc-danger', title: t('delete_chat'), 'aria-label': t('delete_chat'), onClick: () => setConfirming(item) }, h(Icon, { name: 'trash' })),
            ),
        );

        return h('div', { className: 'kc-body' },
            h('div', { className: 'kc-search' },
                h(Icon, { name: 'search' }),
                h('input', { type: 'search', value: term, placeholder: t('search_chats'), onChange: (event) => setTerm(event.target.value), 'aria-label': t('search_chats') }),
            ),
            confirming ? h(Confirm, {
                text: t('delete_chat_confirm'),
                onConfirm: () => { onDelete(confirming); setConfirming(null); },
                onCancel: () => setConfirming(null),
            }) : null,
            h('div', { className: 'kc-list' },
                filtered.length === 0 ? h('p', { className: 'kc-empty' }, t('no_chats')) : null,
                pinned.length ? h('p', { className: 'kc-section' }, t('pinned')) : null,
                pinned.map(renderItem),
                pinned.length && others.length ? h('p', { className: 'kc-section' }, t('tab_chats')) : null,
                others.map(renderItem),
            ),
        );
    }

    function Confirm({ text, onConfirm, onCancel }) {
        return h('div', { className: 'kc-confirm', role: 'alertdialog' },
            h('p', null, text),
            h('div', { className: 'kc-confirm-actions' },
                h('button', { type: 'button', className: 'kc-btn kc-btn-ghost', onClick: onCancel }, t('cancel')),
                h('button', { type: 'button', className: 'kc-btn kc-btn-danger', onClick: onConfirm }, t('confirm')),
            ),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Kisiler                                                              */
    /* ------------------------------------------------------------------ */

    function People({ onPerson, onDirect, onGroup }) {
        const [term, setTerm] = useState('');
        const [people, setPeople] = useState([]);
        const [loading, setLoading] = useState(true);
        const [groupMode, setGroupMode] = useState(false);
        const [selected, setSelected] = useState([]);
        const [groupTitle, setGroupTitle] = useState('');

        useEffect(() => {
            let cancelled = false;
            setLoading(true);

            const timer = window.setTimeout(() => {
                api.get(endpoints.directory, { q: term }).then((payload) => {
                    if (! cancelled) {
                        setPeople(payload.people || []);
                        setLoading(false);
                    }
                }).catch(() => {
                    if (! cancelled) {
                        setLoading(false);
                    }
                });
            }, 250);

            return () => {
                cancelled = true;
                window.clearTimeout(timer);
            };
        }, [term]);

        const toggleSelected = (id) => setSelected((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);

        return h('div', { className: 'kc-body' },
            h('div', { className: 'kc-search' },
                h(Icon, { name: 'search' }),
                h('input', { type: 'search', value: term, placeholder: t('search_people'), onChange: (event) => setTerm(event.target.value), 'aria-label': t('search_people') }),
            ),
            h('div', { className: 'kc-group-bar' },
                h('button', { type: 'button', className: 'kc-btn kc-btn-ghost kc-btn-sm', onClick: () => { setGroupMode(! groupMode); setSelected([]); } },
                    h(Icon, { name: groupMode ? 'close' : 'users' }), groupMode ? t('cancel') : t('new_group')),
                groupMode ? h('input', { type: 'text', className: 'kc-input', value: groupTitle, placeholder: t('group_title'), maxLength: 160, onChange: (event) => setGroupTitle(event.target.value) }) : null,
                groupMode ? h('button', { type: 'button', className: 'kc-btn kc-btn-primary kc-btn-sm', disabled: ! groupTitle.trim() || selected.length === 0, onClick: () => onGroup(groupTitle.trim(), selected) }, h(Icon, { name: 'check' }), t('group_create')) : null,
            ),
            groupMode ? h('p', { className: 'kc-hint' }, t('group_members_hint')) : null,
            h('div', { className: 'kc-list' },
                ! loading && people.length === 0 ? h('p', { className: 'kc-empty' }, t('no_people')) : null,
                people.map((person) => h('div', { key: person.id, className: 'kc-row' },
                    groupMode ? h('label', { className: 'kc-check' },
                        h('input', { type: 'checkbox', checked: selected.includes(person.id), onChange: () => toggleSelected(person.id) }),
                    ) : null,
                    h('button', { type: 'button', className: 'kc-row-main', onClick: () => groupMode ? toggleSelected(person.id) : onPerson(person) },
                        h(Avatar, { person, online: person.online }),
                        h('span', { className: 'kc-row-text' },
                            h('span', { className: 'kc-row-top' }, h('span', { className: 'kc-row-title' }, person.name)),
                            h('span', { className: 'kc-row-bottom' }, h('span', { className: 'kc-row-preview' }, [person.role, person.department].filter(Boolean).join(' · ') || person.job_title || '')),
                        ),
                    ),
                    ! groupMode ? h('span', { className: 'kc-row-actions kc-row-actions-visible' },
                        h('button', { type: 'button', className: 'kc-icon-btn kc-icon-btn-sm', title: t('send_message'), 'aria-label': t('send_message'), onClick: () => onDirect(person) }, h(Icon, { name: 'send' })),
                    ) : null,
                )),
            ),
        );
    }

    function ProfileCard({ person, onBack, onMessage }) {
        return h('div', { className: 'kc-home' },
            h('header', { className: 'kc-header' },
                h('button', { type: 'button', className: 'kc-icon-btn', onClick: onBack, 'aria-label': t('back'), title: t('back') }, h(Icon, { name: 'back' })),
                h('h2', { className: 'kc-title' }, t('profile')),
            ),
            h('div', { className: 'kc-profile' },
                h(Avatar, { person, size: 'xl', online: person.online }),
                h('h3', null, person.name),
                h('p', { className: 'kc-profile-status' }, person.online ? t('online') : t('offline')),
                h('dl', { className: 'kc-profile-facts' },
                    h('dt', null, t('role')), h('dd', null, person.role || '-'),
                    h('dt', null, t('department')), h('dd', null, person.department || '-'),
                    h('dt', null, t('job_title')), h('dd', null, person.job_title || '-'),
                ),
                h('button', { type: 'button', className: 'kc-btn kc-btn-primary', onClick: () => onMessage(person) }, h(Icon, { name: 'send' }), t('send_message')),
            ),
        );
    }

    function GroupInfo({ conversation, onBack, onPerson }) {
        return h('div', { className: 'kc-home' },
            h('header', { className: 'kc-header' },
                h('button', { type: 'button', className: 'kc-icon-btn', onClick: onBack, 'aria-label': t('back'), title: t('back') }, h(Icon, { name: 'back' })),
                h('h2', { className: 'kc-title' }, t('members')),
            ),
            h('div', { className: 'kc-profile' },
                h(Avatar, { person: { photo: conversation.photo, initials: conversation.initials, name: conversation.title }, size: 'xl' }),
                h('h3', null, conversation.title),
                h('p', { className: 'kc-profile-status' }, conversation.subtitle),
            ),
            h('div', { className: 'kc-list' },
                (conversation.members || []).map((person) => h('div', { key: person.id, className: 'kc-row' },
                    h('button', { type: 'button', className: 'kc-row-main', onClick: () => onPerson(person) },
                        h(Avatar, { person, online: person.online }),
                        h('span', { className: 'kc-row-text' },
                            h('span', { className: 'kc-row-top' }, h('span', { className: 'kc-row-title' }, person.name)),
                            h('span', { className: 'kc-row-bottom' }, h('span', { className: 'kc-row-preview' }, [person.role, person.department].filter(Boolean).join(' · ') || person.job_title || '')),
                        ),
                    ),
                )),
            ),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Sohbet gorunumu                                                      */
    /* ------------------------------------------------------------------ */

    function ChatView({ conversation, me, poll, maxUploadKb, onBack, onPin, onDelete, onProfile, onGroupInfo, onRead, onError }) {
        const [messages, setMessages] = useState([]);
        const [hasMore, setHasMore] = useState(false);
        const [typing, setTyping] = useState([]);
        const [onlineIds, setOnlineIds] = useState([]);
        const [loaded, setLoaded] = useState(false);
        const [text, setText] = useState('');
        const [link, setLink] = useState(null);
        const [file, setFile] = useState(null);
        const [linkMode, setLinkMode] = useState(false);
        const [docPicker, setDocPicker] = useState(false);
        const [sending, setSending] = useState(false);
        const [confirmDelete, setConfirmDelete] = useState(null);
        const [menu, setMenu] = useState(false);
        const listRef = useRef(null);
        const inputRef = useRef(null);
        const fileRef = useRef(null);
        const lastTyping = useRef(0);
        const stickToBottom = useRef(true);
        const readRef = useRef(0);

        const url = (suffix) => conversationUrl(conversation.id, suffix);
        const lastSequence = messages.length ? messages[messages.length - 1].sequence : 0;

        const scrollToBottom = useCallback(() => {
            const node = listRef.current;

            if (node) {
                node.scrollTop = node.scrollHeight;
            }
        }, []);

        const markRead = useCallback((sequence) => {
            if (! sequence || sequence <= readRef.current) {
                return;
            }

            readRef.current = sequence;
            onRead(sequence);
            api.post(url('/read'), { sequence }).catch(() => {});
        }, [onRead]);

        useEffect(() => {
            let cancelled = false;

            api.get(url('/messages'), { limit: 50 }).then((payload) => {
                if (cancelled) {
                    return;
                }

                setMessages(payload.messages || []);
                setHasMore(Boolean(payload.has_more));
                setTyping(payload.typing || []);
                setOnlineIds(payload.online_ids || []);
                setLoaded(true);
                window.requestAnimationFrame(scrollToBottom);

                const last = (payload.messages || []).slice(-1)[0];
                markRead(last ? last.sequence : payload.last_sequence);
            }).catch((failure) => {
                onError(failure);
                setLoaded(true);
            });

            return () => {
                cancelled = true;
            };
        }, [conversation.id]);

        useInterval(async () => {
            if (document.hidden) {
                return;
            }

            try {
                const payload = await api.get(url('/messages'), { after: lastSequence, limit: 100 });
                setTyping(payload.typing || []);
                setOnlineIds(payload.online_ids || []);

                if (payload.messages && payload.messages.length) {
                    setMessages((current) => {
                        const known = new Set(current.map((item) => item.id));

                        return [...current, ...payload.messages.filter((item) => ! known.has(item.id))];
                    });

                    if (stickToBottom.current) {
                        window.requestAnimationFrame(scrollToBottom);
                    }

                    markRead(payload.messages[payload.messages.length - 1].sequence);
                }
            } catch (failure) {
                // Sessiz.
            }
        }, poll.messages, loaded);

        const loadOlder = async () => {
            if (! messages.length) {
                return;
            }

            const node = listRef.current;
            const previousHeight = node ? node.scrollHeight : 0;

            try {
                const payload = await api.get(url('/messages'), { before: messages[0].sequence, limit: 50 });
                setMessages((current) => [...(payload.messages || []), ...current]);
                setHasMore(Boolean(payload.has_more));
                window.requestAnimationFrame(() => {
                    if (node) {
                        node.scrollTop = node.scrollHeight - previousHeight;
                    }
                });
            } catch (failure) {
                onError(failure);
            }
        };

        const onScroll = () => {
            const node = listRef.current;

            if (! node) {
                return;
            }

            stickToBottom.current = node.scrollHeight - node.scrollTop - node.clientHeight < 40;
        };

        const signalTyping = () => {
            const now = Date.now();

            if (now - lastTyping.current > 3000) {
                lastTyping.current = now;
                api.post(url('/typing')).catch(() => {});
            }
        };

        const pickFile = (event) => {
            const chosen = event.target.files && event.target.files[0];

            if (! chosen) {
                return;
            }

            if (chosen.size > maxUploadKb * 1024) {
                onError(new Error(t('file_too_large', { mb: Math.round(maxUploadKb / 1024) })));
                event.target.value = '';

                return;
            }

            setFile(chosen);
            event.target.value = '';
        };

        const send = async (extra) => {
            const body = text.trim();
            const payload = extra || {};

            if (! body && ! link && ! file && ! payload.document_id) {
                return;
            }

            setSending(true);

            try {
                let response;

                if (file) {
                    const form = new FormData();
                    form.append('file', file);

                    if (body) {
                        form.append('body', body);
                    }

                    response = await api.form(url('/messages'), form);
                } else {
                    response = await api.post(url('/messages'), { body: body || null, link_url: link || null, document_id: payload.document_id || null });
                }

                if (response.message) {
                    setMessages((current) => current.some((item) => item.id === response.message.id) ? current : [...current, response.message]);
                    markRead(response.message.sequence);
                }

                setText('');
                setLink(null);
                setFile(null);
                setLinkMode(false);
                stickToBottom.current = true;
                window.requestAnimationFrame(scrollToBottom);

                if (inputRef.current) {
                    inputRef.current.focus();
                }
            } catch (failure) {
                onError(failure);
            } finally {
                setSending(false);
            }
        };

        const deleteMessage = async (message) => {
            try {
                await api.del(messageUrl(message.id));
                setMessages((current) => current.filter((item) => item.id !== message.id));
            } catch (failure) {
                onError(failure);
            }
        };

        const onKeyDown = (event) => {
            if (event.key === 'Enter' && ! event.shiftKey) {
                event.preventDefault();
                send();
            }
        };

        const other = conversation.other;
        const isOnline = other ? onlineIds.includes(other.id) || other.online : null;
        const typingText = typing.length === 0 ? null : (conversation.type === 'direct' ? t('typing') : t('typing_many', { names: typing.join(', ') }));

        const grouped = [];
        let lastDay = null;
        messages.forEach((message) => {
            const day = message.sent_at ? message.sent_at.slice(0, 10) : '';

            if (day !== lastDay) {
                grouped.push({ separator: true, key: 'day-' + day, label: formatDayLabel(message.sent_at) });
                lastDay = day;
            }

            grouped.push(message);
        });

        return h('div', { className: 'kc-chat' },
            h('header', { className: 'kc-header kc-header-chat' },
                h('button', { type: 'button', className: 'kc-icon-btn', onClick: onBack, 'aria-label': t('back'), title: t('back') }, h(Icon, { name: 'back' })),
                h('button', { type: 'button', className: 'kc-header-person', onClick: () => other ? onProfile({ ...other, online: isOnline }) : onGroupInfo(conversation) },
                    h(Avatar, { person: { photo: conversation.photo, initials: conversation.initials, name: conversation.title }, online: isOnline }),
                    h('span', { className: 'kc-header-text' },
                        h('strong', null, conversation.title),
                        h('small', null, typingText || (conversation.type === 'direct' ? (isOnline ? t('online') : t('offline')) + (conversation.subtitle ? ' · ' + conversation.subtitle : '') : conversation.subtitle)),
                    ),
                ),
                h('span', { className: 'kc-header-actions' },
                    h('button', { type: 'button', className: 'kc-icon-btn', title: conversation.pinned ? t('unpin') : t('pin'), 'aria-label': conversation.pinned ? t('unpin') : t('pin'), onClick: onPin }, h(Icon, { name: 'pin' })),
                    h('button', { type: 'button', className: 'kc-icon-btn kc-danger', title: t('delete_chat'), 'aria-label': t('delete_chat'), onClick: () => setMenu(true) }, h(Icon, { name: 'trash' })),
                ),
            ),
            menu ? h(Confirm, { text: t('delete_chat_confirm'), onConfirm: () => { setMenu(false); onDelete(); }, onCancel: () => setMenu(false) }) : null,
            confirmDelete ? h(Confirm, { text: t('delete_message_confirm'), onConfirm: () => { deleteMessage(confirmDelete); setConfirmDelete(null); }, onCancel: () => setConfirmDelete(null) }) : null,
            docPicker ? h(DocPicker, { onClose: () => setDocPicker(false), onPick: (document) => { setDocPicker(false); send({ document_id: document.id }); } }) : null,
            h('div', { className: 'kc-messages', ref: listRef, onScroll },
                hasMore ? h('button', { type: 'button', className: 'kc-btn kc-btn-ghost kc-btn-sm kc-load-more', onClick: loadOlder }, t('load_more')) : null,
                loaded && messages.length === 0 ? h('p', { className: 'kc-empty' }, t('no_messages')) : null,
                grouped.map((item) => item.separator
                    ? h('div', { key: item.key, className: 'kc-day' }, h('span', null, item.label))
                    : h(MessageItem, { key: item.id, message: item, isGroup: conversation.type !== 'direct', onDelete: () => setConfirmDelete(item), onProfile })),
                typingText ? h('div', { className: 'kc-typing-row' }, h('span', { className: 'kc-typing-dots' }, h('i'), h('i'), h('i')), h('em', null, typingText)) : null,
            ),
            h('footer', { className: 'kc-composer' },
                link ? h('div', { className: 'kc-chip' }, h(Icon, { name: 'link' }), h('span', { className: 'kc-chip-text' }, link), h('button', { type: 'button', className: 'kc-icon-btn kc-icon-btn-sm', 'aria-label': t('remove'), title: t('remove'), onClick: () => setLink(null) }, h(Icon, { name: 'close' }))) : null,
                file ? h('div', { className: 'kc-chip' }, h(Icon, { name: 'clip' }), h('span', { className: 'kc-chip-text' }, file.name), h('button', { type: 'button', className: 'kc-icon-btn kc-icon-btn-sm', 'aria-label': t('remove'), title: t('remove'), onClick: () => setFile(null) }, h(Icon, { name: 'close' }))) : null,
                linkMode ? h(LinkInput, { onAdd: (value) => { setLink(value); setLinkMode(false); }, onCancel: () => setLinkMode(false) }) : null,
                h('div', { className: 'kc-composer-row' },
                    h('input', { type: 'file', ref: fileRef, className: 'kc-hidden', onChange: pickFile }),
                    h('button', { type: 'button', className: 'kc-icon-btn', title: t('attach_file'), 'aria-label': t('attach_file'), onClick: () => fileRef.current && fileRef.current.click() }, h(Icon, { name: 'clip' })),
                    h('button', { type: 'button', className: 'kc-icon-btn', title: t('attach_document'), 'aria-label': t('attach_document'), onClick: () => setDocPicker(true) }, h(Icon, { name: 'doc' })),
                    h('button', { type: 'button', className: 'kc-icon-btn', title: t('attach_link'), 'aria-label': t('attach_link'), onClick: () => setLinkMode(! linkMode) }, h(Icon, { name: 'link' })),
                    h('textarea', {
                        ref: inputRef,
                        className: 'kc-textarea',
                        rows: 1,
                        value: text,
                        placeholder: t('compose_placeholder'),
                        maxLength: 4000,
                        onChange: (event) => { setText(event.target.value); signalTyping(); },
                        onKeyDown,
                        'aria-label': t('compose_placeholder'),
                    }),
                    h('button', { type: 'button', className: 'kc-send', disabled: sending || (! text.trim() && ! link && ! file), onClick: () => send(), 'aria-label': t('send'), title: t('send') }, h(Icon, { name: 'send' })),
                ),
            ),
        );
    }

    function LinkInput({ onAdd, onCancel }) {
        const [value, setValue] = useState('');

        const submit = () => {
            const trimmed = value.trim();

            if (! trimmed) {
                return;
            }

            const normalized = /^https?:\/\//i.test(trimmed) ? trimmed : (trimmed.startsWith('/') ? window.location.origin + trimmed : 'https://' + trimmed);
            onAdd(normalized);
        };

        return h('div', { className: 'kc-link-input' },
            h('input', { type: 'url', className: 'kc-input', value, autoFocus: true, placeholder: t('link_placeholder'), onChange: (event) => setValue(event.target.value), onKeyDown: (event) => { if (event.key === 'Enter') { event.preventDefault(); submit(); } if (event.key === 'Escape') { onCancel(); } } }),
            h('button', { type: 'button', className: 'kc-btn kc-btn-primary kc-btn-sm', onClick: submit }, t('link_add')),
            h('button', { type: 'button', className: 'kc-btn kc-btn-ghost kc-btn-sm', onClick: onCancel }, t('cancel')),
        );
    }

    function MessageItem({ message, isGroup, onDelete, onProfile }) {
        const mine = message.mine;

        return h('div', { className: 'kc-msg' + (mine ? ' kc-msg-mine' : '') },
            ! mine ? h('button', { type: 'button', className: 'kc-msg-avatar', onClick: () => message.author && onProfile(message.author), 'aria-label': message.author ? message.author.name : '' }, h(Avatar, { person: message.author, size: 'sm' })) : null,
            h('div', { className: 'kc-bubble' },
                isGroup && ! mine && message.author ? h('span', { className: 'kc-msg-author' }, message.author.name) : null,
                message.body ? h('p', { className: 'kc-msg-text' }, linkify(message.body)) : null,
                message.link ? h(LinkCard, { link: message.link }) : null,
                (message.attachments || []).map((attachment) => h(AttachmentCard, { key: attachment.id, attachment })),
                h('span', { className: 'kc-msg-meta' }, formatTime(message.sent_at)),
            ),
            h('span', { className: 'kc-msg-tools' },
                endpoints.request_create
                    ? h('a', { className: 'kc-icon-btn kc-icon-btn-sm', href: endpoints.request_create.replace('__ID__', String(message.id)), title: t('open_request'), 'aria-label': t('open_request') }, h(Icon, { name: 'ticket' }))
                    : null,
                h('button', { type: 'button', className: 'kc-icon-btn kc-icon-btn-sm', title: t('delete_message'), 'aria-label': t('delete_message'), onClick: onDelete }, h(Icon, { name: 'trash' })),
            ),
        );
    }

    function LinkCard({ link }) {
        const denied = link.in_app && link.accessible === false;

        return h('a', {
            className: 'kc-link-card' + (denied ? ' kc-link-denied' : ''),
            href: link.url,
            target: link.in_app ? '_self' : '_blank',
            rel: link.in_app ? undefined : 'noopener noreferrer',
            title: denied ? t('link_no_access_hint') : link.url,
        },
            h(Icon, { name: denied ? 'lock' : (link.in_app ? 'link' : 'external') }),
            h('span', { className: 'kc-link-body' },
                h('strong', null, link.label || link.url),
                h('small', null, denied ? t('link_no_access') : (link.in_app ? t('link_in_app') : link.url)),
            ),
        );
    }

    function AttachmentCard({ attachment }) {
        if (attachment.kind === 'document') {
            const denied = attachment.accessible === false;

            return h('a', {
                className: 'kc-link-card kc-doc-card' + (denied ? ' kc-link-denied' : ''),
                href: attachment.url || '#',
                title: denied ? t('document_no_access') : attachment.title,
            },
                h(Icon, { name: denied ? 'lock' : 'doc' }),
                h('span', { className: 'kc-link-body' },
                    h('strong', null, attachment.title),
                    h('small', null, denied ? t('link_no_access') : (attachment.document_no || t('link_in_app'))),
                ),
            );
        }

        return h('div', { className: 'kc-file-card' },
            attachment.is_image && attachment.thumbnail_url
                ? h('a', { href: attachment.url, target: '_blank', rel: 'noopener', className: 'kc-file-image' }, h('img', { src: attachment.thumbnail_url, alt: attachment.name, loading: 'lazy' }))
                : null,
            h('span', { className: 'kc-file-row' },
                h(Icon, { name: 'clip' }),
                h('span', { className: 'kc-link-body' },
                    h('strong', null, attachment.name),
                    h('small', null, attachment.size || ''),
                ),
                h('a', { className: 'kc-icon-btn kc-icon-btn-sm', href: attachment.download_url || attachment.url, title: t('download'), 'aria-label': t('download') }, h(Icon, { name: 'download' })),
            ),
        );
    }

    function DocPicker({ onClose, onPick }) {
        const [term, setTerm] = useState('');
        const [documents, setDocuments] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            let cancelled = false;
            setLoading(true);

            const timer = window.setTimeout(() => {
                api.get(endpoints.documents, { q: term }).then((payload) => {
                    if (! cancelled) {
                        setDocuments(payload.documents || []);
                        setLoading(false);
                    }
                }).catch(() => {
                    if (! cancelled) {
                        setLoading(false);
                    }
                });
            }, 250);

            return () => {
                cancelled = true;
                window.clearTimeout(timer);
            };
        }, [term]);

        return h('div', { className: 'kc-overlay', role: 'dialog', 'aria-label': t('document_pick') },
            h('div', { className: 'kc-overlay-head' },
                h('strong', null, t('document_pick')),
                h('button', { type: 'button', className: 'kc-icon-btn', onClick: onClose, 'aria-label': t('close') }, h(Icon, { name: 'close' })),
            ),
            h('div', { className: 'kc-search' },
                h(Icon, { name: 'search' }),
                h('input', { type: 'search', autoFocus: true, value: term, placeholder: t('search_documents'), onChange: (event) => setTerm(event.target.value) }),
            ),
            h('div', { className: 'kc-list' },
                ! loading && documents.length === 0 ? h('p', { className: 'kc-empty' }, t('no_documents')) : null,
                documents.map((document) => h('button', { key: document.id, type: 'button', className: 'kc-row kc-row-main kc-row-doc', onClick: () => onPick(document) },
                    h(Icon, { name: 'doc' }),
                    h('span', { className: 'kc-row-text' },
                        h('span', { className: 'kc-row-title' }, document.title),
                        h('span', { className: 'kc-row-preview' }, document.document_no || ''),
                    ),
                )),
            ),
        );
    }

    ReactDOM.createRoot(root).render(h(App));
})();
