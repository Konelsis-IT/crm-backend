<?php

return [
    'label' => 'Maliyet tahmini',
    'plural' => 'Maliyet tahminleri',

    'sections' => [
        'main' => 'Tahmin bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'currency' => 'Para birimi',
        'exchange_rate_snapshot' => 'Kur snapshot',
        'notes' => 'Notlar',
        'preparer' => 'Hazırlayan',
        'proposal' => 'Teklif',
        'proposal_version' => 'Teklif sürümü',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'target_margin_pct' => 'Hedef marj (%)',
        'total_cost' => 'Toplam maliyet',
        'total_price' => 'Toplam fiyat',
        'version_no' => 'Sürüm no',
    ],

    'relation' => [
        'title' => 'Maliyet Tahminleri',
        'empty' => 'Henüz tahmin yok.',
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
