<?php

return [
    'label' => 'Onay kapısı şablonu',
    'plural' => 'Onay kapısı şablonları',

    'sections' => [
        'main' => 'Şablon bilgileri',
    ],

    'fields' => [
        'code' => 'Kod',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'name_en' => 'Ad (EN)',
        'name_tr' => 'Ad (TR)',
        'project_type' => 'Proje tipi',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Şablonlar',
        'empty' => 'Henüz şablon yok.',
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
