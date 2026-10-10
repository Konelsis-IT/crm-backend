<?php

declare(strict_types=1);

// D-176: "Tum belgeleri indir" (klasorlu ZIP), otomatik sirket belgeleri
// (Genel katalog) ve potansiyel iste coklu belge ekleme.

return [
    'actions' => [
        'download_all' => 'Tüm belgeleri indir',
        'download_all_help' => 'Bu kayıttaki bütün belgeler klasörlere ayrılmış tek ZIP dosyası olarak iner.',
        'add_documents' => 'Belge ekle',
    ],

    // ZIP icindeki klasor adlari.
    'folders' => [
        'general' => 'Genel belgeler',
        'archive' => 'Arşiv',
        'scopes' => 'Kapsam listeleri',
        'cost_lists' => 'Maliyet listeleri',
        'checklist' => 'Kontrol listesi',
        'extra' => 'Ek belgeler',
        'proposals' => 'Teklifler',
        'previous_versions' => 'Önceki sürümler',
        'version' => 'Sürüm :no',
    ],

    // ZIP dosya adinin sonu: "TKLF-2026-0001 Baslik - Belgeler.zip".
    'file_suffix' => '- Belgeler',

    'add' => [
        'heading' => 'Belge ekle',
        'help' => 'Seçtiğiniz her dosya ayrı bir belge olarak eklenir; bir maddeye ya da ek belgelere birden fazla dosya eklenebilir. Kontrol listesi tahtasında maddenin ilk belgesi görünür, diğerleri bu kartta listelenir.',
        'target' => 'Belgenin yeri',
        'extra' => 'Ek belge',
        'files' => 'Dosyalar',
        'added' => ':count belge eklendi.',
    ],

    'card' => [
        'empty' => 'Ek belge yok. Kontrol listesi belgeleri tahtada, teklif belgeleri tekliflerin Dokümanlar sekmesinde.',
    ],
];
