<?php

return [
    'label' => 'Yükümlülük',
    'plural' => 'Yükümlülükler',

    'sections' => [
        'main' => 'Yükümlülük bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'due_on' => 'Termin',
        'obligation_code' => 'Yükümlülük kodu',
        'obligation_type' => 'Yükümlülük türü',
        'reason' => 'Gerekçe',
        'responsible_party' => 'Sorumlu taraf',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Yükümlülükler',
        'empty' => 'Henüz yükümlülük yok.',
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
