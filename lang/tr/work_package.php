<?php

return [
    'label' => 'İş paketi',
    'plural' => 'İş paketleri',

    'sections' => [
        'main' => 'İş paketi bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'name' => 'Ad',
        'owner' => 'Sahip',
        'package_code' => 'Paket kodu',
        'planned_finish_on' => 'Planlanan bitiş',
        'planned_start_on' => 'Planlanan başlangıç',
        'project' => 'Proje',
        'project_workstream' => 'Adım (departman)',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'wbs_node' => 'İş kırılımı düğümü',
        'workstream' => 'Adım (departman)',
    ],

    'relation' => [
        'title' => 'İş Paketleri',
        'empty' => 'Henüz iş paketi yok.',
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
