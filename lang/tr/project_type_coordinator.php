<?php

declare(strict_types=1);

// Proje tipi koordinatorleri (B49, D-175).
return [
    'label' => 'Proje tipi koordinatörü',
    'plural' => 'Proje tipi koordinatörleri',
    'nav' => 'Proje tipi koordinatörleri',
    'title' => 'Proje tipi koordinatörleri',
    'subheading' => 'Koordinatör, proje müdürlerinden bağımsız olarak o proje tipinin bütün projeleriyle ilgilenir ve görüşür. Her tipin bir koordinatörü olur; seçimi boşaltmak koordinatörü kaldırır, eski atama geçmişte kalır.',

    'columns' => [
        'type' => 'Proje tipi',
        'coordinator' => 'Koordinatör',
        'since' => 'Atandığı tarih',
        'project_count' => 'Proje sayısı',
    ],

    'fields' => [
        'personnel' => 'Koordinatör',
        // Proje sayfasi, proje listesi, personel listesi
        'project_coordinators' => 'Proje tipi koordinatörleri',
        'project_coordinator' => 'Proje tipi koordinatörü',
        'personnel_roles' => 'Proje tipi koordinatörlüğü',
    ],

    'help' => [
        'personnel' => 'Yalnız aktif personel seçilebilir. Eski koordinatörün ataması geçmişte kalır.',
    ],

    'values' => [
        'none' => 'Atanmadı',
        'badge' => ':type koordinatörü',
        'project_item' => ':type: :name',
        'projects' => ':count proje',
        'since' => ':date tarihinden beri',
    ],

    'actions' => [
        'assign' => 'Koordinatör ata',
        'change' => 'Koordinatörü değiştir',
        'remove' => 'Koordinatörü kaldır',
        'save' => 'Kaydet',
        'close' => 'Kapat',
    ],

    'modals' => [
        'assign_heading' => ':type koordinatörü',
        'remove_heading' => ':type koordinatörünü kaldır',
        'remove_description' => ':name bu tipin koordinatörlüğünden alınır; ataması geçmişte kalır.',
    ],

    'messages' => [
        'assigned' => 'Koordinatör atandı.',
        'removed' => 'Koordinatör kaldırıldı.',
        'saved' => 'Koordinatörler kaydedildi.',
        'unchanged' => 'Değişiklik yok.',
    ],

    'empty' => 'Proje tipi bulunamadı.',
];
