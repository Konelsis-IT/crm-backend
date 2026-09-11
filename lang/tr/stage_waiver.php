<?php

return [
    'label' => 'Onay kapısı muafiyeti',
    'plural' => 'Onay kapısı muafiyetleri',

    'sections' => [
        'main' => 'Muafiyet bilgileri',
    ],

    'fields' => [
        'approver' => 'Onaylayan',
        'created_at' => 'Oluşturulma',
        'granted_at' => 'Verilme tarihi',
        'reason' => 'Gerekçe',
        'remediation_due_on' => 'Telafi tarihi',
        'requirement' => 'Gereksinim',
        'risk_owner' => 'Risk sahibi',
    ],

    'relation' => [
        'title' => 'Muafiyetler',
        'empty' => 'Henüz muafiyet yok.',
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
