<?php

return [
    'label' => 'Saha fotoğrafı',
    'plural' => 'Saha fotoğrafları',

    'sections' => [
        'main' => 'Fotoğraf bilgileri',
    ],

    'fields' => [
        'file' => 'Fotoğraf dosyası',
        'files' => 'Fotoğraf dosyaları',
        'caption' => 'Açıklama',
        'taken_on' => 'Çekim tarihi',
        'sort_order' => 'Sıra',
        'is_cover' => 'Kapak görseli',
        'workstream' => 'Adım (departman)',
        'uploaded_at' => 'Yüklenme',
        'uploader' => 'Yükleyen',
        'created_at' => 'Oluşturulma',
        'reason' => 'Gerekçe',
    ],

    'help' => [
        'files' => 'Birden fazla fotoğraf seçebilirsiniz; ilk fotoğraf otomatik olarak kapak görseli olur.',
        'is_cover' => 'İşaretlenirse proje kartında ve çalışma alanının başında bu fotoğraf gösterilir.',
    ],

    'relation' => [
        'title' => 'Saha fotoğrafları',
        'empty' => 'Henüz fotoğraf eklenmemiş.',
    ],

    'actions' => [
        'upload' => 'Fotoğraf ekle',
        'set_cover' => 'Kapak yap',
        'change_status' => 'Durum değiştir',
        'download' => 'İndir',
        'preview' => 'Önizle',
        'set_status' => 'Durumu \":status\" yap',
    ],

    'messages' => [
        'done' => 'İşlem tamamlandı.',
        'uploaded' => ':count fotoğraf eklendi.',
        'status_changed' => 'Durum güncellendi.',
    ],

    'validation' => [
        'duplicate' => 'Bu fotoğraf zaten ekli.',
    ],
];
