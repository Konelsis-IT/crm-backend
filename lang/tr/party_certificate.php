<?php

return [
    'label' => 'Sertifika',
    'plural' => 'Sertifikalar',

    'sections' => [
        'main' => 'Sertifika bilgileri',
    ],

    'fields' => [
        'certificate_no' => 'Sertifika no',
        'certificate_type' => 'Sertifika türü',
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'issued_on' => 'Veriliş tarihi',
        'issuer' => 'Veren kurum',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'valid_until' => 'Bitiş',
    ],

    'relation' => [
        'title' => 'Sertifikalar',
        'empty' => 'Henüz sertifika yok.',
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
