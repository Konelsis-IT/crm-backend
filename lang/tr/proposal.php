<?php

return [
    'label' => 'Teklif',
    'plural' => 'Teklifler',

    'sections' => [
        'main' => 'Teklif bilgileri',
    ],

    'fields' => [
        'business_case' => 'İş dosyası',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'is_selected' => 'Seçili',
        'offer_status' => 'Teklif durumu',
        'owner' => 'Sahip',
        'proposal_no' => 'Teklif no',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Teklifler',
        'empty' => 'Henüz teklif yok.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu ":status" yap',
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
