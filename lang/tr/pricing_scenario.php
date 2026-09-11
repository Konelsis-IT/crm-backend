<?php

return [
    'label' => 'Fiyat senaryosu',
    'plural' => 'Fiyat senaryoları',

    'sections' => [
        'main' => 'Senaryo bilgileri',
    ],

    'fields' => [
        'adjustment_pct' => 'Düzeltme (%)',
        'created_at' => 'Oluşturulma',
        'is_selected' => 'Seçili',
        'name' => 'Ad',
        'reason' => 'Gerekçe',
        'scenario_code' => 'Senaryo kodu',
        'target_margin_pct' => 'Hedef marj (%)',
        'total_price' => 'Toplam fiyat',
    ],

    'relation' => [
        'title' => 'Fiyat Senaryoları',
        'empty' => 'Henüz senaryo yok.',
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
