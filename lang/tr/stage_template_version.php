<?php

return [
    'label' => 'Şablon sürümü',
    'plural' => 'Şablon sürümleri',

    'sections' => [
        'main' => 'Sürüm bilgileri',
    ],

    'fields' => [
        'change_summary' => 'Değişiklik özeti',
        'created_at' => 'Oluşturulma',
        'published_at' => 'Yayım tarihi',
        'publisher' => 'Yayımlayan',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'template' => 'Şablon',
        'version_no' => 'Sürüm no',
    ],

    'relation' => [
        'title' => 'Sürümler',
        'empty' => 'Henüz sürüm yok.',
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
