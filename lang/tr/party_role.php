<?php

return [
    'label' => 'Taraf rolü',
    'plural' => 'Taraf rolleri',

    'sections' => [
        'main' => 'Rol bilgileri',
    ],

    'fields' => [
        'approved_by' => 'Approved by',
        'approver' => 'Onaylayan',
        'created_at' => 'Oluşturulma',
        'reason' => 'Gerekçe',
        'role_code' => 'Rol',
        'status' => 'Durum',
        'valid_from' => 'Başlangıç',
        'valid_until' => 'Bitiş',
    ],

    'relation' => [
        'title' => 'Roller',
        'empty' => 'Henüz rol yok.',
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
