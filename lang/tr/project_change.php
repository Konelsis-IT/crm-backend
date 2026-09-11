<?php

return [
    'label' => 'Değişiklik talebi',
    'plural' => 'Değişiklik talepleri',

    'sections' => [
        'main' => 'Değişiklik bilgileri',
    ],

    'fields' => [
        'affects_baseline' => 'Baseline\'ı etkiler',
        'change_no' => 'Değişiklik no',
        'change_type' => 'Değişiklik türü',
        'created_at' => 'Oluşturulma',
        'currency' => 'Para birimi',
        'description' => 'Açıklama',
        'impact_cost' => 'Maliyet etkisi',
        'impact_days' => 'Süre etkisi (gün)',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Değişiklikler',
        'empty' => 'Henüz değişiklik yok.',
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
