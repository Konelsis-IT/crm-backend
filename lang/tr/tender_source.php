<?php

return [
    'label' => 'İhale kaynağı',
    'plural' => 'İhale kaynakları',

    'sections' => [
        'main' => 'Kaynak bilgileri',
    ],

    'fields' => [
        'access_mode' => 'Erişim yolu',
        'base_url' => 'Ana adres',
        'code' => 'Kod',
        'created_at' => 'Oluşturulma',
        'name_en' => 'Ad (EN)',
        'name_tr' => 'Ad (TR)',
        'reason' => 'Gerekçe',
        'scraping_allowed' => 'Otomatik tarama izni',
        'source_type' => 'Kaynak türü',
        'status' => 'Durum',
        'terms_reference' => 'Kullanım şartı referansı',
    ],

    'relation' => [
        'title' => 'İhale Kaynakları',
        'empty' => 'Henüz kaynak yok.',
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
