<?php

declare(strict_types=1);

/*
 * Gorev ve sorumluluklar (tekrarli isler, D-219, B57).
 */
return [
    'label' => 'Görev ve sorumluluk',
    'plural' => 'Görev ve sorumluluklar',
    'nav' => 'Görev ve sorumluluklar',
    'empty' => 'Görev ve sorumluluk tanımlanmadı',
    'empty_help' => 'Personelin düzenli işleri (rapor, mail kutusu, kontroller) burada tanımlanır; her dönem panosuna iş düşer.',
    'empty_person' => 'Bu kişiye tanımlı görev ve sorumluluk yok',

    'sections' => [
        'main' => 'Görev',
        'where' => 'Kapsam ve süre',
    ],

    'fields' => [
        'title' => 'Görev',
        'description' => 'Neye bakılacak',
        'personnel' => 'Sorumlu',
        'kind' => 'Tür',
        'frequency' => 'Sıklık',
        'weekday' => 'Gün',
        'month_day' => 'Ayın günü',
        'priority' => 'Kritiklik',
        'org_unit' => 'Departman',
        'project' => 'Taahhüt',
        'category' => 'Kategori',
        'starts_on' => 'Başlangıç',
        'ends_on' => 'Bitiş',
        'is_active' => 'Aktif',
        'compliance' => 'Son dönemler',
    ],

    'help' => [
        'title' => 'Örn. "Günlük saha raporu kontrolü", "Satın alma mail kutusu".',
        'weekday' => 'Haftalık işin beklendiği gün; boşsa pazartesi.',
        'month_day' => 'Aylık işin beklendiği gün; boşsa ayın ilk iş günü.',
        'ends_on' => 'Boşsa süresiz.',
        'is_active' => 'Kapalıyken dönem işi açılmaz; tanım ve geçmiş kalır.',
        'description' => 'Panodaki işin notuna yazılır.',
        'compliance' => 'Son sekiz dönem, eskiden yeniye: ✓ zamanında, ◔ geç, ✗ yapılmadı, ○ sürüyor.',
    ],

    'values' => [
        'active' => 'Aktif',
        'passive' => 'Pasif',
        'weekday_default' => 'Pazartesi',
        'every_workday' => 'Hafta içi her gün',
        'every_weekday' => 'Her :day',
        'every_month_day' => 'Her ayın :day. günü',
        'every_month_first' => 'Ayın ilk iş günü',
        'no_period' => 'Henüz dönem yok',
    ],

    'weekdays' => [
        1 => 'Pazartesi',
        2 => 'Salı',
        3 => 'Çarşamba',
        4 => 'Perşembe',
        5 => 'Cuma',
        6 => 'Cumartesi',
        7 => 'Pazar',
    ],

    'period' => [
        'week' => ':year / :week. hafta',
    ],

    'states' => [
        'on_time' => 'Zamanında',
        'late' => 'Geç',
        'missed' => 'Yapılmadı',
        'open' => 'Sürüyor',
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
    ],

    'actions' => [
        'create' => 'Yeni görev',
        'archive' => 'Arşivle',
        'unarchive' => 'Arşivden çıkar',
        'open_list' => 'Görev ve sorumluluklar',
    ],

    'occurrences' => [
        'title' => 'Dönem işleri',
        'help' => 'Her dönem sorumlunun panosuna açılan iş ve yapılıp yapılmadığı.',
        'period' => 'Dönem',
        'done_on' => 'Tamamlandı',
        'empty' => 'Henüz dönem işi açılmadı',
    ],
];
