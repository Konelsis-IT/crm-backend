<?php

return [
    'label' => 'Teklif dokümanı',
    'plural' => 'Teklif dokümanları',

    'sections' => [
        'main' => 'Doküman bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'document_role' => 'Doküman rolü',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Dokümanlar',
        'empty' => 'Henüz doküman yok.',
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
