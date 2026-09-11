<?php

return [
    'label' => 'Maliyet kırılımı düğümü',
    'plural' => 'Maliyet Kırılımı',

    'sections' => [
        'main' => 'Maliyet kırılımı bilgileri',
    ],

    'fields' => [
        'cost_category' => 'Maliyet kategorisi',
        'cost_code' => 'Maliyet kodu',
        'created_at' => 'Oluşturulma',
        'name' => 'Ad',
        'parent' => 'Üst',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Maliyet Kırılımı',
        'empty' => 'Henüz maliyet kırılımı düğümü yok.',
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
