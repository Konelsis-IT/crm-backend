<?php

return [
    'label' => 'Eğitim',
    'plural' => 'Eğitimler',

    'sections' => [
        'main' => 'Eğitim bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Eğitim adı',
        'training_kind' => 'Tür',
        'provider' => 'Sağlayıcı',
        'planned_on' => 'Planlanan tarih',
        'duration_hours' => 'Süre (saat)',
        'attendee_count' => 'Katılımcı sayısı',
        'status' => 'Durum',
        'attended_on' => 'Katılım tarihi',
        'outcome' => 'Sonuç',
        'score' => 'Puan',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu personel bu eğitime zaten kayıtlı.',
    ],

    'relation' => [
        'title' => 'Eğitimler',
        'empty' => 'Kayıtlı eğitim katılımı yok.',
    ],
];
