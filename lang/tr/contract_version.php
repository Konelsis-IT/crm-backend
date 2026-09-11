<?php

return [
    'label' => 'Sözleşme sürümü',
    'plural' => 'Sözleşme sürümleri',

    'sections' => [
        'main' => 'Sürüm bilgileri',
    ],

    'fields' => [
        'approved_at' => 'Onay tarihi',
        'contract' => 'Sözleşme',
        'contract_value' => 'Sözleşme bedeli',
        'created_at' => 'Oluşturulma',
        'currency' => 'Para birimi',
        'effective_from' => 'Yürürlük başlangıcı',
        'effective_until' => 'Yürürlük bitişi',
        'executed_at' => 'Yürürlük tarihi',
        'locale' => 'Dil',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'summary' => 'Özet',
        'version_no' => 'Sürüm no',
    ],

    'relation' => [
        'title' => 'Sürümler',
        'empty' => 'Henüz sürüm yok.',
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
