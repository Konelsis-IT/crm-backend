<?php

return [
    'label' => 'Faaliyet alanı',
    'plural' => 'Faaliyet alanları',

    'sections' => [
        'main' => 'Faaliyet alanı bilgileri',
    ],

    'fields' => [
        'parent' => 'Ana faaliyet alanı',
        'code' => 'Kod',
        'name_tr' => 'Ad (TR)',
        'name_en' => 'Ad (EN)',
        'sort_order' => 'Sıra',
        'status' => 'Durum',
    ],

    'help' => [
        'parent' => 'Boş bırakılırsa ana faaliyet alanı olur; seçilirse onun alt faaliyet alanı olur.',
    ],

    'values' => [
        'root' => 'Ana faaliyet alanı',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
    ],
];
