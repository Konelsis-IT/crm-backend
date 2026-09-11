<?php

return [
    'label' => 'Devir sürümü',
    'plural' => 'Devir sürümleri',

    'sections' => [
        'main' => 'Sürüm bilgileri',
    ],

    'fields' => [
        'comment' => 'Yorum',
        'created_at' => 'Oluşturulma',
        'decision' => 'Karar',
        'project' => 'Proje',
        'reason' => 'Gerekçe',
        'snapshot_hash' => 'Snapshot hash\'i',
        'status' => 'Durum',
        'submitted_at' => 'Gönderim tarihi',
        'submitter' => 'Gönderen',
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
