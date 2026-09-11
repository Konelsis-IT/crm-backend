<?php

return [
    'label' => 'Devir sürümü',
    'plural' => 'Devir sürümleri',

    'sections' => [
        'main' => 'Sürüm bilgileri',
    ],

    'fields' => [
        'business_case' => 'İş dosyası',
        'comment' => 'Yorum',
        'contract_version' => 'Sözleşme sürümü',
        'created_at' => 'Oluşturulma',
        'decision' => 'Karar',
        'decision_reason' => 'Karar gerekçesi',
        'manifest_document_revision' => 'Manifest document revision',
        'planned_finish_on' => 'Planlanan bitiş',
        'planned_start_on' => 'Planlanan başlangıç',
        'project_manager' => 'Proje yöneticisi',
        'project_name' => 'Proje adı',
        'proposal_version' => 'Teklif sürümü',
        'reason' => 'Gerekçe',
        'site_location' => 'Saha konumu',
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
        'reviewed' => 'İnceleme kararı kaydedildi.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],

    'help' => [
        'proposal_version' => 'Boş bırakılırsa seçili teklifin onaylı sürümü bağlanır.',
        'contract_version' => 'Boş bırakılırsa iş dosyasının yürürlükteki sözleşme sürümü bağlanır (D-10).',
    ],
];
