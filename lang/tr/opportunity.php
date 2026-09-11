<?php

return [
    'label' => 'Fırsat',
    'plural' => 'Fırsatlar',

    'sections' => [
        'main' => 'Fırsat bilgileri',
    ],

    'fields' => [
        'bid_decider' => 'Bid kararını veren',
        'bid_decision' => 'Bid kararı',
        'competitor_note' => 'Rakip notu',
        'created_at' => 'Oluşturulma',
        'expected_decision_on' => 'Beklenen karar tarihi',
        'expected_value' => 'Beklenen değer',
        'market_code' => 'Pazar',
        'probability_pct' => 'Olasılık (%)',
        'reason' => 'Gerekçe',
        'stage' => 'Aşama',
    ],

    'relation' => [
        'title' => 'Fırsat',
        'empty' => 'Fırsat kaydı yok.',
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
