<?php

return [
    'label' => 'Ticari açıklama talebi',
    'plural' => 'Ticari açıklama talepleri',

    'sections' => [
        'main' => 'Talep bilgileri',
    ],

    'fields' => [
        'clarification_no' => 'Talep no',
        'clarification_type' => 'Tür',
        'created_at' => 'Oluşturulma',
        'customer_contact_party' => 'Müşteri iletişim kişisi (taraf)',
        'description' => 'Açıklama',
        'linked_change' => 'Bağlı değişiklik',
        'reason' => 'Gerekçe',
        'responded_at' => 'Yanıt tarihi',
        'response' => 'Yanıt',
        'status' => 'Durum',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Ticari Açıklamalar',
        'empty' => 'Henüz talep yok.',
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
