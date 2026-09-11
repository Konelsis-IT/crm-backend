<?php

return [
    'label' => 'Onay kapısı incelemesi',
    'plural' => 'Onay kapısı incelemeleri',

    'sections' => [
        'main' => 'İnceleme bilgileri',
    ],

    'fields' => [
        'comment' => 'Yorum',
        'conditions' => 'Koşullar',
        'created_at' => 'Oluşturulma',
        'decided_at' => 'Karar tarihi',
        'decision' => 'Karar',
        'reason' => 'Gerekçe',
        'reviewer' => 'İnceleyen',
    ],

    'relation' => [
        'title' => 'İncelemeler',
        'empty' => 'Henüz inceleme yok.',
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
