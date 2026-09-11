<?php

return [
    'label' => 'Lisans',
    'plural' => 'Lisanslar',

    'sections' => [
        'main' => 'Lisans bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'issued_on' => 'Veriliş tarihi',
        'issuer' => 'Veren kurum',
        'license_no' => 'Lisans no',
        'license_type' => 'Lisans türü',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'valid_until' => 'Bitiş',
    ],

    'relation' => [
        'title' => 'Lisanslar',
        'empty' => 'Henüz lisans yok.',
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
