<?php

return [
    'label' => 'Onay kapısı kanıtı',
    'plural' => 'Onay kapısı kanıtları',

    'sections' => [
        'main' => 'Kanıt bilgileri',
    ],

    'fields' => [
        'accepted_at' => 'Kabul tarihi',
        'acceptor' => 'Kabul eden',
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'evidence_hash' => 'Kanıt hash\'i',
        'reason' => 'Gerekçe',
        'requirement' => 'Gereksinim',
        'submitted_at' => 'Gönderim tarihi',
        'submitter' => 'Gönderen',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Kanıtlar',
        'empty' => 'Henüz kanıt yok.',
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
