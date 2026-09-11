<?php

return [
    'label' => 'Paylaşım bağlantısı',
    'plural' => 'Paylaşımlar',

    'sections' => [
        'main' => 'Paylaşım bilgileri',
    ],

    'fields' => [
        'label' => 'Etiket',
        'url' => 'Bağlantı',
        'status' => 'Durum',
        'allow_download' => 'İndirmeye izin ver',
        'expires_at' => 'Son geçerlilik',
        'access_count' => 'Açılma',
        'last_accessed_at' => 'Son açılma',
        'created_by' => 'Oluşturan',
        'created_at' => 'Oluşturulma',
    ],

    'help' => [
        'label' => 'Kime/niçin gönderildiği (örn. "Müşteriye gönderildi").',
        'expires_at' => 'Boş bırakılırsa bağlantı iptal edilene kadar açık kalır.',
        'relation' => 'Bağlantıyı bilen herkes belgeyi görür; artık gerekmeyen bağlantıyı iptal edin.',
    ],

    'values' => [
        'no_expiry' => 'Süresiz',
    ],

    'actions' => [
        'revoke' => 'Bağlantıyı iptal et',
    ],

    'messages' => [
        'copied' => 'Bağlantı kopyalandı.',
        'revoked' => 'Paylaşım bağlantısı iptal edildi.',
        'empty' => 'Henüz paylaşım bağlantısı yok.',
    ],
];
