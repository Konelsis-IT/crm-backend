<?php

return [
    'label' => 'Maliyet payı',
    'plural' => 'Maliyet payları',

    'sections' => [
        'main' => 'Maliyet payı bilgileri',
    ],

    'fields' => [
        'allocation_pct' => 'Dağılım (%)',
        'cbs_node' => 'Maliyet kırılımı düğümü',
        'cost_category' => 'Maliyet kategorisi',
        'created_at' => 'Oluşturulma',
        'name' => 'Ad',
        'reason' => 'Gerekçe',
    ],

    'relation' => [
        'title' => 'Maliyet Dağılımı',
        'empty' => 'Henüz maliyet payı yok.',
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
