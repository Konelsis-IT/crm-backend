<?php

declare(strict_types=1);

// Ayarlar > Birimler ve satin alma birim secimi (D-194, B54).
return [
    'label' => 'Birim',
    'plural' => 'Birimler',
    'nav' => 'Birimler',
    'field' => 'Birim',
    'fields' => [
        'name' => 'Ad',
        'symbol' => 'Simge',
        'dimension' => 'Tür',
        'status' => 'Durum',
    ],
    'help' => [
        'name' => 'Seçim listelerinde ve belgelerde görünen ad, örneğin Metre.',
        'symbol' => 'Kısa yazılışı, örneğin m. Listede adın yanında görünür.',
        'locked' => 'Malzeme / hizmetin birimi; ürünün biriminden gelir.',
        'catalog_required' => 'Ürün oluşurken birimi belirlenir; satın alma satırlarında bu birim kendiliğinden gelir.',
        'deactivate' => 'Pasif birim seçim listelerinden kalkar; bu birimi kullanan kayıtlar onu göstermeye devam eder.',
        'activate' => 'Birim seçim listelerine geri döner.',
    ],
    'filters' => [
        'dimension' => 'Tür',
        'status' => 'Durum',
    ],
    'actions' => [
        'create' => 'Birim ekle',
        'deactivate' => 'Pasife al',
        'activate' => 'Aktif yap',
    ],
    'messages' => [
        'deactivated' => 'Birim pasife alındı.',
        'activated' => 'Birim aktif yapıldı.',
    ],
    'values' => [
        'inactive_suffix' => '(pasif)',
    ],
    'empty' => 'Henüz birim yok.',
];
