<?php

return [
    'label' => 'Operasyon grubu',
    'plural' => 'Operasyon grupları',

    'sections' => [
        'main' => 'Grup bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'created_at' => 'Oluşturulma',
        'default_sort_order' => 'Varsayılan sıra',
        'name_en' => 'Ad (EN)',
        'name_tr' => 'Ad (TR)',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Gruplar',
        'empty' => 'Henüz grup yok.',
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
