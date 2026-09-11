<?php

return [
    'label' => 'Önemli tarih',
    'plural' => 'Önemli tarihler',

    'sections' => [
        'main' => 'Önemli tarih bilgileri',
    ],

    'fields' => [
        'actual_at' => 'Gerçekleşen',
        'baseline_at' => 'Referans tarih',
        'contract_milestone' => 'Sözleşme kilometre taşı',
        'created_at' => 'Oluşturulma',
        'forecast_at' => 'Tahmin',
        'milestone_code' => 'Önemli tarih kodu',
        'milestone_kind' => 'Tür',
        'name' => 'Ad',
        'planned_at' => 'Planlanan',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'wbs_node' => 'İş kırılımı düğümü',
    ],

    'relation' => [
        'title' => 'Önemli Tarihler',
        'empty' => 'Henüz önemli tarih yok.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
