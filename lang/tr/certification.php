<?php

return [
    'label' => 'Sertifika',
    'plural' => 'Sertifikalar',

    'sections' => [
        'main' => 'Sertifika bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'name' => 'Sertifika adı',
        'issuer' => 'Veren kurum',
        'validity_months' => 'Geçerlilik (ay)',
        'is_field_mandatory' => 'Saha görevi ön koşulu',
        'personnel_count' => 'Personel sayısı',
        'status' => 'Durum',
        'certificate_no' => 'Belge no',
        'issued_on' => 'Veriliş tarihi',
        'valid_until' => 'Geçerlilik tarihi',
        'verified_by' => 'Doğrulayan',
    ],

    'help' => [
        'validity_months' => 'Boş bırakılırsa süresiz kabul edilir.',
        'valid_until' => 'Boş bırakılırsa süresiz kabul edilir; durum bu tarihe göre otomatik hesaplanır.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu personel için aynı sertifika ve tarihte bir kayıt zaten var.',
    ],

    'relation' => [
        'title' => 'Sertifikalar',
        'empty' => 'Kayıtlı sertifika yok.',
    ],
];
