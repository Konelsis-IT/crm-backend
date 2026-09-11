<?php

return [
    'label' => 'Son tarih',
    'plural' => 'Son tarihler',

    'sections' => [
        'main' => 'Son tarih bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'deadline_type' => 'Son tarih türü',
        'due_at_utc' => 'UTC son an',
        'local_due_date' => 'Yerel tarih',
        'local_due_time' => 'Yerel saat',
        'reason' => 'Gerekçe',
        'timezone' => 'Saat dilimi',
    ],

    'relation' => [
        'title' => 'Son Tarihler',
        'empty' => 'Henüz son tarih yok.',
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
