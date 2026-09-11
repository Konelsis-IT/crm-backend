<?php

return [
    'label' => 'Proje kararı',
    'plural' => 'Proje kararları',

    'sections' => [
        'main' => 'Karar bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'decided_at' => 'Karar tarihi',
        'decider' => 'Karar veren',
        'decision_no' => 'Karar no',
        'decision_scope' => 'Kapsam',
        'description' => 'Açıklama',
        'document_revision' => 'Doküman revizyonu',
        'reason' => 'Gerekçe',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Kararlar',
        'empty' => 'Henüz karar yok.',
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
