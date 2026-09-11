<?php

return [
    'label' => 'Doküman Tipi',
    'plural' => 'Doküman Tipleri',

    'sections' => [
        'main' => 'Tip bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Tip adı',
        'discipline' => 'Disiplin',
        'numbering_prefix' => 'Numaralandırma öneki',
        'is_controlled' => 'Kontrollü doküman',
        'allowed_extensions' => 'İzin verilen uzantılar',
        'max_byte_size' => 'Azami dosya boyutu (bayt)',
        'default_classification' => 'Varsayılan gizlilik sınıfı',
        'default_retention_policy' => 'Varsayılan saklama politikası',
        'document_count' => 'Doküman sayısı',
        'status' => 'Durum',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
    ],
];
