<?php

return [
    'label' => 'İş geliştirme aktivitesi',
    'plural' => 'Aktiviteler',

    'sections' => [
        'main' => 'Aktivite bilgileri',
    ],

    'fields' => [
        'activity_type' => 'Aktivite türü',
        'created_at' => 'Oluşturulma',
        'location' => 'Yer',
        'next_action' => 'Sonraki adım',
        'next_action_due_at' => 'Sonraki adım termini',
        'occurred_at' => 'Tarih ve saat',
        'organizer' => 'Organizatör',
        'outcome_summary' => 'Sonuç özeti',
        'party' => 'Taraf',
        'reason' => 'Gerekçe',
        'subject' => 'Konu',
    ],

    'relation' => [
        'title' => 'Aktiviteler',
        'empty' => 'Henüz aktivite yok.',
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
