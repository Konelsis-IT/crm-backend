<?php

return [
    'label' => 'Yetkinlik',
    'plural' => 'Yetkinlikler',

    'sections' => [
        'main' => 'Yetkinlik bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Yetkinlik adı',
        'category' => 'Kategori',
        'description' => 'Açıklama',
        'personnel_count' => 'Personel sayısı',
        'status' => 'Durum',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
    ],
];
