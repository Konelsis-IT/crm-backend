<?php

return [
    'label' => 'Hukuki Tutma',
    'plural' => 'Hukuki Tutmalar',

    'sections' => [
        'main' => 'Tutma bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Ad',
        'reason' => 'Gerekçe',
        'requester' => 'Talep eden',
        'approver' => 'Onaylayan',
        'status' => 'Durum',
        'starts_at' => 'Başlangıç',
        'released_at' => 'Kaldırılma tarihi',
        'release_reason' => 'Kaldırma gerekçesi',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
    ],

    'relation' => [
        'documents' => [
            'title' => 'Kapsamdaki Dokümanlar',
            'empty' => 'Henüz doküman eklenmedi.',
        ],
    ],
];
