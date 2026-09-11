<?php

return [
    'label' => 'Onay politikası',
    'plural' => 'Onay Politikaları',

    'sections' => [
        'main' => 'Politika bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name_tr' => 'Ad (TR)',
        'name_en' => 'Ad (EN)',
        'subject_type' => 'Konu türü',
        'current_version' => 'Yayımlı sürüm',
        'status' => 'Durum',
    ],

    'help' => [
        'main' => 'Politika, bir konu türü (örn. doküman revizyonu) için onay akışını tanımlar. Adımlar sürümde tutulur; yayımlanan sürüm değişmez.',
        'code' => 'Büyük harf, tekil; örn. DOC_REVISION_STANDARD.',
        'subject_type' => 'Politikanın uygulanacağı kayıt türü. Yayımlı sürüm varken değiştirilemez.',
        'no_version' => 'Henüz yayımlı sürüm yok',
    ],

    'subject_types' => [
        'work_request' => 'Talep',
        'document_revision' => 'Doküman revizyonu',
    ],

];
