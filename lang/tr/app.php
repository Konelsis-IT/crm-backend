<?php

return [
    'name' => 'Konelsis Yönetim Paneli',
    'language' => 'Dil seçimi',

    'actions' => [
        'open' => 'Aç',
        'save' => 'Kaydet',
        'save_draft' => 'Taslak olarak kaydet',
    ],

    'dashboard' => [
        'title' => 'Genel bakış',
    ],

    'days' => [
        'today' => 'Bugün',
        'tomorrow' => 'Yarın',
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

    // Taslak kaydi (D-155).
    'values' => [
        'draft' => 'Taslak',
        'draft_at' => 'Taslak · :step adımında kaldı',
        'draft_hint' => 'Taslak kayıt: henüz tamamlanmadı, kaldığı adımdan devam edilir',
    ],

    'tabs' => [
        'all' => 'Tümü',
        'drafts' => 'Taslaklar',
    ],

    'errors' => [
        'title' => 'İşlem tamamlanamadı',
        'stale_record' => 'Kayıt siz açtıktan sonra başka biri tarafından değiştirildi. Sayfayı yenileyip tekrar deneyin.',
        'invalid_transition' => 'Bu durum değişikliğine izin verilmiyor.',
        'self_parent' => 'Bir departman kendi üst departmanı olamaz.',
        'generic' => 'Beklenmeyen bir hata oluştu. İşlem kaydedilmedi.',
    ],
];
