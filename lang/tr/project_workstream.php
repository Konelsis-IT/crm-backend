<?php

return [
    'label' => 'Workstream',
    'plural' => 'Workstream\'ler',

    'sections' => [
        'main' => 'Workstream bilgileri',
    ],

    'fields' => [
        'actual_finish_on' => 'Fiili bitiş',
        'actual_start_on' => 'Fiili başlangıç',
        'block_reason' => 'Blokaj gerekçesi',
        'created_at' => 'Oluşturulma',
        'group' => 'Grup',
        'owner' => 'Sahip',
        'planned_finish_on' => 'Planlanan bitiş',
        'planned_start_on' => 'Planlanan başlangıç',
        'progress_pct' => 'İlerleme (%)',
        'project' => 'Proje',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Workstream\'ler',
        'empty' => 'Henüz workstream yok.',
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
