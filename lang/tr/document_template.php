<?php

return [
    'label' => 'Şablon',
    'plural' => 'Şablonlar',

    'sections' => [
        'main' => 'Şablon bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Şablon adı',
        'output_kind' => 'Çıktı türü',
        'status' => 'Durum',
        'version_no' => 'Sürüm no',
        'locale' => 'Dil',
        'view_key' => 'Görünüm anahtarı',
        'version_status' => 'Sürüm durumu',
        'published_at' => 'Yayım tarihi',
        'publisher' => 'Yayımlayan',
        'layout_config' => 'Yerleşim ayarları',
        'required_field_keys' => 'Zorunlu alan anahtarları',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
    ],

    'relation' => [
        'versions' => [
            'title' => 'Sürümler',
            'empty' => 'Henüz sürüm yok.',
        ],
    ],
];
