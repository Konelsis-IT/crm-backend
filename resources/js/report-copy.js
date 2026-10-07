/*
 * Raporu bicimli kopyala (D-167, 6 Ekim 2026 kullanici onayi).
 *
 * Filament'in copyable() ozelligi yalniz duz metin kopyalar; rapor metninin
 * e-postaya / Word'e basliklari, maddeleri ve kalin yazilariyla yapismasi
 * icin panoya ayni anda zengin metin (text/html) ve duz metin (text/plain)
 * yazilir. Metin sunucuda uretilir ($wire.reportCopyPayload, yetki kontrollu);
 * ClipboardItem'a Promise verilir ki tarayici kullanici tiklamasini kaybetmesin
 * (Safari). Desteklenmeyen tarayicida gizli bir alan uzerinden kopyalanir.
 */
(function () {
    'use strict';

    function notify(title, status) {
        if (! title || typeof window.FilamentNotification !== 'function') {
            return;
        }

        const notification = new window.FilamentNotification().title(title);

        (status === 'success' ? notification.success() : notification.danger()).send();
    }

    function legacyCopy(data) {
        const holder = document.createElement('div');

        holder.setAttribute('contenteditable', 'true');
        holder.style.position = 'fixed';
        holder.style.left = '-9999px';
        holder.style.top = '0';
        holder.innerHTML = data.html;
        document.body.appendChild(holder);

        const range = document.createRange();
        const selection = window.getSelection();

        range.selectNodeContents(holder);
        selection.removeAllRanges();
        selection.addRange(range);

        const copied = document.execCommand('copy');

        selection.removeAllRanges();
        holder.remove();

        if (! copied) {
            throw new Error('copy failed');
        }
    }

    function checked(data) {
        if (! data || typeof data.html !== 'string' || typeof data.text !== 'string') {
            throw new Error('no payload');
        }

        return data;
    }

    window.konelsisReportCopy = {
        async copy(load, labels) {
            labels = labels || {};

            const payload = Promise.resolve(load()).then(checked);

            try {
                if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
                    await navigator.clipboard.write([
                        new window.ClipboardItem({
                            'text/html': payload.then((data) => new Blob([data.html], { type: 'text/html' })),
                            'text/plain': payload.then((data) => new Blob([data.text], { type: 'text/plain' })),
                        }),
                    ]);
                } else {
                    legacyCopy(await payload);
                }

                notify(labels.ok, 'success');
            } catch (error) {
                try {
                    legacyCopy(await payload);
                    notify(labels.ok, 'success');
                } catch (fallbackError) {
                    notify(labels.fail, 'danger');
                }
            }
        },
    };
})();
