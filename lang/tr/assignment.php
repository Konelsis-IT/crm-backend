<?php

return [
    'fields' => [
        'department' => 'Departman',
        'job_title' => 'Görev',
        'direct_manager' => 'Doğrudan Amir',
        'effective_from' => 'Başlangıç',
        'effective_to' => 'Bitiş',
    ],

    'messages' => [
        'ongoing' => 'Devam ediyor',
    ],

    'relation' => [
        'title' => 'Atama Geçmişi',
        'help' => 'Organizasyon birimi ve görev değişiklikleri',
        'empty' => 'Henüz bir değişiklik kaydedilmedi.',
    ],

    'reporting' => [
        'title' => 'Raporlama Geçmişi',
        'help' => 'Doğrudan amir değişiklikleri',
        'empty' => 'Henüz bir değişiklik kaydedilmedi.',
        'manager' => 'Doğrudan Amir',
    ],
];
