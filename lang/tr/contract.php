<?php

return [
    'label' => 'Sözleşme',
    'plural' => 'Sözleşmeler',

    'sections' => [
        'main' => 'Sözleşme bilgileri',
    ],

    'fields' => [
        'business_case' => 'İş dosyası',
        'contract_no' => 'Sözleşme no',
        'contract_type' => 'Sözleşme türü',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'customer_party' => 'Müşteri',
        'effective_from' => 'Yürürlük başlangıcı',
        'reason' => 'Gerekçe',
        'signed_on' => 'İmza tarihi',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Sözleşmeler',
        'empty' => 'Henüz sözleşme yok.',
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
