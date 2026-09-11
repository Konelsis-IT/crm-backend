<?php

return [
    'label' => 'Rol',

    'fields' => [
        'roles' => 'Roller',
    ],

    'help' => [
        'roles' => 'Bu personelin sahip olduğu roller; panel erişimi ve yetkiler buna göre belirlenir.',
    ],
    // Teknik rol adlarinin ekran karsiligi; pozisyon rolleri zaten okunur baslik tasir (D-81).
    'names' => [
        'system_admin' => 'Sistem yöneticisi',
        'auditor' => 'Denetçi',
    ],

];
