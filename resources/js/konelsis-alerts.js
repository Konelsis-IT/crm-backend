/*
 * Konelsis - masaustu (Windows) bildirimi ve bildirim sesi (D-126; kullanici
 * onayi 25 Eylul 2026: "Bir mesaj veya bildirim geldiginde, windows bildirimi
 * halinde gelmesini ... uygulama icinde veya farkli sekmede olundugunda fark
 * etmeden ... bildirim sesi calsin").
 *
 * - Her panel sayfasinda yuklenir; ayarlar window.filamentData.konelsisAlerts
 *   (FilamentAssetsProvider, yalniz oturum acmis personel).
 * - Beslemeyi (AlertFeedController) ~20 sn'de bir okur: en son okunmamis zil
 *   bildirimi ve en son okunmamis sohbet mesaji. Sekme arka plandayken de
 *   calisir (tarayici arka planda zamanlayiciyi yavaslatabilir).
 * - Yeni kayitta kisa bir ses calar (Web Audio; ses dosyasi yok). Sekme
 *   arkadaysa ya da pencere odakta degilse Windows bildirimi de gosterir
 *   (izin verilmisse). Bildirime tiklamak ilgili sayfayi acar.
 * - Birden fazla sekme: yoklamayi tek sekme yapar, ayni kayit bir kez
 *   bildirilir (localStorage; yalniz bu tarayicidaki tercih / durum).
 * - Ilk yuklemede eski okunmamislar bildirilmez (taban cizgisi).
 * - Acik olan sohbetteki mesaj, sekme ondeyken bildirilmez
 *   (window.KonelsisChatState, konelsis-chat.js).
 * - Izin: kullanici menusundeki "Masaustu bildirimlerini ac"
 *   (window.KonelsisAlerts.enable); tarayici izni yalniz tiklamayla sorar.
 */
(function () {
    'use strict';

    const data = (window.filamentData || {}).konelsisAlerts;

    if (!data || !data.feed || window.KonelsisAlerts) {
        return;
    }

    const labels = data.labels || {};
    const interval = Number(data.poll) || 20000;
    const stateKey = 'konelsis.alerts.' + data.user;
    const pollKey = 'konelsis.alerts.poll.' + data.user;

    function read(key) {
        try {
            return JSON.parse(window.localStorage.getItem(key) || 'null');
        } catch (ignored) {
            return null;
        }
    }

    function write(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (ignored) {
            // Depolama kapaliysa her sekme kendi basina calisir.
        }
    }

    /* ---- ses ---------------------------------------------------------- */

    let audio = null;

    function audioContext() {
        if (audio) {
            return audio;
        }

        const Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            return null;
        }

        try {
            audio = new Context();
        } catch (ignored) {
            audio = null;
        }

        return audio;
    }

    // Tarayici sesi ancak bir kullanici etkilesiminden sonra acar.
    function unlock() {
        const context = audioContext();

        if (context && context.state === 'suspended') {
            context.resume().catch(function () {});
        }
    }

    ['pointerdown', 'keydown'].forEach(function (type) {
        window.addEventListener(type, unlock, { once: true, passive: true });
    });

    function chime() {
        const context = audioContext();

        if (!context) {
            return;
        }

        if (context.state === 'suspended') {
            context.resume().catch(function () {});
        }

        const now = context.currentTime;

        [[880, 0], [1320, 0.13]].forEach(function (tone) {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const start = now + tone[1];

            oscillator.type = 'sine';
            oscillator.frequency.value = tone[0];
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.22, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.45);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.5);
        });
    }

    /* ---- Windows bildirimi ------------------------------------------- */

    function supported() {
        return 'Notification' in window && window.isSecureContext === true;
    }

    function desktop(item, granted) {
        if (!supported() || (granted !== true && window.Notification.permission !== 'granted')) {
            return;
        }

        try {
            const notification = new window.Notification(item.title || labels.app || 'Konelsis', {
                body: item.body || '',
                icon: data.icon,
                tag: item.tag,
            });

            notification.onclick = function () {
                window.focus();

                if (item.url) {
                    window.location.assign(item.url);
                }

                notification.close();
            };
        } catch (ignored) {
            // Bazi tarayicilar yapici ile bildirimi desteklemez.
        }
    }

    function toast(text, status) {
        if (window.FilamentNotification) {
            new window.FilamentNotification().title(text)[status]().send();
        }
    }

    /* ---- yoklama ------------------------------------------------------ */

    // Yeni mi? Ilk gorulen kayit taban cizgisidir; ayni kayit bir kez bildirilir.
    function isFresh(state, kind, item) {
        const seen = state[kind];

        if (!item) {
            if (seen === undefined) {
                state[kind] = { id: null, at: null };
            }

            return false;
        }

        if (seen && seen.id === item.id) {
            return false;
        }

        state[kind] = { id: item.id, at: item.at };

        if (seen === undefined) {
            return false;
        }

        return !seen || !seen.at || !item.at || item.at > seen.at;
    }

    function chatIsOpenOn(conversationId) {
        const chat = window.KonelsisChatState;

        return !document.hidden && !!chat && chat.open === true && chat.activeId === conversationId;
    }

    let busy = false;

    function poll(force) {
        const last = Number(read(pollKey) || 0);

        // Baska bir sekme az once yokladiysa bu sekme beklemez.
        if (busy || (!force && Date.now() - last < interval * 0.75)) {
            return;
        }

        busy = true;
        write(pollKey, Date.now());

        window.fetch(data.feed, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (json) {
            if (!json) {
                return;
            }

            const state = read(stateKey) || {};
            const alerts = [];
            const notification = json.notification || null;
            const chat = json.chat || null;

            if (isFresh(state, 'notification', notification)) {
                alerts.push({ title: notification.title, body: notification.body, url: notification.url, tag: 'konelsis-notification-' + notification.id });
            }

            if (isFresh(state, 'chat', chat) && !chatIsOpenOn(chat.conversation_id)) {
                alerts.push({ title: chat.title, body: chat.body, url: chat.url, tag: 'konelsis-chat-' + chat.conversation_id });
            }

            write(stateKey, state);

            if (!alerts.length) {
                return;
            }

            chime();

            // Sekme onde ve odaktayken zil / sohbet zaten gorunur; Windows
            // bildirimi yalniz kullanici baska yerdeyken gosterilir.
            if (document.hidden || !document.hasFocus()) {
                alerts.forEach(function (item) { desktop(item); });
            }
        }).catch(function () {
            // Oturum dusmusse ya da ag yoksa sessizce bir sonraki turu bekler.
        }).finally(function () {
            busy = false;
        });
    }

    async function enable() {
        unlock();
        chime();

        if (!supported()) {
            toast(labels.unsupported, 'warning');

            return;
        }

        let permission = window.Notification.permission;

        if (permission === 'default') {
            try {
                permission = await window.Notification.requestPermission();
            } catch (ignored) {
                permission = window.Notification.permission;
            }
        }

        if (permission === 'granted') {
            toast(labels.enabled, 'success');
            desktop({ title: labels.app, body: labels.enabled, tag: 'konelsis-alerts-test' }, true);

            return;
        }

        toast(labels.denied, 'danger');
    }

    window.KonelsisAlerts = {
        enable: enable,
        chime: chime,
        poll: function () { poll(true); },
    };

    poll(true);
    window.setInterval(function () { poll(false); }, interval);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll(false);
        }
    });
}());
