<?php

return [
    'label' => 'Odak beklentisi',
    'plural' => 'Odak beklentileri',

    'sections' => [
        'main' => 'Beklenti tanımı',
    ],

    'fields' => [
        'group' => 'Adım (operasyon grubu)',
        'code' => 'Kod',
        'name_tr' => 'Ad (TR)',
        'name_en' => 'Ad (EN)',
        'kind' => 'Sayım kuralı',
        'min_count' => 'En az',
        'is_mandatory' => 'Zorunlu',
        'help_tr' => 'Yardım metni (TR)',
        'help_en' => 'Yardım metni (EN)',
        'sort_order' => 'Sıra',
        'status' => 'Durum',
        'created_at' => 'Oluşturulma',
        'reason' => 'Gerekçe',
    ],

    'help' => [
        'kind' => 'Projede hangi verinin sayılacağını belirler; sayı "en az" değerine ulaşınca beklenti karşılanmış olur.',
        'is_mandatory' => 'Zorunlu beklentiler karşılanmadan proje bir sonraki adıma (gerekçesiz) geçemez.',
    ],

    'relation' => [
        'title' => 'Beklentiler',
        'empty' => 'Bu adım için beklenti tanımlanmamış.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
    ],

    'messages' => [
        'done' => 'İşlem tamamlandı.',
        'status_changed' => 'Durum güncellendi.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod aynı adımda zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
