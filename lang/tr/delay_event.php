<?php

return [
    'label' => 'Gecikme olayı',
    'plural' => 'Gecikme olayları',

    'sections' => [
        'main' => 'Gecikme bilgileri',
    ],

    'fields' => [
        'cause_category' => 'Neden',
        'created_at' => 'Oluşturulma',
        'delay_days' => 'Gecikme (gün)',
        'description' => 'Açıklama',
        'detected_at' => 'Tespit tarihi',
        'evidence_document_revision' => 'Evidence document revision',
        'is_excusable' => 'Mazur görülebilir',
        'project' => 'Proje',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'workstream' => 'Adım (departman)',
    ],

    'relation' => [
        'title' => 'Gecikmeler',
        'empty' => 'Henüz gecikme yok.',
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
