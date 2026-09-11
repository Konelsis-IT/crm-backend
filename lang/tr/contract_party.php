<?php

return [
    'label' => 'Sözleşme tarafı',
    'plural' => 'Sözleşme tarafları',

    'sections' => [
        'main' => 'Taraf bilgileri',
    ],

    'fields' => [
        'contract_role' => 'Sözleşme rolü',
        'created_at' => 'Oluşturulma',
        'party' => 'Taraf',
        'reason' => 'Gerekçe',
        'signatory_name' => 'İmza yetkilisi',
    ],

    'relation' => [
        'title' => 'Taraflar',
        'empty' => 'Henüz taraf yok.',
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
