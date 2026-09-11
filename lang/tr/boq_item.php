<?php

return [
    'label' => 'BOQ kalemi',
    'plural' => 'BOQ kalemleri',

    'sections' => [
        'main' => 'Kalem bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'item_code' => 'Madde kodu',
        'quantity' => 'Miktar',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'unit_price' => 'Birim fiyat',
        'uom' => 'Birim',
    ],

    'relation' => [
        'title' => 'BOQ',
        'empty' => 'Henüz kalem yok.',
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
