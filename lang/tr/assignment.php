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
        'empty' => 'Henüz bir amir kaydedilmedi.',
    ],

    'reporting' => [
        'kind' => 'Amir türü',
        'add' => 'Ek amir ekle',
        'close' => 'Kapat',
        'kind_help' => 'Doğrudan amir tektir; ikinci ve sonraki amirler işlevsel ya da proje amiri olarak eklenir.',
        'title' => 'Amir Geçmişi',
        'help' => 'Doğrudan amir tektir; kişi birden fazla müdüre bağlı olabilir, ek amirler işlevsel ya da proje amiri olarak eklenir.',
        'empty' => 'Henüz bir amir kaydedilmedi.',
        'manager' => 'Amir',
    ],
];
