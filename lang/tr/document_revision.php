<?php

return [
    'label' => 'Revizyon',
    'plural' => 'Revizyonlar',

    'sections' => [
        'main' => 'Revizyon bilgileri',
        'content' => 'İçerik',
    ],

    'fields' => [
        'revision_no' => 'Revizyon no',
        'revision_code' => 'Revizyon kodu',
        'language' => 'Dil',
        'title' => 'Başlık',
        'purpose' => 'Amaç',
        'status' => 'Durum',
        'content_kind' => 'İçerik kaynağı',
        'body' => 'Belge metni',
        'change_summary' => 'Değişiklik özeti',
        'file' => 'Dosya',
        'file_size' => 'Boyut',
        'mime_type' => 'Tür',
        'thumbnail' => 'Görsel',
        'preparer' => 'Hazırlayan',
        'prepared_at' => 'Hazırlanma tarihi',
        'checker' => 'Kontrol eden',
        'approver' => 'Onaylayan',
        'approved_at' => 'Onay tarihi',
        'issued_at' => 'Yayım tarihi',
    ],

    'help' => [
        'revision_no' => 'Otomatik üretilir.',
        'file' => 'Bu revizyonun orijinal dosyası. Aynı içerikli dosya daha önce yüklendiyse otomatik olarak ona bağlanır.',
        'content_kind' => 'Belgenin aslını dosya olarak yükleyin ya da belgeyi burada yazın.',
        'body' => 'Belge metni; başlık, liste ve tablo kullanılabilir. İçerik değişince yeni bir içerik hash\'i üretilir.',
    ],

    'values' => [
        'authored' => 'Sistemde yazıldı',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'download' => 'İndir',
        'preview' => 'Önizle',
        'set_status' => 'Durumu ":status" yap',
    ],

    'messages' => [
        'status_changed' => 'Revizyon durumu güncellendi.',
    ],
];
