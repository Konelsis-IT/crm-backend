<?php

// Referanslar (B50, D-177).
return [
    'label' => 'Referans',
    'plural' => 'Referanslar',

    'fields' => [
        'title' => 'Referans',
        'scope_types' => 'Proje tipi',
        'sort_order' => 'Sıra',
        'archived_at' => 'Arşive alındığı tarih',
    ],

    'help' => [
        'title' => 'Yapılan işin listede görünecek metni (örnek: "METGÜN ENERJİ ELBİSTAN GÜNEŞ ENERJİ SANTRALİ KOMPLE EPC SİSTEMLERİ (50MW)").',
        'scope_types' => 'Referansın görüneceği proje tipleri; birden fazla seçilebilir.',
        'archive' => 'Referans listelerden ve Excel\'den kalkar; "Arşiv" süzgecinde Arşivlenenler seçilince görünür ve geri alınabilir.',
        'restore' => 'Referans yeniden listelerde ve Excel\'de görünür.',
    ],

    'filters' => [
        'scope_type' => 'Proje tipi',
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
    ],

    'actions' => [
        'create' => 'Referans ekle',
        'edit' => 'Düzenle',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
        'excel' => 'Excel',
        'download' => 'İndir',
        'open_list' => 'Referans listesini aç',
        'close' => 'Kapat',
        // D-183: teklifin kapsam bölümlerinin başlığında.
        'references' => 'Referanslar',
        'download_type' => 'Referansları indir',
    ],

    // D-183: Ayarlar > Referanslar listesinde proje tipine göre sekmeler.
    'tabs' => [
        'all' => 'Tümü',
    ],

    'messages' => [
        'created' => 'Referans eklendi.',
        'saved' => 'Referans kaydedildi.',
        'archived' => 'Referans arşive alındı.',
        'restored' => 'Referans arşivden çıkarıldı.',
    ],

    // Teklifteki referans penceresi (D-183: kapsam bölümünün "Referanslar" düğmesi).
    'list' => [
        'modal_heading' => 'Referans listesi',
        'modal_heading_type' => ':type referansları',
        'modal_description' => 'Bu proje tipinin referansları. Arayabilir, süzebilir, Excel\'e indirebilir ve yeni referans ekleyebilirsiniz.',
        'table_heading' => 'Referanslar',
    ],

    'empty' => 'Referans bulunamadı.',
];
