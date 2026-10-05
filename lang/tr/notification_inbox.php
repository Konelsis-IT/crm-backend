<?php

declare(strict_types=1);

return [
    'singular' => 'Bildirim',
    'plural' => 'Tüm bildirimler',
    'help' => 'Size gönderilen bütün bildirimler; eskiler de dahil. Satıra tıklayınca bildirimin ilgili sayfası açılır ve bildirim okundu olur.',
    'help_all' => 'Bütün personele gönderilen bildirimler; eskiler de dahil. "Alıcı" sütunu bildirimin kime gittiğini gösterir, süzgeçten kişi seçilebilir. Satıra tıklayınca bildirimin ilgili sayfası açılır; başkasının bildirimi okundu olmaz.',
    'open_all' => 'Tüm bildirimleri gör',
    'empty' => 'Henüz bildiriminiz yok',
    'empty_all' => 'Henüz bildirim yok',
    'fields' => [
        'status' => 'Durum',
        'recipient' => 'Alıcı',
        'title' => 'Başlık',
        'body' => 'İçerik',
        'created_at' => 'Tarih',
    ],
    'status' => [
        'read' => 'Okundu',
        'unread' => 'Okunmadı',
    ],
    'filters' => [
        'read' => 'Okunma durumu',
        'recipient' => 'Alıcı',
    ],
    'tabs' => [
        'all' => 'Tümü',
        'unread' => 'Okunmamış',
    ],
    'actions' => [
        'open' => 'Aç',
        'mark_read' => 'Okundu işaretle',
        'mark_unread' => 'Okunmadı işaretle',
        'mark_all_read' => 'Tümünü okundu işaretle',
    ],
];
