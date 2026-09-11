<?php

return [
    'label' => 'İhale ilanı',
    'plural' => 'İhale ilanları',

    'sections' => [
        'identity' => 'İlan kimliği',
        'publication' => 'Yayın ve durum',
        'summary' => 'Özet',
        'main' => 'İlan bilgileri',
    ],

    'fields' => [
        'business_case' => 'İş dosyası',
        'captured_at' => 'Yakalanma tarihi',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'external_notice' => 'Harici ilan no',
        'issuer_party' => 'İlanı veren',
        'notice_url' => 'İlan adresi',
        'published_on' => 'Yayım tarihi',
        'reason' => 'Gerekçe',
        'source_document_revision' => 'İlan dosyası',
        'status' => 'Durum',
        'summary' => 'Özet',
        'tender_source' => 'İhale kaynağı',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'İhale İlanları',
        'empty' => 'Henüz ilan yok.',
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
