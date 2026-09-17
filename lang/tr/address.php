<?php

return [
    'label' => 'Adres',
    'plural' => 'Adresler',

    'sections' => [
        'main' => 'Adres bilgileri',
    ],

    'fields' => [
        'address_type' => 'Adres türü',
        'city' => 'Şehir',
        'country' => 'Ülke',
        'created_at' => 'Oluşturulma',
        'abroad' => 'Türkiye dışında',
        'district' => 'İlçe',
        'is_primary' => 'Varsayılan',
        'line1' => 'Adres satırı 1',
        'line2' => 'Adres satırı 2',
        'postal_code' => 'Posta kodu',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Adresler',
        'empty' => 'Henüz adres yok.',
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
