<?php

return [
    'label' => 'Departman devri',
    'plural' => 'Departman devirleri',

    'sections' => [
        'main' => 'Devir bilgileri',
    ],

    'fields' => [
        'accepted_at' => 'Kabul tarihi',
        'created_at' => 'Oluşturulma',
        'project' => 'Proje',
        'reason' => 'Gerekçe',
        'sla_due_at' => 'SLA termini',
        'source_workstream' => 'Kaynak workstream',
        'status' => 'Durum',
        'target_workstream' => 'Hedef workstream',
        'trigger_stage_instance' => 'Tetikleyen onay kapısı',
    ],

    'relation' => [
        'title' => 'Departman Devirleri',
        'empty' => 'Henüz devir yok.',
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
