<?php

return [
    'label' => 'Teslim Tutanağı',
    'plural' => 'Teslim Tutanakları',

    'sections' => [
        'main' => 'Tutanak bilgileri',
    ],

    'fields' => [
        'project' => 'Proje',
        'recipient_party' => 'Alıcı taraf',
        'transmittal_no' => 'Tutanak no',
        'recipient_description' => 'Alıcı',
        'purpose' => 'Amaç',
        'status' => 'Durum',
        'issuer' => 'Gönderen',
        'issued_at' => 'Gönderim tarihi',
        'cover_revision' => 'Kapak dokümanı',
        'external_reference' => 'Dış referans',
    ],

    'help' => [
        'transmittal_no' => 'Otomatik üretilir.',
    ],

    'relation' => [
        'items' => [
            'title' => 'Kalemler',
            'empty' => 'Henüz kalem eklenmedi.',
        ],
    ],
];
