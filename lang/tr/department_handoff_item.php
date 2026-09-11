<?php

return [
    'label' => 'Devir maddesi',
    'plural' => 'Devir maddeleri',

    'sections' => [
        'main' => 'Madde bilgileri',
    ],

    'fields' => [
        'completion_state' => 'Tamamlanma',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'document_revision' => 'Doküman revizyonu',
        'item_code' => 'Madde kodu',
        'item_type' => 'Madde türü',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
    ],

    'relation' => [
        'title' => 'Manifest Maddeleri',
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
