<?php

return [
    'label' => 'Ticari maruziyet',
    'plural' => 'Ticari maruziyetler',

    'sections' => [
        'main' => 'Maruziyet bilgileri',
    ],

    'fields' => [
        'cbs_node' => 'Maliyet kırılımı düğümü',
        'created_at' => 'Oluşturulma',
        'currency' => 'Para birimi',
        'description' => 'Açıklama',
        'exposure_amount' => 'Tutar',
        'exposure_kind' => 'Tür',
        'exposure_no' => 'Maruziyet no',
        'owner' => 'Sahip',
        'probability' => 'Olasılık (0–1)',
        'reason' => 'Gerekçe',
        'source_change' => 'Kaynak değişiklik',
        'source_delay_event' => 'Kaynak gecikme',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Ticari Maruziyet',
        'empty' => 'Henüz maruziyet yok.',
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
