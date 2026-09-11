<?php

return [
    'label' => 'Uygunluk maddesi',
    'plural' => 'Uygunluk maddeleri',

    'sections' => [
        'main' => 'Madde bilgileri',
    ],

    'fields' => [
        'compliance_state' => 'Uygunluk',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'note' => 'Not',
        'reason' => 'Gerekçe',
        'requirement_code' => 'Şart kodu',
        'sort_order' => 'Sıra',
        'tender_requirement' => 'İhale şartı',
    ],

    'relation' => [
        'title' => 'Şartname Uygunluğu',
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
