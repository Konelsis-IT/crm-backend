<?php

return [
    'label' => 'Onay kapısı gereksinimi',
    'plural' => 'Onay kapısı gereksinimleri',

    'sections' => [
        'main' => 'Gereksinim bilgileri',
    ],

    'fields' => [
        'applicability' => 'Uygulanabilirlik',
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'due_at' => 'Termin',
        'evidence_type_snapshot' => 'Kanıt türü',
        'is_mandatory_snapshot' => 'Zorunlu',
        'name_snapshot_tr' => 'Gereksinim (TR)',
        'outcome_note' => 'Sonuç notu',
        'owner' => 'Sahip',
        'reason' => 'Gerekçe',
        'requirement_code_snapshot' => 'Gereksinim kodu',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Gereksinimler',
        'empty' => 'Henüz gereksinim yok.',
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
