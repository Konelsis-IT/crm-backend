<?php

return [
    'label' => 'Yıllık değerlendirme',
    'plural' => 'Yıllık değerlendirmeler',

    'sections' => [
        'main' => 'Değerlendirme bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'next_review_on' => 'Sonraki değerlendirme',
        'outcome' => 'Sonuç',
        'reason' => 'Gerekçe',
        'review_year' => 'Değerlendirme yılı',
        'reviewed_at' => 'Değerlendirme tarihi',
        'reviewer' => 'İnceleyen',
        'score' => 'Puan',
        'summary' => 'Özet',
    ],

    'relation' => [
        'title' => 'Yıllık Değerlendirmeler',
        'empty' => 'Henüz değerlendirme yok.',
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
