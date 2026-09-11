<?php

return [
    'label' => 'Organizasyon Birimi',
    'plural' => 'Organizasyon Birimleri',

    'sections' => [
        'main' => 'Birim bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Birim adı',
        'unit_type' => 'Tür',
        'parent' => 'Üst Birim',
        'manager' => 'Yönetici',
        'cost_center_code' => 'Masraf merkezi kodu',
        'personnel_count' => 'Personel sayısı',
        'status' => 'Durum',
        'valid_from' => 'Geçerlilik başlangıcı',
        'valid_until' => 'Geçerlilik bitişi',
    ],

    'help' => [
        'parent' => 'Değiştirdiğinizde eski üst birim tarihe kaydedilir, yenisi bugünden geçerli olur.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'self_parent' => 'Bir birim kendi üst birimi olamaz.',
    ],
];
