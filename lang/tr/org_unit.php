<?php

return [
    'label' => 'Organizasyon Birimi',
    'plural' => 'Organizasyon Birimleri',

    'sections' => [
        'main' => 'Birim bilgileri',
        'projects' => 'Taahhütlerde',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Birim adı',
        'unit_type' => 'Tür',
        'parent' => 'Üst Birim',
        'manager' => 'Yönetici',
        'cost_center_code' => 'Masraf merkezi kodu',
        'personnel_count' => 'Personel sayısı',
        'position_count' => 'Görev sayısı',
        'status' => 'Durum',
        'valid_from' => 'Geçerlilik başlangıcı',
        'valid_until' => 'Geçerlilik bitişi',
        'in_projects' => 'Taahhütlerde çalışır',
        'project_sort' => 'Taahhütteki sırası',
        'in_projects_short' => 'Taahhütlerde',
        'in_projects_value' => 'Evet · :sort. sırada',
        'in_projects_no' => 'Hayır',
    ],

    'help' => [
        'parent' => 'Değiştirdiğinizde eski üst birim tarihe kaydedilir, yenisi bugünden geçerli olur.',
        'in_projects' => 'Açıksa bu departman her yeni taahhüdün Departmanlar sekmesinde yer alır: sorumlusu, ilerlemesi ve tarihleri orada izlenir.',
        'project_sort' => 'Taahhüdün Departmanlar sekmesindeki sırası (küçük önce).',
    ],

    'relation' => [
        'personnel' => 'Personel',
        'personnel_empty' => 'Bu birime bağlı personel yok.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'self_parent' => 'Bir birim kendi üst birimi olamaz.',
    ],
];
