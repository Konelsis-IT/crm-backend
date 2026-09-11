<?php

return [
    'label' => 'Sorun',
    'plural' => 'Sorunlar',

    'sections' => [
        'main' => 'Sorun bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'due_at' => 'Termin',
        'issue_no' => 'Sorun no',
        'owner' => 'Sahip',
        'raised_at' => 'Açılış',
        'reason' => 'Gerekçe',
        'resolution' => 'Çözüm',
        'severity' => 'Önem',
        'status' => 'Durum',
        'title' => 'Başlık',
        'workstream' => 'Adım (departman)',
    ],

    'relation' => [
        'title' => 'Sorunlar',
        'empty' => 'Henüz sorun yok.',
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
