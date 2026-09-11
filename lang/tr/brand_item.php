<?php

return [
    'label' => 'Marka maddesi',
    'plural' => 'Marka listesi',

    'sections' => [
        'main' => 'Marka bilgileri',
    ],

    'fields' => [
        'alternative_brand' => 'Alternatif marka',
        'approval_state' => 'Onay durumu',
        'created_at' => 'Oluşturulma',
        'item_code' => 'Madde kodu',
        'item_description' => 'Kalem açıklaması',
        'origin_country' => 'Menşe ülke',
        'proposed_brand' => 'Önerilen marka',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
    ],

    'relation' => [
        'title' => 'Marka Listesi',
        'empty' => 'Henüz marka yok.',
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
