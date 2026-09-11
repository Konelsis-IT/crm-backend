<?php

return [
    'label' => 'İlerleme fotoğrafı',
    'plural' => 'İlerleme fotoğrafları',

    'sections' => [
        'main' => 'İlerleme bilgileri',
    ],

    'fields' => [
        'cost_progress_pct' => 'Maliyet ilerlemesi (%)',
        'created_at' => 'Oluşturulma',
        'physical_progress_pct' => 'Fiziksel ilerleme (%)',
        'planned_progress_pct' => 'Planlanan ilerleme (%)',
        'reason' => 'Gerekçe',
        'reporter' => 'Bildiren',
        'snapshot_at' => 'Fotoğraf anı',
        'source' => 'Kaynak',
    ],

    'relation' => [
        'title' => 'İlerleme',
        'empty' => 'Henüz ilerleme kaydı yok.',
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
