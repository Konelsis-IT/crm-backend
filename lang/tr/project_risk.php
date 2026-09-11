<?php

return [
    'label' => 'Risk',
    'plural' => 'Riskler',

    'sections' => [
        'main' => 'Risk bilgileri',
    ],

    'fields' => [
        'category' => 'Kategori',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'impact' => 'Etki (1–5)',
        'mitigation_plan' => 'Azaltma planı',
        'owner' => 'Sahip',
        'probability' => 'Olasılık (0–1)',
        'reason' => 'Gerekçe',
        'response_strategy' => 'Yanıt stratejisi',
        'review_due_on' => 'Gözden geçirme tarihi',
        'risk_no' => 'Risk no',
        'score' => 'Puan',
        'status' => 'Durum',
        'title' => 'Başlık',
        'workstream' => 'Adım (departman)',
    ],

    'relation' => [
        'title' => 'Riskler',
        'empty' => 'Henüz risk yok.',
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
