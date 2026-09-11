<?php

return [
    'label' => 'Tahmin satırı',
    'plural' => 'Tahmin satırları',

    'sections' => [
        'main' => 'Satır bilgileri',
    ],

    'fields' => [
        'cost_type' => 'Maliyet türü',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'line_code' => 'Satır kodu',
        'line_total_cost' => 'Satır maliyeti',
        'parent_line' => 'Parent line',
        'quantity' => 'Miktar',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'unit_cost' => 'Birim maliyet',
        'unit_price' => 'Birim fiyat',
        'uom' => 'Birim',
        'wbs_hint' => 'İş kırılımı ipucu',
    ],

    'relation' => [
        'title' => 'Satırlar',
        'empty' => 'Henüz satır yok.',
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
