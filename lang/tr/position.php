<?php

return [
    'label' => 'Pozisyon',
    'plural' => 'Pozisyonlar',

    'sections' => [
        'main' => 'Pozisyon bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'title' => 'Pozisyon adı',
        'org_unit' => 'Organizasyon birimi',
        'grade' => 'Kademe',
        'managerial_level' => 'Yöneticilik düzeyi',
        'headcount' => 'Kadro sayısı',
        'assignee_count' => 'Atanan personel',
        'status' => 'Durum',
        'is_primary' => 'Asıl pozisyon',
        'allocation_pct' => 'Pay (%)',
        'valid_from' => 'Başlangıç',
        'valid_until' => 'Bitiş',
    ],

    'help' => [
        'managerial_level' => '0 = yönetici değil; en fazla 5.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate_primary' => 'Bu personelin zaten bir asıl pozisyonu var.',
    ],

    'relation' => [
        'title' => 'Pozisyonlar',
        'empty' => 'Atanmış pozisyon yok.',
    ],
];
