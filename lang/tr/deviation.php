<?php

return [
    'label' => 'Sapma',
    'plural' => 'Sapmalar',

    'sections' => [
        'main' => 'Sapma bilgileri',
    ],

    'fields' => [
        'compliance_item' => 'Uygunluk maddesi',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'deviation_type' => 'Sapma türü',
        'justification' => 'Gerekçe',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Sapmalar',
        'empty' => 'Henüz sapma yok.',
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
