<?php

return [
    'name' => 'Konelsis Yönetim Paneli',
    'language' => 'Dil seçimi',

    'actions' => [
        'open' => 'Aç',
        'save' => 'Kaydet',
    ],

    'dashboard' => [
        'title' => 'Genel bakış',
    ],

    'nav' => [
        'reports' => 'Raporlar',
        'analytics' => 'Analizler',
        'acquisition' => 'İş Alım',
        'operations' => 'Operasyon',
        'project_group' => 'Proje Grubu',
        'procurement' => 'Satın Alma',
        'tenders' => 'İhaleler',
        'administrative' => 'İdari',
        'documents' => 'Belgeler',
        'settings' => 'Ayarlar',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'updated_at' => 'Güncellenme',
    ],

    'errors' => [
        'title' => 'İşlem tamamlanamadı',
        'stale_record' => 'Kayıt siz açtıktan sonra başka biri tarafından değiştirildi. Sayfayı yenileyip tekrar deneyin.',
        'invalid_transition' => 'Bu durum değişikliğine izin verilmiyor.',
        'self_parent' => 'Bir departman kendi üst departmanı olamaz.',
        'generic' => 'Beklenmeyen bir hata oluştu. İşlem kaydedilmedi.',
    ],
];
