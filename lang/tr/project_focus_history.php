<?php

return [
    'label' => 'Odak geçmişi',
    'plural' => 'Odak geçmişi',

    'sections' => [
        'main' => 'Odak bilgileri',
    ],

    'fields' => [
        'changer' => 'Değiştiren',
        'created_at' => 'Oluşturulma',
        'direction' => 'Yön',
        'ended_at' => 'Bitiş',
        'reason' => 'Gerekçe',
        'started_at' => 'Başlangıç',
        'workstream' => 'Adım (departman)',
    ],

    'relation' => [
        'title' => 'Odak Geçmişi',
        'empty' => 'Henüz odak kaydı yok.',
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
