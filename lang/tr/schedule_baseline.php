<?php

return [
    'label' => 'Referans takvim',
    'plural' => 'Referans takvimler',

    'sections' => [
        'main' => 'Referans takvim bilgileri',
    ],

    'fields' => [
        'approved_at' => 'Onay tarihi',
        'baseline_document_revision' => 'Referans belge revizyonu',
        'created_at' => 'Oluşturulma',
        'name' => 'Ad',
        'planned_finish_on' => 'Planlanan bitiş',
        'planned_start_on' => 'Planlanan başlangıç',
        'reason' => 'Gerekçe',
        'source' => 'Kaynak',
        'status' => 'Durum',
        'version_no' => 'Sürüm no',
    ],

    'relation' => [
        'title' => 'Referans Takvim',
        'empty' => 'Henüz referans takvim yok.',
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
