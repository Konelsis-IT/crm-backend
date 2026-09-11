<?php

return [
    'label' => 'Onay kapısı gereksinim tanımı',
    'plural' => 'Gereksinim tanımları',

    'sections' => [
        'main' => 'Gereksinim bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'evidence_type' => 'Kanıt türü',
        'is_mandatory' => 'Zorunlu',
        'min_document_type' => 'Asgari doküman tipi',
        'name_en' => 'Ad (EN)',
        'name_tr' => 'Ad (TR)',
        'reason' => 'Gerekçe',
        'requirement_code' => 'Şart kodu',
        'sort_order' => 'Sıra',
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
