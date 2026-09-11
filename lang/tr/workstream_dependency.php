<?php

return [
    'label' => 'Workstream bağımlılığı',
    'plural' => 'Workstream bağımlılıkları',

    'sections' => [
        'main' => 'Bağımlılık bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'dependency_type' => 'Bağımlılık türü',
        'is_hard' => 'Hard bağımlılık',
        'lag_days' => 'Gecikme (gün)',
        'predecessor' => 'Öncül',
        'predecessor_workstream' => 'Predecessor workstream',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'waiver' => 'Muaf tutan',
        'waiver_reason' => 'Muafiyet gerekçesi',
    ],

    'relation' => [
        'title' => 'Bağımlılıklar (öncüller)',
        'empty' => 'Henüz bağımlılık yok.',
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
