<?php

return [
    'label' => 'İletişim noktası',
    'plural' => 'İletişim noktaları',

    'sections' => [
        'main' => 'İletişim bilgileri',
    ],

    'fields' => [
        'channel_type' => 'Kanal',
        'created_at' => 'Oluşturulma',
        'is_primary' => 'Varsayılan',
        'purpose' => 'Amaç',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'value' => 'Değer',
    ],

    'relation' => [
        'title' => 'İletişim Noktaları',
        'empty' => 'Henüz iletişim noktası yok.',
    ],

    'actions' => [
        'copy' => 'Kopyala',
        'copied' => 'Kopyalandı',
        'group' => 'İletişim',
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
