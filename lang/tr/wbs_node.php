<?php

return [
    'label' => 'İş kırılımı düğümü',
    'plural' => 'İş Kırılımı',

    'sections' => [
        'main' => 'İş kırılımı bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'level' => 'Seviye',
        'name' => 'Ad',
        'parent' => 'Üst',
        'project' => 'Proje',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'status' => 'Durum',
        'wbs_code' => 'İş kırılımı kodu',
    ],

    'relation' => [
        'title' => 'İş Kırılımı',
        'empty' => 'Henüz iş kırılımı düğümü yok.',
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
