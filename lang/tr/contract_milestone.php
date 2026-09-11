<?php

return [
    'label' => 'Sözleşme kilometre taşı',
    'plural' => 'Sözleşme kilometre taşları',

    'sections' => [
        'main' => 'Kilometre taşı bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'milestone_code' => 'Kilometre taşı kodu',
        'name' => 'Ad',
        'payment_amount' => 'Ödeme tutarı',
        'payment_pct' => 'Ödeme (%)',
        'planned_on' => 'Planlanan tarih',
        'reason' => 'Gerekçe',
    ],

    'relation' => [
        'title' => 'Kilometre Taşları',
        'empty' => 'Henüz kilometre taşı yok.',
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
