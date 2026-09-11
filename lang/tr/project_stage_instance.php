<?php

return [
    'label' => 'Proje onay kapısı',
    'plural' => 'Proje onay kapıları',

    'sections' => [
        'main' => 'Onay kapısı bilgileri',
    ],

    'fields' => [
        'comment' => 'Yorum',
        'condition_due_on' => 'Şart telafi tarihi',
        'conditions' => 'Koşullar',
        'created_at' => 'Oluşturulma',
        'decision' => 'Karar',
        'entered_at' => 'Giriş',
        'name' => 'Ad',
        'owner' => 'Sahip',
        'passed_at' => 'Geçiş',
        'project' => 'Proje',
        'reason' => 'Gerekçe',
        'remediation_due_on' => 'Telafi tarihi',
        'requirement' => 'Gereksinim',
        'risk_owner' => 'Risk sahibi',
        'stage_code' => 'Onay kapısı kodu',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Onay kapıları',
        'empty' => 'Henüz onay kapısı yok.',
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
