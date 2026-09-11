<?php

return [
    'label' => 'Telafi aksiyonu',
    'plural' => 'Telafi aksiyonları',

    'sections' => [
        'main' => 'Aksiyon bilgileri',
    ],

    'fields' => [
        'completed_at' => 'Tamamlanma',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'due_at' => 'Termin',
        'expected_recovery_days' => 'Beklenen telafi (gün)',
        'owner' => 'Sahip',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Telafi Aksiyonları',
        'empty' => 'Henüz aksiyon yok.',
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
