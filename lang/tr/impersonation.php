<?php

declare(strict_types=1);

/*
 * Personel degistirme (D-120): yalniz gizli sistem hesabinda gorunur.
 */
return [
    'actions' => [
        'switch' => 'Personel değiştir',
        'submit' => 'Bu personele geç',
        'stop' => 'Kendi hesabıma dön',
    ],

    'modal' => [
        'heading' => 'Personel değiştir',
        'description' => 'Seçilen personelin gördüğü arayüze geçersiniz; yaptığınız kayıtlar kendi hesabınıza yazılır.',
    ],

    'fields' => [
        'personnel' => 'Personel',
    ],

    'messages' => [
        'failed' => 'Bu personele geçilemedi.',
        'started' => ':name olarak görüyorsunuz.',
        'stopped' => 'Kendi hesabınıza döndünüz.',
    ],
];
