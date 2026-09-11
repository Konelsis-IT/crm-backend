<?php

return [
    'label' => 'Sorumluluk maddesi',
    'plural' => 'Sorumluluk matrisi',

    'sections' => [
        'main' => 'Sorumluluk bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'note' => 'Not',
        'reason' => 'Gerekçe',
        'responsible_party_role' => 'Sorumlu taraf',
        'scope_code' => 'Kapsam kodu',
        'scope_description' => 'Kapsam',
        'sort_order' => 'Sıra',
    ],

    'relation' => [
        'title' => 'Sorumluluk Matrisi',
        'empty' => 'Henüz madde yok.',
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
