<?php

return [
    'label' => 'Görüşme notu',
    'plural' => 'Görüşme notları',

    'sections' => [
        'main' => 'Görüşme bilgileri',
    ],

    'fields' => [
        'channel' => 'Kanal',
        'contact' => 'Görüşülen kişi',
        'created_at' => 'Oluşturulma',
        'next_action' => 'Sonraki adım',
        'next_action_on' => 'Sonraki adım tarihi',
        'note' => 'Not',
        'noted_on' => 'Görüşme tarihi',
        'personnel' => 'Görüşen personel',
        'reason' => 'Gerekçe',
        'subject' => 'Konu',
    ],

    'relation' => [
        'title' => 'Görüşme notları',
        'empty' => 'Henüz görüşme notu yok.',
    ],

    'actions' => [
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
